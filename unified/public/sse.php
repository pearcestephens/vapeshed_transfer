<?php
/**
 * Deprecated placeholder (see transfer_engine/public/sse.php)
 * This file remains to avoid 404s during migration; it delegates to the active SSE endpoint.
 */
declare(strict_types=1);
// Delegate to the active SSE endpoint in transfer_engine
require_once __DIR__ . '/../../transfer_engine/public/sse.php';
