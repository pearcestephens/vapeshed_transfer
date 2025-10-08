<?php
#!/usr/bin/env php
<?php
/**
 * monitor.php - System Monitoring CLI Tool
 * 
 * Command-line interface for monitoring system health, performance,
 * logs, and alerts with watch mode and alerting capabilities.
 * 
 * Usage:
 *   bin/monitor.php health                      - Check system health
 *   bin/monitor.php health --watch              - Watch health continuously
 *   bin/monitor.php health --history 24         - Show 24h health history
 *   bin/monitor.php performance                 - Show performance metrics
 *   bin/monitor.php performance --range 6h      - Show 6h performance data
 *   bin/monitor.php logs --search "error"       - Search logs
 *   bin/monitor.php logs --tail 100             - Tail last 100 log lines
 *   bin/monitor.php logs --stats                - Show log statistics
 *   bin/monitor.php alerts                      - Show alert history
 *   bin/monitor.php alerts --send               - Send test alert
 *   bin/monitor.php overview                    - System overview dashboard
 *   bin/monitor.php overview --watch            - Watch overview continuously
 * 
 * @package VapeshedTransfer
 * @subpackage Bin
 */

require_once __DIR__ . '/../config/bootstrap.php';

use VapeshedTransfer\Support\Logger;
use VapeshedTransfer\Support\Cache;
use VapeshedTransfer\Support\HealthMonitor;
use VapeshedTransfer\Support\PerformanceProfiler;
use VapeshedTransfer\Support\LogAggregator;
use VapeshedTransfer\Support\AlertManager;
use VapeshedTransfer\Support\NeuroContext;

// Parse command line arguments
$command = $argv[1] ?? 'overview';
$options = parseOptions(array_slice($argv, 2));

// Initialize services
$logger = new Logger('monitor_cli');
$cache = new Cache($logger);
$alertManager = new AlertManager($logger, $cache);
$healthMonitor = new HealthMonitor($logger, $cache, $alertManager);
$profiler = new PerformanceProfiler($logger, $cache, $alertManager);
$logAggregator = new LogAggregator($logger, storage_path('logs'));

