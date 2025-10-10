<?php
/**
 * Configuration API Controller
 * Handles system and dashboard configuration
 *
 * @package VapeshedTransfer
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\ConfigManager;
use Exception;

class ConfigController extends BaseController
{
    private Database $db;
    private ConfigManager $configManager;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->configManager = new ConfigManager();
    }

    /**
     * Get dashboard configuration
     */
    public function getDashboardConfig(): array
    {
        try {
            $config = [
                'refresh_interval' => $this->configManager->get('dashboard.refresh_interval', 30000),
                'theme' => $this->configManager->get('dashboard.theme', 'dark'),
                'auto_refresh' => $this->configManager->get('dashboard.auto_refresh', true),
                'enable_notifications' => $this->configManager->get('dashboard.enable_notifications', true),
                'enable_sounds' => $this->configManager->get('dashboard.enable_sounds', false),
                'chart_type' => $this->configManager->get('dashboard.chart_type', 'line'),
                'show_advanced_metrics' => $this->configManager->get('dashboard.show_advanced_metrics', false),
                'compact_mode' => $this->configManager->get('dashboard.compact_mode', false),
                'timezone' => $this->configManager->get('dashboard.timezone', 'Pacific/Auckland'),
                'date_format' => $this->configManager->get('dashboard.date_format', 'Y-m-d H:i:s'),
                'currency_format' => $this->configManager->get('dashboard.currency_format', 'NZD'),
                'decimal_places' => $this->configManager->get('dashboard.decimal_places', 2),
                'widgets' => $this->getDashboardWidgets(),
                'layout' => $this->getDashboardLayout()
            ];

            return $this->apiResponse(true, $config);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Update dashboard configuration
     */
    public function updateDashboardConfig(): array
    {
        try {
            $input = $this->getJsonInput();

            // Validate input
            $this->validateDashboardConfig($input);

            // Update each configuration setting
            foreach ($input as $key => $value) {
                $this->configManager->set("dashboard.{$key}", $value);
            }

            // Save configuration
            $this->configManager->save();

            // Log configuration change
            $this->logger->info('Dashboard configuration updated', [
                'updated_fields' => array_keys($input),
                'user' => $_SESSION['user_id'] ?? 'system'
            ]);

            return $this->apiResponse(true, $input, [
                'message' => 'Dashboard configuration updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Get system configuration
     */
    public function getSystemConfig(): array
    {
        try {
            $config = [
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'debug_mode' => $this->configManager->get('system.debug_mode', false),
                'browse_mode' => $this->configManager->get('system.browse_mode', false),
                'maintenance_mode' => $this->configManager->get('system.maintenance_mode', false),
                'max_concurrent_transfers' => $this->configManager->get('system.max_concurrent_transfers', 10),
                'max_concurrent_crawlers' => $this->configManager->get('system.max_concurrent_crawlers', 2),
                'rate_limit_requests' => $this->configManager->get('system.rate_limit_requests', 100),
                'rate_limit_window' => $this->configManager->get('system.rate_limit_window', 60),
                'session_timeout' => $this->configManager->get('system.session_timeout', 3600),
                'log_level' => $this->configManager->get('system.log_level', 'info'),
                'enable_profiling' => $this->configManager->get('system.enable_profiling', false),
                'enable_metrics' => $this->configManager->get('system.enable_metrics', true),
                'backup_retention_days' => $this->configManager->get('system.backup_retention_days', 30),
                'notification_email' => $this->configManager->get('system.notification_email', ''),
                'emergency_contacts' => $this->getEmergencyContacts()
            ];

            return $this->apiResponse(true, $config);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Update system configuration
     */
    public function updateSystemConfig(): array
    {
        try {
            $input = $this->getJsonInput();

            // Validate input
            $this->validateSystemConfig($input);

            // Special handling for sensitive settings
            if (isset($input['browse_mode'])) {
                $this->handleBrowseModeChange($input['browse_mode']);
            }

            if (isset($input['maintenance_mode'])) {
                $this->handleMaintenanceModeChange($input['maintenance_mode']);
            }

            // Update configuration
            foreach ($input as $key => $value) {
                $this->configManager->set("system.{$key}", $value);
            }

            $this->configManager->save();

            // Log critical configuration changes
            $this->logger->warning('System configuration updated', [
                'updated_fields' => array_keys($input),
                'user' => $_SESSION['user_id'] ?? 'system',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $this->apiResponse(true, $input, [
                'message' => 'System configuration updated successfully',
                'restart_required' => $this->requiresRestart($input)
            ]);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Get engine configuration
     */
    public function getEngineConfig(): array
    {
        try {
            $config = [
                'autonomous_engine' => [
                    'enabled' => $this->configManager->get('engines.autonomous.enabled', true),
                    'max_transfers_per_run' => $this->configManager->get('engines.autonomous.max_transfers_per_run', 50),
                    'max_price_changes_per_run' => $this->configManager->get('engines.autonomous.max_price_changes_per_run', 100),
                    'min_profit_threshold' => $this->configManager->get('engines.autonomous.min_profit_threshold', 10.0),
                    'run_interval_minutes' => $this->configManager->get('engines.autonomous.run_interval_minutes', 60),
                    'safety_checks' => $this->configManager->get('engines.autonomous.safety_checks', true),
                    'dry_run_mode' => $this->configManager->get('engines.autonomous.dry_run_mode', false)
                ],
                'crawler_engine' => [
                    'enabled' => $this->configManager->get('engines.crawler.enabled', true),
                    'stealth_mode' => $this->configManager->get('engines.crawler.stealth_mode', true),
                    'max_concurrent' => $this->configManager->get('engines.crawler.max_concurrent', 1),
                    'timeout_seconds' => $this->configManager->get('engines.crawler.timeout_seconds', 180),
                    'retry_attempts' => $this->configManager->get('engines.crawler.retry_attempts', 2),
                    'min_delay_seconds' => $this->configManager->get('engines.crawler.min_delay_seconds', 15),
                    'max_delay_seconds' => $this->configManager->get('engines.crawler.max_delay_seconds', 45),
                    'take_screenshots' => $this->configManager->get('engines.crawler.take_screenshots', true),
                    'use_proxies' => $this->configManager->get('engines.crawler.use_proxies', false)
                ],
                'transfer_engine' => [
                    'enabled' => $this->configManager->get('engines.transfer.enabled', true),
                    'auto_approve' => $this->configManager->get('engines.transfer.auto_approve', false),
                    'max_transfer_value' => $this->configManager->get('engines.transfer.max_transfer_value', 10000.0),
                    'require_manager_approval' => $this->configManager->get('engines.transfer.require_manager_approval', true),
                    'notification_threshold' => $this->configManager->get('engines.transfer.notification_threshold', 1000.0)
                ]
            ];

            return $this->apiResponse(true, $config);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Update engine configuration
     */
    public function updateEngineConfig(): array
    {
        try {
            $input = $this->getJsonInput();

            // Validate input
            $this->validateEngineConfig($input);

            // Update engine configurations
            foreach ($input as $engine => $settings) {
                foreach ($settings as $key => $value) {
                    $this->configManager->set("engines.{$engine}.{$key}", $value);
                }
            }

            $this->configManager->save();

            // Log engine configuration changes
            $this->logger->info('Engine configuration updated', [
                'engines' => array_keys($input),
                'user' => $_SESSION['user_id'] ?? 'system'
            ]);

            return $this->apiResponse(true, $input, [
                'message' => 'Engine configuration updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Get user configuration
     */
    public function getUserConfig(): array
    {
        try {
            $userId = $_SESSION['user_id'] ?? 'guest';

            $config = [
                'theme' => $this->getUserSetting($userId, 'theme', 'dark'),
                'language' => $this->getUserSetting($userId, 'language', 'en'),
                'timezone' => $this->getUserSetting($userId, 'timezone', 'Pacific/Auckland'),
                'notifications' => [
                    'email' => $this->getUserSetting($userId, 'notifications.email', true),
                    'browser' => $this->getUserSetting($userId, 'notifications.browser', true),
                    'mobile' => $this->getUserSetting($userId, 'notifications.mobile', false)
                ],
                'dashboard' => [
                    'layout' => $this->getUserSetting($userId, 'dashboard.layout', 'default'),
                    'widgets' => $this->getUserSetting($userId, 'dashboard.widgets', []),
                    'refresh_interval' => $this->getUserSetting($userId, 'dashboard.refresh_interval', 30000)
                ],
                'reports' => [
                    'default_format' => $this->getUserSetting($userId, 'reports.default_format', 'pdf'),
                    'auto_email' => $this->getUserSetting($userId, 'reports.auto_email', false)
                ]
            ];

            return $this->apiResponse(true, $config);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Update user configuration
     */
    public function updateUserConfig(): array
    {
        try {
            $input = $this->getJsonInput();
            $userId = $_SESSION['user_id'] ?? 'guest';

            // Validate input
            $this->validateUserConfig($input);

            // Update user settings
            foreach ($input as $key => $value) {
                $this->setUserSetting($userId, $key, $value);
            }

            return $this->apiResponse(true, $input, [
                'message' => 'User configuration updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->apiResponse(false, null, null, $e->getMessage());
        }
    }

    /**
     * Get dashboard widgets configuration
     */
    private function getDashboardWidgets(): array
    {
        return [
            'revenue_chart' => [
                'enabled' => true,
                'position' => 1,
                'size' => 'large'
            ],
            'system_status' => [
                'enabled' => true,
                'position' => 2,
                'size' => 'medium'
            ],
            'recent_activity' => [
                'enabled' => true,
                'position' => 3,
                'size' => 'medium'
            ],
            'competitive_intelligence' => [
                'enabled' => true,
                'position' => 4,
                'size' => 'large'
            ],
            'performance_metrics' => [
                'enabled' => false,
                'position' => 5,
                'size' => 'small'
            ]
        ];
    }

    /**
     * Get dashboard layout configuration
     */
    private function getDashboardLayout(): array
    {
        return [
            'grid_columns' => 4,
            'grid_rows' => 3,
            'widget_spacing' => 20,
            'responsive_breakpoints' => [
                'mobile' => 768,
                'tablet' => 1024,
                'desktop' => 1200
            ]
        ];
    }

    /**
     * Get emergency contacts
     */
    private function getEmergencyContacts(): array
    {
        return [
            [
                'name' => 'Pearce Stephens',
                'role' => 'Director/Owner',
                'email' => 'pearce.stephens@ecigdis.co.nz',
                'phone' => '+64-xxx-xxx-xxx',
                'primary' => true
            ]
        ];
    }

    /**
     * Validate dashboard configuration
     */
    private function validateDashboardConfig(array $config): void
    {
        if (isset($config['refresh_interval'])) {
            $interval = (int)$config['refresh_interval'];
            if ($interval < 5000 || $interval > 300000) {
                throw new Exception('Refresh interval must be between 5 and 300 seconds');
            }
        }

        if (isset($config['theme'])) {
            $validThemes = ['light', 'dark', 'auto'];
            if (!in_array($config['theme'], $validThemes)) {
                throw new Exception('Invalid theme. Must be one of: ' . implode(', ', $validThemes));
            }
        }

        if (isset($config['decimal_places'])) {
            $places = (int)$config['decimal_places'];
            if ($places < 0 || $places > 4) {
                throw new Exception('Decimal places must be between 0 and 4');
            }
        }
    }

    /**
     * Validate system configuration
     */
    private function validateSystemConfig(array $config): void
    {
        if (isset($config['max_concurrent_transfers'])) {
            $max = (int)$config['max_concurrent_transfers'];
            if ($max < 1 || $max > 100) {
                throw new Exception('Max concurrent transfers must be between 1 and 100');
            }
        }

        if (isset($config['max_concurrent_crawlers'])) {
            $max = (int)$config['max_concurrent_crawlers'];
            if ($max < 1 || $max > 5) {
                throw new Exception('Max concurrent crawlers must be between 1 and 5');
            }
        }

        if (isset($config['session_timeout'])) {
            $timeout = (int)$config['session_timeout'];
            if ($timeout < 300 || $timeout > 86400) {
                throw new Exception('Session timeout must be between 5 minutes and 24 hours');
            }
        }
    }

    /**
     * Validate engine configuration
     */
    private function validateEngineConfig(array $config): void
    {
        if (isset($config['autonomous_engine']['max_transfers_per_run'])) {
            $max = (int)$config['autonomous_engine']['max_transfers_per_run'];
            if ($max < 1 || $max > 1000) {
                throw new Exception('Max transfers per run must be between 1 and 1000');
            }
        }

        if (isset($config['crawler_engine']['timeout_seconds'])) {
            $timeout = (int)$config['crawler_engine']['timeout_seconds'];
            if ($timeout < 30 || $timeout > 600) {
                throw new Exception('Crawler timeout must be between 30 and 600 seconds');
            }
        }
    }

    /**
     * Validate user configuration
     */
    private function validateUserConfig(array $config): void
    {
        if (isset($config['theme'])) {
            $validThemes = ['light', 'dark', 'auto'];
            if (!in_array($config['theme'], $validThemes)) {
                throw new Exception('Invalid theme selection');
            }
        }

        if (isset($config['language'])) {
            $validLanguages = ['en', 'es', 'fr', 'de'];
            if (!in_array($config['language'], $validLanguages)) {
                throw new Exception('Invalid language selection');
            }
        }
    }

    /**
     * Handle browse mode change
     */
    private function handleBrowseModeChange(bool $browseMode): void
    {
        if ($browseMode) {
            // Activate browse mode - disable all modification operations
            $this->logger->warning('Browse mode activated - system is now read-only');

            // Set environment variable
            $_ENV['BROWSE_MODE'] = 'true';

        } else {
            // Deactivate browse mode
            $this->logger->info('Browse mode deactivated - system is now writable');

            unset($_ENV['BROWSE_MODE']);
        }
    }

    /**
     * Handle maintenance mode change
     */
    private function handleMaintenanceModeChange(bool $maintenanceMode): void
    {
        if ($maintenanceMode) {
            $this->logger->warning('Maintenance mode activated');

            // Stop all background processes
            // Could implement process stopping here

        } else {
            $this->logger->info('Maintenance mode deactivated');
        }
    }

    /**
     * Check if configuration change requires restart
     */
    private function requiresRestart(array $config): bool
    {
        $restartRequired = [
            'max_concurrent_transfers',
            'max_concurrent_crawlers',
            'debug_mode',
            'log_level'
        ];

        return !empty(array_intersect(array_keys($config), $restartRequired));
    }

    /**
     * Get user setting
     */
    private function getUserSetting(string $userId, string $key, mixed $default = null): mixed
    {
        $sql = "SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('ss', $userId, $key);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return json_decode($row['setting_value'], true);
        }

        return $default;
    }

    /**
     * Set user setting
     */
    private function setUserSetting(string $userId, string $key, mixed $value): void
    {
        $sql = "
            INSERT INTO user_settings (user_id, setting_key, setting_value, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
        ";

        $stmt = $this->db->getConnection()->prepare($sql);
        $encodedValue = json_encode($value);
        $stmt->bind_param('sss', $userId, $key, $encodedValue);
        $stmt->execute();
    }
}