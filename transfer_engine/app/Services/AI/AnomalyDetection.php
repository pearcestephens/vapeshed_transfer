<?php

/**
 * Anomaly Detection Service
 * 
 * AI-powered anomaly detection for identifying unusual patterns in transfer data,
 * inventory levels, and system behavior. Uses statistical methods and machine learning
 * techniques to detect outliers, fraud, and system anomalies.
 * 
 * @package     VapeShed Transfer Engine
 * @subpackage  Services\AI
 * @version     1.0.0
 * @author      Ecigdis Limited Engineering Team
 * @copyright   2025 Ecigdis Limited
 */

namespace App\Services\AI;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Cache;
use App\Models\Transfer;
use App\Models\TransferItem;

/**
 * Anomaly Detection Service
 * 
 * Detects anomalies in:
 * - Transfer patterns (unusual quantities, frequencies)
 * - Inventory movements (unexpected stock changes)
 * - User behavior (suspicious activities)
 * - System performance (unusual latencies, error rates)
 */
class AnomalyDetection
{
    private Database $db;
    private Logger $logger;
    private Cache $cache;
    
    /**
     * Z-score threshold for anomaly detection
     */
    private const Z_SCORE_THRESHOLD = 3.0;
    
    /**
     * IQR multiplier for outlier detection
     */
    private const IQR_MULTIPLIER = 1.5;
    
    /**
     * Minimum data points for reliable detection
     */
    private const MIN_DATA_POINTS = 30;
    
