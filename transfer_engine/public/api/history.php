<?php
/**
 * History API Endpoint
 * Provides enriched proposal history with guardrail rollups
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Persistence\ReadModels\HistoryReadModel;
use Unified\Support\UiKernel;
use Unified\Support\Config;
use Unified\Support\Api;
use Unified\Support\Validator;

Api::initJson();
Api::applyCors();
Api::handleOptionsPreflight();

// Feature flag guard
if (!Config::get('neuro.unified.ui.history_api_enabled', false)) {
    Api::error('DISABLED', 'History API is disabled', 403);
}

// Optional token authentication
// Normalize Authorization: Bearer <token> -> X-API-TOKEN
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);

// Optional per-IP GET rate limiting (group: history)
Api::enforceGetRateLimit('history');

$logger = UiKernel::logger();

try {
    $readModel = new HistoryReadModel($logger);

    $type = isset($_GET['type']) ? (string)$_GET['type'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    // Strict validations
    if ($type !== null && !in_array($type, ['transfer', 'pricing'], true)) {
        Api::error('INVALID_TYPE', 'Type must be "transfer" or "pricing"', 400);
    }
    try { Validator::intRange($limit, 1, 200, 'limit'); } catch (\InvalidArgumentException $ex) {
        Api::error('INVALID_LIMIT', 'Limit must be between 1 and 200', 400);
    }

    $history = $readModel->enrichedHistory($type, $limit);

    Api::ok([
        'items' => $history,
        'count' => count($history),
        'type' => $type ?? 'all',
        'limit' => $limit,
        'correlation_id' => correlationId()
    ]);

} catch (Exception $e) {
    $logger->error('api.history.error', [
        'correlation_id' => correlationId(),
        'error' => $e->getMessage()
    ]);
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
