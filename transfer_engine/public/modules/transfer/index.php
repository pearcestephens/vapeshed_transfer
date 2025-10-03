<?php
/**
 * Transfer Module Entry Point
 * 
 * Self-contained transfer module with single bootstrap require
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';

// Require authentication
auth()->requireAuth();

// Get module configuration
$moduleConfig = config('modules.transfer');

// Get module instance
$module = new TransferModule();

// Render module view
echo $module->render();
