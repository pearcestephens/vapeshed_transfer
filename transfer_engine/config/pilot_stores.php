<?php
/**
 * Pilot Stores Configuration
 * 
 * Defines which stores are included in the pilot program
 * Set pilot_enabled to true to activate pilot mode
 */
return [
    'pilot_enabled' => false, // Set to true to activate pilot
    
    'pilot_stores' => [
        // Botany - High-performing donor candidate
        '0a6f6e36-8b71-11eb-f3d6-40cea3d59c5a',
        
        // Browns Bay - Stockout receiver candidate
        // TODO: Replace with actual outlet ID
        'browns-bay-outlet-id-placeholder',
        
        // Glenfield - Data quality test case (negative inventory)
        // TODO: Replace with actual outlet ID
        'glenfield-outlet-id-placeholder',
    ],
    
    'pilot_start_date' => '2025-10-08',
    'pilot_duration_days' => 7,
    'notification_email' => 'inventory@vapeshed.co.nz',
    
    // Alert thresholds
    'alerts' => [
        'critical_stock_level' => 0, // Negative or zero inventory
        'low_stock_threshold' => 5,   // Below reorder point by this amount
        'max_transfer_quantity' => 50, // Max items to suggest in single transfer
    ],
];
