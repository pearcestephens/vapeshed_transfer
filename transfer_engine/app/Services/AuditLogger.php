<?php
/**
 * Audit Logger Service
 *
 * Security event logging for:
 * - Authentication events (login, logout, failed attempts)
 * - Authorization events (permission checks, access denials)
 * - Data modifications (create, update, delete)
 * - Configuration changes
 * - Security incidents
 * - Admin actions
 * - API access
 * - Export operations
 *
 * @category   Service
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

namespace App\Services;

/**
 * Audit Logger Service
 */
class AuditLogger
{
    /**
     * Log storage path
     *
     * @var string
     */
    private $storagePath;

    /**
     * Database connection
     *
     * @var object|null
     */
    private $db;

    /**
     * Current user ID
     *
     * @var int|null
     */
    private $userId;

    /**
     * Current user IP
     *
     * @var string
     */
    private $userIp;

    /**
     * Session ID
     *
     * @var string
     */
    private $sessionId;

    /**
     * Event categories
     *
     * @var array
     */
    private const CATEGORIES = [
        'auth' => 'Authentication',
        'authz' => 'Authorization',
        'data' => 'Data Modification',
        'config' => 'Configuration',
        'security' => 'Security Incident',
        'admin' => 'Administrative Action',
        'api' => 'API Access',
        'export' => 'Data Export',
        'transfer' => 'Stock Transfer',
        'system' => 'System Event'
    ];

