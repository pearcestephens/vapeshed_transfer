<?php
declare(strict_types=1);
namespace Unified\Support;
/** Env.php (Phase M1)
 * Minimal environment variable accessor. Can be extended to parse .env.
 */
final class Env
{
    public static function load(): void { /* placeholder */ }
    public static function get(string $key, ?string $default=null): ?string
    { $v = getenv($key); return $v === false ? $default : $v; }
}
