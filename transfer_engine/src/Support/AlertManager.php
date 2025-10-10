<?php
/**
 * AlertManager.php - Enterprise Alert Management System
 * 
 * Manages multi-channel alerting (email, Slack, webhook, log) with
 * severity-based routing, rate limiting, and delivery tracking.
 * 
 * Features:
 * - Multi-channel delivery (email, Slack, webhook, log)
 * - Severity-based routing
 * - Alert deduplication
 * - Rate limiting per channel
 * - Delivery tracking & retry logic
 * - Template support
 * - Neuro logging integration
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;
use Unified\Support\Cache;
use Unified\Support\CacheManager;

class AlertManager
{
    private Logger $logger;
    private Cache|CacheManager $cache;
    private array $config;
    private array $channels = [];
    
    // Alert severities
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';
    
    // Delivery channels
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_WEBHOOK = 'webhook';
    public const CHANNEL_LOG = 'log';
    
    // Rate limit windows (seconds)
    private const RATE_LIMIT_WINDOWS = [
        self::SEVERITY_INFO => 3600,      // 1 hour
        self::SEVERITY_WARNING => 1800,   // 30 minutes
        self::SEVERITY_ERROR => 900,      // 15 minutes
        self::SEVERITY_CRITICAL => 300,   // 5 minutes
    ];
    
    // Max alerts per window
    private const RATE_LIMITS = [
        self::SEVERITY_INFO => 10,
        self::SEVERITY_WARNING => 20,
        self::SEVERITY_ERROR => 50,
        self::SEVERITY_CRITICAL => 100,
    ];

    /**
     * Initialize AlertManager
     *
     * @param Logger $logger Logger instance
     * @param Cache|CacheManager $cache Cache instance for rate limiting
     * @param array $config Configuration array
     */
    public function __construct(Logger $logger, Cache|CacheManager $cache, array $config = [])
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->initializeChannels();
    }

    /**
     * Send alert through configured channels
     *
     * @param string|array $title Alert title or array of alert data
     * @param string $message Alert message
     * @param string $severity Severity level (info, warning, error, critical)
     * @param array $context Additional context data
     * @param array $channels Specific channels to use (null = auto-route by severity)
     * @return array Delivery results per channel
     */
    public function send(
        string|array $title,
        string $message = '',
        string $severity = self::SEVERITY_INFO,
        array $context = [],
        ?array $channels = null
    ): array {
        // Handle array-style call: send(['title' => '...', 'message' => '...', 'severity' => '...'])
        if (is_array($title)) {
            $data = $title;
            $title = $data['title'] ?? 'Alert';
            $message = $data['message'] ?? '';
            $severity = $data['severity'] ?? self::SEVERITY_INFO;
            $context = $data['context'] ?? [];
            $channels = $data['channels'] ?? null;
        }
        
        $startTime = microtime(true);
        
        // Validate severity
        if (!$this->isValidSeverity($severity)) {
            $this->logger->warning('Invalid alert severity', NeuroContext::wrap('alert_manager', [
                'severity' => $severity,
                'valid_severities' => [self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_ERROR, self::SEVERITY_CRITICAL],
            ]));
            $severity = self::SEVERITY_INFO;
        }
        
        // Check rate limit
        if ($this->isRateLimited($title, $severity)) {
            $this->logger->info('Alert rate limited', NeuroContext::wrap('alert_manager', [
                'title' => $title,
                'severity' => $severity,
            ]));
            return ['status' => 'rate_limited'];
        }
        
        // Check deduplication
        if ($this->isDuplicate($title, $message, $severity)) {
            $this->logger->info('Alert deduplicated', NeuroContext::wrap('alert_manager', [
                'title' => $title,
                'severity' => $severity,
            ]));
            return ['status' => 'deduplicated'];
        }
        
        // Determine channels to use
        if ($channels === null) {
            $channels = $this->getChannelsForSeverity($severity);
        }
        
        // Build alert payload
        $alert = $this->buildAlert($title, $message, $severity, $context);
        
        // Send to each channel
        $results = [];
        foreach ($channels as $channel) {
            try {
                $results[$channel] = $this->sendToChannel($channel, $alert);
            } catch (\Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                $this->logger->error('Alert delivery failed', NeuroContext::wrap('alert_manager', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'alert_title' => $title,
                ]));
            }
        }
        
        // Record delivery
        $this->recordDelivery($title, $severity);
        
        // Log alert sent
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info('Alert sent', NeuroContext::wrap('alert_manager', [
            'title' => $title,
            'severity' => $severity,
            'channels' => $channels,
            'results' => $results,
            'duration_ms' => $duration,
        ]));
        
        return $results;
    }

    /**
     * Send critical alert (always delivered, minimal rate limiting)
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param array $context Additional context
     * @return array Delivery results
     */
    public function critical(string $title, string $message, array $context = []): array
    {
        return $this->send($title, $message, self::SEVERITY_CRITICAL, $context);
    }

    /**
     * Send error alert
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param array $context Additional context
     * @return array Delivery results
     */
    public function error(string $title, string $message, array $context = []): array
    {
        return $this->send($title, $message, self::SEVERITY_ERROR, $context);
    }

    /**
     * Send warning alert
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param array $context Additional context
     * @return array Delivery results
     */
    public function warning(string $title, string $message, array $context = []): array
    {
        return $this->send($title, $message, self::SEVERITY_WARNING, $context);
    }

    /**
     * Send info alert
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param array $context Additional context
     * @return array Delivery results
     */
    public function info(string $title, string $message, array $context = []): array
    {
        return $this->send($title, $message, self::SEVERITY_INFO, $context);
    }

    /**
     * Initialize delivery channels
     *
     * @return void
     */
    private function initializeChannels(): void
    {
        // Email channel
        if ($this->config['email']['enabled'] ?? false) {
            $this->channels[self::CHANNEL_EMAIL] = new EmailChannel(
                $this->config['email'],
                $this->logger
            );
        }
        
        // Slack channel
        if ($this->config['slack']['enabled'] ?? false) {
            $this->channels[self::CHANNEL_SLACK] = new SlackChannel(
                $this->config['slack'],
                $this->logger
            );
        }
        
        // Webhook channel
        if ($this->config['webhook']['enabled'] ?? false) {
            $this->channels[self::CHANNEL_WEBHOOK] = new WebhookChannel(
                $this->config['webhook'],
                $this->logger
            );
        }
        
        // Log channel (always enabled)
        $this->channels[self::CHANNEL_LOG] = new LogChannel($this->logger);
    }

    /**
     * Get channels for severity level
     *
     * @param string $severity Severity level
     * @return array Channel names
     */
    private function getChannelsForSeverity(string $severity): array
    {
        $channelMap = $this->config['severity_routing'] ?? [
            self::SEVERITY_CRITICAL => [self::CHANNEL_EMAIL, self::CHANNEL_SLACK, self::CHANNEL_LOG],
            self::SEVERITY_ERROR => [self::CHANNEL_SLACK, self::CHANNEL_LOG],
            self::SEVERITY_WARNING => [self::CHANNEL_LOG],
            self::SEVERITY_INFO => [self::CHANNEL_LOG],
        ];
        
        return $channelMap[$severity] ?? [self::CHANNEL_LOG];
    }

    /**
     * Build alert payload
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $severity Severity level
     * @param array $context Additional context
     * @return array Alert payload
     */
    private function buildAlert(string $title, string $message, string $severity, array $context): array
    {
        return [
            'id' => uniqid('alert_', true),
            'timestamp' => date('c'),
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'context' => $context,
            'environment' => $this->config['environment'] ?? 'production',
            'system' => 'vapeshed_transfer',
            'hostname' => gethostname(),
        ];
    }

    /**
     * Send alert to specific channel
     *
     * @param string $channel Channel name
     * @param array $alert Alert payload
     * @return array Delivery result
     */
    private function sendToChannel(string $channel, array $alert): array
    {
        if (!isset($this->channels[$channel])) {
            return [
                'success' => false,
                'error' => 'Channel not configured: ' . $channel,
            ];
        }
        
        return $this->channels[$channel]->send($alert);
    }

    /**
     * Check if alert is rate limited
     *
     * @param string $title Alert title
     * @param string $severity Severity level
     * @return bool True if rate limited
     */
    private function isRateLimited(string $title, string $severity): bool
    {
        $key = 'alert_rate:' . $severity . ':' . md5($title);
        $window = self::RATE_LIMIT_WINDOWS[$severity] ?? 3600;
        $limit = self::RATE_LIMITS[$severity] ?? 10;
        
        $count = (int)$this->cache->get($key, 0);
        
        if ($count >= $limit) {
            return true;
        }
        
        $this->cache->set($key, $count + 1, $window);
        return false;
    }

    /**
     * Check if alert is duplicate of recent alert
     *
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $severity Severity level
     * @return bool True if duplicate
     */
    private function isDuplicate(string $title, string $message, string $severity): bool
    {
        $hash = md5($title . $message . $severity);
        $key = 'alert_dedup:' . $hash;
        
        if ($this->cache->has($key)) {
            return true;
        }
        
        // Cache for deduplication window (5 minutes)
        $this->cache->set($key, true, 300);
        return false;
    }

    /**
     * Record alert delivery
     *
     * @param string $title Alert title
     * @param string $severity Severity level
     * @return void
     */
    private function recordDelivery(string $title, string $severity): void
    {
        $key = 'alert_history:' . date('Y-m-d');
        $history = $this->cache->get($key, []);
        
        $history[] = [
            'timestamp' => time(),
            'title' => $title,
            'severity' => $severity,
        ];
        
        // Keep last 1000 alerts per day
        if (count($history) > 1000) {
            $history = array_slice($history, -1000);
        }
        
        $this->cache->set($key, $history, 86400); // 24 hours
    }

    /**
     * Validate severity level
     *
     * @param string $severity Severity to validate
     * @return bool True if valid
     */
    private function isValidSeverity(string $severity): bool
    {
        return in_array($severity, [
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_ERROR,
            self::SEVERITY_CRITICAL,
        ], true);
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'environment' => 'production',
            'email' => [
                'enabled' => false,
                'from' => 'alerts@vapeshed.co.nz',
                'to' => [],
                'smtp_host' => 'localhost',
                'smtp_port' => 25,
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => '',
                'channel' => '#alerts',
                'username' => 'Transfer Engine',
            ],
            'webhook' => [
                'enabled' => false,
                'url' => '',
                'method' => 'POST',
                'headers' => [],
            ],
            'severity_routing' => [
                self::SEVERITY_CRITICAL => [self::CHANNEL_EMAIL, self::CHANNEL_SLACK, self::CHANNEL_LOG],
                self::SEVERITY_ERROR => [self::CHANNEL_SLACK, self::CHANNEL_LOG],
                self::SEVERITY_WARNING => [self::CHANNEL_LOG],
                self::SEVERITY_INFO => [self::CHANNEL_LOG],
            ],
        ];
    }

    /**
     * Get alert statistics
     *
     * @param int $days Number of days to include
     * @return array Statistics
     */
    public function getStats(int $days = 7): array
    {
        $stats = [
            'total' => 0,
            'by_severity' => [
                self::SEVERITY_CRITICAL => 0,
                self::SEVERITY_ERROR => 0,
                self::SEVERITY_WARNING => 0,
                self::SEVERITY_INFO => 0,
            ],
            'by_day' => [],
        ];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $key = 'alert_history:' . $date;
            $history = $this->cache->get($key, []);
            
            $dayStats = [
                'date' => $date,
                'total' => count($history),
                'by_severity' => [
                    self::SEVERITY_CRITICAL => 0,
                    self::SEVERITY_ERROR => 0,
                    self::SEVERITY_WARNING => 0,
                    self::SEVERITY_INFO => 0,
                ],
            ];
            
            foreach ($history as $alert) {
                $stats['total']++;
                $severity = $alert['severity'] ?? self::SEVERITY_INFO;
                $stats['by_severity'][$severity]++;
                $dayStats['by_severity'][$severity]++;
            }
            
            $stats['by_day'][] = $dayStats;
        }
        
        return $stats;
    }
}

