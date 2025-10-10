<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Alert Service - Multi-Channel Notifications
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Send critical alerts via Email, Slack, SMS
 * 
 * FEATURES:
 * - Email notifications (PHP mail or SMTP)
 * - Slack webhook integration
 * - SMS alerts (Twilio integration ready)
 * - Alert throttling (prevent spam)
 * - Priority-based routing
 */
class AlertService
{
    private Logger $logger;
    private array $alertHistory = [];
    private const THROTTLE_SECONDS = 300; // 5 minutes between duplicate alerts
    
    public function __construct()
    {
        $this->logger = new Logger();
    }
    
    /**
     * Send critical alert through all configured channels
     */
    public function sendCriticalAlert(string $message, array $context = [], string $priority = 'high'): void
    {
        // Check throttling
        if ($this->isThrottled($message, $priority)) {
            $this->logger->debug('Alert throttled', ['message' => $message]);
            return;
        }
        
        $this->logger->critical('Sending critical alert', [
            'message' => $message,
            'priority' => $priority,
            'context' => $context
        ]);
        
        // Send through all enabled channels
        try {
            if ($this->isEmailEnabled()) {
                $this->sendEmail($message, $context, $priority);
            }
            
            if ($this->isSlackEnabled()) {
                $this->sendSlack($message, $context, $priority);
            }
            
            if ($this->isSMSEnabled() && $priority === 'critical') {
                $this->sendSMS($message, $context);
            }
            
            // Record alert
            $this->recordAlert($message, $priority);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send alert', [
                'error' => $e->getMessage(),
                'original_message' => $message
            ]);
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmail(string $message, array $context, string $priority): void
    {
        $to = $_ENV['ALERT_EMAIL'] ?? 'pearce.stephens@ecigdis.co.nz';
        $subject = "[{$priority}] Transfer Engine Alert";
        
        $body = $this->formatEmailBody($message, $context, $priority);
        
        $headers = [
            'From: noreply@vapeshed.co.nz',
            'Reply-To: support@vapeshed.co.nz',
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html; charset=UTF-8',
            'X-Priority: 1', // High priority
            'Importance: High'
        ];
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($success) {
            $this->logger->info('Email alert sent', ['to' => $to]);
        } else {
            $this->logger->error('Failed to send email alert', ['to' => $to]);
        }
    }
    
