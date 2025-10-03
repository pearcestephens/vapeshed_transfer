<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Matching;

use mysqli; use RuntimeException;
use VapeshedTransfer\App\Repositories\BrandSynonymRepository;
use VapeshedTransfer\App\Repositories\BrandSynonymCandidateRepository;
use VapeshedTransfer\App\Repositories\MatchThresholdRepository;

/**
 * MatchingService
 * Provides candidate → internal SKU linking via layered strategies (fuzzy, rules, embedding placeholder).
 * FUTURE: vision-assisted re-ranking will incorporate perceptual hash similarity & GPT-vision labels.
 */
class MatchingService
{
    private ?BrandSynonymRepository $brandSyn = null;
    private ?BrandSynonymCandidateRepository $brandSynCandidates = null;
    private ?MatchThresholdRepository $thresholdRepo = null;
    /** Feature flags (all enabled by default). */
    private array $flags = [
        'brand_weighting' => true,
        'duplicate_suppression' => true,
        'category_analytics' => true,
        'synonym_learning' => true,
        'image_similarity' => true,
        'vision_bonus' => true,
        'cluster_dup_penalty' => true,
    ];
    /** Cache of already accepted SKU IDs for duplicate suppression */
    private array $acceptedSkuIds = [];
    private bool $acceptedSkuIdsLoaded = false;
    private array $acceptedSkuPhashes = [];
    private array $acceptedSkuClusterReps = [];
    public function __construct(private mysqli $db) {}

    public function setBrandSynonymRepository(BrandSynonymRepository $repo): void
    { $this->brandSyn = $repo; }

    public function setBrandSynonymCandidateRepository(BrandSynonymCandidateRepository $repo): void
    { $this->brandSynCandidates = $repo; }

    public function setThresholdRepository(MatchThresholdRepository $repo): void
    { $this->thresholdRepo = $repo; }

    /** Override feature flags selectively */
    public function setFlags(array $overrides): void
    {
        foreach ($overrides as $k=>$v) {
            if (array_key_exists($k, $this->flags)) {
                $this->flags[$k] = (bool)$v;
            }
        }
    }

    /**
     * Propose matches with optional vision signals.
     * @param array{vision_confidence?:float,vision_labels?:array<string>,image_similarity?:float} $signals
     */
    public function proposeMatchesWithSignals(string $candidateId, string $candidateTitle, ?string $brand, ?string $variant, array $norm = [], array $signals = []): array
    {
        $base = $this->proposeMatches($candidateId, $candidateTitle, $brand, $variant, $norm);
        $visionBonus = 0.0;
        $visionComponents = [];
        if ($this->flags['image_similarity'] && !empty($signals['image_similarity'])) {
            $sim = (float)$signals['image_similarity'];
            if ($sim >= 0.9) { $visionBonus = 0.20; }
            elseif ($sim >= 0.8) { $visionBonus = 0.12; }
            elseif ($sim >= 0.7) { $visionBonus = 0.07; }
            $visionComponents['image_similarity'] = $sim;
        }
        if ($this->flags['vision_bonus'] && !empty($signals['vision_confidence'])) {
            $vc = (float)$signals['vision_confidence'];
            $visionComponents['vision_confidence'] = $vc;
            if ($vc > 0.85) { $visionBonus += 0.05; }
        }
        // Apply bonus uniformly to top N (avoid inflating weak proposals)
        foreach ($base as &$b) {
            if ($b['score'] >= 0.35 && $this->flags['vision_bonus']) {
                $b['components']['vision_bonus'] = $visionBonus;
                $b['score'] = round(min(1.0, $b['score'] + $visionBonus),4);
                if ($visionComponents) { $b['components'] = array_merge($b['components'], $visionComponents); }
            }
        }
        unset($b);
        usort($base, fn($a,$b)=> $b['score']<=>$a['score']);
        return $base;
    }

