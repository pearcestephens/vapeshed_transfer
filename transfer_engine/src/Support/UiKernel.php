<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * UiKernel (Phase B Draft)
 * Centralizes future UI initialization & service exposure so legacy bootstrap
 * can be deprecated cleanly. For now it offers a static init() facade and
 * service getters that delegate to existing helpers (transitional layer).
 */
final class UiKernel
{
    private static bool $initialized = false;
    public static function init(): void
    {
        if (self::$initialized) return;
        Config::prime();
        self::$initialized = true;
    }

    public static function pdo(): \PDO { return Pdo::instance(); }
    public static function logger(string $channel='ui'): Logger { return new Logger($channel); }
    public static function correlationId(): string { return \correlationId(); }
}
