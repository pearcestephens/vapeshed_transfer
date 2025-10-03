<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Security;

class KillSwitchController extends BaseController
{
    private string $killPath;

    public function __construct()
    {
        parent::__construct();
        $this->killPath = APP_ROOT . '/KILL_SWITCH';
    }

    public function get(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [ 'active' => is_file($this->killPath) ]
        ]);
    }

    public function activate(): void
    {
        header('Content-Type: application/json');
        // CSRF and write-policy enforcement
        Security::requireCSRF();
        Security::ensureWriteAllowed('kill_switch_activate');

        $ok = @file_put_contents($this->killPath, '1');
        if ($ok === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to activate kill switch']);
            return;
        }
        echo json_encode([ 'success' => true, 'data' => [ 'active' => true ] ]);
    }

    public function deactivate(): void
    {
        header('Content-Type: application/json');
        // CSRF and write-policy enforcement
        Security::requireCSRF();
        Security::ensureWriteAllowed('kill_switch_deactivate');

        if (is_file($this->killPath)) {
            if (!@unlink($this->killPath)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to deactivate kill switch']);
                return;
            }
        }
        echo json_encode([ 'success' => true, 'data' => [ 'active' => false ] ]);
    }
}
