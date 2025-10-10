<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Controllers\Admin;

use CisConsole\App\Support\Response;

final class HealthController
{
    private array $app;
    private array $security;

    public function __construct(array $app, array $security)
    {
        $this->app = $app;
        $this->security = $security;
    }

    public function ping(): void
    {
        Response::json(['success' => true, 'data' => ['pong' => true, 'env' => $this->app['env']]]);
    }

    public function phpinfo(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        ob_start();
        phpinfo();
        $html = ob_get_clean();
        echo $html;
    }

    public function checks(): void
    {
        $logs = $this->security['logs'] ?? [];
        $checks = [
            'php_version' => PHP_VERSION,
            'disk_free' => disk_free_space('/') ?: 0,
            'time' => time(),
            'apache_error_log_readable' => isset($logs['apache_error']) && is_readable($logs['apache_error']),
            'apache_access_log_readable' => isset($logs['apache_access']) && is_readable($logs['apache_access']),
            'snapshot_dir_writable' => isset($logs['snapshot_dir']) && is_dir($logs['snapshot_dir']) && is_writable($logs['snapshot_dir']),
            'ssl_status' => 'not_applicable',
            'db_status' => 'not_configured',
            'vend_api' => (strtolower((string)($this->app['browse_mode'] ? 'on' : 'off')) === 'on') ? 'blocked_in_browse_mode' : 'not_configured',
        ];
        Response::json(['success' => true, 'data' => $checks]);
    }

    public function grid(): void
    {
        $logs = $this->security['logs'] ?? [];
        $items = [
            ['name' => 'PHP', 'ok' => version_compare(PHP_VERSION, '8.1', '>='), 'detail' => PHP_VERSION],
            ['name' => 'Disk', 'ok' => (disk_free_space('/') ?: 0) > 500 * 1024 * 1024, 'detail' => (string)disk_free_space('/')],
            ['name' => 'Apache Error Log', 'ok' => isset($logs['apache_error']) && is_readable($logs['apache_error']), 'detail' => $logs['apache_error'] ?? ''],
            ['name' => 'Apache Access Log', 'ok' => isset($logs['apache_access']) && is_readable($logs['apache_access']), 'detail' => $logs['apache_access'] ?? ''],
            ['name' => 'Snapshot Dir', 'ok' => isset($logs['snapshot_dir']) && is_dir($logs['snapshot_dir']) && is_writable($logs['snapshot_dir']), 'detail' => $logs['snapshot_dir'] ?? ''],
            ['name' => 'Vend API', 'ok' => false, 'detail' => ($this->app['browse_mode'] ? 'blocked_in_browse_mode' : 'not_configured')],
        ];
        Response::json(['success' => true, 'data' => ['items' => $items]]);
    }

    public function oneClick(): void
    {
        $results = [];
        $ok = true;
        // SSL
        $isHttps = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
        $results['ssl'] = ['ok' => $isHttps, 'detail' => $isHttps ? 'https' : 'http'];
        $ok = $ok && $isHttps;
        // Disk
        $free = (int)(disk_free_space('/') ?: 0);
        $diskOk = $free > 500 * 1024 * 1024;
        $results['disk'] = ['ok' => $diskOk, 'detail' => $free];
        $ok = $ok && $diskOk;
        // PHP-FPM/apache responsiveness (self-ping)
        $results['self'] = ['ok' => true, 'detail' => 'router alive'];
        // DB (placeholder)
        $results['db'] = ['ok' => false, 'detail' => 'not_configured'];
        // Queue workers (placeholder)
        $results['queue'] = ['ok' => false, 'detail' => 'not_configured'];
        // Vend API connectivity
        $results['vend_api'] = ['ok' => false, 'detail' => $this->app['browse_mode'] ? 'blocked_in_browse_mode' : 'not_configured'];

        Response::json(['success' => true, 'data' => ['summary_ok' => $ok, 'checks' => $results]]);
    }
}
