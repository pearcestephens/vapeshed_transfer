<?php
declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Engine\Contracts\QueuePort;

final class FileQueueAdapter implements QueuePort
{
    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = $base ?: (defined('APP_ROOT') ? APP_ROOT . '/var/queue' : sys_get_temp_dir() . '/queue');
        if (!is_dir($this->base.'/ready')) @mkdir($this->base.'/ready', 0755, true);
        if (!is_dir($this->base.'/reserved')) @mkdir($this->base.'/reserved', 0755, true);
        if (!is_dir($this->base.'/done')) @mkdir($this->base.'/done', 0755, true);
        if (!is_dir($this->base.'/failed')) @mkdir($this->base.'/failed', 0755, true);
    }

    public function enqueue(array $payload): string
    {
        $id = 'job_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $path = $this->base . '/ready/' . $id . '.json';
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
        return $id;
    }

    public function reserve(int $timeoutSec = 0): ?array
    {
        $end = time() + max(0, $timeoutSec);
        do {
            $files = glob($this->base . '/ready/*.json') ?: [];
            if (!empty($files)) {
                $file = $files[0];
                $id = basename($file, '.json');
                $dest = $this->base . '/reserved/' . basename($file);
                if (@rename($file, $dest)) {
                    $payload = json_decode(file_get_contents($dest), true) ?: [];
                    $payload['_job_id'] = $id;
                    $payload['_job_path'] = $dest;
                    return $payload;
                }
            }
            if ($timeoutSec <= 0) return null;
            usleep(200 * 1000);
        } while (time() < $end);
        return null;
    }

    public function complete(string $jobId, array $result = []): void
    {
        $src = $this->base . '/reserved/' . $jobId . '.json';
        $dst = $this->base . '/done/' . $jobId . '.json';
        if (is_file($src)) {
            if (!empty($result)) {
                file_put_contents($src, json_encode($result, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
            }
            @rename($src, $dst);
        }
    }

    public function fail(string $jobId, string $reason, bool $requeue = false): void
    {
        $src = $this->base . '/reserved/' . $jobId . '.json';
        if ($requeue) {
            $dst = $this->base . '/ready/' . $jobId . '.json';
            if (is_file($src)) @rename($src, $dst);
            return;
        }
        $dst = $this->base . '/failed/' . $jobId . '.json';
        if (is_file($src)) {
            $payload = json_decode(file_get_contents($src), true) ?: [];
            $payload['_failed_reason'] = $reason;
            file_put_contents($src, json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
            @rename($src, $dst);
        }
    }
}
