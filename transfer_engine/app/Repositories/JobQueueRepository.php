<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use RuntimeException;

class JobQueueRepository
{
    public function __construct(private mysqli $db) {}

    public function enqueue(string $queue, string $runId, string $idempotencyKey, array $payload, int $maxAttempts=5): ?string
    {
        $jobId = self::uuid();
        $stmt = $this->db->prepare("INSERT INTO jobs (job_id, queue, run_id, idempotency_key, payload_json, max_attempts) VALUES (?,?,?,?,?,?)");
        if (!$stmt) { throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt->bind_param('sssssi', $jobId, $queue, $runId, $idempotencyKey, $json, $maxAttempts);
        try { $stmt->execute(); } catch (\Throwable $e) { $stmt->close(); return null; }
        $stmt->close();
        return $jobId;
    }

    /** Lease a job (at-least-once). */
    public function lease(string $queue, int $leaseSeconds=60): ?array
    {
        $this->db->begin_transaction();
        try {
            $now = date('Y-m-d H:i:s');
            $res = $this->db->query("SELECT job_id FROM jobs WHERE queue='".$this->db->real_escape_string($queue)."' AND status='pending' AND (next_visible_at IS NULL OR next_visible_at <= '$now') ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
            if (!$res || !$res->num_rows) { $this->db->commit(); return null; }
            $row = $res->fetch_assoc();
            $jobId = $row['job_id'];
            $leaseExpiry = date('Y-m-d H:i:s', time()+$leaseSeconds);
            $upd = $this->db->prepare("UPDATE jobs SET status='leased', lease_expires_at=?, heartbeat_at=?, attempt=attempt+1 WHERE job_id=?");
            $upd->bind_param('sss', $leaseExpiry, $now, $jobId); $upd->execute(); $upd->close();
            $payloadRes = $this->db->query("SELECT * FROM jobs WHERE job_id='".$this->db->real_escape_string($jobId)."'");
            $job = $payloadRes->fetch_assoc();
            $this->db->commit();
            return $job;
        } catch (\Throwable $e) {
            $this->db->rollback();
            return null;
        }
    }

    public function heartbeat(string $jobId, int $extendSeconds=60): bool
    {
        $leaseExpiry = date('Y-m-d H:i:s', time()+$extendSeconds);
        $stmt=$this->db->prepare("UPDATE jobs SET heartbeat_at=NOW(), lease_expires_at=? WHERE job_id=? AND status IN ('leased','processing')");
        $stmt->bind_param('ss',$leaseExpiry,$jobId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function startProcessing(string $jobId): bool
    {
        $stmt=$this->db->prepare("UPDATE jobs SET status='processing' WHERE job_id=? AND status='leased'");
        $stmt->bind_param('s',$jobId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function complete(string $jobId): bool
    {
        $stmt=$this->db->prepare("UPDATE jobs SET status='completed', completed_at=NOW() WHERE job_id=? AND status IN ('processing','leased')");
        $stmt->bind_param('s',$jobId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function failAndReschedule(string $jobId, int $retryDelaySeconds, string $error): bool
    {
        $nextVisible = date('Y-m-d H:i:s', time()+$retryDelaySeconds);
        $stmt=$this->db->prepare("UPDATE jobs SET status=IF(attempt < max_attempts,'pending','dead'), next_visible_at=IF(attempt < max_attempts, ?, NULL), last_error=? WHERE job_id=? AND status IN ('processing','leased')");
        $stmt->bind_param('sss',$nextVisible,$error,$jobId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function reclaimExpiredLeases(): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt=$this->db->prepare("UPDATE jobs SET status='pending', lease_expires_at=NULL WHERE status IN ('leased','processing') AND lease_expires_at IS NOT NULL AND lease_expires_at < ?");
        $stmt->bind_param('s',$now); $stmt->execute(); $rows=$stmt->affected_rows; $stmt->close(); return $rows;
    }

    public function purgeDead(int $olderThanHours=24): int
    {
        $cut = date('Y-m-d H:i:s', time()-$olderThanHours*3600);
        $stmt=$this->db->prepare("DELETE FROM jobs WHERE status='dead' AND updated_at < ?");
        $stmt->bind_param('s',$cut); $stmt->execute(); $rows=$stmt->affected_rows; $stmt->close(); return $rows;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16); $data[6] = chr(ord($data[6]) & 0x0f | 0x40); $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4));
    }
}
