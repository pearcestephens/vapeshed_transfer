<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Database abstraction layer providing PDO access and query helpers for metrics.
 */
final class Db
{
    private static ?self $instance = null;
    private static ?\PDO $pdo = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
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

    /**
     * Execute INSERT query and return inserted ID
     */
    public function insert(string $query, array $params = []): int|false
    {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare($query);
            $success = $stmt->execute($params);

            return $success ? (int)$pdo->lastInsertId() : false;
        } catch (\PDOException $e) {
            error_log('Db::insert() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute query and return all results
     */
    public function fetchAll(string $query, array $params = []): array
    {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return [];
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('Db::fetchAll() failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute query and return single result
     */
    public function fetchOne(string $query, array $params = []): array|false
    {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result !== false ? $result : false;
        } catch (\PDOException $e) {
            error_log('Db::fetchOne() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute query (UPDATE, DELETE, etc.)
     */
    public function execute(string $query, array $params = []): bool
    {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare($query);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log('Db::execute() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get number of affected rows
     */
    public function rowCount(): int
    {
        try {
            $pdo = self::pdo();
            if (!$pdo) {
                return 0;
            }

            return (int)$pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        } catch (\PDOException $e) {
            error_log('Db::rowCount() failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        try {
            $pdo = self::pdo();
            return $pdo ? $pdo->beginTransaction() : false;
        } catch (\PDOException $e) {
            error_log('Db::beginTransaction() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        try {
            $pdo = self::pdo();
            return $pdo ? $pdo->commit() : false;
        } catch (\PDOException $e) {
            error_log('Db::commit() failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        try {
            $pdo = self::pdo();
            return $pdo ? $pdo->rollBack() : false;
        } catch (\PDOException $e) {
            error_log('Db::rollback() failed: ' . $e->getMessage());
            return false;
        }
    }
}
