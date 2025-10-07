<?php
declare(strict_types=1);
namespace Unified\Support;
/** Config.php (Phase M1)
 * Unified config abstraction. Currently in-memory; to be replaced with DB-backed loader (config_items) + fallback shim.
 */
final class Config
{
    private static array $cache = [];
    private static array $fallbackMap = [];
    private static array $warned = [];
    private static ?Logger $logger = null;

    public static function setLogger(Logger $logger): void { self::$logger = $logger; }

    public static function registerFallbacks(array $map): void
    { self::$fallbackMap = $map + self::$fallbackMap; }

    public static function prime(): void
    {
        if (!self::$cache) {
            self::$cache = [
                // Environment & Security (sane production defaults)
                'neuro.unified.environment' => Env::get('APP_ENV', 'production'),
                'neuro.unified.security.csrf_required' => (Env::get('CSRF_REQUIRED', 'false') === 'true'),
                'neuro.unified.security.post_rate_limit_per_min' => (int) (Env::get('POST_RL_PER_MIN', '0') ?? '0'),
                'neuro.unified.security.post_rate_burst' => (int) (Env::get('POST_RL_BURST', '0') ?? '0'),
                // CORS allowlist can be provided via env CORS_ALLOWLIST (comma-separated). If not, allow staff domains by default.
                'neuro.unified.security.cors_allowlist' => (function () {
                    $envList = Env::get('CORS_ALLOWLIST');
                    if (is_string($envList) && $envList !== '') {
                        return array_filter(array_map('trim', explode(',', $envList)));
                    }
                    return [
                        'https://staff.vapeshed.co.nz',
                        'https://www.staff.vapeshed.co.nz'
                    ];
                })(),
                'neuro.unified.balancer.target_dsr' => 10,
                'neuro.unified.balancer.daily_line_cap' => 500,
                'neuro.unified.matching.min_confidence' => 0.82,
                'neuro.unified.pricing.min_margin_pct' => 0.22,
                'neuro.unified.pricing.delta_cap_pct' => 0.07,
                'neuro.unified.policy.auto_apply_min' => 0.65,
                'neuro.unified.policy.propose_min' => 0.15,
                'neuro.unified.policy.auto_apply_pricing' => false, // Phase M18 flag
                'neuro.unified.policy.cooloff_hours' => 24, // Phase M18 cooloff baseline
                'neuro.unified.drift.psi_warn' => 0.15,
                'neuro.unified.drift.psi_critical' => 0.25,
                'neuro.unified.views.materialize.v_sales_daily' => false,
                'neuro.unified.views.materialize.v_inventory_daily' => false,
            ];
        }
    }

    public static function get(string $key, mixed $default=null): mixed
    {
        if (array_key_exists($key, self::$cache)) { return self::$cache[$key]; }
        // Fallback lookup (legacy -> new)
        if (isset(self::$fallbackMap[$key])) {
            $legacyKey = self::$fallbackMap[$key];
            if (isset(self::$cache[$legacyKey])) {
                self::warnOnce('config.fallback', [ 'requested'=>$key,'legacy_used'=>$legacyKey ]);
                return self::$cache[$legacyKey];
            }
        }
        return $default;
    }

    public static function set(string $key, mixed $value): void
    { self::$cache[$key] = $value; }

    public static function requiredKeys(): array
    {
        return [
            'neuro.unified.balancer.target_dsr',
            'neuro.unified.balancer.daily_line_cap',
            'neuro.unified.matching.min_confidence',
            'neuro.unified.pricing.min_margin_pct',
            'neuro.unified.pricing.delta_cap_pct',
            'neuro.unified.policy.auto_apply_min',
            'neuro.unified.policy.propose_min'
        ];
    }

    public static function missing(): array
    { self::prime(); $m=[]; foreach(self::requiredKeys() as $k){ if(!array_key_exists($k,self::$cache)) $m[]=$k; } return $m; }

    private static function warnOnce(string $code, array $ctx): void
    {
        $hash = $code.'|'.md5(json_encode($ctx));
        if (isset(self::$warned[$hash])) { return; }
        self::$warned[$hash] = true;
        if (self::$logger) { self::$logger->warn($code, $ctx); }
        else { fwrite(STDERR, '[WARN]['.$code.'] '.json_encode($ctx)."\n"); }
    }
}