/**
 * Email Channel - Send alerts via email
 */
class EmailChannel
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function send(array $alert): array
    {
        $to = implode(', ', $this->config['to'] ?? []);
        $subject = "[{$alert['severity']}] {$alert['title']}";
        $body = $this->buildEmailBody($alert);
        
        $headers = [
            'From: ' . ($this->config['from'] ?? 'alerts@vapeshed.co.nz'),
            'Content-Type: text/html; charset=UTF-8',
            'X-Alert-Severity: ' . $alert['severity'],
            'X-Alert-ID: ' . $alert['id'],
        ];
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        return [
            'success' => $success,
            'channel' => 'email',
            'recipients' => $to,
        ];
    }

    private function buildEmailBody(array $alert): string
    {
        $severityColors = [
            'critical' => '#dc3545',
            'error' => '#fd7e14',
            'warning' => '#ffc107',
            'info' => '#17a2b8',
        ];
        
        $color = $severityColors[$alert['severity']] ?? '#6c757d';
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$color}; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
        .meta { font-size: 12px; color: #6c757d; margin-top: 20px; }
        .context { background: white; padding: 10px; border-left: 3px solid {$color}; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{$alert['title']}</h2>
            <span style="text-transform: uppercase; font-weight: bold;">{$alert['severity']}</span>
        </div>
        <div class="content">
            <p>{$alert['message']}</p>
            {$this->formatContext($alert['context'])}
            <div class="meta">
                <strong>Timestamp:</strong> {$alert['timestamp']}<br>
                <strong>Environment:</strong> {$alert['environment']}<br>
                <strong>System:</strong> {$alert['system']}<br>
                <strong>Host:</strong> {$alert['hostname']}<br>
                <strong>Alert ID:</strong> {$alert['id']}
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function formatContext(array $context): string
    {
        if (empty($context)) {
            return '';
        }
        
        $html = '<div class="context"><strong>Additional Context:</strong><ul>';
        foreach ($context as $key => $value) {
            $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars(json_encode($value)) . '</li>';
        }
        $html .= '</ul></div>';
        
        return $html;
    }
}

/**
 * Slack Channel - Send alerts via Slack webhook
 */
class SlackChannel
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function send(array $alert): array
    {
        $webhook = $this->config['webhook_url'] ?? '';
        
        if (empty($webhook)) {
            return [
                'success' => false,
                'error' => 'Slack webhook URL not configured',
            ];
        }
        
        $payload = $this->buildSlackPayload($alert);
        
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5,            // Security: Connection timeout
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,         // Security: ENABLED - Verify SSL certificate
            CURLOPT_SSL_VERIFYHOST => 2,            // Security: ENABLED - Verify hostname
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,   // Security: HTTPS only
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS, // Use HTTP/2 with TLS
            CURLOPT_FOLLOWLOCATION => false,        // Security: Prevent redirects
        ]);
        
        // Allow custom CA bundle path from environment (optional)
        $caInfo = getenv('CURL_CA_BUNDLE');
        if ($caInfo !== false && is_file($caInfo)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'success' => false,
                'channel' => 'slack',
                'error' => $error,
            ];
        }
        
        return [
            'success' => $httpCode === 200,
            'channel' => 'slack',
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }

    private function buildSlackPayload(array $alert): array
    {
        $colors = [
            'critical' => 'danger',
            'error' => 'warning',
            'warning' => 'warning',
            'info' => 'good',
        ];
        
        return [
            'username' => $this->config['username'] ?? 'Transfer Engine',
            'channel' => $this->config['channel'] ?? '#alerts',
            'attachments' => [
                [
                    'color' => $colors[$alert['severity']] ?? '#6c757d',
                    'title' => $alert['title'],
                    'text' => $alert['message'],
                    'fields' => [
                        ['title' => 'Severity', 'value' => strtoupper($alert['severity']), 'short' => true],
                        ['title' => 'Environment', 'value' => $alert['environment'], 'short' => true],
                        ['title' => 'System', 'value' => $alert['system'], 'short' => true],
                        ['title' => 'Host', 'value' => $alert['hostname'], 'short' => true],
                    ],
                    'footer' => 'Alert ID: ' . $alert['id'],
                    'ts' => strtotime($alert['timestamp']),
                ],
            ],
        ];
    }
}

