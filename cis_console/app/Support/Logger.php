<?php
declare(strict_types=1);

namespace CisConsole\App\Support;

final class Logger
{
    public static function correlationId(): string
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        $id = $incoming !== '' ? $incoming : bin2hex(random_bytes(8));
        return $id;
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $record = [
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
            'request_id' => self::correlationId(),
            'endpoint' => $_GET['endpoint'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ctx' => $context,
        ];
        error_log('[cis_console] ' . json_encode($record, JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void { self::log('info', $message, $context); }
    /** @param array<string,mixed> $context */
    public static function warn(string $message, array $context = []): void { self::log('warn', $message, $context); }
    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void { self::log('error', $message, $context); }
}
