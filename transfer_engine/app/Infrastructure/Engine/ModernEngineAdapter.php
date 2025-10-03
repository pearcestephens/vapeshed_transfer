<?php
declare(strict_types=1);

namespace App\Infrastructure\Engine;

use App\Domain\Engine\Contracts\EnginePort;
use App\Domain\Engine\DTO\ExecuteRequest;
use App\Domain\Engine\DTO\ExecuteResult;

/**
 * ModernEngineAdapter
 * Canonical URL: https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/app/Infrastructure/Engine/ModernEngineAdapter.php
 * Purpose: Implements EnginePort for modern/clean architecture path.
 * Author: Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * Last Modified: 2025-09-20
 */
/**
 * ModernEngineAdapter
 * Implements EnginePort by running the new test_mode pipeline (fast, timed)
 * and seeding file-backed progress store. For non-test flows, it only accepts
 * and returns a queued result; actual heavy work can be implemented later.
 */
 final class ModernEngineAdapter implements EnginePort
{
    public function __construct()
    {
    }
    public function execute(ExecuteRequest $request): ExecuteResult
    {
    // Use provided runId if present (seeded by API), else generate
    $runId = $request->runId ?: ('run_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8));

        // Seed progress
        if (!function_exists('App\\Infrastructure\\Engine\\seed_progress')) {
            // load the helper shim if needed
            require_once __DIR__ . '/helpers_shim.php';
        }

        seed_progress($runId, $request->mode, $request->preset);

        // If simulate: just accept
        if ($request->simulate) {
            return new ExecuteResult($runId, true, false, 'queued', 'Simulated execute');
        }

        // Test Mode: run the quick-timed steps using the same helpers
        if ($request->testMode) {
            timer_start($runId, 'select_items');
            // Use safe default for selection size; specific config can be injected later via a ConfigPort
            $selectedCount = 50;
            usleep(100 * 1000);
            $durSel = timer_end($runId, 'select_items');
            progress_update($runId, ['stage' => 'selected items', 'percent' => 10, 'selected' => $selectedCount, 'select_ms' => (int)round($durSel*1000)]);

            timer_start($runId, 'build_plan');
            usleep(200 * 1000);
            $durPlan = timer_end($runId, 'build_plan');
            progress_update($runId, ['stage' => 'built plan', 'percent' => 30, 'plan_ms' => (int)round($durPlan*1000)]);

            for ($i=1;$i<=3;$i++) {
                timer_start($runId, 'batch_'.$i);
                usleep(150 * 1000);
                $d = timer_end($runId, 'batch_'.$i);
                $pct = 30 + $i*20;
                progress_update($runId, ['stage' => 'processing batch '.$i, 'percent' => $pct, 'batch_'.$i.'_ms' => (int)round($d*1000)]);
            }

            timer_start($runId, 'finalize');
            usleep(100 * 1000);
            $df = timer_end($runId, 'finalize');
            progress_update($runId, ['stage' => 'finalizing', 'percent' => 100, 'finalize_ms' => (int)round($df*1000)]);
            $p = load_json_file(progress_path($runId), []);
            $p['state'] = 'completed';
            $p['finished_at'] = date('c');
            save_json_file(progress_path($runId), $p);
            mark_run_inactive($runId);
            return new ExecuteResult($runId, true, false, 'queued', 'Execute accepted (test mode)');
        }

        // Non-test: run synchronously via LegacyEngineBridge to preserve behavior
        $bridge = new \App\Services\LegacyEngineBridge();
        // Mark running in progress store
        $p = load_json_file(progress_path($runId), []);
        if (!empty($p)) { progress_update($runId, ['stage' => 'processing', 'state' => 'running']); }
        try {
            $raw = $bridge->runRaw([
                'mode' => $request->mode,
                'preset' => $request->preset,
                'format' => 'json'
            ], 'json');
            // On success mark complete
            $p = load_json_file(progress_path($runId), []);
            if (!empty($p)) {
                $p['state'] = 'completed';
                $p['percent'] = 100;
                $p['stage'] = 'finished';
                $p['finished_at'] = date('c');
                save_json_file(progress_path($runId), $p);
                mark_run_inactive($runId);
            }
            return new ExecuteResult($runId, true, false, 'queued', 'Execute completed');
        } catch (\Throwable $e) {
            $p = load_json_file(progress_path($runId), []);
            if (!empty($p)) {
                $p['state'] = 'failed';
                $p['stage'] = 'error';
                $p['error'] = $e->getMessage();
                $p['finished_at'] = date('c');
                save_json_file(progress_path($runId), $p);
                mark_run_inactive($runId);
            }
            return new ExecuteResult($runId, true, false, 'queued', 'Execute failed: ' . $e->getMessage());
        }
    }
}
