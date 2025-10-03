<?php
declare(strict_types=1);
namespace Unified\Support;
/** Idem.php
 * Idempotency placeholder (DB/Redis later). Currently returns a one-shot token pass.
 */
final class Idem
{
    public static function check(string $key): bool { return true; }
}