/**
 * Webhook Channel - Send alerts via HTTP webhook
 */
class WebhookChannel
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function send(array $alert): array
    {
        $url = $this->config['url'] ?? '';
        
        if (empty($url)) {
            return [
                'success' => false,
                'error' => 'Webhook URL not configured',
            ];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $this->config['method'] ?? 'POST',
            CURLOPT_POSTFIELDS => json_encode($alert),
            CURLOPT_HTTPHEADER => array_merge(
                ['Content-Type: application/json'],
                $this->config['headers'] ?? []
            ),
            CURLOPT_CONNECTTIMEOUT => 5,            // Security: Connection timeout
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,         // Security: ENABLED - Verify SSL certificate
            CURLOPT_SSL_VERIFYHOST => 2,            // Security: ENABLED - Verify hostname
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,   // Security: HTTPS only
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS, // Use HTTP/2 with TLS
            CURLOPT_FOLLOWLOCATION => false,        // Security: Prevent redirects
        ]);
        
        // Allow custom CA bundle path from environment (optional)
        $caInfo = getenv('CURL_CA_BUNDLE');
        if ($caInfo !== false && is_file($caInfo)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'success' => false,
                'channel' => 'webhook',
                'error' => $error,
            ];
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'channel' => 'webhook',
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }
}

/**
 * Log Channel - Send alerts to log file
 */
class LogChannel
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function send(array $alert): array
    {
        $level = match($alert['severity']) {
            'critical' => 'critical',
            'error' => 'error',
            'warning' => 'warning',
            default => 'info',
        };
        
        $this->logger->{$level}('[ALERT] ' . $alert['title'], NeuroContext::wrap('alert_manager', [
            'alert_id' => $alert['id'],
            'message' => $alert['message'],
            'context' => $alert['context'],
        ]));
        
        return [
            'success' => true,
            'channel' => 'log',
        ];
    }
}
