<?php
declare(strict_types=1);

// This shim exposes specific helper functions used by the engine adapter by referencing
// the implementations in control-panel/api.php. To avoid duplication, we re-declare thin
// wrappers when functions are not yet available in this scope.

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 3) . '/config/bootstrap.php';
}

// Minimal re-declarations if missing
if (!function_exists('progress_dir')) {
    function progress_dir(): string { return APP_ROOT . '/var/tmp/progress'; }
}
if (!function_exists('progress_path')) {
    function progress_path(string $runId): string { return progress_dir() . '/' . basename($runId) . '.json'; }
}
if (!function_exists('active_runs_path')) {
    function active_runs_path(): string { return APP_ROOT . '/var/tmp/active_runs.json'; }
}
if (!function_exists('ensure_progress_dir')) {
    function ensure_progress_dir(): void { $d = progress_dir(); if (!is_dir($d)) { @mkdir($d, 0755, true); } }
}
if (!function_exists('load_json_file')) {
    function load_json_file(string $path, $default) { $raw = @file_get_contents($path); if ($raw === false) return $default; $j = @json_decode($raw, true); return is_array($j) ? $j : $default; }
}
if (!function_exists('save_json_file')) {
    function save_json_file(string $path, array $data): bool { $dir = dirname($path); if (!is_dir($dir)) @mkdir($dir,0755,true); return (bool)@file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
}
if (!function_exists('mark_run_active')) {
    function mark_run_active(string $runId): void {
        $path = active_runs_path();
        $data = load_json_file($path, [ 'runs' => [] ]);
        $exists = false;
        foreach ($data['runs'] as $r) { if (($r['run_id'] ?? '') === $runId) { $exists = true; break; } }
        if (!$exists) { $data['runs'][] = ['run_id' => $runId, 'since' => date('c')]; save_json_file($path, $data); }
    }
}
if (!function_exists('mark_run_inactive')) {
    function mark_run_inactive(string $runId): void {
        $path = active_runs_path();
        $data = load_json_file($path, [ 'runs' => [] ]);
        $data['runs'] = array_values(array_filter($data['runs'], static fn($r) => ($r['run_id'] ?? '') !== $runId));
        save_json_file($path, $data);
    }
}
if (!function_exists('seed_progress')) {
    function seed_progress(string $runId, string $mode, string $preset): array {
        ensure_progress_dir();
        $now = date('c');
        $state = [
            'run_id' => $runId,
            'state' => 'queued',
            'percent' => 0,
            'stage' => 'queued',
            'processed' => 0,
            'total' => 0,
            'mode' => $mode,
            'preset' => $preset,
            'started_at' => $now,
            'updated_at' => $now
        ];
        save_json_file(progress_path($runId), $state);
        mark_run_active($runId);
        return $state;
    }
}
if (!function_exists('timer_store_path')) {
    function timer_store_path(string $runId): string { return APP_ROOT . '/var/tmp/timers/' . basename($runId) . '.json'; }
}
if (!function_exists('timer_start')) {
    function timer_start(string $runId, string $step): void {
        $path = timer_store_path($runId); $now = microtime(true);
        $data = load_json_file($path, ['steps'=>[]]);
        $data['steps'][$step] = ['start' => $now];
        save_json_file($path, $data);
    }
}
if (!function_exists('timer_end')) {
    function timer_end(string $runId, string $step): float {
        $path = timer_store_path($runId); $now = microtime(true);
        $data = load_json_file($path, ['steps'=>[]]);
        $s = $data['steps'][$step]['start'] ?? $now; $dur = max(0.0, $now - (float)$s);
        $data['steps'][$step]['end'] = $now; $data['steps'][$step]['duration_sec'] = $dur;
        save_json_file($path, $data); return $dur;
    }
}
if (!function_exists('progress_update')) {
    function progress_update(string $runId, array $patch): array {
        $path = progress_path($runId);
        $state = load_json_file($path, []);
        if (empty($state)) return [];
        foreach ($patch as $k=>$v) { $state[$k] = $v; }
        $state['updated_at'] = date('c');
        save_json_file($path, $state);
        return $state;
    }
}
