<?php
/**
 * Admin Route Configuration
 * Maps admin URLs to controllers and views
 */

return [
    // Dashboard Routes
    'dashboard' => [
        'controller' => 'DashboardController',
        'view' => 'admin/dashboard/main',
        'title' => 'Dashboard'
    ],

    // Traffic Monitor Routes
    'traffic' => [
        'controller' => 'TrafficController',
        'view' => 'admin/traffic/monitor',
        'title' => 'Traffic Monitor'
    ],
    'performance' => [
        'controller' => 'TrafficController',
        'view' => 'admin/traffic/performance',
        'title' => 'Performance Analytics'
    ],
    'traffic-sources' => [
        'controller' => 'TrafficController',
        'view' => 'admin/traffic/sources',
        'title' => 'Traffic Sources'
    ],
    'error-tracking' => [
        'controller' => 'TrafficController',
        'view' => 'admin/traffic/errors',
        'title' => 'Error Tracking'
    ],
    'site-health' => [
        'controller' => 'TrafficController',
        'view' => 'admin/traffic/health',
        'title' => 'Site Health'
    ],

    // API Lab Routes
    'api-lab' => [
        'controller' => 'DashboardController',
        'view' => 'admin/api-lab/main',
        'title' => 'API Testing Lab'
    ],
    'webhook-lab' => [
        'controller' => 'WebhookLabController',
        'view' => 'admin/api-lab/webhook',
        'title' => 'Webhook Test Lab'
    ],
    'vend-tester' => [
        'controller' => 'VendTesterController',
        'view' => 'admin/api-lab/vend',
        'title' => 'Vend API Tester'
    ],
    'lightspeed-tester' => [
        'controller' => 'LightspeedTesterController',
        'view' => 'admin/api-lab/lightspeed',
        'title' => 'Lightspeed Sync Tester'
    ],
    'queue-tester' => [
        'controller' => 'QueueJobTesterController',
        'view' => 'admin/api-lab/queue',
        'title' => 'Queue Job Tester'
    ],
    'api-suite' => [
        'controller' => 'SuiteRunnerController',
        'view' => 'admin/api-lab/suite',
        'title' => 'Test Suite Runner'
    ],
    'code-snippets' => [
        'controller' => 'SnippetLibraryController',
        'view' => 'admin/api-lab/snippets',
        'title' => 'Code Snippet Library'
    ],

    // Configuration Routes
    'config' => [
        'controller' => 'ConfigController',
        'view' => 'admin/config/main',
        'title' => 'Configuration'
    ],
    'settings' => [
        'controller' => 'SettingsController',
        'view' => 'admin/settings/main',
        'title' => 'Settings'
    ],

    // Transfer Engine Routes
    'transfers' => [
        'controller' => 'TransferController',
        'view' => 'admin/transfers/main',
        'title' => 'Transfer Management'
    ],
    'legacy-engine' => [
        'controller' => 'LegacyEngineController',
        'view' => 'admin/legacy/main',
        'title' => 'Legacy Engine Interface'
    ],

    // System Routes
    'health' => [
        'controller' => 'HealthController',
        'view' => 'admin/health/main',
        'title' => 'System Health'
    ],
    'logs' => [
        'controller' => 'LogsController',
        'view' => 'admin/logs/main',
        'title' => 'System Logs'
    ],
    'reports' => [
        'controller' => 'ReportsController',
        'view' => 'admin/reports/main',
        'title' => 'Reports'
    ],

    // API Endpoints (return JSON, no views)
    'api' => [
        'controller' => 'api',
        'endpoints' => [
            // Dashboard API
            'dashboard/status' => 'DashboardController@getStatus',
            'dashboard/metrics' => 'DashboardController@getMetrics',
            'dashboard/profile' => 'DashboardController@setProfile',

            // Traffic API
            'traffic/live' => 'TrafficController@getLiveData',
            'traffic/metrics' => 'TrafficController@getMetrics',
            'traffic/performance' => 'TrafficController@getPerformance',
            'traffic/errors' => 'TrafficController@getErrors',
            'traffic/health-check' => 'TrafficController@runHealthCheck',

            // API Lab Endpoints
            'webhook-lab/send' => 'WebhookLabController@sendWebhook',
            'webhook-lab/history' => 'WebhookLabController@getHistory',
            'webhook-lab/templates' => 'WebhookLabController@getTemplates',

            'vend-tester/auth' => 'VendTesterController@testAuth',
            'vend-tester/request' => 'VendTesterController@makeRequest',
            'vend-tester/endpoints' => 'VendTesterController@getEndpoints',
            'vend-tester/history' => 'VendTesterController@getHistory',

            'lightspeed-tester/sync' => 'LightspeedTesterController@runSync',
            'lightspeed-tester/status' => 'LightspeedTesterController@getSyncStatus',
            'lightspeed-tester/logs' => 'LightspeedTesterController@getSyncLogs',
            'lightspeed-tester/force-sync' => 'LightspeedTesterController@forceSync',

            'queue-tester/dispatch' => 'QueueJobTesterController@dispatchJob',
            'queue-tester/status' => 'QueueJobTesterController@getJobStatus',
            'queue-tester/cancel' => 'QueueJobTesterController@cancelJob',
            'queue-tester/stress' => 'QueueJobTesterController@runStressTest',

            'suite-runner/run' => 'SuiteRunnerController@runSuite',
            'suite-runner/status' => 'SuiteRunnerController@getExecutionStatus',
            'suite-runner/stop' => 'SuiteRunnerController@stopExecution',
            'suite-runner/templates' => 'SuiteRunnerController@getTemplates',

            'snippets/list' => 'SnippetLibraryController@getSnippets',
            'snippets/save' => 'SnippetLibraryController@saveSnippet',
            'snippets/delete' => 'SnippetLibraryController@deleteSnippet',
            'snippets/copy' => 'SnippetLibraryController@copySnippet',

            // System API
            'health/ping' => 'HealthController@ping',
            'health/status' => 'HealthController@getStatus',
            'logs/tail' => 'LogsController@tailLogs',
            'config/get' => 'ConfigController@getValue',
            'config/set' => 'ConfigController@setValue'
        ]
    ]
];