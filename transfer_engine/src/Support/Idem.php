<?php
declare(strict_types=1);
namespace Unified\Support;
/** Idem.php (Phase M1)
 * Idempotency placeholder. Future: hashed key registry with TTL + storage backend.
 */
final class Idem
{
    public static function check(string $key): bool { return true; }
}
