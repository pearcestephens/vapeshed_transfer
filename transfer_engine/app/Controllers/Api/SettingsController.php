<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\ConfigManager;
use App\Core\Security;

class SettingsController extends BaseController
{
    private ConfigManager $configManager;

    public function __construct()
    {
        parent::__construct();
        $this->configManager = new ConfigManager();
    }

    public function get(): void
    {
        header('Content-Type: application/json');
        try {
            $settings = $this->configManager->loadSettings();
            echo json_encode(['success' => true, 'data' => $settings]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load settings']);
        }
    }

    public function save(): void
    {
        header('Content-Type: application/json');
        try {
            Security::requireCSRF();
            Security::ensureWriteAllowed('settings_save');
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true) ?: [];
            $settings = $payload['settings'] ?? $payload; // accept either {settings:{...}} or {...}
            if (!is_array($settings)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid settings payload']);
                return;
            }
            $this->configManager->saveSettings($settings);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Settings save failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
        }
    }
}
