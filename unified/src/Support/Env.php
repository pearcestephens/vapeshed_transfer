<?php
declare(strict_types=1);
namespace Unified\Support;
/** Env.php
 * Simple environment loader (extensible). Currently relies on existing server vars.
 */
final class Env
{
    public static function load(): void
    {
        // Placeholder: could parse .env if needed later.
    }
    public static function get(string $key, ?string $default=null): ?string
    {
        $v = getenv($key); return $v === false ? $default : $v;
    }
}
