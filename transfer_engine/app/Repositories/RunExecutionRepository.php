<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;
use RuntimeException;

class RunExecutionRepository
{
    public function __construct(private mysqli $db) {}

    public function recordStart(string $runId, string $workflow, string $stepName, string $stepHash): bool
    {
        $stmt=$this->db->prepare("INSERT IGNORE INTO run_executions (run_id, step_hash, workflow, step_name, status) VALUES (?,?,?,?, 'started')");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $stmt->bind_param('ssss',$runId,$stepHash,$workflow,$stepName); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function markComplete(string $runId, string $stepHash, ?string $resultHash=null): bool
    {
        $stmt=$this->db->prepare("UPDATE run_executions SET status='completed', result_hash=? WHERE run_id=? AND step_hash=? AND status IN ('started','failed')");
        $stmt->bind_param('sss',$resultHash,$runId,$stepHash); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function markFailed(string $runId, string $stepHash, string $error): bool
    {
        $stmt=$this->db->prepare("UPDATE run_executions SET status='failed', error_text=? WHERE run_id=? AND step_hash=?");
        $stmt->bind_param('sss',$error,$runId,$stepHash); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }

    public function alreadyCompleted(string $runId, string $stepHash): bool
    {
        $stmt=$this->db->prepare("SELECT 1 FROM run_executions WHERE run_id=? AND step_hash=? AND status='completed' LIMIT 1");
        $stmt->bind_param('ss',$runId,$stepHash); $stmt->execute(); $stmt->store_result(); $found=$stmt->num_rows>0; $stmt->close(); return $found;
    }
}