    /**
     * Send Slack notification
     */
    private function sendSlack(string $message, array $context, string $priority): void
    {
        $webhookUrl = $_ENV['SLACK_WEBHOOK_URL'] ?? null;
        
        if (!$webhookUrl) {
            $this->logger->debug('Slack webhook not configured');
            return;
        }
        
        $color = match($priority) {
            'critical' => '#FF0000',
            'high' => '#FF6600',
            'medium' => '#FFCC00',
            default => '#36a64f'
        };
        
        $payload = [
            'username' => 'Transfer Engine',
            'icon_emoji' => ':warning:',
            'attachments' => [[
                'color' => $color,
                'title' => "🚨 {$priority} Alert",
                'text' => $message,
                'fields' => $this->formatSlackFields($context),
                'footer' => 'Transfer Engine',
                'ts' => time()
            ]]
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $this->logger->info('Slack alert sent successfully');
        } else {
            $this->logger->error('Failed to send Slack alert', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
        }
    }
    
    /**
     * Send SMS notification (Twilio integration)
     */
    private function sendSMS(string $message, array $context): void
    {
        $twilioSid = $_ENV['TWILIO_SID'] ?? null;
        $twilioToken = $_ENV['TWILIO_TOKEN'] ?? null;
        $twilioFrom = $_ENV['TWILIO_FROM'] ?? null;
        $alertNumbers = $_ENV['ALERT_SMS_NUMBERS'] ?? null; // Comma-separated
        
        if (!$twilioSid || !$twilioToken || !$twilioFrom || !$alertNumbers) {
            $this->logger->debug('SMS alerts not configured');
            return;
        }
        
        $numbers = array_map('trim', explode(',', $alertNumbers));
        $smsBody = "🚨 CRITICAL: " . substr($message, 0, 140);
        
        foreach ($numbers as $number) {
            try {
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";
                
                $data = [
                    'From' => $twilioFrom,
                    'To' => $number,
                    'Body' => $smsBody
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$twilioSid}:{$twilioToken}");
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 201) {
                    $this->logger->info('SMS sent', ['to' => $number]);
                } else {
                    $this->logger->error('SMS failed', ['to' => $number, 'code' => $httpCode]);
                }
                
            } catch (\Exception $e) {
                $this->logger->error('SMS exception', ['to' => $number, 'error' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * Check if alert should be throttled
     */
    private function isThrottled(string $message, string $priority): bool
    {
        $key = md5($message . $priority);
        
        if (isset($this->alertHistory[$key])) {
            $lastSent = $this->alertHistory[$key];
            $elapsed = time() - $lastSent;
            
            if ($elapsed < self::THROTTLE_SECONDS) {
                return true; // Throttled
            }
        }
        
        return false;
    }
    
    /**
     * Record alert in history
     */
    private function recordAlert(string $message, string $priority): void
    {
        $key = md5($message . $priority);
        $this->alertHistory[$key] = time();
        
        // Clean old history (keep last hour only)
        $cutoff = time() - 3600;
        $this->alertHistory = array_filter($this->alertHistory, fn($ts) => $ts > $cutoff);
    }
    
    /**
     * Check if email is enabled
     */
    private function isEmailEnabled(): bool
    {
        return !empty($_ENV['ALERT_EMAIL_ENABLED']) && 
               $_ENV['ALERT_EMAIL_ENABLED'] !== 'false';
    }
    
    /**
     * Check if Slack is enabled
     */
    private function isSlackEnabled(): bool
    {
        return !empty($_ENV['SLACK_WEBHOOK_URL']);
    }
    
    /**
     * Check if SMS is enabled
     */
    private function isSMSEnabled(): bool
    {
        return !empty($_ENV['TWILIO_SID']) && 
               !empty($_ENV['TWILIO_TOKEN']) &&
               !empty($_ENV['ALERT_SMS_NUMBERS']);
    }
    
    /**
     * Format email body
     */
    private function formatEmailBody(string $message, array $context, string $priority): string
    {
        $contextHtml = '';
        if (!empty($context)) {
            $contextHtml = '<h3>Context:</h3><ul>';
            foreach ($context as $key => $value) {
                $safeValue = htmlspecialchars(is_scalar($value) ? (string)$value : json_encode($value));
                $contextHtml .= "<li><strong>{$key}:</strong> {$safeValue}</li>";
            }
            $contextHtml .= '</ul>';
        }
        
        $priorityColor = match($priority) {
            'critical' => '#FF0000',
            'high' => '#FF6600',
            'medium' => '#FFCC00',
            default => '#36a64f'
        };
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .alert-box { border: 3px solid {$priorityColor}; padding: 20px; margin: 20px 0; background: #f9f9f9; }
        .priority { color: {$priorityColor}; font-weight: bold; text-transform: uppercase; }
        .timestamp { color: #666; font-size: 0.9em; }
        h2 { margin-top: 0; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
    <div class="alert-box">
        <h2>🚨 Transfer Engine Alert</h2>
        <p class="priority">Priority: {$priority}</p>
        <p><strong>Message:</strong> {$message}</p>
        {$contextHtml}
        <p class="timestamp">Timestamp: " . date('Y-m-d H:i:s T') . "</p>
        <p>Server: {$_SERVER['SERVER_NAME']}</p>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Format Slack fields
     */
    private function formatSlackFields(array $context): array
    {
        $fields = [];
        
        foreach ($context as $key => $value) {
            $fields[] = [
                'title' => ucwords(str_replace('_', ' ', $key)),
                'value' => is_scalar($value) ? (string)$value : json_encode($value),
                'short' => strlen((string)$value) < 40
            ];
        }
        
        $fields[] = [
            'title' => 'Server',
            'value' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'short' => true
        ];
        
        $fields[] = [
            'title' => 'Time',
            'value' => date('Y-m-d H:i:s T'),
            'short' => true
        ];
        
        return $fields;
    }
    
    /**
     * Test all alert channels
     */
    public function testAlerts(): array
    {
        $results = [];
        
        // Test email
        if ($this->isEmailEnabled()) {
            try {
                $this->sendEmail('Test alert from Transfer Engine', ['test' => 'value'], 'medium');
                $results['email'] = 'SENT';
            } catch (\Exception $e) {
                $results['email'] = 'FAILED: ' . $e->getMessage();
            }
        } else {
            $results['email'] = 'DISABLED';
        }
        
        // Test Slack
        if ($this->isSlackEnabled()) {
            try {
                $this->sendSlack('Test alert from Transfer Engine', ['test' => 'value'], 'medium');
                $results['slack'] = 'SENT';
            } catch (\Exception $e) {
                $results['slack'] = 'FAILED: ' . $e->getMessage();
            }
        } else {
            $results['slack'] = 'DISABLED';
        }
        
        // Test SMS
        if ($this->isSMSEnabled()) {
            try {
                $this->sendSMS('Test alert from Transfer Engine', ['test' => 'value']);
                $results['sms'] = 'SENT';
            } catch (\Exception $e) {
                $results['sms'] = 'FAILED: ' . $e->getMessage();
            }
        } else {
            $results['sms'] = 'DISABLED';
        }
        
        return $results;
    }
}
