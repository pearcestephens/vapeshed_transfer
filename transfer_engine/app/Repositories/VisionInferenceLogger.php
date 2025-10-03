<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use VapeshedTransfer\Core\Logger;

class VisionInferenceLogger
{
    public function __construct(private mysqli $db, private Logger $logger) {}

    public function log(array $data): ?string
    {
        $id = 'VIS_' . substr(md5(json_encode($data) . microtime(true)), 0, 24);
        $fields = ['inference_id','provider','model','vision_type','source_url','frame_hash','status','latency_ms','tokens_in','tokens_out','cost_usd','meta_json'];
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO vision_inference_logs (" . implode(',', $fields) . ") VALUES ($placeholders)";
        try {
            $stmt = $this->db->prepare($sql);
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->db->error); }
            $metaJson = isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null;
            $provider = $data['provider'] ?? 'unknown';
            $model = $data['model'] ?? null;
            $visionType = $data['vision_type'] ?? null;
            $source = $data['source_url'] ?? null;
            $frameHash = $data['frame_hash'] ?? null;
            $status = $data['status'] ?? 'pending';
            $latency = $data['latency_ms'] ?? null;
            $tokensIn = $data['tokens_in'] ?? null;
            $tokensOut = $data['tokens_out'] ?? null;
            $cost = $data['cost_usd'] ?? null;
            $stmt->bind_param(
                'sssssssiiiis',
                $id,
                $provider,
                $model,
                $visionType,
                $source,
                $frameHash,
                $status,
                $latency,
                $tokensIn,
                $tokensOut,
                $cost,
                $metaJson
            );
            $stmt->execute();
            $stmt->close();
            return $id;
        } catch (\Throwable $e) {
            $this->logger->error('Vision inference log failed', ['error'=>$e->getMessage()]);
            return null;
        }
    }

    public function updateStatus(string $inferenceId, string $status, ?array $meta=null): bool
    {
        try {
            $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
            $stmt = $this->db->prepare('UPDATE vision_inference_logs SET status=?, meta_json=IFNULL(?, meta_json) WHERE inference_id=?');
            if (!$stmt) { throw new \RuntimeException('Prepare failed: ' . $this->db->error); }
            $stmt->bind_param('sss', $status, $metaJson, $inferenceId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (\Throwable $e) {
            $this->logger->error('Vision inference status update failed', ['error'=>$e->getMessage()]);
            return false;
        }
    }
}
