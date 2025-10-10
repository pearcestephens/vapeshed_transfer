<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Middleware;

use CisConsole\App\Support\Response;

final class RateLimitMiddleware
{
    private int $max;
    private int $window;

    public function __construct(array $config)
    {
        $this->max = (int)($config['requests'] ?? 30);
        $this->window = (int)($config['window'] ?? 60);
    }

    public function enforce(string $key): void
    {
        $dir = sys_get_temp_dir() . '/cis_console_rate/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $bucket = $dir . rawurlencode($key) . '.json';
        $now = time();
        $data = ['ts' => $now, 'count' => 0];
        if (is_file($bucket)) {
            $raw = (string)@file_get_contents($bucket);
            $parsed = json_decode($raw, true);
            if (is_array($parsed) && isset($parsed['ts'], $parsed['count'])) {
                $data = $parsed;
            }
        }
        if (($now - (int)$data['ts']) >= $this->window) {
            $data = ['ts' => $now, 'count' => 0];
        }
        $data['count']++;
        if ($data['count'] > $this->max) {
            Response::json(['success' => false, 'error' => ['code' => 'rate_limited', 'message' => 'Too many requests']], 429);
            exit;
        }
        @file_put_contents($bucket, json_encode($data));
    }
}
