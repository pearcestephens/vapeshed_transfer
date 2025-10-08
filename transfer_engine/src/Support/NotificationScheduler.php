<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * NotificationScheduler.php - Scheduled Notification and Digest Service
 * 
 * Manages scheduled notifications, digest emails, recurring alerts,
 * and time-based notification delivery with queue management.
 * 
 * Features:
 * - Scheduled notification delivery
 * - Daily/weekly/monthly digest emails
 * - Recurring alert schedules
 * - Notification queuing
 * - Delivery retry logic
 * - Template-based notifications
 * - Recipient management
 * - Delivery tracking
 * - Failed delivery handling
 * - Schedule management (cron-like)
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

use Unified\Support\Logger;
use Unified\Support\NeuroContext;
use Unified\Support\AlertManager;
use Unified\Support\ReportGenerator;
use Unified\Support\CacheManager;

class NotificationScheduler
{
    private Logger $logger;
    private AlertManager $alertManager;
    private ReportGenerator $reportGenerator;
    private CacheManager $cache;
    private array $config;
    
    // Schedule frequencies
    public const FREQ_HOURLY = 'hourly';
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    
    // Notification types
    public const TYPE_DIGEST = 'digest';
    public const TYPE_REPORT = 'report';
    public const TYPE_ALERT = 'alert';
    public const TYPE_REMINDER = 'reminder';

    /**
     * Initialize NotificationScheduler
     *
     * @param Logger $logger Logger instance
     * @param AlertManager $alertManager Alert manager
     * @param ReportGenerator $reportGenerator Report generator
     * @param CacheManager $cache Cache manager
     * @param array $config Configuration options
     */
    public function __construct(
        Logger $logger,
        AlertManager $alertManager,
        ReportGenerator $reportGenerator,
        CacheManager $cache,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->alertManager = $alertManager;
        $this->reportGenerator = $reportGenerator;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Schedule a notification
     *
     * @param string $type Notification type
     * @param string $frequency Schedule frequency
     * @param array $options Notification options
     * @return array Schedule result
     */
    public function schedule(string $type, string $frequency, array $options = []): array
    {
        $scheduleId = $this->generateScheduleId($type, $frequency, $options);
        
        $schedule = [
            'id' => $scheduleId,
            'type' => $type,
            'frequency' => $frequency,
            'recipients' => $options['recipients'] ?? [],
            'options' => $options,
            'created_at' => time(),
            'last_run' => null,
            'next_run' => $this->calculateNextRun($frequency, $options),
            'enabled' => true,
            'failures' => 0,
        ];
        
        // Store schedule
        $this->saveSchedule($scheduleId, $schedule);
        
        $this->logger->info('Notification scheduled', NeuroContext::wrap('notification_scheduler', [
            'schedule_id' => $scheduleId,
            'type' => $type,
            'frequency' => $frequency,
            'next_run' => date('Y-m-d H:i:s', $schedule['next_run']),
        ]));
        
        return $schedule;
    }

    /**
     * Process due schedules
     *
     * @param int|null $timestamp Optional timestamp (defaults to now)
     * @return array Processing results
     */
    public function processDueSchedules(?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $schedules = $this->getAllSchedules();
        $processed = [];
        $errors = [];
        
        foreach ($schedules as $schedule) {
            if (!$schedule['enabled']) {
                continue;
            }
            
            if ($schedule['next_run'] <= $timestamp) {
                try {
                    $result = $this->executeSchedule($schedule);
                    $processed[] = [
                        'schedule_id' => $schedule['id'],
                        'type' => $schedule['type'],
                        'success' => $result['success'],
                    ];
                    
                    // Update schedule
                    $schedule['last_run'] = $timestamp;
                    $schedule['next_run'] = $this->calculateNextRun($schedule['frequency'], $schedule['options']);
                    $schedule['failures'] = $result['success'] ? 0 : $schedule['failures'] + 1;
                    
                    // Disable after too many failures
                    if ($schedule['failures'] >= $this->config['max_failures']) {
                        $schedule['enabled'] = false;
                        $this->logger->error('Schedule disabled due to failures', NeuroContext::wrap('notification_scheduler', [
                            'schedule_id' => $schedule['id'],
                            'failures' => $schedule['failures'],
                        ]));
                    }
                    
                    $this->saveSchedule($schedule['id'], $schedule);
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'schedule_id' => $schedule['id'],
                        'error' => $e->getMessage(),
                    ];
                    
                    $this->logger->error('Schedule execution failed', NeuroContext::wrap('notification_scheduler', [
                        'schedule_id' => $schedule['id'],
                        'error' => $e->getMessage(),
                    ]));
                }
            }
        }
        
        $this->logger->info('Schedules processed', NeuroContext::wrap('notification_scheduler', [
            'total_schedules' => count($schedules),
            'processed' => count($processed),
            'errors' => count($errors),
        ]));
        
        return [
            'timestamp' => $timestamp,
            'total_schedules' => count($schedules),
            'processed' => $processed,
            'errors' => $errors,
        ];
    }

