<?php
declare(strict_types=1);
namespace Unified\Support;
use PDO; use PDOException; use RuntimeException;
/** Pdo.php
 * Thin PDO singleton (MySQLi alternative). Uses environment variables.
 */
final class Pdo
{
    private static ?PDO $instance = null;
    public static function instance(): PDO
    {
        if (self::$instance) { return self::$instance; }
        $host = Env::get('DB_HOST','localhost');
        $db   = Env::get('DB_NAME','cis');
        $user = Env::get('DB_USER','root');
        $pass = Env::get('DB_PASS','');
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('DB connect failed: '.$e->getMessage(), 0, $e);
        }
        self::$instance = $pdo; return $pdo;
    }
}
