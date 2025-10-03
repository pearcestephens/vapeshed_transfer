<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\ConfigManager;
use App\Core\Security;

class PresetsController extends BaseController
{
    private ConfigManager $configManager;

    public function __construct()
    {
        parent::__construct();
        $this->configManager = new ConfigManager();
    }

    // GET /api/presets → list all preset names
    public function index(): void
    {
        header('Content-Type: application/json');
        $presets = $this->configManager->getAvailablePresets();
        echo json_encode([
            'success' => true,
            'data' => [ 'presets' => array_keys($presets) ]
        ]);
    }

    // POST /api/presets { name } → return validated config for a preset
    public function load(): void
    {
        header('Content-Type: application/json');

        // Accept JSON body
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: [];
        $name = trim((string)($payload['name'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing preset name']);
            return;
        }

        $config = $this->configManager->loadPreset($name);
        if ($config === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Preset not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'name' => $name,
                'config' => $config
            ]
        ]);
    }
}