    /**
     * Severity levels
     *
     * @var array
     */
    private const SEVERITY = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct(array $config = [])
    {
        $this->storagePath = $config['storage_path'] ?? __DIR__ . '/../../storage/logs/audit';
        $this->db = $config['db'] ?? null;
        $this->userId = $config['user_id'] ?? null;
        $this->userIp = $this->getClientIp();
        $this->sessionId = session_id() ?: 'cli';

        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Log authentication event
     *
     * @param string $action Action (login, logout, failed_login, etc.)
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logAuth(string $action, array $details = [], string $severity = 'info'): bool
    {
        return $this->log('auth', $action, $details, $severity);
    }

    /**
     * Log authorization event
     *
     * @param string $action Action (access_granted, access_denied, etc.)
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logAuthz(string $action, array $details = [], string $severity = 'notice'): bool
    {
        return $this->log('authz', $action, $details, $severity);
    }

    /**
     * Log data modification event
     *
     * @param string $action Action (create, update, delete)
     * @param string $entity Entity type (transfer, user, config, etc.)
     * @param mixed $entityId Entity ID
     * @param array $changes Changes made
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logDataChange(string $action, string $entity, $entityId, array $changes = [], string $severity = 'info'): bool
    {
        $details = [
            'entity' => $entity,
            'entity_id' => $entityId,
            'changes' => $changes,
            'action' => $action
        ];

        return $this->log('data', "{$action}_{$entity}", $details, $severity);
    }

    /**
     * Log configuration change
     *
     * @param string $key Configuration key
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logConfigChange(string $key, $oldValue, $newValue, string $severity = 'warning'): bool
    {
        $details = [
            'key' => $key,
            'old_value' => $this->maskSensitive($key, $oldValue),
            'new_value' => $this->maskSensitive($key, $newValue)
        ];

        return $this->log('config', 'config_change', $details, $severity);
    }

    /**
     * Log security incident
     *
     * @param string $incident Incident type
     * @param array $details Incident details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logSecurityIncident(string $incident, array $details = [], string $severity = 'critical'): bool
    {
        return $this->log('security', $incident, $details, $severity);
    }

    /**
     * Log admin action
     *
     * @param string $action Action performed
     * @param array $details Action details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logAdminAction(string $action, array $details = [], string $severity = 'notice'): bool
    {
        return $this->log('admin', $action, $details, $severity);
    }

    /**
     * Log API access
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $details Request details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logApiAccess(string $endpoint, string $method, array $details = [], string $severity = 'info'): bool
    {
        $details = array_merge($details, [
            'endpoint' => $endpoint,
            'method' => $method
        ]);

        return $this->log('api', 'api_request', $details, $severity);
    }

    /**
     * Log data export
     *
     * @param string $type Export type
     * @param array $details Export details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logExport(string $type, array $details = [], string $severity = 'warning'): bool
    {
        $details['export_type'] = $type;
        return $this->log('export', 'data_export', $details, $severity);
    }

    /**
     * Log transfer operation
     *
     * @param string $action Action (create, execute, cancel, etc.)
     * @param int $transferId Transfer ID
     * @param array $details Transfer details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logTransfer(string $action, int $transferId, array $details = [], string $severity = 'info'): bool
    {
        $details['transfer_id'] = $transferId;
        return $this->log('transfer', "transfer_{$action}", $details, $severity);
    }

    /**
     * Core log method
     *
     * @param string $category Event category
     * @param string $action Action performed
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function log(string $category, string $action, array $details = [], string $severity = 'info'): bool
    {
        try {
            $entry = [
                'id' => $this->generateId(),
                'timestamp' => microtime(true),
                'datetime' => date('Y-m-d H:i:s'),
                'category' => $category,
                'action' => $action,
                'severity' => $severity,
                'severity_level' => self::SEVERITY[$severity] ?? 1,
                'user_id' => $this->userId,
                'user_ip' => $this->userIp,
                'session_id' => $this->sessionId,
                'details' => $details,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI'
            ];

            // Write to file
            $this->writeToFile($entry);

            // Write to database if available
            if ($this->db) {
                $this->writeToDatabase($entry);
            }

            // Alert on critical events
            if (self::SEVERITY[$severity] >= self::SEVERITY['critical']) {
                $this->alertCriticalEvent($entry);
            }

            return true;

        } catch (\Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write audit entry to file
     *
     * @param array $entry Audit entry
     * @return void
     */
    private function writeToFile(array $entry): void
    {
        // Organize by date and category
        $date = date('Y-m-d');
        $category = $entry['category'];
        $file = "{$this->storagePath}/{$date}_{$category}.log";

        // Format entry
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";

        // Write to file
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        // Also write to master log
        $masterFile = "{$this->storagePath}/{$date}_all.log";
        file_put_contents($masterFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write audit entry to database
     *
     * @param array $entry Audit entry
     * @return void
     */
    private function writeToDatabase(array $entry): void
    {
        $sql = "INSERT INTO audit_log (
            id, timestamp, category, action, severity, severity_level,
            user_id, user_ip, session_id, details, user_agent,
            request_uri, request_method, created_at
        ) VALUES (
            :id, :timestamp, :category, :action, :severity, :severity_level,
            :user_id, :user_ip, :session_id, :details, :user_agent,
            :request_uri, :request_method, NOW()
        )";

        $params = [
            'id' => $entry['id'],
            'timestamp' => $entry['timestamp'],
            'category' => $entry['category'],
            'action' => $entry['action'],
            'severity' => $entry['severity'],
            'severity_level' => $entry['severity_level'],
            'user_id' => $entry['user_id'],
            'user_ip' => $entry['user_ip'],
            'session_id' => $entry['session_id'],
            'details' => json_encode($entry['details']),
            'user_agent' => $entry['user_agent'],
            'request_uri' => $entry['request_uri'],
            'request_method' => $entry['request_method']
        ];

        try {
            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            // Fail silently, file log is primary
            error_log('Database audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Alert on critical events
     *
     * @param array $entry Audit entry
     * @return void
     */
    private function alertCriticalEvent(array $entry): void
    {
        // Write to separate critical events file
        $criticalFile = "{$this->storagePath}/critical_events.log";
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        file_put_contents($criticalFile, $line, FILE_APPEND | LOCK_EX);

        // Send multi-channel alerts for critical events
        try {
            $alertService = new AlertService();
            $message = $entry['message'] ?? 'Critical audit event';
            $context = [
                'event_type' => $entry['type'] ?? 'unknown',
                'user' => $entry['user'] ?? 'system',
                'ip' => $entry['ip'] ?? 'unknown',
                'timestamp' => $entry['timestamp'] ?? date('c')
            ];
            
            $alertService->sendCriticalAlert($message, $context, 'critical');
        } catch (\Exception $e) {
            error_log("Failed to send critical alert: " . $e->getMessage());
        }
    }

    /**
     * Search audit logs
     *
     * @param array $criteria Search criteria
     * @param int $limit Result limit
     * @return array Results
     */
    public function search(array $criteria = [], int $limit = 100): array
    {
        $results = [];

        // If DB available, use it for complex searches
        if ($this->db) {
            return $this->searchDatabase($criteria, $limit);
        }

        // Otherwise search files
        return $this->searchFiles($criteria, $limit);
    }

    /**
     * Search database for audit entries
     *
     * @param array $criteria Search criteria
     * @param int $limit Result limit
     * @return array Results
     */
    private function searchDatabase(array $criteria, int $limit): array
    {
        $where = ['1=1'];
        $params = [];

        if (isset($criteria['category'])) {
            $where[] = 'category = :category';
            $params['category'] = $criteria['category'];
        }

        if (isset($criteria['action'])) {
            $where[] = 'action = :action';
            $params['action'] = $criteria['action'];
        }

        if (isset($criteria['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $criteria['user_id'];
        }

        if (isset($criteria['severity'])) {
            $where[] = 'severity = :severity';
            $params['severity'] = $criteria['severity'];
        }

        if (isset($criteria['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $criteria['date_from'];
        }

        if (isset($criteria['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $criteria['date_to'];
        }

        $sql = "SELECT * FROM audit_log 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $params['limit'] = $limit;

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Exception $e) {
            error_log('Audit search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search log files for audit entries
     *
     * @param array $criteria Search criteria
     * @param int $limit Result limit
     * @return array Results
     */
    private function searchFiles(array $criteria, int $limit): array
    {
        $results = [];
        $category = $criteria['category'] ?? 'all';
        $date = $criteria['date'] ?? date('Y-m-d');

        $file = "{$this->storagePath}/{$date}_{$category}.log";

        if (!file_exists($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach (array_reverse($lines) as $line) {
            if (count($results) >= $limit) {
                break;
            }

            $entry = json_decode($line, true);
            
            if ($this->matchesCriteria($entry, $criteria)) {
                $results[] = $entry;
            }
        }

        return $results;
    }

    /**
     * Check if entry matches search criteria
     *
     * @param array $entry Audit entry
     * @param array $criteria Search criteria
     * @return bool True if matches
     */
    private function matchesCriteria(array $entry, array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            if ($key === 'limit' || $key === 'date') {
                continue;
            }

            if (!isset($entry[$key]) || $entry[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get statistics
     *
     * @param string $date Date (Y-m-d)
     * @return array Statistics
     */
    public function getStats(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $stats = [
            'date' => $date,
            'total' => 0,
            'by_category' => [],
            'by_severity' => [],
            'by_user' => []
        ];

        $file = "{$this->storagePath}/{$date}_all.log";

        if (!file_exists($file)) {
            return $stats;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['total'] = count($lines);

        foreach ($lines as $line) {
            $entry = json_decode($line, true);

            // By category
            $category = $entry['category'];
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;

            // By severity
            $severity = $entry['severity'];
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;

            // By user
            $userId = $entry['user_id'] ?? 'anonymous';
            $stats['by_user'][$userId] = ($stats['by_user'][$userId] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Generate unique entry ID
     *
     * @return string Unique ID
     */
    private function generateId(): string
    {
        return uniqid('audit_', true);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Mask sensitive values in logs
     *
     * @param string $key Configuration key
     * @param mixed $value Value to mask
     * @return mixed Masked value
     */
    private function maskSensitive(string $key, $value)
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'api_key', 'private'];

        foreach ($sensitiveKeys as $sensitive) {
            if (stripos($key, $sensitive) !== false) {
                return '***REDACTED***';
            }
        }

        return $value;
    }

    /**
     * Cleanup old logs
     *
     * @param int $days Keep logs for this many days
     * @return int Files deleted
     */
    public function cleanup(int $days = 90): int
    {
        $deleted = 0;
        $cutoff = strtotime("-{$days} days");

        $files = glob("{$this->storagePath}/*.log");

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Set current user
     *
     * @param int $userId User ID
     * @return self
     */
    public function setUser(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
}
