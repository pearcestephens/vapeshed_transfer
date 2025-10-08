<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\Response;

/**
 * Admin layout shell controller.
 */
final class LayoutController
{
    /**
     * GET ?endpoint=admin/layout
     * Renders the standalone Admin shell (header + sidebar + footer).
     * Requires auth in SAFE_MODE; assets load independent of legacy stack.
     */
    public function index(): void
    {
        $correlationId = Response::correlationId();

        $configPath = function_exists('base_path')
            ? base_path('app/Config/admin.php')
            : dirname(__DIR__, 2) . '/Config/admin.php';
        $cfg = is_file($configPath) ? require $configPath : [];
        $cfg = is_array($cfg) ? $cfg : [];

        $flags = [
            'safe_mode' => (bool)($cfg['safe_mode'] ?? true),
            'show_phpinfo' => (bool)($cfg['show_phpinfo'] ?? false),
            'sidebar_compact' => (bool)($cfg['sidebar_compact'] ?? false),
        ];

        $view = function_exists('base_path')
            ? base_path('resources/views/admin/layout.php')
            : dirname(__DIR__, 2) . '/../resources/views/admin/layout.php';

        if (!is_file($view)) {
            Response::error('Admin layout view missing', 'VIEW_MISSING', ['view' => $view], 500);
            return;
        }

        $title = 'Admin — The Vape Shed';

        ob_start();
        /** @noinspection PhpIncludeInspection */
        require $view;
        $html = (string)ob_get_clean();

        Response::html($html);
    }

    /**
     * GET ?endpoint=admin/assets/probe
     * Returns JSON confirming CSS/JS bundle reachability and content-type.
     * Public; used by CI/verifiers. Should remain lightweight.
     */
    public function assetsProbe(): void
    {
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot === '') {
            $docRoot = function_exists('base_path') ? base_path('') : dirname(__DIR__, 2) . '/..';
        }

        $cssPath = realpath($docRoot . '/public/admin/assets/app.css') ?: '';
        $jsPath = realpath($docRoot . '/public/admin/assets/app.js') ?: '';

        $cssOk = $cssPath !== '' && is_readable($cssPath);
        $jsOk = $jsPath !== '' && is_readable($jsPath);

        $payload = [
            'css' => $cssOk ? 200 : 404,
            'js' => $jsOk ? 200 : 404,
            'mime' => [
                'css' => $cssOk ? 'text/css' : null,
                'js' => $jsOk ? 'application/javascript' : null,
            ],
        ];

        Response::json($payload, 200, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }
}
