<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use RuntimeException;

class CrawlerCacheRepository
{
    public function __construct(private mysqli $db) {}

    private function tableAvailable(string $t): bool
    {
        static $cache = [];
        if (isset($cache[$t])) { return $cache[$t]; }
        $r = $this->db->query("SHOW TABLES LIKE '".$this->db->real_escape_string($t)."'");
        $cache[$t] = $r && $r->num_rows>0; return $cache[$t];
    }

    public function getByUrl(string $url): ?array
    {
        if (!$this->tableAvailable('crawler_page_cache')) { return null; }
        $h = hash('sha256', $url);
        $res = $this->db->query("SELECT * FROM crawler_page_cache WHERE url_hash='".$this->db->real_escape_string($h)."' LIMIT 1");
        return $res && $res->num_rows ? $res->fetch_assoc() : null;
    }

    public function upsert(string $url, string $host, int $statusCode, string $contentHash, ?int $simhash, int $length, ?string $etag, ?string $lastMod, bool $changed, ?string $rawRef, array $meta=[]): void
    {
        if (!$this->tableAvailable('crawler_page_cache')) { return; }
        $h = hash('sha256', $url); $now = date('Y-m-d H:i:s');
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $row = $this->getByUrl($url);
        $unchangedStreak = 0; $nextCheck = null; $changeFlag = $changed ? 1 : 0;
        if ($row) {
            $unchangedStreak = $changed ? 0 : ((int)$row['unchanged_streak'] + 1);
            // adaptive schedule: more unchanged -> farther apart (cap 360 min)
            $mins = $changed ? 10 : min(360, pow(1.6, max(1,$unchangedStreak)) + 5);
            $nextCheck = date('Y-m-d H:i:s', time() + (int)($mins*60));
            $stmt = $this->db->prepare("UPDATE crawler_page_cache SET etag=?, last_modified_header=?, content_hash=?, content_simhash=?, content_length=?, status_code=?, fetched_at=?, next_check_at=?, unchanged_streak=?, change_flag=?, raw_storage_ref=?, meta_json=? WHERE cache_id=?");
            $simhashDb = $simhash; $contentLen = $length; $status = $statusCode; $unch=$unchangedStreak; $cf=$changeFlag; $cid=$row['cache_id'];
            $stmt->bind_param('ssssiissiiisi', $etag,$lastMod,$contentHash,$simhashDb,$contentLen,$status,$now,$nextCheck,$unch,$cf,$rawRef,$metaJson,$cid);
            $stmt->execute(); $stmt->close();
        } else {
            $unchangedStreak = $changed ? 0 : 1;
            $mins = $changed ? 10 : 30;
            $nextCheck = date('Y-m-d H:i:s', time() + (int)($mins*60));
            $stmt = $this->db->prepare("INSERT INTO crawler_page_cache (host,url,url_hash,etag,last_modified_header,content_hash,content_simhash,content_length,status_code,fetched_at,next_check_at,unchanged_streak,change_flag,raw_storage_ref,meta_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $simhashDb=$simhash; $contentLen=$length; $status=$statusCode; $unch=$unchangedStreak; $cf=$changeFlag;
            $stmt->bind_param('sssssssisiisiiss',$host,$url,$h,$etag,$lastMod,$contentHash,$simhashDb,$contentLen,$status,$now,$nextCheck,$unch,$cf,$rawRef,$metaJson);
            $stmt->execute(); $stmt->close();
        }
    }

    public function recordDiff(string $url, string $prevHash, string $newHash, float $ratio, array $blocks): void
    {
        if (!$this->tableAvailable('crawler_page_diffs')) { return; }
        $stmt = $this->db->prepare("INSERT INTO crawler_page_diffs (url_hash, previous_content_hash, new_content_hash, diff_ratio, block_change_json) VALUES (?,?,?,?,?)");
        $h = hash('sha256',$url); $json = json_encode($blocks, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param('sssds', $h,$prevHash,$newHash,$ratio,$json);
        $stmt->execute(); $stmt->close();
    }

    public static function computeSimhash(string $content): int
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($content)) ?: [];
        $weights = array_fill(0,64,0);
        foreach ($tokens as $t) {
            if ($t === '' || strlen($t)>32) { continue; }
            $h = substr(hash('sha256',$t),0,16); // 64 bits hex
            $bin = base_convert($h,16,2);
            $bin = str_pad($bin,64,'0',STR_PAD_LEFT);
            for ($i=0;$i<64;$i++) { $weights[$i] += ($bin[$i]==='1') ? 1 : -1; }
        }
        $bits=''; foreach ($weights as $w) { $bits .= $w>=0 ? '1':'0'; }
        return bindec($bits);
    }

    public static function diffBlocks(string $old, string $new): array
    {
        $oldBlocks = preg_split('/<div[^>]*>/i', $old) ?: [];
        $newBlocks = preg_split('/<div[^>]*>/i', $new) ?: [];
        $changes = []; $matched=0; $total = max(count($oldBlocks), count($newBlocks));
        $limit = min(count($oldBlocks), count($newBlocks));
        for ($i=0;$i<$limit;$i++) {
            $oh = substr(hash('sha256', $oldBlocks[$i]),0,16);
            $nh = substr(hash('sha256', $newBlocks[$i]),0,16);
            if ($oh !== $nh) { $changes[] = ['index'=>$i,'prev'=>$oh,'new'=>$nh]; } else { $matched++; }
        }
        $ratio = $total ? 1 - ($matched / $total) : 0.0;
        return ['ratio'=>$ratio,'changes'=>$changes];
    }
}