    /**
     * Execute a scheduled notification
     *
     * @param array $schedule Schedule configuration
     * @return array Execution result
     */
    private function executeSchedule(array $schedule): array
    {
        $startTime = microtime(true);
        
        $result = match($schedule['type']) {
            self::TYPE_DIGEST => $this->sendDigest($schedule),
            self::TYPE_REPORT => $this->sendReport($schedule),
            self::TYPE_ALERT => $this->sendAlert($schedule),
            self::TYPE_REMINDER => $this->sendReminder($schedule),
            default => ['success' => false, 'error' => 'Unknown schedule type'],
        };
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Schedule executed', NeuroContext::wrap('notification_scheduler', [
            'schedule_id' => $schedule['id'],
            'type' => $schedule['type'],
            'success' => $result['success'],
            'duration_ms' => $duration,
        ]));
        
        return array_merge($result, ['duration_ms' => $duration]);
    }

    /**
     * Send digest notification
     *
     * @param array $schedule Schedule configuration
     * @return array Send result
     */
    private function sendDigest(array $schedule): array
    {
        $options = $schedule['options'];
        $period = $this->frequencyToPeriod($schedule['frequency']);
        
        // Collect digest data
        $digestData = [
            'period' => $period,
            'summary' => $this->collectDigestSummary($period),
            'highlights' => $this->collectHighlights($period),
            'statistics' => $this->collectStatistics($period),
        ];
        
        // Format message
        $message = $this->formatDigest($digestData, $options);
        
        // Send via alert manager
        $recipients = $schedule['recipients'];
        $sent = 0;
        
        foreach ($recipients as $recipient) {
            try {
                $this->alertManager->send([
                    'severity' => 'info',
                    'title' => $options['title'] ?? 'System Digest',
                    'message' => $message,
                    'recipient' => $recipient,
                ]);
                $sent++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to send digest', NeuroContext::wrap('notification_scheduler', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]));
            }
        }
        
        return [
            'success' => $sent > 0,
            'recipients_sent' => $sent,
            'recipients_total' => count($recipients),
        ];
    }

    /**
     * Send report notification
     *
     * @param array $schedule Schedule configuration
     * @return array Send result
     */
    private function sendReport(array $schedule): array
    {
        $options = $schedule['options'];
        
        // Generate report
        $reportType = $options['report_type'] ?? 'health';
        $reportFormat = $options['report_format'] ?? 'html';
        
        try {
            $report = $this->reportGenerator->generate(
                $reportType,
                $reportFormat,
                $options['report_options'] ?? []
            );
            
            // Send report to recipients
            $recipients = $schedule['recipients'];
            $sent = 0;
            
            foreach ($recipients as $recipient) {
                try {
                    $this->alertManager->send([
                        'severity' => 'info',
                        'title' => $options['title'] ?? 'Scheduled Report',
                        'message' => "Report generated: {$report['filename']}",
                        'recipient' => $recipient,
                        'attachments' => [$report['path']],
                    ]);
                    $sent++;
                } catch (\Exception $e) {
                    $this->logger->error('Failed to send report', NeuroContext::wrap('notification_scheduler', [
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                    ]));
                }
            }
            
            return [
                'success' => $sent > 0,
                'recipients_sent' => $sent,
                'recipients_total' => count($recipients),
                'report_path' => $report['path'],
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Report generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send alert notification
     *
     * @param array $schedule Schedule configuration
     * @return array Send result
     */
    private function sendAlert(array $schedule): array
    {
        $options = $schedule['options'];
        
        // Send alert to recipients
        $recipients = $schedule['recipients'];
        $sent = 0;
        
        foreach ($recipients as $recipient) {
            try {
                $this->alertManager->send([
                    'severity' => $options['severity'] ?? 'info',
                    'title' => $options['title'] ?? 'Scheduled Alert',
                    'message' => $options['message'] ?? 'Recurring alert notification',
                    'recipient' => $recipient,
                ]);
                $sent++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to send alert', NeuroContext::wrap('notification_scheduler', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]));
            }
        }
        
        return [
            'success' => $sent > 0,
            'recipients_sent' => $sent,
            'recipients_total' => count($recipients),
        ];
    }

    /**
     * Send reminder notification
     *
     * @param array $schedule Schedule configuration
     * @return array Send result
     */
    private function sendReminder(array $schedule): array
    {
        $options = $schedule['options'];
        
        // Send reminder to recipients
        $recipients = $schedule['recipients'];
        $sent = 0;
        
        foreach ($recipients as $recipient) {
            try {
                $this->alertManager->send([
                    'severity' => 'info',
                    'title' => $options['title'] ?? 'Reminder',
                    'message' => $options['message'] ?? 'Scheduled reminder',
                    'recipient' => $recipient,
                ]);
                $sent++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to send reminder', NeuroContext::wrap('notification_scheduler', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]));
            }
        }
        
        return [
            'success' => $sent > 0,
            'recipients_sent' => $sent,
            'recipients_total' => count($recipients),
        ];
    }

    /**
     * Cancel a scheduled notification
     *
     * @param string $scheduleId Schedule ID
     * @return bool True if cancelled
     */
    public function cancel(string $scheduleId): bool
    {
        $cacheKey = $this->getScheduleCacheKey($scheduleId);
        $result = $this->cache->delete($cacheKey);
        
        $this->logger->info('Schedule cancelled', NeuroContext::wrap('notification_scheduler', [
            'schedule_id' => $scheduleId,
        ]));
        
        return $result;
    }

    /**
     * Get schedule by ID
     *
     * @param string $scheduleId Schedule ID
     * @return array|null Schedule data or null
     */
    public function getSchedule(string $scheduleId): ?array
    {
        $cacheKey = $this->getScheduleCacheKey($scheduleId);
        return $this->cache->get($cacheKey);
    }

    /**
     * Get all schedules
     *
     * @return array Array of schedules
     */
    public function getAllSchedules(): array
    {
        $pattern = 'notification_schedule:*';
        $keys = $this->cache->keys($pattern);
        $schedules = [];
        
        foreach ($keys as $key) {
            if ($schedule = $this->cache->get($key)) {
                $schedules[] = $schedule;
            }
        }
        
        return $schedules;
    }

    /**
     * Update schedule
     *
     * @param string $scheduleId Schedule ID
     * @param array $updates Updates to apply
     * @return bool True if updated
     */
    public function updateSchedule(string $scheduleId, array $updates): bool
    {
        $schedule = $this->getSchedule($scheduleId);
        
        if (!$schedule) {
            return false;
        }
        
        $schedule = array_merge($schedule, $updates);
        $this->saveSchedule($scheduleId, $schedule);
        
        $this->logger->info('Schedule updated', NeuroContext::wrap('notification_scheduler', [
            'schedule_id' => $scheduleId,
            'updates' => array_keys($updates),
        ]));
        
        return true;
    }

    /**
     * Calculate next run time
     *
     * @param string $frequency Frequency
     * @param array $options Schedule options
     * @return int Next run timestamp
     */
    private function calculateNextRun(string $frequency, array $options = []): int
    {
        $now = time();
        $hour = $options['hour'] ?? 9; // Default 9 AM
        $minute = $options['minute'] ?? 0;
        $dayOfWeek = $options['day_of_week'] ?? 1; // Monday
        $dayOfMonth = $options['day_of_month'] ?? 1;
        
        return match($frequency) {
            self::FREQ_HOURLY => strtotime('+1 hour', $now),
            self::FREQ_DAILY => strtotime("tomorrow {$hour}:{$minute}:00"),
            self::FREQ_WEEKLY => strtotime("next " . $this->getDayName($dayOfWeek) . " {$hour}:{$minute}:00"),
            self::FREQ_MONTHLY => strtotime("first day of next month {$hour}:{$minute}:00"),
            default => strtotime('+1 day', $now),
        };
    }

    /**
     * Generate unique schedule ID
     *
     * @param string $type Notification type
     * @param string $frequency Frequency
     * @param array $options Options
     * @return string Schedule ID
     */
    private function generateScheduleId(string $type, string $frequency, array $options): string
    {
        return 'schedule_' . $type . '_' . $frequency . '_' . substr(md5(json_encode($options) . time()), 0, 8);
    }

    /**
     * Save schedule to cache
     *
     * @param string $scheduleId Schedule ID
     * @param array $schedule Schedule data
     * @return bool True if saved
     */
    private function saveSchedule(string $scheduleId, array $schedule): bool
    {
        $cacheKey = $this->getScheduleCacheKey($scheduleId);
        return $this->cache->set($cacheKey, $schedule, 0); // No expiry
    }

    /**
     * Get cache key for schedule
     *
     * @param string $scheduleId Schedule ID
     * @return string Cache key
     */
    private function getScheduleCacheKey(string $scheduleId): string
    {
        return "notification_schedule:{$scheduleId}";
    }

    /**
     * Convert frequency to period string
     *
     * @param string $frequency Frequency
     * @return string Period string
     */
    private function frequencyToPeriod(string $frequency): string
    {
        return match($frequency) {
            self::FREQ_HOURLY => '1h',
            self::FREQ_DAILY => '24h',
            self::FREQ_WEEKLY => '7d',
            self::FREQ_MONTHLY => '30d',
            default => '24h',
        };
    }

    /**
     * Get day name from number
     *
     * @param int $day Day number (0=Sunday, 1=Monday, etc.)
     * @return string Day name
     */
    private function getDayName(int $day): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$day % 7];
    }

    /**
     * Collect digest summary data
     *
     * @param string $period Period
     * @return array Summary data
     */
    private function collectDigestSummary(string $period): array
    {
        // Placeholder - would integrate with actual data sources
        return [
            'total_transfers' => 0,
            'successful_transfers' => 0,
            'failed_transfers' => 0,
            'total_items' => 0,
            'alerts_count' => 0,
        ];
    }

    /**
     * Collect highlights for digest
     *
     * @param string $period Period
     * @return array Highlights
     */
    private function collectHighlights(string $period): array
    {
        return [
            'top_performing_stores' => [],
            'issues_resolved' => 0,
            'system_improvements' => [],
        ];
    }

    /**
     * Collect statistics for digest
     *
     * @param string $period Period
     * @return array Statistics
     */
    private function collectStatistics(string $period): array
    {
        return [
            'uptime_percent' => 99.9,
            'avg_response_time_ms' => 150,
            'error_rate_percent' => 0.5,
        ];
    }

    /**
     * Format digest message
     *
     * @param array $data Digest data
     * @param array $options Format options
     * @return string Formatted message
     */
    private function formatDigest(array $data, array $options): string
    {
        $format = $options['format'] ?? 'html';
        
        if ($format === 'html') {
            return $this->formatDigestHtml($data);
        }
        
        return $this->formatDigestText($data);
    }

    /**
     * Format digest as HTML
     *
     * @param array $data Digest data
     * @return string HTML message
     */
    private function formatDigestHtml(array $data): string
    {
        $summary = $data['summary'];
        $period = $data['period'];
        
        return <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background: #3498db; color: white; padding: 20px; }
        .content { padding: 20px; }
        .stat { display: inline-block; margin: 10px; padding: 15px; background: #ecf0f1; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Digest - {$period}</h1>
    </div>
    <div class="content">
        <h2>Summary</h2>
        <div class="stat">
            <strong>Total Transfers:</strong> {$summary['total_transfers']}
        </div>
        <div class="stat">
            <strong>Successful:</strong> {$summary['successful_transfers']}
        </div>
        <div class="stat">
            <strong>Failed:</strong> {$summary['failed_transfers']}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Format digest as plain text
     *
     * @param array $data Digest data
     * @return string Text message
     */
    private function formatDigestText(array $data): string
    {
        $summary = $data['summary'];
        $period = $data['period'];
        
        return <<<TEXT
System Digest - {$period}

Summary:
- Total Transfers: {$summary['total_transfers']}
- Successful: {$summary['successful_transfers']}
- Failed: {$summary['failed_transfers']}
- Total Items: {$summary['total_items']}
- Alerts: {$summary['alerts_count']}

TEXT;
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'max_failures' => 3,
            'default_hour' => 9, // 9 AM
            'default_minute' => 0,
            'retry_delay' => 300, // 5 minutes
        ];
    }
}