// Execute command
try {
    switch ($command) {
        case 'health':
            handleHealth($healthMonitor, $options);
            break;
            
        case 'performance':
        case 'perf':
            handlePerformance($profiler, $options);
            break;
            
        case 'logs':
            handleLogs($logAggregator, $options);
            break;
            
        case 'alerts':
            handleAlerts($alertManager, $options);
            break;
            
        case 'overview':
            handleOverview($healthMonitor, $profiler, $logAggregator, $alertManager, $options);
            break;
            
        case 'help':
        case '--help':
        case '-h':
            showHelp();
            break;
            
        default:
            echo "Unknown command: {$command}\n";
            echo "Run 'monitor.php help' for usage information.\n";
            exit(1);
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (isset($options['verbose'])) {
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}

/**
 * Handle health command
 */
function handleHealth(HealthMonitor $monitor, array $options): void
{
    if (isset($options['watch'])) {
        $interval = (int)($options['watch'] ?: 5);
        watchHealth($monitor, $interval);
        return;
    }
    
    if (isset($options['history'])) {
        $hours = (int)($options['history'] ?: 24);
        showHealthHistory($monitor, $hours);
        return;
    }
    
    $detailed = isset($options['detailed']);
    $result = $monitor->check($detailed);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         SYSTEM HEALTH CHECK              ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Overall Status: " . formatStatus($result['status']) . "\n";
    echo "Timestamp: " . $result['timestamp'] . "\n";
    echo "Duration: " . $result['duration_ms'] . "ms\n\n";
    
    echo "Component Status:\n";
    echo str_repeat("─", 50) . "\n";
    
    foreach ($result['checks'] as $name => $check) {
        $status = formatStatus($check['status']);
        $message = $check['message'] ?? '';
        echo sprintf("%-20s %s %s\n", $name, $status, $message);
        
        if ($detailed && isset($check['details'])) {
            foreach ($check['details'] as $key => $value) {
                echo sprintf("  %-18s %s\n", $key . ':', formatValue($value));
            }
        }
    }
    
    echo "\n";
}

/**
 * Watch health continuously
 */
function watchHealth(HealthMonitor $monitor, int $interval): void
{
    echo "Watching health (every {$interval}s, Ctrl+C to stop)...\n\n";
    
    while (true) {
        system('clear');
        
        $result = $monitor->check(false);
        
        echo "╔══════════════════════════════════════════╗\n";
        echo "║     HEALTH MONITOR (Live)                ║\n";
        echo "╚══════════════════════════════════════════╝\n\n";
        
        echo "Status: " . formatStatus($result['status']) . " | ";
        echo "Time: " . date('H:i:s') . "\n\n";
        
        foreach ($result['checks'] as $name => $check) {
            echo sprintf("%-20s %s\n", $name, formatStatus($check['status']));
        }
        
        echo "\nPress Ctrl+C to stop...\n";
        
        sleep($interval);
    }
}

/**
 * Show health history
 */
function showHealthHistory(HealthMonitor $monitor, int $hours): void
{
    $history = $monitor->getHistory($hours);
    $trends = $monitor->getTrends($hours);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         HEALTH HISTORY ({$hours}h)               ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Trends:\n";
    echo "  Uptime: " . $trends['uptime_percent'] . "%\n";
    echo "  Degraded: " . $trends['degraded_count'] . " occurrences\n";
    echo "  Unhealthy: " . $trends['unhealthy_count'] . " occurrences\n";
    echo "  Critical: " . $trends['critical_count'] . " occurrences\n";
    
    if ($trends['mtbf'] !== null) {
        echo "  MTBF: " . $trends['mtbf'] . " minutes\n";
    }
    
    if ($trends['mttr'] !== null) {
        echo "  MTTR: " . $trends['mttr'] . " minutes\n";
    }
    
    echo "\nRecent History:\n";
    echo str_repeat("─", 50) . "\n";
    
    foreach (array_slice($history, -20) as $entry) {
        $timestamp = date('Y-m-d H:i:s', $entry['timestamp']);
        $status = formatStatus($entry['status']);
        echo "{$timestamp}  {$status}\n";
    }
    
    echo "\n";
}

/**
 * Handle performance command
 */
function handlePerformance(PerformanceProfiler $profiler, array $options): void
{
    $range = $options['range'] ?? '1h';
    $dashboard = $profiler->getDashboard($range);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║      PERFORMANCE DASHBOARD ({$range})         ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    $summary = $dashboard['summary'];
    
    echo "Request Summary:\n";
    echo "  Total Requests: " . $summary['requests'] . "\n";
    echo "  Avg Duration: " . $summary['avg_duration_ms'] . "ms\n";
    echo "  Median Duration: " . $summary['median_duration_ms'] . "ms\n";
    echo "  P95 Duration: " . $summary['p95_duration_ms'] . "ms\n";
    echo "  Max Duration: " . $summary['max_duration_ms'] . "ms\n";
    echo "  Slow Requests: " . $summary['slow_requests'] . " (" . $summary['slow_request_rate'] . "%)\n\n";
    
    echo "Query Summary:\n";
    echo "  Total Queries: " . $summary['total_queries'] . "\n";
    echo "  Slow Queries: " . $summary['slow_queries'] . " (" . $summary['slow_query_rate'] . "%)\n\n";
    
    if (!empty($dashboard['bottlenecks'])) {
        echo "Bottlenecks Detected:\n";
        foreach ($dashboard['bottlenecks'] as $bottleneck) {
            $severity = strtoupper($bottleneck['severity']);
            echo "  [{$severity}] " . $bottleneck['message'] . "\n";
            echo "    → " . $bottleneck['recommendation'] . "\n";
        }
        echo "\n";
    }
    
    if (isset($options['slow-requests']) && !empty($dashboard['slow_requests'])) {
        echo "Slow Requests (Top 10):\n";
        echo str_repeat("─", 70) . "\n";
        
        foreach (array_slice($dashboard['slow_requests'], 0, 10) as $req) {
            echo sprintf("%s  %6.2fms  %5.2fMB  %3d queries\n",
                $req['datetime'],
                $req['duration_ms'],
                $req['memory_mb'],
                $req['queries']
            );
        }
        echo "\n";
    }
}

/**
 * Handle logs command
 */
function handleLogs(LogAggregator $aggregator, array $options): void
{
    if (isset($options['tail'])) {
        $lines = (int)($options['tail'] ?: 100);
        handleLogTail($aggregator, $lines);
        return;
    }
    
    if (isset($options['stats'])) {
        handleLogStats($aggregator, $options);
        return;
    }
    
    if (isset($options['search'])) {
        handleLogSearch($aggregator, $options);
        return;
    }
    
    echo "Please specify an operation: --tail, --stats, or --search\n";
    exit(1);
}

/**
 * Handle log tail
 */
function handleLogTail(LogAggregator $aggregator, int $lines): void
{
    $result = $aggregator->tail($lines);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         LOG TAIL (Last {$lines} lines)       ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    foreach ($result['entries'] as $entry) {
        $timestamp = $entry['timestamp'] ?? '';
        $severity = strtoupper($entry['severity'] ?? 'INFO');
        $message = $entry['message'] ?? '';
        $component = $entry['neuro']['component'] ?? '';
        
        echo sprintf("[%s] %-8s %-15s %s\n",
            $timestamp,
            $severity,
            $component,
            $message
        );
    }
    
    echo "\n";
}

/**
 * Handle log statistics
 */
function handleLogStats(LogAggregator $aggregator, array $options): void
{
    $days = (int)($options['days'] ?? 7);
    
    $stats = $aggregator->getStats([
        'start_date' => date('Y-m-d', strtotime("-{$days} days")),
        'end_date' => date('Y-m-d'),
    ]);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         LOG STATISTICS ({$days}d)             ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Total Entries: " . number_format($stats['total_entries']) . "\n\n";
    
    echo "By Severity:\n";
    foreach ($stats['by_severity'] as $severity => $count) {
        $percent = ($count / max(1, $stats['total_entries'])) * 100;
        echo sprintf("  %-10s %10s (%5.2f%%)\n", ucfirst($severity), number_format($count), $percent);
    }
    echo "\n";
    
    echo "Top Components:\n";
    $i = 0;
    foreach ($stats['by_component'] as $component => $count) {
        if ($i++ >= 10) break;
        echo sprintf("  %-20s %10s\n", $component, number_format($count));
    }
    echo "\n";
    
    if (!empty($stats['top_errors'])) {
        echo "Top Errors:\n";
        foreach (array_slice($stats['top_errors'], 0, 5) as $error) {
            echo sprintf("  [%d] %s\n", $error['count'], substr($error['message'], 0, 70));
        }
        echo "\n";
    }
}

/**
 * Handle log search
 */
function handleLogSearch(LogAggregator $aggregator, array $options): void
{
    $filters = [
        'query' => $options['search'],
        'severity' => $options['severity'] ?? null,
        'component' => $options['component'] ?? null,
        'page' => 1,
        'per_page' => (int)($options['limit'] ?? 50),
    ];
    
    $result = $aggregator->search($filters);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         LOG SEARCH RESULTS               ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Query: " . $filters['query'] . "\n";
    echo "Results: " . $result['pagination']['total'] . " (" . $result['duration_ms'] . "ms)\n\n";
    
    foreach ($result['entries'] as $entry) {
        $timestamp = $entry['timestamp'] ?? '';
        $severity = strtoupper($entry['severity'] ?? 'INFO');
        $message = $entry['message'] ?? '';
        
        echo "─────────────────────────────────────────\n";
        echo "[{$timestamp}] {$severity}\n";
        echo $message . "\n";
    }
    
    echo "\n";
}

/**
 * Handle alerts command
 */
function handleAlerts(AlertManager $manager, array $options): void
{
    if (isset($options['send'])) {
        handleAlertSend($manager, $options);
        return;
    }
    
    $days = (int)($options['days'] ?? 7);
    $stats = $manager->getStats($days);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         ALERT STATISTICS ({$days}d)           ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Total Alerts: " . $stats['total'] . "\n\n";
    
    echo "By Severity:\n";
    foreach ($stats['by_severity'] as $severity => $count) {
        echo sprintf("  %-10s %10s\n", ucfirst($severity), $count);
    }
    echo "\n";
    
    echo "By Day:\n";
    foreach ($stats['by_day'] as $day) {
        echo sprintf("  %s  %5d total  %5d critical  %5d errors\n",
            $day['date'],
            $day['total'],
            $day['by_severity']['critical'],
            $day['by_severity']['error']
        );
    }
    echo "\n";
}

/**
 * Handle alert send
 */
function handleAlertSend(AlertManager $manager, array $options): void
{
    $title = $options['title'] ?? 'Test Alert';
    $message = $options['message'] ?? 'This is a test alert from monitor CLI';
    $severity = $options['severity'] ?? 'info';
    
    echo "Sending test alert...\n";
    
    $result = $manager->send($title, $message, $severity);
    
    if (isset($result['status']) && $result['status'] === 'rate_limited') {
        echo "Alert was rate limited.\n";
    } elseif (isset($result['status']) && $result['status'] === 'deduplicated') {
        echo "Alert was deduplicated.\n";
    } else {
        echo "Alert sent successfully!\n\n";
        
        foreach ($result as $channel => $channelResult) {
            $status = $channelResult['success'] ? '✓' : '✗';
            echo "  {$channel}: {$status}\n";
        }
    }
    
    echo "\n";
}

/**
 * Handle overview command
 */
function handleOverview(
    HealthMonitor $healthMonitor,
    PerformanceProfiler $profiler,
    LogAggregator $logAggregator,
    AlertManager $alertManager,
    array $options
): void {
    if (isset($options['watch'])) {
        $interval = (int)($options['watch'] ?: 5);
        watchOverview($healthMonitor, $profiler, $logAggregator, $alertManager, $interval);
        return;
    }
    
    displayOverview($healthMonitor, $profiler, $logAggregator, $alertManager);
}

/**
 * Display overview dashboard
 */
function displayOverview(
    HealthMonitor $healthMonitor,
    PerformanceProfiler $profiler,
    LogAggregator $logAggregator,
    AlertManager $alertManager
): void {
    $health = $healthMonitor->check(false);
    $perfMetrics = $profiler->getSystemMetrics();
    $alertStats = $alertManager->getStats(1);
    $logStats = $logAggregator->getStats(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')]);
    
    echo "╔══════════════════════════════════════════╗\n";
    echo "║         SYSTEM OVERVIEW                  ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    echo "Health: " . formatStatus($health['status']) . " | ";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "Performance:\n";
    echo "  Memory: " . $perfMetrics['memory']['current_mb'] . " MB\n";
    echo "  Peak: " . $perfMetrics['memory']['peak_mb'] . " MB\n";
    echo "  Load (1m): " . ($perfMetrics['cpu']['load_1m'] ?? 'N/A') . "\n\n";
    
    echo "Today's Activity:\n";
    echo "  Alerts: " . ($alertStats['total'] ?? 0) . "\n";
    echo "  Log Entries: " . ($logStats['total_entries'] ?? 0) . "\n";
    echo "  Errors: " . (($logStats['by_severity']['error'] ?? 0) + ($logStats['by_severity']['critical'] ?? 0)) . "\n\n";
}

/**
 * Watch overview continuously
 */
function watchOverview(
    HealthMonitor $healthMonitor,
    PerformanceProfiler $profiler,
    LogAggregator $logAggregator,
    AlertManager $alertManager,
    int $interval
): void {
    echo "Watching overview (every {$interval}s, Ctrl+C to stop)...\n\n";
    
    while (true) {
        system('clear');
        displayOverview($healthMonitor, $profiler, $logAggregator, $alertManager);
        echo "Press Ctrl+C to stop...\n";
        sleep($interval);
    }
}

/**
 * Format status with color
 */
function formatStatus(string $status): string
{
    $colors = [
        'healthy' => "\033[32m✓ HEALTHY\033[0m",
        'degraded' => "\033[33m⚠ DEGRADED\033[0m",
        'unhealthy' => "\033[31m✗ UNHEALTHY\033[0m",
        'critical' => "\033[1;31m✗✗ CRITICAL\033[0m",
    ];
    
    return $colors[$status] ?? $status;
}

/**
 * Format value for display
 */
function formatValue($value): string
{
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    
    if (is_array($value)) {
        return json_encode($value);
    }
    
    return (string)$value;
}

/**
 * Parse command line options
 */
function parseOptions(array $args): array
{
    $options = [];
    
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        
        if (strpos($arg, '--') === 0) {
            $key = substr($arg, 2);
            $value = true;
            
            if (isset($args[$i + 1]) && strpos($args[$i + 1], '--') !== 0) {
                $value = $args[++$i];
            }
            
            $options[$key] = $value;
        }
    }
    
    return $options;
}

/**
 * Show help
 */
function showHelp(): void
{
    echo <<<HELP
System Monitoring CLI Tool

Usage: bin/monitor.php <command> [options]

Commands:
  health                    Check system health
  health --watch [N]        Watch health (refresh every N seconds, default: 5)
  health --history N        Show health history for N hours
  health --detailed         Show detailed health check results
  
  performance               Show performance metrics
  performance --range T     Show metrics for time range (5m, 1h, 6h, 24h, 7d)
  performance --slow-requests  Show slow request details
  
  logs --tail N             Tail last N log lines
  logs --stats              Show log statistics
  logs --search "query"     Search logs
  logs --severity LEVEL     Filter by severity (debug, info, warning, error, critical)
  
  alerts                    Show alert statistics
  alerts --send             Send test alert
  alerts --title "Title"    Alert title
  alerts --message "Msg"    Alert message
  alerts --severity LEVEL   Alert severity
  
  overview                  Show system overview dashboard
  overview --watch [N]      Watch overview (refresh every N seconds)
  
  help                      Show this help message

Examples:
  bin/monitor.php health --watch 10
  bin/monitor.php performance --range 24h
  bin/monitor.php logs --tail 50
  bin/monitor.php logs --search "database" --severity error
  bin/monitor.php alerts --send --severity critical
  bin/monitor.php overview --watch

HELP;
}
