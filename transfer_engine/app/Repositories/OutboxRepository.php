<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use RuntimeException;

class OutboxRepository
{
    public function __construct(private mysqli $db) {}

    public function enqueue(string $aggregateType, string $aggregateId, string $eventType, string $runId, string $idempotencyKey, array $payload): ?string
    {
        $id = self::uuid();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt=$this->db->prepare("INSERT INTO outbox_events (event_id, aggregate_type, aggregate_id, event_type, run_id, idempotency_key, payload_json) VALUES (?,?,?,?,?,?,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        try { $stmt->bind_param('sssssss',$id,$aggregateType,$aggregateId,$eventType,$runId,$idempotencyKey,$json); $stmt->execute(); } catch(\Throwable $e){ $stmt->close(); return null; }
        $stmt->close(); return $id;
    }

    public function fetchBatch(int $limit=50): array
    {
        $res=$this->db->query("SELECT * FROM outbox_events WHERE status='pending' ORDER BY created_at ASC LIMIT ".$limit);
        $rows=[]; while($res && $r=$res->fetch_assoc()){ $rows[]=$r; } return $rows;
    }

    public function markDispatched(string $eventId): bool
    {
        $stmt=$this->db->prepare("UPDATE outbox_events SET status='dispatched', dispatched_at=NOW() WHERE event_id=? AND status='pending'");
        $stmt->bind_param('s',$eventId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function markFailed(string $eventId, string $error): bool
    {
        $stmt=$this->db->prepare("UPDATE outbox_events SET status='failed', last_error=? WHERE event_id=?");
        $stmt->bind_param('ss',$error,$eventId); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    private static function uuid(): string
    {
        $data=random_bytes(16); $data[6]=chr(ord($data[6]) & 0x0f | 0x40); $data[8]=chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data),4));
    }
}
