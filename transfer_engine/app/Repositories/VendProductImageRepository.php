<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli; use RuntimeException;

class VendProductImageRepository
{
    public function __construct(private mysqli $db) {}

    public function upsert(string $productId, string $url): string
    {
        $urlHash = hash('sha256',$url);
        $id = self::uuid();
        $escPid = $this->db->real_escape_string($productId);
        $escHash = $this->db->real_escape_string($urlHash);
        $res = $this->db->query("SELECT image_id FROM vend_product_images WHERE product_id='$escPid' AND url_hash='$escHash' LIMIT 1");
        if ($res && $row=$res->fetch_assoc()) { return $row['image_id']; }
        $stmt = $this->db->prepare("INSERT INTO vend_product_images (image_id,product_id,image_url,url_hash) VALUES (?,?,?,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed '.$this->db->error); }
        $stmt->bind_param('ssss',$id,$productId,$url,$urlHash); $stmt->execute(); $stmt->close(); return $id;
    }

    public function listPending(int $limit=50): array
    {
        $res = $this->db->query("SELECT * FROM vend_product_images WHERE status='pending_fetch' LIMIT $limit");
        return $res? $res->fetch_all(MYSQLI_ASSOC):[];
    }

    public function markFetched(string $imageId, array $meta): void
    {
        $stmt = $this->db->prepare("UPDATE vend_product_images SET status='fetched', content_hash=?, p_hash=?, d_hash=?, a_hash=?, dominant_color=?, width=?, height=?, bytes=?, format=?, fetched_at=NOW() WHERE image_id=?");
        if($stmt){
            $stmt->bind_param('sssssiisss',$meta['content_hash'],$meta['p_hash'],$meta['d_hash'],$meta['a_hash'],$meta['dominant_color'],$meta['width'],$meta['height'],$meta['bytes'],$meta['format'],$imageId);
            $stmt->execute(); $stmt->close();
        }
    }

    public function markError(string $imageId, string $msg): void
    {
        $stmt = $this->db->prepare("UPDATE vend_product_images SET status='error', error_msg=? WHERE image_id=?");
        if($stmt){ $stmt->bind_param('ss',$msg,$imageId); $stmt->execute(); $stmt->close(); }
    }

    private static function uuid(): string
    { $d=random_bytes(16); $d[6]=chr(ord($d[6]) & 0x0f | 0x40); $d[8]=chr(ord($d[8]) & 0x3f | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4)); }
}