    public function proposeMatches(string $candidateId, string $candidateTitle, ?string $brand, ?string $variant, array $norm = []): array
    {
    $rawConcat = trim($candidateTitle.' '.($variant??''));
    $tokens = $this->tokenize($rawConcat);
    $tokens = $this->filterStopwords($tokens);
    if ($this->brandSyn) { $tokens = $this->brandSyn->normalizeTokens($tokens); }
        if ($brand) { $tokens[] = strtolower($brand); }
        $tokenSet = array_unique($tokens);
        $nic = $norm['nicotine_mg'] ?? null;
        $vol = $norm['volume_ml'] ?? null;
        $coilPrimary = $norm['primary_coil_ohm'] ?? null;
        $price = $norm['price'] ?? null;

        // Build base query from vend_products (restrict active, not deleted)
        $where = "(vp.is_deleted IS NULL OR vp.is_deleted=0) AND (vp.active=1 OR vp.is_active=1)";
        $limit = 6000; // safeguard upper bound
        $brandFilter = '';
        if ($brand) {
            $escBrand = $this->db->real_escape_string($brand);
            $brandFilter = " AND (vp.name LIKE '%$escBrand%' OR vp.variant_name LIKE '%$escBrand%')";
        }
        // Lightweight prefilter with first 2 distinctive tokens if present
        $prefilter = '';
        $distinctTokens = array_slice(array_filter($tokenSet, fn($t)=>strlen($t)>3),0,2);
        if ($distinctTokens) {
            $parts = [];
            foreach ($distinctTokens as $dt) { $esc = $this->db->real_escape_string($dt); $parts[] = "(vp.name LIKE '%$esc%' OR vp.variant_name LIKE '%$esc%')"; }
            $prefilter = ' AND '.implode(' AND ',$parts);
        }
        $sql = "SELECT vp.id product_id, vp.name, vp.variant_name, vp.brand_id, vp.sku, vp.price_including_tax, vp.handle FROM vend_products vp WHERE $where $brandFilter $prefilter LIMIT $limit";
        $res = $this->db->query($sql);
        $candidates = [];
        // Preload candidate primary image p_hash for similarity (if available)
        $candidateImageHash = $this->fetchCandidatePrimaryPhash($candidateId);
        while ($res && $row = $res->fetch_assoc()) {
            $nameCombined = trim(($row['name'] ?? '').' '.($row['variant_name'] ?? ''));
            $scoreTokens = $this->fuzzyTokenScore($tokenSet, $nameCombined);
            if ($scoreTokens < 0.2) { continue; }
            $scoreBrand = 0.0;
            if ($this->flags['brand_weighting'] && $brand) {
                if (stripos($nameCombined, $brand)!==false) { $scoreBrand = 0.15; }
            }
            $scoreNic = 0.0;
            if ($nic) {
                if (preg_match('/(\d{1,3})\s?mg/i', $nameCombined, $m)) {
                    $nFound = (float)$m[1];
                    $diff = abs($nFound - (float)$nic);
                    if ($diff < 1) $scoreNic = 0.1; elseif ($diff <=3) $scoreNic = 0.05;
                }
            }
            $scoreVol = 0.0;
            if ($vol) {
                if (preg_match('/(\d{1,3})\s?ml/i', $nameCombined, $m2)) {
                    $vFound = (float)$m2[1]; $vdiff = abs($vFound - (float)$vol);
                    if ($vdiff < 2) $scoreVol = 0.08; elseif ($vdiff <=5) $scoreVol = 0.04;
                }
            }
            $scoreCoil = 0.0;
            if ($coilPrimary) {
                if (preg_match_all('/(0\.[0-9]{2,3}|[0-9]\.[0-9]{2})\s?(ohm|Ω)/i',$nameCombined,$ohmMatch)) {
                    foreach ($ohmMatch[1] as $ov) { if (abs(((float)$ov) - (float)$coilPrimary) < 0.02) { $scoreCoil = 0.1; break; } }
                }
            }
            $scorePrice = 0.0;
            if ($price && isset($row['price_including_tax']) && $row['price_including_tax'] !== null) {
                $pFound = (float)$row['price_including_tax'];
                if ($pFound > 0) {
                    $rel = abs($pFound - $price) / max($pFound, $price);
                    if ($rel < 0.05) $scorePrice = 0.08; elseif ($rel < 0.12) $scorePrice = 0.03;
                }
            }
            $scoreImage = 0.0;
            if ($candidateImageHash && $this->flags['image_similarity']) {
                $productPhash = $this->fetchVendPrimaryPhash($row['product_id']);
                if ($productPhash) {
                    $dist = $this->hammingDistanceHex($candidateImageHash, $productPhash);
                    if ($dist !== null) {
                        if ($dist <= 18) $scoreImage = 0.18; elseif ($dist <= 26) $scoreImage = 0.10; elseif ($dist <= 34) $scoreImage = 0.05;
                    }
                    // Advanced duplicate penalty: candidate image near an already accepted SKU (different sku_id)
                    if ($this->flags['cluster_dup_penalty'] && $candidateImageHash) {
                        $penaltyApplied = false;
                        // First: cluster representative comparison (fast if clusters built)
                        $candidateRep = $this->fetchClusterRepresentative($candidateImageHash);
                        if ($candidateRep) {
                            foreach ($this->acceptedSkuClusterReps as $accSku=>$rep) {
                                if ((string)$accSku === (string)$row['product_id']) { continue; }
                                if ($rep && $rep === $candidateRep) { $scoreImage = max(0.0, $scoreImage - 0.08); $penaltyApplied = true; break; }
                            }
                        }
                        // Fallback: direct perceptual distance if no cluster rep hit
                        if (!$penaltyApplied) {
                            foreach ($this->acceptedSkuPhashes as $accSku=>$accHash) {
                                if ((string)$accSku === (string)$row['product_id'] || !$accHash) { continue; }
                                $d2 = $this->hammingDistanceHex($candidateImageHash, $accHash ?? '');
                                if ($d2 !== null && $d2 <= 12) { $scoreImage = max(0.0, $scoreImage - 0.08); $penaltyApplied = true; break; }
                            }
                        }
                    }
                }
            }
            $composite = $scoreTokens + $scoreBrand + $scoreNic + $scoreVol + $scoreCoil + $scorePrice;
            $composite += $scoreImage; // integrate image similarity
            if ($composite < 0.35) { continue; }
            // Duplicate suppression: skip if SKU already accepted elsewhere
            if ($this->flags['duplicate_suppression'] && $this->isSkuAlreadyAccepted((string)$row['product_id'])) {
                // Hard skip; alternative would be penalty: $composite -= 0.12
                continue;
            }
            $candidates[] = [
                'sku_id' => $row['product_id'],
                'score' => round($composite,4),
                'method' => 'multi_fuzzy',
                'components' => [
                    'tokens'=>$scoreTokens,
                    'brand'=>$scoreBrand,
                    'nic'=>$scoreNic,
                    'vol'=>$scoreVol,
                    'coil'=>$scoreCoil,
                    'price'=>$scorePrice,
                    'image'=>$scoreImage
                ]
            ];
        }
        usort($candidates, fn($a,$b)=> $b['score']<=>$a['score']);
        // Learn potential brand-like tokens when top candidate strong
        if ($this->flags['synonym_learning'] && $this->brandSynCandidates && $candidates) {
            $top = $candidates[0];
            if (($top['score'] ?? 0) >= 0.7) {
                foreach ($tokenSet as $tk) {
                    if ($this->maybeBrandLike($tk)) { $this->brandSynCandidates->recordToken($tk, $candidateId); }
                }
            }
        }
        return array_slice($candidates,0,25);
    }

