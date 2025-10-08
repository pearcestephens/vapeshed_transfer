<?php
declare(strict_types=1);

/**
 * Traffic monitoring configuration.
 */
return [
    // Enable request recording
    'enabled'        => true,

    // Sample rate (0.0 = never, 1.0 = always)
    'sample_rate'    => 1.0,

    // Max response body size to log (0 = never log body)
    'max_body_log'   => 0,

    // SSE stream update interval (milliseconds)
    'sse_tick_ms'    => 2000,

    // Privacy salt for UA hashing
    'privacy_salt'   => $_ENV['TRAFFIC_PRIVACY_SALT'] ?? 'change-me-in-production',
];
