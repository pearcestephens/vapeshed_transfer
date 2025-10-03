<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Service;

use App\Engines\AutoBalancer\Config\AutoBalancerConfig;
use mysqli;

final class ExecutionService
{
    public function __construct(private readonly mysqli $db, private readonly AutoBalancerConfig $config) {}

    public function execute(array $plan, bool $dryRun = true): array
    {
        $results = ['executed'=>0,'allocations'=>0,'dry_run'=>$dryRun];
        if ($dryRun) { return $results; }
        foreach (['urgent','high','normal'] as $tier) {
            foreach ($plan[$tier] ?? [] as $transfer) {
                $execId = $this->insertExecution($tier);
                $this->insertAllocation($execId, $transfer);
                $results['executed']++; $results['allocations']++;
            }
        }
        return $results;
    }

    private function insertExecution(string $priority): int
    {
        $publicId = 'auto_' . date('Ymd_His') . '_' . substr(md5($priority.microtime(true)),0,8);
        $alias = strtoupper($priority) . '_' . date('Ymd_His');
        $sql = "INSERT INTO transfer_executions (public_id, alias_code, config_id, simulation_mode, status, executed_by) VALUES (?,?,?,?,?,?)";
        $stmt = $this->db->prepare($sql);
        $configId = 1; $sim = 0; $status='pending'; $by='auto_balancer';
        $stmt->bind_param('ssii ss', $publicId,$alias,$configId,$sim,$status,$by); // space in types will be ignored by mysqli but kept readable
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function insertAllocation(int $executionId, array $transfer): void
    {
        $sql = "INSERT INTO transfer_allocations (execution_id, product_id, allocated_quantity, qty, calculation_data, public_id) VALUES (?,?,?,?,?,?)";
        $stmt = $this->db->prepare($sql);
        $calc = json_encode([
            'reason' => $transfer['reason'],
            'urgency' => $transfer['urgency_score'],
            'from' => $transfer['from_outlet'],
            'to' => $transfer['to_outlet'],
        ], JSON_UNESCAPED_SLASHES);
        $publicId = 'alloc_' . substr(md5($transfer['product_id'].$transfer['from_outlet'].$transfer['to_outlet']),0,12);
        $qty = $transfer['recommended_qty'];
        $stmt->bind_param('is iisss', $executionId, $transfer['product_id'], $qty, $qty, $calc, $publicId);
        $stmt->execute();
        $stmt->close();
    }
}
