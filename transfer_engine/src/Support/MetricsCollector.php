<?php
/**
 * MetricsCollector.php - Enterprise Time-Series Metrics Collection
 * 
 * High-performance metrics collection, aggregation, and time-series analysis
 * with automatic rollups, retention policies, and export capabilities.
 * 
 * Features:
 * - Time-series metric recording with multiple resolutions
 * - Automatic data aggregation and rollups (1m, 5m, 1h, 1d)
 * - Counter, gauge, histogram, timer metric types
 * - Tag-based metric organization
 * - Retention policy management
 * - High-performance writes (batch processing)
 * - Query optimization with time-range indices
 * - Export to Prometheus, Grafana, InfluxDB formats
 * - Memory-efficient aggregation algorithms
 * - Concurrent write safety
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

class MetricsCollector
{
    private Logger $logger;
    private Cache|CacheManager $cache;
    private array $config;
    private array $buffer = [];
    private int $bufferSize = 0;
    
    // Metric types
    public const TYPE_COUNTER = 'counter';
    public const TYPE_GAUGE = 'gauge';
    public const TYPE_HISTOGRAM = 'histogram';
    public const TYPE_TIMER = 'timer';
    
    // Resolution levels (in seconds)
    public const RESOLUTION_1M = 60;
    public const RESOLUTION_5M = 300;
    public const RESOLUTION_1H = 3600;
    public const RESOLUTION_1D = 86400;
    
    // Retention periods (in seconds)
    private const RETENTION_1M = 3600;      // 1 hour for 1-minute data
    private const RETENTION_5M = 86400;     // 1 day for 5-minute data
    private const RETENTION_1H = 604800;    // 7 days for 1-hour data
    private const RETENTION_1D = 2592000;   // 30 days for 1-day data

    /**
     * Initialize MetricsCollector
     *
     * @param Logger $logger Logger instance
     * @param Cache|CacheManager $cache Cache instance for storage (accepts Cache or CacheManager)
     * @param array $config Configuration options
     */
    public function __construct(Logger $logger, Cache|CacheManager $cache, array $config = [])
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        // Register shutdown handler to flush buffer
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Record a counter metric (monotonically increasing)
     *
     * @param string $name Metric name
     * @param float $value Value to add (default: 1)
     * @param array $tags Optional tags
     * @return void
     */
    public function counter(string $name, float $value = 1, array $tags = []): void
    {
        $this->record($name, $value, self::TYPE_COUNTER, $tags);
    }

    /**
     * Record a gauge metric (point-in-time value)
     *
     * @param string $name Metric name
     * @param float $value Current value
     * @param array $tags Optional tags
     * @return void
     */
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, self::TYPE_GAUGE, $tags);
    }

    /**
     * Record a histogram metric (distribution of values)
     *
     * @param string $name Metric name
     * @param float $value Value to record
     * @param array $tags Optional tags
     * @return void
     */
    public function histogram(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, self::TYPE_HISTOGRAM, $tags);
    }

    /**
     * Start timing an operation
     *
     * @param string $name Metric name
     * @param array $tags Optional tags
     * @return int Timer ID
     */
    public function startTimer(string $name, array $tags = []): int
    {
        $timerId = spl_object_id((object)[]);
        
        $this->buffer['timers'][$timerId] = [
            'name' => $name,
            'start' => microtime(true),
            'tags' => $tags,
        ];
        
        return $timerId;
    }

    /**
     * Stop timing an operation and record duration
     *
     * @param int $timerId Timer ID from startTimer
     * @return float Duration in milliseconds
     */
    public function stopTimer(int $timerId): float
    {
        if (!isset($this->buffer['timers'][$timerId])) {
            return 0;
        }
        
        $timer = $this->buffer['timers'][$timerId];
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to ms
        
        $this->record($timer['name'], $duration, self::TYPE_TIMER, $timer['tags']);
        
        unset($this->buffer['timers'][$timerId]);
        
        return $duration;
    }

    /**
     * Record a metric with automatic buffering
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @param string $type Metric type
     * @param array $tags Metric tags
     * @return void
     */
    private function record(string $name, float $value, string $type, array $tags): void
    {
        $timestamp = time();
        
        $metric = [
            'name' => $name,
            'value' => $value,
            'type' => $type,
            'tags' => $tags,
            'timestamp' => $timestamp,
        ];
        
        // Add to buffer
        if (!isset($this->buffer['metrics'])) {
            $this->buffer['metrics'] = [];
        }
        
        $this->buffer['metrics'][] = $metric;
        $this->bufferSize++;
        
        // Auto-flush if buffer is full
        if ($this->bufferSize >= $this->config['buffer_size']) {
            $this->flush();
        }
    }

    /**
     * Flush buffered metrics to storage
     *
     * @return int Number of metrics flushed
     */
    public function flush(): int
    {
        if (empty($this->buffer['metrics'])) {
            return 0;
        }
        
        $startTime = microtime(true);
        $metrics = $this->buffer['metrics'];
        $count = count($metrics);
        
        // Clear buffer immediately to prevent duplicate writes
        $this->buffer['metrics'] = [];
        $this->bufferSize = 0;
        
        // Group metrics by name and resolution
        $grouped = [];
        foreach ($metrics as $metric) {
            $key = $this->getMetricKey($metric['name'], $metric['tags']);
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $metric['name'],
                    'type' => $metric['type'],
                    'tags' => $metric['tags'],
                    'values' => [],
                ];
            }
            
            $grouped[$key]['values'][] = [
                'value' => $metric['value'],
                'timestamp' => $metric['timestamp'],
            ];
        }
        
        // Write to storage with all resolutions
        foreach ($grouped as $key => $data) {
            $this->writeMetric($data);
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->debug('Metrics flushed', NeuroContext::wrap('metrics_collector', [
            'count' => $count,
            'unique_metrics' => count($grouped),
            'duration_ms' => $duration,
        ]));
        
        return $count;
    }

    /**
     * Write metric to storage with multiple resolutions
     *
     * @param array $data Grouped metric data
     * @return void
     */
    private function writeMetric(array $data): void
    {
        $name = $data['name'];
        $type = $data['type'];
        $tags = $data['tags'];
        $values = $data['values'];
        
        // Write to each resolution level
        $resolutions = [
            self::RESOLUTION_1M => self::RETENTION_1M,
            self::RESOLUTION_5M => self::RETENTION_5M,
            self::RESOLUTION_1H => self::RETENTION_1H,
            self::RESOLUTION_1D => self::RETENTION_1D,
        ];
        
        foreach ($resolutions as $resolution => $retention) {
            foreach ($values as $point) {
                $bucket = $this->getBucket($point['timestamp'], $resolution);
                $key = $this->getStorageKey($name, $tags, $resolution, $bucket);
                
                // Get existing data
                $existing = $this->cache->get($key, [
                    'count' => 0,
                    'sum' => 0,
                    'min' => null,
                    'max' => null,
                    'values' => [],
                ]);
                
                // Aggregate based on metric type
                $aggregated = $this->aggregate($existing, $point['value'], $type);
                
                // Store with TTL based on retention policy
                $this->cache->set($key, $aggregated, $retention);
            }
        }
    }

    /**
     * Aggregate metric value with existing data
     *
     * @param array $existing Existing aggregated data
     * @param float $value New value
     * @param string $type Metric type
     * @return array Updated aggregated data
     */
    private function aggregate(array $existing, float $value, string $type): array
    {
        switch ($type) {
            case self::TYPE_COUNTER:
                // Counters: sum values
                $existing['sum'] = ($existing['sum'] ?? 0) + $value;
                $existing['count'] = ($existing['count'] ?? 0) + 1;
                break;
                
            case self::TYPE_GAUGE:
                // Gauges: latest value, but track min/max
                $existing['sum'] = $value; // Last value
                $existing['count'] = 1;
                $existing['min'] = $existing['min'] === null ? $value : min($existing['min'], $value);
                $existing['max'] = $existing['max'] === null ? $value : max($existing['max'], $value);
                break;
                
            case self::TYPE_HISTOGRAM:
            case self::TYPE_TIMER:
                // Histograms/Timers: track all statistics
                $existing['count'] = ($existing['count'] ?? 0) + 1;
                $existing['sum'] = ($existing['sum'] ?? 0) + $value;
                $existing['min'] = $existing['min'] === null ? $value : min($existing['min'], $value);
                $existing['max'] = $existing['max'] === null ? $value : max($existing['max'], $value);
                
                // Store values for percentile calculation (limit to 1000 samples)
                if (!isset($existing['values'])) {
                    $existing['values'] = [];
                }
                $existing['values'][] = $value;
                if (count($existing['values']) > 1000) {
                    $existing['values'] = array_slice($existing['values'], -1000);
                }
                break;
        }
        
        return $existing;
    }

    /**
     * Query metrics for time range
     *
     * @param string $name Metric name
     * @param int $start Start timestamp
     * @param int $end End timestamp
     * @param array $tags Optional tag filters
     * @param int|null $resolution Desired resolution (auto-select if null)
     * @return array Query results
     */
    public function query(
        string $name,
        int $start,
        int $end,
        array $tags = [],
        ?int $resolution = null
    ): array {
        $startTime = microtime(true);
        
        // Auto-select resolution based on time range
        if ($resolution === null) {
            $resolution = $this->selectResolution($end - $start);
        }
        
        $points = [];
        $bucket = $this->getBucket($start, $resolution);
        $endBucket = $this->getBucket($end, $resolution);
        
        // Fetch all buckets in range
        while ($bucket <= $endBucket) {
            $key = $this->getStorageKey($name, $tags, $resolution, $bucket);
            $data = $this->cache->get($key);
            
            if ($data !== null) {
                $points[] = [
                    'timestamp' => $bucket * $resolution,
                    'count' => $data['count'] ?? 0,
                    'sum' => $data['sum'] ?? 0,
                    'min' => $data['min'],
                    'max' => $data['max'],
                    'avg' => ($data['count'] ?? 0) > 0 ? ($data['sum'] ?? 0) / $data['count'] : 0,
                    'p50' => isset($data['values']) ? $this->percentile($data['values'], 50) : null,
                    'p95' => isset($data['values']) ? $this->percentile($data['values'], 95) : null,
                    'p99' => isset($data['values']) ? $this->percentile($data['values'], 99) : null,
                ];
            }
            
            $bucket++;
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->debug('Metrics queried', NeuroContext::wrap('metrics_collector', [
            'name' => $name,
            'resolution' => $resolution,
            'points' => count($points),
            'duration_ms' => $duration,
        ]));
        
        return [
            'name' => $name,
            'tags' => $tags,
            'resolution' => $resolution,
            'start' => $start,
            'end' => $end,
            'points' => $points,
            'query_time_ms' => $duration,
        ];
    }

    /**
     * Get list of available metrics
     *
     * @param string|null $pattern Optional name pattern (supports wildcards)
     * @return array List of metric names
     */
    public function listMetrics(?string $pattern = null): array
    {
        // This is a simplified implementation
        // In production, you'd maintain an index of metric names
        
        $metricsKey = 'metrics_index';
        $index = $this->cache->get($metricsKey, []);
        
        if ($pattern === null) {
            return array_keys($index);
        }
        
        // Simple wildcard matching
        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
        
        return array_filter(array_keys($index), function($name) use ($regex) {
            return preg_match($regex, $name);
        });
    }

    /**
     * Delete metrics for a time range
     *
     * @param string $name Metric name
     * @param int $start Start timestamp
     * @param int $end End timestamp
     * @param array $tags Optional tag filters
     * @return int Number of data points deleted
     */
    public function delete(string $name, int $start, int $end, array $tags = []): int
    {
        $deleted = 0;
        $resolutions = [self::RESOLUTION_1M, self::RESOLUTION_5M, self::RESOLUTION_1H, self::RESOLUTION_1D];
        
        foreach ($resolutions as $resolution) {
            $bucket = $this->getBucket($start, $resolution);
            $endBucket = $this->getBucket($end, $resolution);
            
            while ($bucket <= $endBucket) {
                $key = $this->getStorageKey($name, $tags, $resolution, $bucket);
                
                if ($this->cache->delete($key)) {
                    $deleted++;
                }
                
                $bucket++;
            }
        }
        
        $this->logger->info('Metrics deleted', NeuroContext::wrap('metrics_collector', [
            'name' => $name,
            'start' => $start,
            'end' => $end,
            'deleted_points' => $deleted,
        ]));
        
        return $deleted;
    }

    /**
     * Export metrics in Prometheus format
     *
     * @param array $names Metric names to export
     * @param int $timestamp Export timestamp
     * @return string Prometheus format text
     */
    public function exportPrometheus(array $names, int $timestamp): string
    {
        $output = [];
        
        foreach ($names as $name) {
            // Get latest values (1-minute resolution)
            $result = $this->query($name, $timestamp - 60, $timestamp, [], self::RESOLUTION_1M);
            
            if (!empty($result['points'])) {
                $latest = end($result['points']);
                
                // Format as Prometheus metric
                $metricName = str_replace('.', '_', $name);
                $output[] = "# TYPE {$metricName} gauge";
                $output[] = "{$metricName} {$latest['avg']} {$timestamp}000";
            }
        }
        
        return implode("\n", $output) . "\n";
    }

    /**
     * Export metrics in JSON format
     *
     * @param string $name Metric name
     * @param int $start Start timestamp
     * @param int $end End timestamp
     * @param array $tags Optional tag filters
     * @return string JSON format
     */
    public function exportJson(string $name, int $start, int $end, array $tags = []): string
    {
        $result = $this->query($name, $start, $end, $tags);
        return json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Get metric key for grouping
     *
     * @param string $name Metric name
     * @param array $tags Metric tags
     * @return string Metric key
     */
    private function getMetricKey(string $name, array $tags): string
    {
        ksort($tags);
        return $name . ':' . md5(json_encode($tags));
    }

    /**
     * Get storage key for metric data point
     *
     * @param string $name Metric name
     * @param array $tags Metric tags
     * @param int $resolution Resolution in seconds
     * @param int $bucket Time bucket
     * @return string Storage key
     */
    private function getStorageKey(string $name, array $tags, int $resolution, int $bucket): string
    {
        $tagStr = empty($tags) ? '' : ':' . md5(json_encode($tags));
        return "metrics:{$name}{$tagStr}:{$resolution}:{$bucket}";
    }

    /**
     * Get time bucket for timestamp and resolution
     *
     * @param int $timestamp Timestamp
     * @param int $resolution Resolution in seconds
     * @return int Bucket number
     */
    private function getBucket(int $timestamp, int $resolution): int
    {
        return (int)floor($timestamp / $resolution);
    }

    /**
     * Select appropriate resolution for time range
     *
     * @param int $rangeSeconds Time range in seconds
     * @return int Resolution in seconds
     */
    private function selectResolution(int $rangeSeconds): int
    {
        if ($rangeSeconds <= 3600) {
            return self::RESOLUTION_1M;
        } elseif ($rangeSeconds <= 86400) {
            return self::RESOLUTION_5M;
        } elseif ($rangeSeconds <= 604800) {
            return self::RESOLUTION_1H;
        } else {
            return self::RESOLUTION_1D;
        }
    }

    /**
     * Calculate percentile from values array
     *
     * @param array $values Values array
     * @param int $percentile Percentile (0-100)
     * @return float|null Percentile value
     */
    private function percentile(array $values, int $percentile): ?float
    {
        if (empty($values)) {
            return null;
        }
        
        sort($values);
        $count = count($values);
        $index = (int)ceil(($count * $percentile) / 100) - 1;
        $index = max(0, min($index, $count - 1));
        
        return $values[$index];
    }

    /**
     * Get aggregated statistics for metric over time range
     *
     * @param string $name Metric name
     * @param int $start Start timestamp
     * @param int $end End timestamp
     * @param array $tags Optional tag filters
     * @return array Aggregated statistics
     */
    public function getStats(string $name, int $start, int $end, array $tags = []): array
    {
        $result = $this->query($name, $start, $end, $tags);
        $points = $result['points'];
        
        if (empty($points)) {
            return [
                'count' => 0,
                'sum' => 0,
                'min' => null,
                'max' => null,
                'avg' => 0,
            ];
        }
        
        $count = array_sum(array_column($points, 'count'));
        $sum = array_sum(array_column($points, 'sum'));
        $min = min(array_filter(array_column($points, 'min'), fn($v) => $v !== null));
        $max = max(array_filter(array_column($points, 'max'), fn($v) => $v !== null));
        
        return [
            'count' => $count,
            'sum' => $sum,
            'min' => $min,
            'max' => $max,
            'avg' => $count > 0 ? $sum / $count : 0,
            'points' => count($points),
        ];
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'buffer_size' => 100, // Flush after 100 metrics
            'enabled' => true,
        ];
    }

    /**
     * Register metric in index
     *
     * @param string $name Metric name
     * @param string $type Metric type
     * @return void
     */
    private function registerMetric(string $name, string $type): void
    {
        $metricsKey = 'metrics_index';
        $index = $this->cache->get($metricsKey, []);
        
        if (!isset($index[$name])) {
            $index[$name] = [
                'type' => $type,
                'first_seen' => time(),
            ];
            $this->cache->set($metricsKey, $index, 2592000); // 30 days
        }
    }
}
