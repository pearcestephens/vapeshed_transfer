<?php
declare(strict_types=1);
namespace Unified\Health;
use Unified\Support\Pdo; use Unified\Support\Logger; use PDOException;
/** HealthProbe (Phase M8)
 * Provides simple health indicators (DB connectivity, time sync stub).
 */
final class HealthProbe
{
    public function __construct(private Logger $logger) {}
    public function check(): array
    {
        $dbOk=false; $err=null; $ts=null;
        try { $pdo=Pdo::instance(); $ts=$pdo->query('SELECT NOW() n')->fetch()['n']; $dbOk=true; } catch (PDOException $e){ $err=$e->getMessage(); }
        return ['db_ok'=>$dbOk,'db_time'=>$ts,'error'=>$err];
    }
}