    /** Log rejection for proposals that didn't auto-accept */
    public function logRejection(string $candidateId, array $proposal, string $reasonCode, array $details=[]): void
    {
        $skuId = $proposal['sku_id'] ?? '';
        $conf = $proposal['score'] ?? 0.0;
        $json = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt = $this->db->prepare("INSERT INTO product_match_rejections (candidate_id,sku_id,confidence,reason_code,details_json) VALUES (?,?,?,?,?)");
        if($stmt){ $stmt->bind_param('ssdss',$candidateId,$skuId,$conf,$reasonCode,$json); $stmt->execute(); $stmt->close(); }
    }

    public function persistMatch(string $candidateId, string $skuId, float $confidence, string $method='fuzzy', array $components = [], bool $autoAccept=false): string
    {
        $matchId = self::uuid();
        $stmt = $this->db->prepare("INSERT INTO product_candidate_matches (match_id,candidate_id,sku_id,confidence,method,status) VALUES (?,?,?,?,?,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $status = $autoAccept ? 'accepted' : 'proposed';
        $stmt->bind_param('sssdss',$matchId,$candidateId,$skuId,$confidence,$method,$status);
        $stmt->execute();
        $stmt->close();
        if ($autoAccept) { $this->enforceSingleAccepted($candidateId, $matchId); }
        if ($components) {
            $this->logEvent($matchId, 'confidence_update', [ 'components'=>$components ]);
        }
        if ($autoAccept) {
            $this->logEvent($matchId,'status_change',['to'=>'accepted','reason'=>'auto_threshold']);
        }
        if ($this->flags['category_analytics']) {
            $this->annotateCategory($matchId, $components);
        }
        return $matchId;
    }

    public function updateStatus(string $matchId, string $status): bool
    {
        $stmt=$this->db->prepare("UPDATE product_candidate_matches SET status=? WHERE match_id=?");
        if(!$stmt){ return false; }
        $stmt->bind_param('ss',$status,$matchId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    /** Auto-accept heuristic combining text + vision enhanced score. */
    public function shouldAutoAccept(array $proposal): bool
    {
        $score = $proposal['score'] ?? 0.0;
        $tokenBase = $proposal['components']['tokens'] ?? 0.0;
        $visionBonus = $proposal['components']['vision_bonus'] ?? 0.0;
        $imgSim = $proposal['components']['image_similarity'] ?? 0.0;
        $factor = $this->deriveCategoryFactor($proposal);
        $th = $this->thresholdRepo? $this->thresholdRepo->get('global') : ['primary'=>0.78,'secondary'=>0.72,'vision'=>0.70,'min_token_base'=>0.45];
        $primary = $th['primary'] * $factor;
        $secondary = $th['secondary'] * $factor;
        $visionTh = $th['vision'] * $factor;
        $minToken = $th['min_token_base'];
        if ($score >= $primary && $tokenBase >= $minToken) { return true; }
        if ($score >= $secondary && $imgSim >= 0.85 && $tokenBase >= ($minToken - 0.05)) { return true; }
        if ($score >= $visionTh && $visionBonus >= 0.15 && $tokenBase >= ($minToken - 0.05)) { return true; }
        return false;
    }

    /** Detailed acceptance path explanation */
    public function evaluateAcceptance(array $proposal): array
    {
        $score = $proposal['score'] ?? 0.0; $tokenBase = $proposal['components']['tokens'] ?? 0.0; $visionBonus=$proposal['components']['vision_bonus']??0.0; $imgSim=$proposal['components']['image_similarity']??0.0; $factor=$this->deriveCategoryFactor($proposal); $cat=$this->deriveCategory($proposal);
        $th = $this->thresholdRepo? $this->thresholdRepo->get($cat?:'global') : ['primary'=>0.78,'secondary'=>0.72,'vision'=>0.70,'min_token_base'=>0.45];
        $primary=$th['primary']*$factor; $secondary=$th['secondary']*$factor; $visionTh=$th['vision']*$factor; $minTok=$th['min_token_base'];
        $path=null; $accepted=false;
        if ($score >= $primary && $tokenBase >= $minTok) { $accepted=true; $path='primary_gate'; }
        elseif ($score >= $secondary && $imgSim >= 0.85 && $tokenBase >= ($minTok-0.05)) { $accepted=true; $path='image_secondary_gate'; }
        elseif ($score >= $visionTh && $visionBonus >= 0.15 && $tokenBase >= ($minTok-0.05)) { $accepted=true; $path='vision_gate'; }
        return [
            'accepted'=>$accepted,
            'path'=>$path,
            'thresholds'=>[ 'primary'=>$primary,'secondary'=>$secondary,'vision'=>$visionTh,'min_token'=>$minTok ],
            'factor'=>$factor,
            'category'=>$cat
        ];
    }

    private function logEvent(string $matchId, string $type, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt = $this->db->prepare("INSERT INTO product_candidate_match_events (match_id,event_type,payload_json) VALUES (?,?,?)");
        if($stmt){
            $stmt->bind_param('sss',$matchId,$type,$json);
            if(!$stmt->execute()){
                // Fallback: if enum constraint or missing column, attempt downgrade to 'note'
                if(stripos($stmt->error,'Incorrect')!==false || stripos($stmt->error,'ENUM')!==false){
                    $stmt->close();
                    $stmt2 = $this->db->prepare("INSERT INTO product_candidate_match_events (match_id,event_type,payload_json) VALUES (?,?,?)");
                    if($stmt2){ $fallback='note'; $stmt2->bind_param('sss',$matchId,$fallback,$json); $stmt2->execute(); $stmt2->close(); }
                    return;
                }
            }
            $stmt->close();
        }
    }

    private function fuzzyTokenScore(array $candidateTokens, string $target): float
    {
        $targetTokens = $this->tokenize($target);
        if (!$targetTokens) { return 0.0; }
        $intersection = array_intersect($candidateTokens, $targetTokens);
        $union = array_unique(array_merge($candidateTokens, $targetTokens));
        $jaccard = $union ? count($intersection)/count($union) : 0.0;
        // slight weight for intersection density
        $density = count($targetTokens) ? (count($intersection)/count($targetTokens)) : 0.0;
        return ($jaccard * 0.75) + ($density * 0.25);
    }

    private function tokenize(string $text): array
    {
        $text = strtolower(preg_replace('/[^a-z0-9\.]+/i',' ', $text));
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        return array_values(array_filter($parts, fn($p)=>strlen($p)>1));
    }

    private function fetchCandidatePrimaryPhash(string $candidateId): ?string
    {
        $esc = $this->db->real_escape_string($candidateId);
        $res = $this->db->query("SELECT p_hash FROM product_candidate_images WHERE candidate_id='$esc' AND role='primary' AND p_hash IS NOT NULL ORDER BY created_at ASC LIMIT 1");
        if($res && $row=$res->fetch_assoc()){ return $row['p_hash']; }
        return null;
    }

    private function fetchVendPrimaryPhash(string $productId): ?string
    {
        $esc = $this->db->real_escape_string($productId);
        $res = $this->db->query("SELECT p_hash FROM vend_product_images WHERE product_id='$esc' AND p_hash IS NOT NULL ORDER BY created_at ASC LIMIT 1");
        if($res && $row=$res->fetch_assoc()){ return $row['p_hash']; }
        return null;
    }

    private function hammingDistanceHex(string $h1, string $h2): ?int
    {
        if (strlen($h1)!==strlen($h2)) { return null; }
        $b1 = hex2bin($h1); $b2 = hex2bin($h2); if($b1===false||$b2===false){ return null; }
        $len = strlen($b1); $dist=0;
        for($i=0;$i<$len;$i++){ $x = ord($b1[$i]) ^ ord($b2[$i]); $dist += substr_count(decbin($x),'1'); }
        return $dist;
    }

    /** Ensure only one accepted match per candidate (latest wins). */
    private function enforceSingleAccepted(string $candidateId, string $newMatchId): void
    {
        $cid = $this->db->real_escape_string($candidateId);
        $res = $this->db->query("SELECT match_id FROM product_candidate_matches WHERE candidate_id='$cid' AND status='accepted' AND match_id <> '".$this->db->real_escape_string($newMatchId)."'");
        if(!$res){ return; }
        while($row=$res->fetch_assoc()){
            $mid = $row['match_id'];
            $stmt = $this->db->prepare("UPDATE product_candidate_matches SET status='rejected' WHERE match_id=?");
            if($stmt){ $stmt->bind_param('s',$mid); $stmt->execute(); $stmt->close(); $this->logEvent($mid,'status_change',['to'=>'rejected','reason'=>'superseded_by_new']); }
        }
    }

    private function filterStopwords(array $tokens): array
    {
        static $stop = ['kit','device','pod','pods','coil','coils','tank','tanks','replacement','authentic','original','pack','packof','pack-of','pcs','ml','mg','vape','edition','series','mesh'];
        return array_values(array_filter($tokens, fn($t)=> !in_array($t,$stop,true)));
    }

    private function maybeBrandLike(string $token): bool
    { return (bool)preg_match('/^[a-z0-9]{3,15}$/', $token); }

    private function deriveCategoryFactor(array $proposal): float
    {
        $hasNic = isset($proposal['components']['nic']) && $proposal['components']['nic'] > 0;
        $hasVol = isset($proposal['components']['vol']) && $proposal['components']['vol'] > 0;
        $hasImage = isset($proposal['components']['image']) && $proposal['components']['image'] > 0;
        if ($hasNic && $hasVol) { return 0.95; }
        if ($hasImage && $hasNic) { return 0.97; }
        return 1.0;
    }

    /** Basic category derivation for analytics */
    private function annotateCategory(string $matchId, array $components): void
    {
        $cat = 'generic';
        $nic = $components['nic'] ?? 0; $vol = $components['vol'] ?? 0; $coil = $components['coil'] ?? 0; $img = $components['image'] ?? 0; $tok = $components['tokens'] ?? 0;
        if ($nic > 0 && $vol > 0) { $cat = 'liquid'; }
        elseif ($coil > 0) { $cat = 'coil'; }
        elseif ($img > 0 && $tok >= 0.3) { $cat = 'hardware'; }
        $this->logEvent($matchId,'category_annotation',['category'=>$cat,'components'=>$components]);
    }

    private function isSkuAlreadyAccepted(string $skuId): bool
    {
        if (!$this->acceptedSkuIdsLoaded) { $this->loadAcceptedSkuIds(); }
        return isset($this->acceptedSkuIds[$skuId]);
    }

    private function loadAcceptedSkuIds(): void
    {
        $res = $this->db->query("SELECT DISTINCT m.sku_id,
            (SELECT p_hash FROM vend_product_images v WHERE v.product_id=m.sku_id AND p_hash IS NOT NULL ORDER BY created_at ASC LIMIT 1) p_hash,
            (SELECT representative_hash FROM image_hash_clusters c WHERE c.p_hash = (SELECT p_hash FROM vend_product_images vi WHERE vi.product_id=m.sku_id AND vi.p_hash IS NOT NULL ORDER BY created_at ASC LIMIT 1) LIMIT 1) rep
            FROM product_candidate_matches m WHERE m.status='accepted'");
        if ($res) {
            while($row=$res->fetch_assoc()) { 
                if(!empty($row['sku_id'])) { 
                    $this->acceptedSkuIds[(string)$row['sku_id']] = true; 
                    if(!empty($row['p_hash'])) { $this->acceptedSkuPhashes[(string)$row['sku_id']] = $row['p_hash']; }
                    if(!empty($row['rep'])) { $this->acceptedSkuClusterReps[(string)$row['sku_id']] = $row['rep']; }
                }
            }
        }
        $this->acceptedSkuIdsLoaded = true;
    }

    /** Fetch cluster representative hash for a given p_hash if clustering exists */
    private function fetchClusterRepresentative(string $phash): ?string
    {
        $esc = $this->db->real_escape_string($phash);
        $res = $this->db->query("SELECT representative_hash FROM image_hash_clusters WHERE p_hash='$esc' LIMIT 1");
        if($res && $row=$res->fetch_assoc()) { return $row['representative_hash']; }
        return null;
    }

    private static function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr(ord($d[6]) & 0x0f | 0x40); $d[8]=chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4));
    }
}
