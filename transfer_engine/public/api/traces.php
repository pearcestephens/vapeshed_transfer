<?php
/**
 * Traces API Endpoint
 * Provides detailed guardrail traces for a specific proposal
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
if (!Config::get('neuro.unified.ui.traces_api_enabled', false)) {
    Api::error('DISABLED', 'Traces API is disabled', 403);
}

// Optional token authentication
// Map Authorization: Bearer <token> to X-API-TOKEN for convenience
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $_SERVER['HTTP_X_API_TOKEN'] = $m[1];
}
Api::enforceOptionalToken('neuro.unified.ui.api_token', ['HTTP_X_API_TOKEN','HTTP_AUTHORIZATION']);

// Optional per-IP GET rate limit (group: traces)
Api::enforceGetRateLimit('traces');

$logger = UiKernel::logger();

try {
    $readModel = new HistoryReadModel($logger);
    
    $proposalId = isset($_GET['proposal_id']) ? (int)$_GET['proposal_id'] : 0;
    try { Validator::intRange($proposalId, 1, 2147483647, 'proposal_id'); }
    catch (\InvalidArgumentException $ex) { Api::error('INVALID_PROPOSAL_ID', 'Valid proposal_id parameter is required', 400); }
    
    $traces = $readModel->proposalTraces($proposalId);
    
    Api::ok([
        'proposal_id' => $proposalId,
        'traces' => $traces,
        'count' => count($traces),
        'correlation_id' => correlationId()
    ]);
    
} catch (Exception $e) {
    $logger->error('api.traces.error', [
        'correlation_id' => correlationId(),
        'proposal_id' => $proposalId ?? null,
        'error' => $e->getMessage()
    ]);
    
    Api::error('INTERNAL_ERROR', 'An internal error occurred', 500);
}
