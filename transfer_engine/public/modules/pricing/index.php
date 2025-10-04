<?php
/**
 * Pricing Module Entry Point
 */
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

auth()->requireAuth();

$module = new PricingModule();
echo $module->render();
