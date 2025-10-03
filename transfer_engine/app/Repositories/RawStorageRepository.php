<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli; use RuntimeException;

class RawStorageRepository
{
    public function __construct(private mysqli $db) {}

    private function tableAvailable(): bool
    {
        static $ok=null; if($ok!==null) return $ok; $r=$this->db->query("SHOW TABLES LIKE 'crawler_raw_storage'"); $ok=$r && $r->num_rows>0; return $ok;
    }

    public function store(string $url, string $body): ?string
    {
        if(!$this->tableAvailable()) { return null; }
        $hash = hash('sha256',$body);
        $escHash = $this->db->real_escape_string($hash);
        $check = $this->db->query("SELECT storage_id FROM crawler_raw_storage WHERE content_hash='$escHash' LIMIT 1");
        if ($check && $check->num_rows) { $row=$check->fetch_assoc(); return $row['storage_id']; }
        $compressed = gzencode($body, 6);
        if ($compressed === false) { $compressed = $body; $compression='none'; }
        else { $compression='gzip'; }
        $storageId = self::uuid();
        $stmt=$this->db->prepare("INSERT INTO crawler_raw_storage (storage_id,url,content_hash,compression,byte_size,body_long) VALUES (?,?,?,?,?,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $size = strlen($compressed);
        $stmt->bind_param('ssssib', $storageId, $url, $hash, $compression, $size, $blob);
        $blob = $compressed; // after bind for clarity
        $stmt->send_long_data(5, $compressed);
        $stmt->execute();
        $stmt->close();
        return $storageId;
    }

    public function fetch(string $storageId): ?array
    {
        if(!$this->tableAvailable()) { return null; }
        $esc = $this->db->real_escape_string($storageId);
        $res=$this->db->query("SELECT * FROM crawler_raw_storage WHERE storage_id='$esc' LIMIT 1");
        if(!$res || !$res->num_rows) { return null; }
        $row=$res->fetch_assoc();
        $body = $row['body_long'];
        if ($row['compression']==='gzip') { $body = gzdecode($body) ?: $body; }
        $row['body']=$body; unset($row['body_long']); return $row;
    }

    private static function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr(ord($d[6]) & 0x0f | 0x40); $d[8]=chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4));
    }
}