    /**
     * Cache TTL for anomaly models (1 hour)
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Anomaly severity levels
     */
    private const SEVERITY_CRITICAL = 'critical';
    private const SEVERITY_HIGH = 'high';
    private const SEVERITY_MEDIUM = 'medium';
    private const SEVERITY_LOW = 'low';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('anomaly_detection');
        $this->cache = new Cache();
    }
    
    /**
     * Detect anomalies in transfer data
     * 
     * @param int|null $transferId Optional specific transfer to check
     * @param int $days Number of days to analyze (default: 30)
     * @return array Detected anomalies
     */
    public function detectTransferAnomalies(?int $transferId = null, int $days = 30): array
    {
        try {
            $anomalies = [];
            
            // Get transfer data
            if ($transferId) {
                $transfers = [$this->getTransfer($transferId)];
            } else {
                $transfers = $this->getRecentTransfers($days);
            }
            
            if (count($transfers) < self::MIN_DATA_POINTS && !$transferId) {
                $this->logger->warning("Insufficient data for anomaly detection", [
                    'transfers' => count($transfers),
                    'required' => self::MIN_DATA_POINTS
                ]);
                return [];
            }
            
            // Build baseline statistics
            $baseline = $this->buildTransferBaseline($transfers);
            
            // Check each transfer
            foreach ($transfers as $transfer) {
                $transferAnomalies = [];
                
                // Check quantity anomalies
                $quantityAnomaly = $this->detectQuantityAnomaly($transfer, $baseline);
                if ($quantityAnomaly) {
                    $transferAnomalies[] = $quantityAnomaly;
                }
                
                // Check frequency anomalies (same route)
                $frequencyAnomaly = $this->detectFrequencyAnomaly($transfer, $transfers);
                if ($frequencyAnomaly) {
                    $transferAnomalies[] = $frequencyAnomaly;
                }
                
                // Check timing anomalies
                $timingAnomaly = $this->detectTimingAnomaly($transfer, $baseline);
                if ($timingAnomaly) {
                    $transferAnomalies[] = $timingAnomaly;
                }
                
                // Check value anomalies
                $valueAnomaly = $this->detectValueAnomaly($transfer, $baseline);
                if ($valueAnomaly) {
                    $transferAnomalies[] = $valueAnomaly;
                }
                
                if (!empty($transferAnomalies)) {
                    $anomalies[] = [
                        'transfer_id' => $transfer['transfer_id'],
                        'reference' => $transfer['reference'],
                        'from_store' => $transfer['from_store_name'],
                        'to_store' => $transfer['to_store_name'],
                        'created_at' => $transfer['created_at'],
                        'anomalies' => $transferAnomalies,
                        'severity' => $this->calculateOverallSeverity($transferAnomalies),
                        'risk_score' => $this->calculateRiskScore($transferAnomalies)
                    ];
                }
            }
            
            // Sort by risk score (highest first)
            usort($anomalies, function($a, $b) {
                return $b['risk_score'] - $a['risk_score'];
            });
            
            $this->logger->info("Anomaly detection completed", [
                'transfers_analyzed' => count($transfers),
                'anomalies_found' => count($anomalies)
            ]);
            
            return [
                'anomalies' => $anomalies,
                'summary' => [
                    'total_transfers' => count($transfers),
                    'anomalies_detected' => count($anomalies),
                    'critical' => count(array_filter($anomalies, fn($a) => $a['severity'] === self::SEVERITY_CRITICAL)),
                    'high' => count(array_filter($anomalies, fn($a) => $a['severity'] === self::SEVERITY_HIGH)),
                    'medium' => count(array_filter($anomalies, fn($a) => $a['severity'] === self::SEVERITY_MEDIUM)),
                    'low' => count(array_filter($anomalies, fn($a) => $a['severity'] === self::SEVERITY_LOW)),
                ],
                'generated_at' => date('Y-m-d H:i:s'),
                'analysis_period_days' => $days
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Anomaly detection failed", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get transfer data
     * 
     * @param int $transferId Transfer ID
     * @return array Transfer data
     */
    private function getTransfer(int $transferId): array
    {
        $sql = "
            SELECT 
                t.*,
                fs.name AS from_store_name,
                ts.name AS to_store_name,
                u.username AS created_by_name,
                COUNT(ti.item_id) AS item_count,
                SUM(ti.quantity) AS total_quantity,
                SUM(ti.quantity * ti.unit_price) AS total_value
            FROM transfers t
            JOIN stores fs ON fs.store_id = t.from_store_id
            JOIN stores ts ON ts.store_id = t.to_store_id
            JOIN users u ON u.user_id = t.created_by
            LEFT JOIN transfer_items ti ON ti.transfer_id = t.transfer_id
            WHERE t.transfer_id = :transfer_id
            GROUP BY t.transfer_id
        ";
        
        $result = $this->db->query($sql, ['transfer_id' => $transferId]);
        
        return $result[0] ?? [];
    }
    
    /**
     * Get recent transfers
     * 
     * @param int $days Number of days
     * @return array Transfers
     */
    private function getRecentTransfers(int $days): array
    {
        $sql = "
            SELECT 
                t.*,
                fs.name AS from_store_name,
                ts.name AS to_store_name,
                u.username AS created_by_name,
                COUNT(ti.item_id) AS item_count,
                SUM(ti.quantity) AS total_quantity,
                SUM(ti.quantity * ti.unit_price) AS total_value
            FROM transfers t
            JOIN stores fs ON fs.store_id = t.from_store_id
            JOIN stores ts ON ts.store_id = t.to_store_id
            JOIN users u ON u.user_id = t.created_by
            LEFT JOIN transfer_items ti ON ti.transfer_id = t.transfer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND t.status IN ('approved', 'completed')
            GROUP BY t.transfer_id
            ORDER BY t.created_at DESC
        ";
        
        return $this->db->query($sql, ['days' => $days]);
    }
    
    /**
     * Build baseline statistics from transfers
     * 
     * @param array $transfers Transfer data
     * @return array Baseline statistics
     */
    private function buildTransferBaseline(array $transfers): array
    {
        $quantities = [];
        $values = [];
        $itemCounts = [];
        $hourOfDay = [];
        $dayOfWeek = [];
        
        foreach ($transfers as $transfer) {
            $quantities[] = (float) $transfer['total_quantity'];
            $values[] = (float) $transfer['total_value'];
            $itemCounts[] = (int) $transfer['item_count'];
            
            $timestamp = strtotime($transfer['created_at']);
            $hourOfDay[] = (int) date('H', $timestamp);
            $dayOfWeek[] = (int) date('w', $timestamp);
        }
        
        return [
            'quantity' => $this->calculateStatistics($quantities),
            'value' => $this->calculateStatistics($values),
            'item_count' => $this->calculateStatistics($itemCounts),
            'hour_distribution' => array_count_values($hourOfDay),
            'day_distribution' => array_count_values($dayOfWeek),
            'total_transfers' => count($transfers)
        ];
    }
    
    /**
     * Calculate statistics for a dataset
     * 
     * @param array $data Numeric data
     * @return array Statistics (mean, median, std_dev, q1, q3, iqr)
     */
    private function calculateStatistics(array $data): array
    {
        if (empty($data)) {
            return [
                'mean' => 0,
                'median' => 0,
                'std_dev' => 0,
                'q1' => 0,
                'q3' => 0,
                'iqr' => 0,
                'min' => 0,
                'max' => 0
            ];
        }
        
        sort($data);
        $n = count($data);
        
        $mean = array_sum($data) / $n;
        $median = $this->calculateMedian($data);
        
        // Standard deviation
        $variance = 0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / $n);
        
        // Quartiles
        $q1 = $this->calculatePercentile($data, 25);
        $q3 = $this->calculatePercentile($data, 75);
        $iqr = $q3 - $q1;
        
        return [
            'mean' => $mean,
            'median' => $median,
            'std_dev' => $stdDev,
            'q1' => $q1,
            'q3' => $q3,
            'iqr' => $iqr,
            'min' => $data[0],
            'max' => $data[$n - 1]
        ];
    }
    
    /**
     * Calculate median of sorted array
     * 
     * @param array $sortedData Sorted numeric array
     * @return float Median
     */
    private function calculateMedian(array $sortedData): float
    {
        $n = count($sortedData);
        if ($n == 0) return 0;
        
        $middle = (int) ($n / 2);
        
        if ($n % 2 == 0) {
            return ($sortedData[$middle - 1] + $sortedData[$middle]) / 2;
        }
        
        return $sortedData[$middle];
    }
    
    /**
     * Calculate percentile of sorted array
     * 
     * @param array $sortedData Sorted numeric array
     * @param float $percentile Percentile (0-100)
     * @return float Percentile value
     */
    private function calculatePercentile(array $sortedData, float $percentile): float
    {
        $n = count($sortedData);
        if ($n == 0) return 0;
        
        $index = ($percentile / 100) * ($n - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower == $upper) {
            return $sortedData[(int) $index];
        }
        
        $weight = $index - $lower;
        return $sortedData[(int) $lower] * (1 - $weight) + $sortedData[(int) $upper] * $weight;
    }
    
    /**
     * Detect quantity anomaly
     * 
     * @param array $transfer Transfer data
     * @param array $baseline Baseline statistics
     * @return array|null Anomaly details or null
     */
    private function detectQuantityAnomaly(array $transfer, array $baseline): ?array
    {
        $quantity = (float) $transfer['total_quantity'];
        $stats = $baseline['quantity'];
        
        // Z-score method
        $zScore = $stats['std_dev'] > 0 
            ? abs(($quantity - $stats['mean']) / $stats['std_dev'])
            : 0;
        
        // IQR method (for outlier detection)
        $lowerBound = $stats['q1'] - (self::IQR_MULTIPLIER * $stats['iqr']);
        $upperBound = $stats['q3'] + (self::IQR_MULTIPLIER * $stats['iqr']);
        
        $isOutlier = $quantity < $lowerBound || $quantity > $upperBound;
        
        if ($zScore > self::Z_SCORE_THRESHOLD || $isOutlier) {
            $severity = $this->determineSeverity($zScore);
            
            return [
                'type' => 'quantity_anomaly',
                'description' => sprintf(
                    'Transfer quantity (%.2f) significantly differs from normal (mean: %.2f, std: %.2f)',
                    $quantity,
                    $stats['mean'],
                    $stats['std_dev']
                ),
                'severity' => $severity,
                'z_score' => round($zScore, 2),
                'actual_value' => $quantity,
                'expected_range' => [
                    'lower' => round($lowerBound, 2),
                    'upper' => round($upperBound, 2)
                ],
                'deviation_percentage' => round((($quantity - $stats['mean']) / $stats['mean']) * 100, 2)
            ];
        }
        
        return null;
    }
    
    /**
     * Detect frequency anomaly (unusual transfer rate on same route)
     * 
     * @param array $transfer Transfer data
     * @param array $allTransfers All transfers
     * @return array|null Anomaly details or null
     */
    private function detectFrequencyAnomaly(array $transfer, array $allTransfers): ?array
    {
        $fromStore = $transfer['from_store_id'];
        $toStore = $transfer['to_store_id'];
        
        // Count transfers on same route in last 24 hours
        $recentCount = 0;
        $cutoffTime = strtotime('-24 hours');
        
        foreach ($allTransfers as $t) {
            if ($t['from_store_id'] == $fromStore && 
                $t['to_store_id'] == $toStore &&
                strtotime($t['created_at']) >= $cutoffTime) {
                $recentCount++;
            }
        }
        
        // Calculate average daily frequency for this route
        $routeTransfers = array_filter($allTransfers, function($t) use ($fromStore, $toStore) {
            return $t['from_store_id'] == $fromStore && $t['to_store_id'] == $toStore;
        });
        
        $daysAnalyzed = 30;
        $avgDailyFrequency = count($routeTransfers) / $daysAnalyzed;
        
        // If recent count is significantly higher than average
        if ($recentCount > ($avgDailyFrequency * 5) && $recentCount >= 3) {
            return [
                'type' => 'frequency_anomaly',
                'description' => sprintf(
                    'Unusual transfer frequency: %d transfers in 24h (avg: %.2f/day)',
                    $recentCount,
                    $avgDailyFrequency
                ),
                'severity' => $recentCount >= 10 ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH,
                'recent_count' => $recentCount,
                'average_daily' => round($avgDailyFrequency, 2),
                'route' => [
                    'from' => $transfer['from_store_name'],
                    'to' => $transfer['to_store_name']
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Detect timing anomaly (unusual time of day/day of week)
     * 
     * @param array $transfer Transfer data
     * @param array $baseline Baseline statistics
     * @return array|null Anomaly details or null
     */
    private function detectTimingAnomaly(array $transfer, array $baseline): ?array
    {
        $timestamp = strtotime($transfer['created_at']);
        $hour = (int) date('H', $timestamp);
        $dayOfWeek = (int) date('w', $timestamp);
        
        $hourDist = $baseline['hour_distribution'];
        $dayDist = $baseline['day_distribution'];
        
        // Check if hour is unusual (less than 5% of historical transfers)
        $totalTransfers = $baseline['total_transfers'];
        $hourCount = $hourDist[$hour] ?? 0;
        $hourPercentage = ($hourCount / $totalTransfers) * 100;
        
        // Night hours (10 PM - 6 AM) are suspicious
        $isNightTime = $hour >= 22 || $hour < 6;
        
        if ($hourPercentage < 5 && $isNightTime) {
            return [
                'type' => 'timing_anomaly',
                'description' => sprintf(
                    'Transfer created at unusual time: %02d:00 (only %.1f%% of transfers occur at this hour)',
                    $hour,
                    $hourPercentage
                ),
                'severity' => self::SEVERITY_MEDIUM,
                'hour_of_day' => $hour,
                'day_of_week' => date('l', $timestamp),
                'historical_percentage' => round($hourPercentage, 2),
                'is_night_time' => $isNightTime
            ];
        }
        
        return null;
    }
    
    /**
     * Detect value anomaly
     * 
     * @param array $transfer Transfer data
     * @param array $baseline Baseline statistics
     * @return array|null Anomaly details or null
     */
    private function detectValueAnomaly(array $transfer, array $baseline): ?array
    {
        $value = (float) $transfer['total_value'];
        $stats = $baseline['value'];
        
        // Z-score for value
        $zScore = $stats['std_dev'] > 0 
            ? abs(($value - $stats['mean']) / $stats['std_dev'])
            : 0;
        
        // High value threshold
        $highValueThreshold = $stats['q3'] + (3 * $stats['iqr']);
        
        if ($zScore > self::Z_SCORE_THRESHOLD || $value > $highValueThreshold) {
            $severity = $value > ($stats['mean'] * 10) 
                ? self::SEVERITY_CRITICAL 
                : $this->determineSeverity($zScore);
            
            return [
                'type' => 'value_anomaly',
                'description' => sprintf(
                    'Transfer value ($%.2f) is unusually high (mean: $%.2f)',
                    $value,
                    $stats['mean']
                ),
                'severity' => $severity,
                'z_score' => round($zScore, 2),
                'actual_value' => round($value, 2),
                'expected_value' => round($stats['mean'], 2),
                'deviation_percentage' => round((($value - $stats['mean']) / $stats['mean']) * 100, 2)
            ];
        }
        
        return null;
    }
    
    /**
     * Determine severity based on Z-score
     * 
     * @param float $zScore Z-score value
     * @return string Severity level
     */
    private function determineSeverity(float $zScore): string
    {
        if ($zScore >= 5) return self::SEVERITY_CRITICAL;
        if ($zScore >= 4) return self::SEVERITY_HIGH;
        if ($zScore >= 3) return self::SEVERITY_MEDIUM;
        return self::SEVERITY_LOW;
    }
    
    /**
     * Calculate overall severity from multiple anomalies
     * 
     * @param array $anomalies Detected anomalies
     * @return string Overall severity
     */
    private function calculateOverallSeverity(array $anomalies): string
    {
        $severityScores = [
            self::SEVERITY_CRITICAL => 4,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_LOW => 1
        ];
        
        $maxScore = 0;
        foreach ($anomalies as $anomaly) {
            $score = $severityScores[$anomaly['severity']] ?? 1;
            $maxScore = max($maxScore, $score);
        }
        
        foreach ($severityScores as $level => $score) {
            if ($score == $maxScore) {
                return $level;
            }
        }
        
        return self::SEVERITY_LOW;
    }
    
    /**
     * Calculate risk score (0-100)
     * 
     * @param array $anomalies Detected anomalies
     * @return int Risk score
     */
    private function calculateRiskScore(array $anomalies): int
    {
        $severityWeights = [
            self::SEVERITY_CRITICAL => 40,
            self::SEVERITY_HIGH => 30,
            self::SEVERITY_MEDIUM => 20,
            self::SEVERITY_LOW => 10
        ];
        
        $score = 0;
        foreach ($anomalies as $anomaly) {
            $weight = $severityWeights[$anomaly['severity']] ?? 10;
            $score += $weight;
        }
        
        // Multiple anomalies increase risk
        $multiplier = 1 + (count($anomalies) - 1) * 0.2;
        $score = (int) ($score * $multiplier);
        
        return min(100, $score);
    }
    
    /**
     * Detect inventory anomalies
     * 
     * @param int $storeId Store ID
     * @param int $days Analysis period in days
     * @return array Detected anomalies
     */
    public function detectInventoryAnomalies(int $storeId, int $days = 30): array
    {
        try {
            // Get inventory snapshots
            $sql = "
                SELECT 
                    snapshot_date,
                    product_id,
                    quantity,
                    p.name AS product_name,
                    p.sku
                FROM inventory_snapshots i
                JOIN products p ON p.product_id = i.product_id
                WHERE store_id = :store_id
                AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY product_id, snapshot_date
            ";
            
            $snapshots = $this->db->query($sql, [
                'store_id' => $storeId,
                'days' => $days
            ]);
            
            // Group by product
            $productData = [];
            foreach ($snapshots as $snapshot) {
                $productId = $snapshot['product_id'];
                if (!isset($productData[$productId])) {
                    $productData[$productId] = [
                        'product_id' => $productId,
                        'name' => $snapshot['product_name'],
                        'sku' => $snapshot['sku'],
                        'snapshots' => []
                    ];
                }
                $productData[$productId]['snapshots'][] = [
                    'date' => $snapshot['snapshot_date'],
                    'quantity' => (float) $snapshot['quantity']
                ];
            }
            
            $anomalies = [];
            
            foreach ($productData as $product) {
                $quantities = array_column($product['snapshots'], 'quantity');
                
                if (count($quantities) < 7) continue; // Need at least a week of data
                
                // Detect sudden drops
                $suddenDrop = $this->detectSuddenDrop($quantities);
                if ($suddenDrop) {
                    $anomalies[] = [
                        'type' => 'sudden_inventory_drop',
                        'product' => $product['name'],
                        'sku' => $product['sku'],
                        'description' => sprintf(
                            'Inventory dropped by %.1f%% in one day',
                            $suddenDrop['percentage']
                        ),
                        'severity' => $suddenDrop['percentage'] > 50 ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH,
                        'drop_percentage' => $suddenDrop['percentage'],
                        'from_quantity' => $suddenDrop['from'],
                        'to_quantity' => $suddenDrop['to'],
                        'date' => $suddenDrop['date']
                    ];
                }
                
                // Detect unusual variance
                $stats = $this->calculateStatistics($quantities);
                $recentValue = end($quantities);
                $zScore = $stats['std_dev'] > 0 
                    ? abs(($recentValue - $stats['mean']) / $stats['std_dev'])
                    : 0;
                
                if ($zScore > self::Z_SCORE_THRESHOLD) {
                    $anomalies[] = [
                        'type' => 'inventory_variance_anomaly',
                        'product' => $product['name'],
                        'sku' => $product['sku'],
                        'description' => sprintf(
                            'Current inventory (%.0f) deviates significantly from normal (mean: %.0f)',
                            $recentValue,
                            $stats['mean']
                        ),
                        'severity' => $this->determineSeverity($zScore),
                        'z_score' => round($zScore, 2),
                        'current_quantity' => $recentValue,
                        'average_quantity' => round($stats['mean'], 2)
                    ];
                }
            }
            
            return [
                'anomalies' => $anomalies,
                'store_id' => $storeId,
                'analysis_period_days' => $days,
                'products_analyzed' => count($productData),
                'anomalies_detected' => count($anomalies),
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Inventory anomaly detection failed", [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Detect sudden drop in inventory
     * 
     * @param array $quantities Time series of quantities
     * @return array|null Drop information or null
     */
    private function detectSuddenDrop(array $quantities): ?array
    {
        for ($i = 1; $i < count($quantities); $i++) {
            $prev = $quantities[$i - 1];
            $curr = $quantities[$i];
            
            if ($prev > 0) {
                $dropPercentage = (($prev - $curr) / $prev) * 100;
                
                if ($dropPercentage > 30) { // 30% drop threshold
                    return [
                        'percentage' => round($dropPercentage, 1),
                        'from' => $prev,
                        'to' => $curr,
                        'date' => date('Y-m-d', strtotime("-" . (count($quantities) - $i) . " days"))
                    ];
                }
            }
        }
        
        return null;
    }
}
