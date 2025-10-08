<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Database abstraction layer providing PDO access for metrics.
 */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): ?\PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        try {
            // Try to get existing CIS database connection first
            if (function_exists('base_path')) {
                $cisConfigPath = dirname(base_path(), 4) . '/assets/functions/config.php';
            } else {
                $cisConfigPath = dirname(__DIR__, 6) . '/assets/functions/config.php';
            }

            if (file_exists($cisConfigPath)) {
                require_once $cisConfigPath;
                global $pdo;
                if ($pdo instanceof \PDO) {
                    self::$pdo = $pdo;
                    return self::$pdo;
                }
            }

            // Fallback to constants or env
            $host = defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost');
            $name = defined('DB_NAME') ? DB_NAME : ($_ENV['DB_NAME'] ?? 'jcepnzzkmj');
            $user = defined('DB_USER') ? DB_USER : ($_ENV['DB_USER'] ?? 'jcepnzzkmj');
            $pass = defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASS'] ?? '');
            $port = defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? 3306);

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$pdo;
        } catch (\Throwable $e) {
            error_log('Db::pdo() failed: ' . $e->getMessage());
            return null;
        }
    }
}
