<?php
declare(strict_types=1);
namespace Unified\Support;
/** Config.php
 * Config abstraction. Phase 1: in-memory array overlay (will integrate with config_items later).
 */
final class Config
{
    private static array $cache = [];
    public static function prime(): void
    {
        // Seed minimal defaults (namespaced) – future: hydrate from DB.
        self::$cache += [
            'neuro.unified.balancer.target_dsr' => 10,
            'neuro.unified.balancer.daily_line_cap' => 500,
            'neuro.unified.matching.min_confidence' => 0.82,
        ];
    }
    public static function get(string $key, mixed $default=null): mixed
    { return self::$cache[$key] ?? $default; }
    public static function set(string $key, mixed $value): void
    { self::$cache[$key] = $value; }
}
