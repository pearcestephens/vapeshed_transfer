<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Pdo; use PDO;
/** Db (Phase M12)
 * Central PDO accessor for persistence layer (thin wrapper for clarity).
 */
final class Db
{
    public static function pdo(): PDO { return Pdo::instance(); }
}
