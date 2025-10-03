<?php
declare(strict_types=1);
/**
 * Bootstrap.php
 * Unified Retail Intelligence Platform bootstrap layer.
 *
 * Responsibilities:
 *  - Environment & config loading wrapper
 *  - Database (PDO/mysqli) connection provisioning
 *  - Lightweight dependency locator for module services
 *  - Guardrail / policy strategy registration (extensible)
 *
 * NOTE: This is a P0 skeleton – real logic will be incrementally filled.
 */

namespace Unified; 

use Unified\Support\Config;
use Unified\Support\Env;
use Unified\Support\Pdo;
use Unified\Support\Logger;

final class Bootstrap
{
    private static bool $booted = false;
    private static array $container = [];

    public static function init(): void
    {
        if (self::$booted) { return; }
        Env::load();
        Config::prime();
        self::$container['logger'] = new Logger('unified');
        self::$container['db'] = Pdo::instance();
        self::$booted = true;
    }

    /** Fetch a dependency from the lightweight container */
    public static function get(string $key): mixed
    {
        return self::$container[$key] ?? null;
    }
}
