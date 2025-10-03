<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;

/**
 * Settings Controller
 * Renders a simple system settings overview page
 */
class SettingsController extends BaseController
{
    public function index(): void
    {
        try {
            $browseMode = isset($_ENV['BROWSE_MODE']) ? (filter_var($_ENV['BROWSE_MODE'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') : 'false';
            $writeWindow = \App\Core\Security::getWriteWindowState();
            $data = [
                'title' => 'System Settings',
                'currentPage' => 'settings',
                'env' => [
                    'APP_URL' => defined('APP_URL') ? APP_URL : '',
                    'BASE_PATH' => defined('BASE_PATH') ? BASE_PATH : '',
                    'APP_BASE_URL' => defined('APP_BASE_URL') ? APP_BASE_URL : '',
                    'ENTRY_URL' => (defined('APP_BASE_URL') ? APP_BASE_URL : '') . '/public',
                    'APP_ENV' => defined('APP_ENV') ? APP_ENV : '',
                    'APP_DEBUG' => defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : 'false',
                    'DB_HOST' => defined('DB_HOST') ? DB_HOST : '',
                    'BROWSE_MODE' => $browseMode,
                ],
                'links' => [
                    'health' => route('/health'),
                    'logs' => route('/logs'),
                    'control_panel' => url('/control-panel'),
                    'entry' => (defined('APP_BASE_URL') ? APP_BASE_URL : '') . '/public',
                    'base' => defined('APP_BASE_URL') ? APP_BASE_URL : '',
                ],
                'csrf_token' => Security::generateCSRFToken(),
                'kill_switch_active' => is_file(APP_ROOT . '/KILL_SWITCH'),
                'write_window' => $writeWindow,
            ];
            $this->render('settings/index', $data);
        } catch (\Throwable $e) {
            $this->logger->error('Settings page failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            include APP_ROOT . '/resources/views/errors/500.php';
        }
    }
}
