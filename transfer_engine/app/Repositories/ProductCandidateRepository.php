<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use RuntimeException;

class ProductCandidateRepository
{
    public function __construct(private mysqli $db) {}

    private function tableAvailable(): bool
    {
        static $ok=null; if ($ok!==null) return $ok;
        $r=$this->db->query("SHOW TABLES LIKE 'product_candidates'"); $ok=$r && $r->num_rows>0; return $ok;
    }

    public function upsertCandidate(string $url, string $host, string $contentHash, string $structureHash, array $norm, array $rawSets): ?string
    {
        if (!$this->tableAvailable()) { return null; }
        $urlHash = hash('sha256',$url);
        $candidateId = self::uuid();
        $title = $norm['name'] ?? null;
        $brand = $norm['brand'] ?? null;
        $variant = $norm['variant'] ?? null;
        $nic = $norm['nicotine_mg'];
        $vol = $norm['volume_ml'];
        $pack = $norm['pack_count'];
        $price = $norm['price'];
        $currency = $norm['currency'];
        $jsonLd = json_encode($rawSets['json_ld'] ?? null, JSON_UNESCAPED_UNICODE);
        $micro = json_encode($rawSets['microdata'] ?? null, JSON_UNESCAPED_UNICODE);
        $og = json_encode($rawSets['open_graph'] ?? null, JSON_UNESCAPED_UNICODE);
        $heur = json_encode($rawSets['heuristic'] ?? null, JSON_UNESCAPED_UNICODE);

        // Detect existing by (url_hash, content_hash)
        $check = $this->db->query("SELECT candidate_id, change_count FROM product_candidates WHERE url_hash='".$this->db->real_escape_string($urlHash)."' AND content_hash='".$this->db->real_escape_string($contentHash)."' LIMIT 1");
        if ($check && $check->num_rows) {
            $row=$check->fetch_assoc();
            $cid=$row['candidate_id'];
            $this->db->query("UPDATE product_candidates SET last_seen_at=NOW() WHERE candidate_id='".$this->db->real_escape_string($cid)."'");
            return $cid; // already stored for this content hash
        }

        // If same url_hash different content_hash exists, increment change_count & supersede old rows
        $old=$this->db->query("SELECT candidate_id FROM product_candidates WHERE url_hash='".$this->db->real_escape_string($urlHash)."' AND status='active'");
        $changeCount=0; $prevIds=[]; while($old && $r=$old->fetch_assoc()){ $prevIds[]=$r['candidate_id']; }
        if ($prevIds) {
            $changeCount = count($prevIds);
            $idsEsc = implode(',', array_map(fn($id)=>'"'.$this->db->real_escape_string($id).'"',$prevIds));
            $this->db->query("UPDATE product_candidates SET status='superseded' WHERE candidate_id IN ($idsEsc)");
        }

        $stmt=$this->db->prepare("INSERT INTO product_candidates (candidate_id,url,url_hash,source_host,content_hash,structure_hash,product_title,brand,variant,nicotine_mg,volume_ml,pack_count,price_detected,currency,json_ld_json,microdata_json,og_json,heuristic_json,change_count) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $stmt->bind_param(
            'ssssssssssddiissssi',
            $candidateId,
            $url,
            $urlHash,
            $host,
            $contentHash,
            $structureHash,
            $title,
            $brand,
            $variant,
            $nic,
            $vol,
            $pack,
            $price,
            $currency,
            $jsonLd,
            $micro,
            $og,
            $heur,
            $changeCount
        );
        $stmt->execute();
        $stmt->close();

        // Post-insert: persist variants & coil ohms if provided in normalized structure
        if (!empty($norm['variants']) && is_array($norm['variants'])) {
            foreach ($norm['variants'] as $v) {
                $this->upsertVariant($candidateId, $v);
            }
        }
        if (!empty($norm['coil_ohms']) && is_array($norm['coil_ohms'])) {
            foreach ($norm['coil_ohms'] as $ohm) {
                $this->upsertCoilOhm($candidateId, (float)$ohm);
            }
        }

        return $candidateId;
    }

    private function variantTablesAvailable(): bool
    {
        static $cache=null; if ($cache!==null) return $cache;
        $v=$this->db->query("SHOW TABLES LIKE 'product_candidate_variants'");
        $c=$this->db->query("SHOW TABLES LIKE 'product_candidate_coil_ohms'");
        $cache = ($v && $v->num_rows>0) && ($c && $c->num_rows>0);
        return $cache;
    }

    private function upsertVariant(string $candidateId, array $variant): void
    {
        if (!$this->variantTablesAvailable()) { return; }
        $vid = self::uuid();
        $sku = $variant['sku'] ?? null;
        $title = $variant['title'] ?? null;
        $price = isset($variant['price']) ? (float)$variant['price'] : null;
        $compare = isset($variant['compare_at_price']) ? (float)$variant['compare_at_price'] : null;
        $available = isset($variant['available']) ? (int)($variant['available'] ? 1:0) : null;
        $optionsJson = isset($variant['options']) ? json_encode($variant['options'], JSON_UNESCAPED_UNICODE) : null;
        // De-dup via unique (candidate_id, sku) if sku present
        if ($sku) {
            $escSku = $this->db->real_escape_string($sku);
            $escCid = $this->db->real_escape_string($candidateId);
            $exists = $this->db->query("SELECT variant_id FROM product_candidate_variants WHERE candidate_id='$escCid' AND sku='$escSku' LIMIT 1");
            if ($exists && $exists->num_rows) { return; }
        }
        $stmt = $this->db->prepare("INSERT INTO product_candidate_variants (variant_id,candidate_id,sku,title,price,compare_at_price,available,options_json) VALUES (?,?,?,?,?,?,?,?)");
        if (!$stmt) { return; }
        $stmt->bind_param('ssssddis', $vid, $candidateId, $sku, $title, $price, $compare, $available, $optionsJson);
        $stmt->execute();
        $stmt->close();
    }

    private function upsertCoilOhm(string $candidateId, float $ohm): void
    {
        if (!$this->variantTablesAvailable()) { return; }
        $escCid = $this->db->real_escape_string($candidateId);
        $escOhm = $this->db->real_escape_string(number_format($ohm,2,'.',''));
        $check = $this->db->query("SELECT coil_id FROM product_candidate_coil_ohms WHERE candidate_id='$escCid' AND ohm_value='$escOhm' LIMIT 1");
        if ($check && $check->num_rows) { return; }
        $this->db->query("INSERT INTO product_candidate_coil_ohms (candidate_id, ohm_value) VALUES ('$escCid','$escOhm')");
    }

    private static function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr(ord($d[6]) & 0x0f | 0x40); $d[8]=chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4));
    }
}
