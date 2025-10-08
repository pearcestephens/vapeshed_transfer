<?php
/**
 * Monitoring API Routes
 * 
 * Routes for monitoring, health checks, performance profiling,
 * log aggregation, and alerting management.
 */

use VapeshedTransfer\Controllers\Api\MonitoringController;

$router->group('/api/monitoring', function($router) {
    // Health endpoints
    $router->get('/health', [MonitoringController::class, 'health']);
    $router->get('/health/history', [MonitoringController::class, 'healthHistory']);
    
    // Performance endpoints
    $router->get('/performance', [MonitoringController::class, 'performance']);
    $router->get('/performance/current', [MonitoringController::class, 'performanceCurrent']);
    
    // Log endpoints
    $router->get('/logs', [MonitoringController::class, 'logs']);
    $router->get('/logs/stats', [MonitoringController::class, 'logStats']);
    $router->get('/logs/tail', [MonitoringController::class, 'logTail']);
    $router->post('/logs/export', [MonitoringController::class, 'logExport']);
    
    // Alert endpoints
    $router->get('/alerts', [MonitoringController::class, 'alerts']);
    $router->post('/alerts/send', [MonitoringController::class, 'sendAlert']);
    
    // Overview endpoint
    $router->get('/overview', [MonitoringController::class, 'overview']);
});
