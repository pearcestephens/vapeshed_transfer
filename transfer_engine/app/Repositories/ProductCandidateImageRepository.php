<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli; use RuntimeException;

/**
 * ProductCandidateImageRepository
 * Persists image metadata for product candidates to support future vision-based matching.
 */
class ProductCandidateImageRepository
{
    public function __construct(private mysqli $db) {}

    /**
     * Idempotent insert (candidate_id + url hash). Optionally updates role if new role is more specific than existing.
     * @param array{width?:int,height?:int,bytes?:int,format?:string,role?:string,content_hash?:?string} $meta
     */
    public function upsert(string $candidateId, string $imageUrl, array $meta = []): string
    {
        $urlHash = hash('sha256', $imageUrl);
        $imageId = self::uuid();
        $role = $meta['role'] ?? 'unknown';
        $width = $meta['width'] ?? null; $height = $meta['height'] ?? null; $bytes = $meta['bytes'] ?? null; $format = $meta['format'] ?? null; $contentHash = $meta['content_hash'] ?? null;
        $stmt = $this->db->prepare("SELECT image_id, role FROM product_candidate_images WHERE candidate_id=? AND url_hash=? LIMIT 1");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $stmt->bind_param('ss',$candidateId,$urlHash); $stmt->execute(); $res=$stmt->get_result(); $existing=$res? $res->fetch_assoc():null; $stmt->close();
        if ($existing) {
            $imageId = $existing['image_id'];
            // Upgrade role if existing is unknown and new is specific
            if ($existing['role']==='unknown' && $role !== 'unknown') {
                $upd = $this->db->prepare("UPDATE product_candidate_images SET role=? WHERE image_id=?");
                if($upd){ $upd->bind_param('ss',$role,$imageId); $upd->execute(); $upd->close(); }
            }
            return $imageId;
        }
        $stmt2 = $this->db->prepare("INSERT INTO product_candidate_images (image_id,candidate_id,image_url,url_hash,content_hash,width,height,bytes,format,role,fetched_at) VALUES (?,?,?,?,?,?,?,?,?,?,NULL)");
        if(!$stmt2){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $stmt2->bind_param('sssssiisss',$imageId,$candidateId,$imageUrl,$urlHash,$contentHash,$width,$height,$bytes,$format,$role);
        $stmt2->execute(); $stmt2->close();
        return $imageId;
    }

    /**
     * Store content hash + basic binary meta after fetch.
     */
    public function updateBinaryMeta(string $imageId, string $contentHash, int $bytes, ?int $width=null, ?int $height=null, ?string $format=null): bool
    {
        $stmt = $this->db->prepare("UPDATE product_candidate_images SET content_hash=?, bytes=?, width=COALESCE(?,width), height=COALESCE(?,height), format=COALESCE(?,format), fetched_at=NOW() WHERE image_id=?");
        if(!$stmt){ return false; }
        $stmt->bind_param('siisss',$contentHash,$bytes,$width,$height,$format,$imageId); $stmt->execute(); $ok=$stmt->affected_rows>=0; $stmt->close(); return $ok;
    }

    public function listByCandidate(string $candidateId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM product_candidate_images WHERE candidate_id=? ORDER BY created_at ASC");
        if(!$stmt){ return []; }
        $stmt->bind_param('s',$candidateId); $stmt->execute(); $res=$stmt->get_result(); $rows=$res? $res->fetch_all(MYSQLI_ASSOC):[]; $stmt->close(); return $rows;
    }

    private static function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr(ord($d[6]) & 0x0f | 0x40); $d[8]=chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d),4));
    }
}
