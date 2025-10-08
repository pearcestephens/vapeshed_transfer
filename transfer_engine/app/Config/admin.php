<?php
declare(strict_types=1);

/**
 * Admin feature flags & UI toggles.
 * These may be overridden by env at boot if you already map env → config.
 */
return [
    // Keep protected endpoints behind auth in SAFE_MODE (401 envelope).
    'safe_mode' => true,

    // Show phpinfo tile in Health (use only in secured environments!)
    'show_phpinfo' => false,

    // Compact sidebar by default (collapsible at <992px regardless).
    'sidebar_compact' => false,
];
