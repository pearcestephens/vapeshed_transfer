<?php
declare(strict_types=1);

return [
    'routes' => [
        'admin/health/ping' => ['controller' => 'Admin\\HealthController@ping', 'method' => 'GET', 'auth' => true],
        'admin/health/phpinfo' => ['controller' => 'Admin\\HealthController@phpinfo', 'method' => 'GET', 'auth' => true],
    'admin/health/checks' => ['controller' => 'Admin\\HealthController@checks', 'method' => 'GET', 'auth' => true],
    'admin/health/grid' => ['controller' => 'Admin\\HealthController@grid', 'method' => 'GET', 'auth' => true],
    'admin/health/oneclick' => ['controller' => 'Admin\\HealthController@oneClick', 'method' => 'GET', 'auth' => true],
    'admin/dashboard' => ['controller' => 'Admin\\DashboardController@index', 'method' => 'GET', 'auth' => true],
    'admin/monitoring' => ['controller' => 'Admin\\DashboardController@monitoring', 'method' => 'GET', 'auth' => true],
        'admin/traffic/live' => ['controller' => 'Admin\\TrafficController@live', 'method' => 'GET', 'auth' => true],
    'admin/traffic/metrics' => ['controller' => 'Admin\\TrafficController@metrics', 'method' => 'GET', 'auth' => true],
    'admin/traffic/alerts' => ['controller' => 'Admin\\TrafficController@alerts', 'method' => 'GET', 'auth' => true],
        'admin/logs/apache-error-tail' => ['controller' => 'Admin\\LogsController@apacheErrorTail', 'method' => 'GET', 'auth' => true],
        'admin/errors/top404' => ['controller' => 'Admin\\ErrorsController@top404', 'method' => 'GET', 'auth' => true],
    'admin/errors/top500' => ['controller' => 'Admin\\ErrorsController@top500', 'method' => 'GET', 'auth' => true],
    'admin/errors/list-redirects' => ['controller' => 'Admin\\ErrorsController@listRedirects', 'method' => 'GET', 'auth' => true],
        'admin/errors/create-redirect' => ['controller' => 'Admin\\ErrorsController@createRedirect', 'method' => 'GET', 'auth' => true],
    ],
];
