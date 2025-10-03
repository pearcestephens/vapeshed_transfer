<?php
declare(strict_types=1);

use VapeshedTransfer\App\Repositories\OutboxRepository;
use VapeshedTransfer\App\Repositories\ProductCandidateRepository; // for type reference only
use VapeshedTransfer\App\Services\Matching\MatchingService;
use VapeshedTransfer\App\Repositories\ProductCandidateImageRepository;
use VapeshedTransfer\App\Repositories\BrandSynonymRepository;
use VapeshedTransfer\App\Repositories\BrandSynonymCandidateRepository;
use VapeshedTransfer\App\Repositories\MatchThresholdRepository;
use VapeshedTransfer\Core\Logger;

require_once __DIR__.'/_cli_bootstrap.php';

$mysqli = cli_db();
$logger = new Logger('matching_worker');
$outbox = new OutboxRepository($mysqli);
$matcher = new MatchingService($mysqli);
$matcher->setBrandSynonymRepository(new BrandSynonymRepository($mysqli));
$matcher->setBrandSynonymCandidateRepository(new BrandSynonymCandidateRepository($mysqli));
$matcher->setThresholdRepository(new MatchThresholdRepository($mysqli));
$matcher->setFlags([
    'brand_weighting' => getenv('MATCH_FLAG_BRAND_WEIGHTING') !== '0',
    'duplicate_suppression' => getenv('MATCH_FLAG_DUP_SUPPRESS') !== '0',
    'category_analytics' => getenv('MATCH_FLAG_CATEGORY_ANALYTICS') !== '0',
    'synonym_learning' => getenv('MATCH_FLAG_SYNONYM_LEARN') !== '0',
    'image_similarity' => getenv('MATCH_FLAG_IMAGE_SIMILARITY') !== '0',
    'vision_bonus' => getenv('MATCH_FLAG_VISION_BONUS') !== '0',
    'cluster_dup_penalty' => getenv('MATCH_FLAG_CLUSTER_DUP_PENALTY') !== '0',
]);
$imgRepo = new ProductCandidateImageRepository($mysqli);

$batch = $outbox->fetchBatch(50);
$loop=0; $lastFlagReload=time();
foreach ($batch as $evt) {
    $loop++;
    if (time()-$lastFlagReload > 60) { // periodic env refresh every minute
        $matcher->setFlags([
            'brand_weighting' => getenv('MATCH_FLAG_BRAND_WEIGHTING') !== '0',
            'duplicate_suppression' => getenv('MATCH_FLAG_DUP_SUPPRESS') !== '0',
            'category_analytics' => getenv('MATCH_FLAG_CATEGORY_ANALYTICS') !== '0',
            'synonym_learning' => getenv('MATCH_FLAG_SYNONYM_LEARN') !== '0',
            'image_similarity' => getenv('MATCH_FLAG_IMAGE_SIMILARITY') !== '0',
            'vision_bonus' => getenv('MATCH_FLAG_VISION_BONUS') !== '0',
            'cluster_dup_penalty' => getenv('MATCH_FLAG_CLUSTER_DUP_PENALTY') !== '0',
        ]);
        $lastFlagReload=time();
    }
    if ($evt['event_type'] !== 'product.candidate.changed') { continue; }
    $payload = json_decode($evt['payload_json'], true) ?: [];
    $candidateId = $payload['candidate_id'] ?? null;
    if (!$candidateId) { $outbox->markDispatched($evt['event_id']); continue; }
    // Pull candidate basic fields (minimal) directly
    $cidEsc = $mysqli->real_escape_string($candidateId);
    $res = $mysqli->query("SELECT candidate_id, product_title, brand, variant, nicotine_mg, volume_ml, pack_count FROM product_candidates WHERE candidate_id='$cidEsc' LIMIT 1");
    if (!$res || !$res->num_rows) { $outbox->markDispatched($evt['event_id']); continue; }
    $row = $res->fetch_assoc();
    $title = $row['product_title'] ?? '';
    $brand = $row['brand'] ?? null;
    $variant = $row['variant'] ?? null;
    // Build vision/image signals (very early heuristic using first image hashes if present)
    $signals = [];
    $imgs = $imgRepo->listByCandidate($candidateId);
    if ($imgs) {
        $primary = $imgs[0];
        // Placeholder: treat presence of perceptual hash as mild confidence baseline
        if (!empty($primary['p_hash'])) { $signals['image_similarity'] = 0.75; }
        if (!empty($primary['vision_labels'])) { $signals['vision_confidence'] = 0.8; }
    }
    $proposals = $matcher->proposeMatchesWithSignals($candidateId, $title, $brand, $variant, [], $signals);
    $accepted=0; $stored=0;
    foreach ($proposals as $p) {
        try {
            $eval = $matcher->evaluateAcceptance($p);
            if (!$eval['accepted']) {
                $reasonCode = 'below_threshold';
                if(($p['components']['tokens']??0) < 0.25){ $reasonCode='insufficient_tokens'; }
                elseif($p['score'] < ($eval['thresholds']['secondary']??0.70)){ $reasonCode='below_secondary'; }
                $matcher->logRejection($candidateId, $p, $reasonCode, ['eval'=>$eval]);
            }
            $matcher->persistMatch($candidateId, $p['sku_id'], (float)$p['score'], $p['method'], array_merge($p['components'], ['accept_eval'=>$eval]), $eval['accepted']);
            $stored++;
        } catch (Throwable $e) { $logger->warning('Persist match failed', ['error'=>$e->getMessage()]); }
    }
    $outbox->markDispatched($evt['event_id']);
    $autoAccepts = array_sum(array_map(function($p) use ($matcher){ return $matcher->evaluateAcceptance($p)['accepted']?1:0; }, $proposals));
    $logger->info('Matching processed', ['candidate'=>$candidateId,'proposals'=>$stored,'auto_accepts'=>$autoAccepts]);
}
