<?php
/**
 * Modules Configuration
 * 
 * Define all available modules and their properties
 */
return [
    'transfer' => [
        'name' => 'Transfer Engine',
        'icon' => 'exchange-alt',
        'color' => '#8b5cf6',
        'status' => 'active',
        'description' => 'Stock transfer automation with DSR calculator',
        'permissions' => ['transfer.view', 'transfer.execute', 'transfer.approve']
    ],
    
    'pricing' => [
        'name' => 'Pricing Intelligence',
        'icon' => 'tags',
        'color' => '#ec4899',
        'status' => 'active',
        'description' => 'Competitive pricing with market intelligence',
        'permissions' => ['pricing.view', 'pricing.propose', 'pricing.approve']
    ],
    
    'crawler' => [
        'name' => 'Market Crawler',
        'icon' => 'spider',
        'color' => '#f97316',
        'status' => 'planned',
        'description' => 'Competitor website monitoring',
        'permissions' => ['crawler.view', 'crawler.manage']
    ],
    
    'matching' => [
        'name' => 'Matching & Synonyms',
        'icon' => 'link',
        'color' => '#14b8a6',
        'status' => 'active',
        'description' => 'Product matching and brand normalization',
        'permissions' => ['matching.view', 'matching.manage']
    ],
    
    'forecast' => [
        'name' => 'Forecast & Demand',
        'icon' => 'chart-line',
        'color' => '#06b6d4',
        'status' => 'beta',
        'description' => 'Demand forecasting and trend analysis',
        'permissions' => ['forecast.view']
    ],
    
    'insights' => [
        'name' => 'Neuro Insights',
        'icon' => 'brain',
        'color' => '#6366f1',
        'status' => 'active',
        'description' => 'AI-powered business intelligence',
        'permissions' => ['insights.view']
    ],
    
    'guardrails' => [
        'name' => 'Guardrails & Policy',
        'icon' => 'shield-alt',
        'color' => '#eab308',
        'status' => 'active',
        'description' => 'Safety controls and policy enforcement',
        'permissions' => ['guardrails.view', 'guardrails.manage']
    ],
    
    'images' => [
        'name' => 'Image Clustering',
        'icon' => 'images',
        'color' => '#a855f7',
        'status' => 'beta',
        'description' => 'Visual product clustering',
        'permissions' => ['images.view']
    ],
    
    'config' => [
        'name' => 'Configuration',
        'icon' => 'cog',
        'color' => '#64748b',
        'status' => 'active',
        'description' => 'System configuration management',
        'permissions' => ['config.view', 'config.edit']
    ],
    
    'health' => [
        'name' => 'System Health',
        'icon' => 'heartbeat',
        'color' => '#10b981',
        'status' => 'active',
        'description' => 'System health monitoring and diagnostics',
        'permissions' => ['health.view']
    ],
    
    'drift' => [
        'name' => 'Drift Monitoring',
        'icon' => 'chart-area',
        'color' => '#f59e0b',
        'status' => 'active',
        'description' => 'Model drift detection and PSI tracking',
        'permissions' => ['drift.view']
    ],
    
    'simulation' => [
        'name' => 'Simulation Harness',
        'icon' => 'flask',
        'color' => '#3b82f6',
        'status' => 'planned',
        'description' => 'Scenario testing and simulation',
        'permissions' => ['simulation.view', 'simulation.run']
    ]
];
