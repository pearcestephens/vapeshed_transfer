<?php
/**
 * URL Routing Map
 * Maps ?endpoint=... values to controller handlers and HTTP metadata.
 */
return [
    'admin/health/ping' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\HealthController@ping',
        'auth' => ['role' => 'admin'],
    ],
    'admin/health/phpinfo' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\HealthController@phpinfo',
        'auth' => ['role' => 'superadmin'],
        'rate_limit' => ['per_minute' => (int)\Unified\Support\Env::get('ADMIN_RATE_LIMIT_PER_MIN', 60), 'burst' => 5],
    ],
    'admin/http/one-click-check' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\HealthController@bundle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/traffic/summary' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\TrafficController@summary',
        'auth' => ['role' => 'admin'],
    ],
    'admin/traffic/live' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\TrafficController@liveStream',
        'auth' => ['role' => 'admin'],
        'sse' => true,
    ],
    'admin/traffic/alerts' => [
        'methods' => ['GET', 'POST'],
        'handler' => 'Admin\\TrafficController@alerts',
        'auth' => ['role' => 'admin'],
    ],
    'admin/errors/top' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\ErrorsController@top',
        'auth' => ['role' => 'admin'],
    ],
    'admin/errors/redirects' => [
        'methods' => ['POST'],
        'handler' => 'Admin\\ErrorsController@createRedirect',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/webhook' => [
        'methods' => ['GET', 'POST'],
        'handler' => 'Admin\\ApiLab\\WebhookLabController@handle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/vend' => [
        'methods' => ['GET', 'POST'],
        'handler' => 'Admin\\ApiLab\\VendTesterController@handle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/lightspeed' => [
        'methods' => ['POST'],
        'handler' => 'Admin\\ApiLab\\LightspeedTesterController@handle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/queue' => [
        'methods' => ['POST'],
        'handler' => 'Admin\\ApiLab\\QueueJobTesterController@handle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/suite' => [
        'methods' => ['POST'],
        'handler' => 'Admin\\ApiLab\\SuiteRunnerController@handle',
        'auth' => ['role' => 'admin'],
    ],
    'admin/api-lab/snippets' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\ApiLab\\SnippetLibraryController@index',
        'auth' => ['role' => 'admin'],
    ],
    'admin/logs/apache-error-tail' => [
        'methods' => ['GET'],
        'handler' => 'Admin\\LogsController@apacheErrorTail',
        'auth' => ['role' => 'admin'],
    ],
];
