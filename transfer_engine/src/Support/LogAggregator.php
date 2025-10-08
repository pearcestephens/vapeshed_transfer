<?php
/**
 * LogAggregator.php - Enterprise Log Aggregation & Search
 * 
 * Provides log aggregation, search, filtering, and analysis capabilities
 * across multiple log files with pagination and export support.
 * 
 * Features:
 * - Multi-file log aggregation
 * - Full-text search with regex
 * - Severity-based filtering
 * - Time-range filtering
 * - Component filtering
 * - Pagination support
 * - Export to JSON/CSV
 * - Real-time log tailing
 * - Log statistics & analytics
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;

class LogAggregator
{
    private Logger $logger;
    private string $logDirectory;
    private array $config;

    /**
     * Initialize LogAggregator
     *
     * @param Logger $logger Logger instance
     * @param string $logDirectory Base log directory
     * @param array $config Configuration options
     */
    public function __construct(Logger $logger, string $logDirectory, array $config = [])
    {
        $this->logger = $logger;
        $this->logDirectory = rtrim($logDirectory, '/');
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Search logs with filters
     *
     * @param array|string $filters Search filters array or search query string
     * @param array $options Additional options if first param is string
     * @return array Search results with pagination
     */
    public function search(array|string $filters = [], array $options = []): array
    {
        // Handle string query parameter: search('keyword', ['level' => 'info'])
        if (is_string($filters)) {
            $query = $filters;
            $filters = array_merge(['query' => $query], $options);
        }
        
        $startTime = microtime(true);
        
        // Parse filters
        $query = $filters['query'] ?? '';
        $severity = $filters['severity'] ?? null;
        $component = $filters['component'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(1000, max(10, (int)($filters['per_page'] ?? 100)));
        $isRegex = (bool)($filters['regex'] ?? false);
        
        // Get log files in date range
        $logFiles = $this->getLogFiles($startDate, $endDate);
        
        // Parse and filter logs
        $entries = [];
        foreach ($logFiles as $file) {
            $fileEntries = $this->parseLogFile($file, [
                'query' => $query,
                'severity' => $severity,
                'component' => $component,
                'is_regex' => $isRegex,
            ]);
            $entries = array_merge($entries, $fileEntries);
        }
        
        // Sort by timestamp (newest first)
        usort($entries, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));
        
        // Pagination
        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $paginatedEntries = array_slice($entries, $offset, $perPage);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Log search completed', NeuroContext::wrap('log_aggregator', [
            'query' => $query,
            'filters' => $filters,
            'total_results' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'duration_ms' => $duration,
        ]));
        
        return [
            'entries' => $paginatedEntries,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage),
            ],
            'filters' => $filters,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Get log statistics
     *
     * @param array $filters Filter options
     * @return array Statistics
     */
    public function getStats(array $filters = []): array
    {
        $startTime = microtime(true);
        
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        
        $logFiles = $this->getLogFiles($startDate, $endDate);
        
        $stats = [
            'total_entries' => 0,
            'by_severity' => [
                'debug' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0,
            ],
            'by_component' => [],
            'by_day' => [],
            'top_errors' => [],
            'file_count' => count($logFiles),
        ];
        
        foreach ($logFiles as $file) {
            $entries = $this->parseLogFile($file);
            $stats['total_entries'] += count($entries);
            
            foreach ($entries as $entry) {
                // Count by severity
                $severity = strtolower($entry['severity'] ?? 'info');
                if (isset($stats['by_severity'][$severity])) {
                    $stats['by_severity'][$severity]++;
                }
                
                // Count by component
                $component = $entry['neuro']['component'] ?? 'unknown';
                if (!isset($stats['by_component'][$component])) {
                    $stats['by_component'][$component] = 0;
                }
                $stats['by_component'][$component]++;
                
                // Count by day
                $day = substr($entry['timestamp'], 0, 10);
                if (!isset($stats['by_day'][$day])) {
                    $stats['by_day'][$day] = [
                        'date' => $day,
                        'total' => 0,
                        'errors' => 0,
                    ];
                }
                $stats['by_day'][$day]['total']++;
                if (in_array($severity, ['error', 'critical'])) {
                    $stats['by_day'][$day]['errors']++;
                }
                
                // Track top errors
                if (in_array($severity, ['error', 'critical'])) {
                    $message = $entry['message'] ?? '';
                    if (!isset($stats['top_errors'][$message])) {
                        $stats['top_errors'][$message] = [
                            'message' => $message,
                            'count' => 0,
                            'severity' => $severity,
                        ];
                    }
                    $stats['top_errors'][$message]['count']++;
                }
            }
        }
        
        // Sort top errors by count
        usort($stats['top_errors'], fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_errors'] = array_slice($stats['top_errors'], 0, 10);
        
        // Sort components by count
        arsort($stats['by_component']);
        
        // Convert by_day to array
        $stats['by_day'] = array_values($stats['by_day']);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $stats['duration_ms'] = $duration;
        
        return $stats;
    }

    /**
     * Alias for getStats() (for compatibility)
     *
     * @param array $filters Filter options
     * @return array Statistics
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->getStats($filters);
    }

    /**
     * Tail logs in real-time
     *
     * @param int $lines Number of lines to tail
     * @param string|null $file Specific log file (null = latest)
     * @return array Recent log entries
     */
    public function tail(int $lines = 100, ?string $file = null): array
    {
        if ($file === null) {
            $logFiles = $this->getLogFiles();
            $file = end($logFiles);
        }
        
        if (!file_exists($file)) {
            return [
                'entries' => [],
                'file' => $file,
                'error' => 'Log file not found',
            ];
        }
        
        // Read last N lines using tail command (faster than PHP for large files)
        $command = sprintf('tail -n %d %s', $lines, escapeshellarg($file));
        $output = [];
        exec($command, $output);
        
        $entries = [];
        foreach ($output as $line) {
            $entry = $this->parseLogLine($line);
            if ($entry) {
                $entries[] = $entry;
            }
        }
        
        return [
            'entries' => $entries,
            'file' => $file,
            'count' => count($entries),
        ];
    }

    /**
     * Export logs to file
     *
     * @param array $filters Search filters
     * @param string $format Export format (json, csv)
     * @param string $outputPath Output file path
     * @return array Export result
     */
    public function export(array $filters, string $format = 'json', string $outputPath = null): array
    {
        $startTime = microtime(true);
        
        // Search logs
        $filters['per_page'] = 10000; // Large page for export
        $result = $this->search($filters);
        $entries = $result['entries'];
        
        // Generate output path if not provided
        if ($outputPath === null) {
            $timestamp = date('Y-m-d_His');
            $outputPath = $this->config['export_directory'] . "/logs_export_{$timestamp}.{$format}";
        }
        
        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Export based on format
        switch ($format) {
            case 'csv':
                $success = $this->exportToCsv($entries, $outputPath);
                break;
            case 'json':
            default:
                $success = $this->exportToJson($entries, $outputPath);
                break;
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Log export completed', NeuroContext::wrap('log_aggregator', [
            'format' => $format,
            'output_path' => $outputPath,
            'entry_count' => count($entries),
            'duration_ms' => $duration,
        ]));
        
        return [
            'success' => $success,
            'path' => $outputPath,
            'format' => $format,
            'entry_count' => count($entries),
            'duration_ms' => $duration,
        ];
    }

    /**
     * Get log files in date range
     *
     * @param string|null $startDate Start date (YYYY-MM-DD)
     * @param string|null $endDate End date (YYYY-MM-DD)
     * @return array Log file paths
     */
    private function getLogFiles(?string $startDate = null, ?string $endDate = null): array
    {
        $files = glob($this->logDirectory . '/*.log*');
        
        if ($startDate === null && $endDate === null) {
            return $files;
        }
        
        $startTimestamp = $startDate ? strtotime($startDate) : 0;
        $endTimestamp = $endDate ? strtotime($endDate . ' 23:59:59') : time();
        
        return array_filter($files, function($file) use ($startTimestamp, $endTimestamp) {
            $mtime = filemtime($file);
            return $mtime >= $startTimestamp && $mtime <= $endTimestamp;
        });
    }

    /**
     * Parse log file with filters
     *
     * @param string $file Log file path
     * @param array $filters Filter options
     * @return array Parsed log entries
     */
    private function parseLogFile(string $file, array $filters = []): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $entries = [];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            return [];
        }
        
        while (($line = fgets($handle)) !== false) {
            $entry = $this->parseLogLine($line);
            
            if (!$entry) {
                continue;
            }
            
            // Apply filters
            if (!$this->matchesFilters($entry, $filters)) {
                continue;
            }
            
            $entries[] = $entry;
        }
        
        fclose($handle);
        
        return $entries;
    }

    /**
     * Parse single log line
     *
     * @param string $line Log line
     * @return array|null Parsed entry or null if invalid
     */
    private function parseLogLine(string $line): ?array
    {
        $line = trim($line);
        
        if (empty($line)) {
            return null;
        }
        
        // Try to parse as JSON (structured log)
        if ($line[0] === '{') {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        // Try to parse as traditional log format
        // [2024-10-07 12:34:56] severity: message
        if (preg_match('/^\[([^\]]+)\]\s+(\w+):\s+(.+)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'severity' => strtolower($matches[2]),
                'message' => $matches[3],
                'neuro' => [],
            ];
        }
        
        return null;
    }

    /**
     * Check if entry matches filters
     *
     * @param array $entry Log entry
     * @param array $filters Filter options
     * @return bool True if matches
     */
    private function matchesFilters(array $entry, array $filters): bool
    {
        // Query filter
        if (!empty($filters['query'])) {
            $message = strtolower($entry['message'] ?? '');
            $query = strtolower($filters['query']);
            
            if ($filters['is_regex'] ?? false) {
                if (!preg_match('/' . $query . '/i', $message)) {
                    return false;
                }
            } else {
                if (strpos($message, $query) === false) {
                    return false;
                }
            }
        }
        
        // Severity filter
        if (!empty($filters['severity'])) {
            $severity = strtolower($entry['severity'] ?? 'info');
            if ($severity !== strtolower($filters['severity'])) {
                return false;
            }
        }
        
        // Component filter
        if (!empty($filters['component'])) {
            $component = $entry['neuro']['component'] ?? '';
            if ($component !== $filters['component']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Export entries to JSON
     *
     * @param array $entries Log entries
     * @param string $outputPath Output file path
     * @return bool Success status
     */
    private function exportToJson(array $entries, string $outputPath): bool
    {
        $json = json_encode([
            'exported_at' => date('c'),
            'entry_count' => count($entries),
            'entries' => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return file_put_contents($outputPath, $json) !== false;
    }

    /**
     * Export entries to CSV
     *
     * @param array $entries Log entries
     * @param string $outputPath Output file path
     * @return bool Success status
     */
    private function exportToCsv(array $entries, string $outputPath): bool
    {
        $handle = fopen($outputPath, 'w');
        
        if (!$handle) {
            return false;
        }
        
        // Write header
        fputcsv($handle, [
            'Timestamp',
            'Severity',
            'Message',
            'Component',
            'Correlation ID',
            'Environment',
        ]);
        
        // Write entries
        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry['timestamp'] ?? '',
                $entry['severity'] ?? '',
                $entry['message'] ?? '',
                $entry['neuro']['component'] ?? '',
                $entry['correlation_id'] ?? '',
                $entry['neuro']['environment'] ?? '',
            ]);
        }
        
        fclose($handle);
        
        return true;
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'export_directory' => $this->logDirectory . '/exports',
            'max_file_size' => 100 * 1024 * 1024, // 100MB
        ];
    }

    /**
     * Clean old log files
     *
     * @param int $days Keep logs for this many days
     * @return array Cleanup result
     */
    public function cleanup(int $days = 30): array
    {
        $cutoff = strtotime("-{$days} days");
        $files = glob($this->logDirectory . '/*.log*');
        $removed = [];
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $removed[] = $file;
                }
            }
        }
        
        $this->logger->info('Log cleanup completed', NeuroContext::wrap('log_aggregator', [
            'days_retained' => $days,
            'files_removed' => count($removed),
        ]));
        
        return [
            'success' => true,
            'removed_count' => count($removed),
            'removed_files' => $removed,
        ];
    }
}
