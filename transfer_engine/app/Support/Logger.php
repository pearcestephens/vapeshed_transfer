<?php
declare(strict_types=1);

namespace App\Support;

use Unified\Support\Logger as UnifiedLogger;
use Unified\Support\Env;

final class Logger
{
    /** @var array<string, UnifiedLogger> */
    private static array $channels = [];

    public static function channel(string $channel = 'admin'): UnifiedLogger
    {
        if (!isset(self::$channels[$channel])) {
            $defaultStorage = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage';
            $logDir = Env::get('LOG_PATH', $defaultStorage . '/logs');
            $logFile = rtrim($logDir, '/') . '/' . $channel . '.log';
            self::$channels[$channel] = new UnifiedLogger($channel, $logFile);
        }

        return self::$channels[$channel];
    }

    public static function info(string $message, array $context = [], string $channel = 'admin'): void
    {
        self::channel($channel)->info($message, $context);
    }

    public static function error(string $message, array $context = [], string $channel = 'admin'): void
    {
        self::channel($channel)->error($message, $context);
    }

    public static function warn(string $message, array $context = [], string $channel = 'admin'): void
    {
        self::channel($channel)->warn($message, $context);
    }

    public static function debug(string $message, array $context = [], string $channel = 'admin'): void
    {
        self::channel($channel)->debug($message, $context);
    }

    public static function exception(\Throwable $e, array $context = [], string $channel = 'admin'): void
    {
        self::channel($channel)->exception($e, 'ERROR', $context);
    }
}
