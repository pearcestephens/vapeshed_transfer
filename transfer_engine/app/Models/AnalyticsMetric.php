<?php
/**
 * Analytics Metric Model
 *
 * Handles database operations for analytics metrics including:
 * - Transfer metrics storage and retrieval
 * - API usage tracking
 * - Performance metrics logging
 * - Cost calculations
 * - Scheduled reports management
 *
 * @category   Model
 * @package    VapeshedTransfer
 * @subpackage Analytics
 * @version    1.0.0
 */

namespace App\Models;

use App\Support\Db;
use PDO;
use PDOException;
use DateTime;
use Exception;

/**
 * AnalyticsMetric Model
 */
class AnalyticsMetric
{
    /**
     * Database connection
     *
     * @var Db
     */
    private $db;

    /**
     * Table names
     *
     * @var array
     */
    private $tables = [
        'transfer_metrics' => 'transfer_metrics',
        'api_usage_metrics' => 'api_usage_metrics',
        'performance_metrics' => 'performance_metrics',
        'scheduled_reports' => 'scheduled_reports'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    /**
     * Record transfer metric
     *
     * @param array $data Metric data
     * @return int|false Inserted ID or false on failure
     */
    public function recordTransferMetric(array $data)
    {
        try {
            $query = "INSERT INTO {$this->tables['transfer_metrics']} (
                transfer_id,
                source_outlet_id,
                destination_outlet_id,
                total_items,
                total_quantity,
                status,
                processing_time_ms,
                api_calls_made,
                cost_calculated,
                created_at,
                metadata
            ) VALUES (
                :transfer_id,
                :source_outlet_id,
                :destination_outlet_id,
                :total_items,
                :total_quantity,
                :status,
                :processing_time_ms,
                :api_calls_made,
                :cost_calculated,
                NOW(),
                :metadata
            )";

            $params = [
                ':transfer_id' => $data['transfer_id'] ?? null,
                ':source_outlet_id' => $data['source_outlet_id'] ?? null,
                ':destination_outlet_id' => $data['destination_outlet_id'] ?? null,
                ':total_items' => $data['total_items'] ?? 0,
                ':total_quantity' => $data['total_quantity'] ?? 0,
                ':status' => $data['status'] ?? 'pending',
                ':processing_time_ms' => $data['processing_time_ms'] ?? 0,
                ':api_calls_made' => $data['api_calls_made'] ?? 0,
                ':cost_calculated' => $data['cost_calculated'] ?? 0.00,
                ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];

            return $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to record transfer metric: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record API usage metric
     *
     * @param array $data Metric data
     * @return int|false Inserted ID or false on failure
     */
    public function recordApiUsageMetric(array $data)
    {
        try {
            $query = "INSERT INTO {$this->tables['api_usage_metrics']} (
                endpoint,
                method,
                provider,
                response_time_ms,
                status_code,
                success,
                error_message,
                rate_limit_remaining,
                created_at,
                metadata
            ) VALUES (
                :endpoint,
                :method,
                :provider,
                :response_time_ms,
                :status_code,
                :success,
                :error_message,
                :rate_limit_remaining,
                NOW(),
                :metadata
            )";

            $params = [
                ':endpoint' => $data['endpoint'] ?? '',
                ':method' => $data['method'] ?? 'GET',
                ':provider' => $data['provider'] ?? 'vend',
                ':response_time_ms' => $data['response_time_ms'] ?? 0,
                ':status_code' => $data['status_code'] ?? 200,
                ':success' => isset($data['success']) ? (int)$data['success'] : 1,
                ':error_message' => $data['error_message'] ?? null,
                ':rate_limit_remaining' => $data['rate_limit_remaining'] ?? null,
                ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];

            return $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to record API usage metric: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record performance metric
     *
     * @param array $data Metric data
     * @return int|false Inserted ID or false on failure
     */
    public function recordPerformanceMetric(array $data)
    {
        try {
            $query = "INSERT INTO {$this->tables['performance_metrics']} (
                metric_type,
                metric_value,
                operation,
                query_text,
                execution_time_ms,
                memory_usage_mb,
                created_at,
                metadata
            ) VALUES (
                :metric_type,
                :metric_value,
                :operation,
                :query_text,
                :execution_time_ms,
                :memory_usage_mb,
                NOW(),
                :metadata
            )";

            $params = [
                ':metric_type' => $data['metric_type'] ?? 'general',
                ':metric_value' => $data['metric_value'] ?? 0,
                ':operation' => $data['operation'] ?? null,
                ':query_text' => $data['query_text'] ?? null,
                ':execution_time_ms' => $data['execution_time_ms'] ?? 0,
                ':memory_usage_mb' => $data['memory_usage_mb'] ?? 0,
                ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ];

            return $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to record performance metric: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get transfer metrics for date range
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param array $filters Optional filters
     * @return array
     */
    public function getTransferMetrics($startDate, $endDate, array $filters = [])
    {
        try {
            $whereClauses = ["DATE(created_at) BETWEEN :start_date AND :end_date"];
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            if (isset($filters['status'])) {
                $whereClauses[] = "status = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['source_outlet_id'])) {
                $whereClauses[] = "source_outlet_id = :source_outlet_id";
                $params[':source_outlet_id'] = $filters['source_outlet_id'];
            }

            if (isset($filters['destination_outlet_id'])) {
                $whereClauses[] = "destination_outlet_id = :destination_outlet_id";
                $params[':destination_outlet_id'] = $filters['destination_outlet_id'];
            }

            $whereClause = implode(' AND ', $whereClauses);

            $query = "SELECT * FROM {$this->tables['transfer_metrics']}
                      WHERE {$whereClause}
                      ORDER BY created_at DESC";

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get transfer metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get API usage metrics for date range
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param array $filters Optional filters
     * @return array
     */
    public function getApiUsageMetrics($startDate, $endDate, array $filters = [])
    {
        try {
            $whereClauses = ["DATE(created_at) BETWEEN :start_date AND :end_date"];
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            if (isset($filters['provider'])) {
                $whereClauses[] = "provider = :provider";
                $params[':provider'] = $filters['provider'];
            }

            if (isset($filters['endpoint'])) {
                $whereClauses[] = "endpoint LIKE :endpoint";
                $params[':endpoint'] = '%' . $filters['endpoint'] . '%';
            }

            $whereClause = implode(' AND ', $whereClauses);

            $query = "SELECT * FROM {$this->tables['api_usage_metrics']}
                      WHERE {$whereClause}
                      ORDER BY created_at DESC";

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get API usage metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get performance metrics for date range
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param array $filters Optional filters
     * @return array
     */
    public function getPerformanceMetrics($startDate, $endDate, array $filters = [])
    {
        try {
            $whereClauses = ["DATE(created_at) BETWEEN :start_date AND :end_date"];
            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            if (isset($filters['metric_type'])) {
                $whereClauses[] = "metric_type = :metric_type";
                $params[':metric_type'] = $filters['metric_type'];
            }

            $whereClause = implode(' AND ', $whereClauses);

            $query = "SELECT * FROM {$this->tables['performance_metrics']}
                      WHERE {$whereClause}
                      ORDER BY created_at DESC";

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get performance metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get aggregated transfer statistics
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getTransferStatistics($startDate, $endDate)
    {
        try {
            $query = "SELECT
                COUNT(*) as total_transfers,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_transfers,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transfers,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transfers,
                AVG(processing_time_ms) as avg_processing_time,
                MAX(processing_time_ms) as max_processing_time,
                MIN(processing_time_ms) as min_processing_time,
                SUM(total_items) as total_items_transferred,
                SUM(total_quantity) as total_quantity_transferred,
                SUM(api_calls_made) as total_api_calls,
                SUM(cost_calculated) as total_cost
            FROM {$this->tables['transfer_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            return $this->db->fetchOne($query, $params) ?: [];
        } catch (PDOException $e) {
            error_log("Failed to get transfer statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top transfer routes
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int $limit Number of routes to return
     * @return array
     */
    public function getTopTransferRoutes($startDate, $endDate, $limit = 10)
    {
        try {
            $query = "SELECT
                source_outlet_id,
                destination_outlet_id,
                COUNT(*) as transfer_count,
                SUM(total_items) as total_items,
                SUM(total_quantity) as total_quantity,
                AVG(processing_time_ms) as avg_processing_time,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_count,
                (SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100) as success_rate
            FROM {$this->tables['transfer_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY source_outlet_id, destination_outlet_id
            ORDER BY transfer_count DESC
            LIMIT :limit";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':limit' => $limit
            ];

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get top transfer routes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get API endpoint statistics
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getApiEndpointStatistics($startDate, $endDate)
    {
        try {
            $query = "SELECT
                endpoint,
                provider,
                COUNT(*) as total_calls,
                AVG(response_time_ms) as avg_response_time,
                MAX(response_time_ms) as max_response_time,
                MIN(response_time_ms) as min_response_time,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_calls,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_calls,
                (SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100) as error_rate
            FROM {$this->tables['api_usage_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY endpoint, provider
            ORDER BY total_calls DESC";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get API endpoint statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get slow queries
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int $thresholdMs Minimum execution time in milliseconds
     * @param int $limit Number of queries to return
     * @return array
     */
    public function getSlowQueries($startDate, $endDate, $thresholdMs = 1000, $limit = 20)
    {
        try {
            $query = "SELECT
                query_text,
                COUNT(*) as execution_count,
                AVG(execution_time_ms) as avg_time,
                MAX(execution_time_ms) as max_time,
                MIN(execution_time_ms) as min_time,
                AVG(memory_usage_mb) as avg_memory
            FROM {$this->tables['performance_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                AND metric_type = 'query'
                AND execution_time_ms >= :threshold
                AND query_text IS NOT NULL
            GROUP BY query_text
            ORDER BY avg_time DESC
            LIMIT :limit";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':threshold' => $thresholdMs,
                ':limit' => $limit
            ];

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get slow queries: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get hourly distribution
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getHourlyDistribution($startDate, $endDate)
    {
        try {
            $query = "SELECT
                HOUR(created_at) as hour,
                COUNT(*) as count,
                AVG(processing_time_ms) as avg_processing_time
            FROM {$this->tables['transfer_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY HOUR(created_at)
            ORDER BY hour";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get hourly distribution: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily trend
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array
     */
    public function getDailyTrend($startDate, $endDate)
    {
        try {
            $query = "SELECT
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(processing_time_ms) as avg_processing_time
            FROM {$this->tables['transfer_metrics']}
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(created_at)
            ORDER BY date";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            return $this->db->fetchAll($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to get daily trend: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create scheduled report
     *
     * @param array $data Report configuration
     * @return int|false Inserted ID or false on failure
     */
    public function createScheduledReport(array $data)
    {
        try {
            $query = "INSERT INTO {$this->tables['scheduled_reports']} (
                name,
                report_type,
                format,
                frequency,
                recipients,
                filters,
                is_active,
                created_at,
                next_run_at
            ) VALUES (
                :name,
                :report_type,
                :format,
                :frequency,
                :recipients,
                :filters,
                :is_active,
                NOW(),
                :next_run_at
            )";

            $params = [
                ':name' => $data['name'] ?? '',
                ':report_type' => $data['report_type'] ?? 'full',
                ':format' => $data['format'] ?? 'pdf',
                ':frequency' => $data['frequency'] ?? 'weekly',
                ':recipients' => isset($data['recipients']) ? json_encode($data['recipients']) : '[]',
                ':filters' => isset($data['filters']) ? json_encode($data['filters']) : '{}',
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':next_run_at' => $data['next_run_at'] ?? null
            ];

            return $this->db->insert($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to create scheduled report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get scheduled reports
     *
     * @param bool $activeOnly Get only active reports
     * @return array
     */
    public function getScheduledReports($activeOnly = false)
    {
        try {
            $query = "SELECT * FROM {$this->tables['scheduled_reports']}";

            if ($activeOnly) {
                $query .= " WHERE is_active = 1";
            }

            $query .= " ORDER BY created_at DESC";

            return $this->db->fetchAll($query);
        } catch (PDOException $e) {
            error_log("Failed to get scheduled reports: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update scheduled report
     *
     * @param int $id Report ID
     * @param array $data Update data
     * @return bool
     */
    public function updateScheduledReport($id, array $data)
    {
        try {
            $setClauses = [];
            $params = [':id' => $id];

            $allowedFields = [
                'name', 'report_type', 'format', 'frequency',
                'recipients', 'filters', 'is_active', 'next_run_at'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setClauses[] = "{$field} = :{$field}";

                    if (in_array($field, ['recipients', 'filters'])) {
                        $params[":{$field}"] = json_encode($data[$field]);
                    } else {
                        $params[":{$field}"] = $data[$field];
                    }
                }
            }

            if (empty($setClauses)) {
                return false;
            }

            $query = "UPDATE {$this->tables['scheduled_reports']}
                      SET " . implode(', ', $setClauses) . "
                      WHERE id = :id";

            return $this->db->execute($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to update scheduled report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete scheduled report
     *
     * @param int $id Report ID
     * @return bool
     */
    public function deleteScheduledReport($id)
    {
        try {
            $query = "DELETE FROM {$this->tables['scheduled_reports']} WHERE id = :id";
            $params = [':id' => $id];

            return $this->db->execute($query, $params);
        } catch (PDOException $e) {
            error_log("Failed to delete scheduled report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean old metrics
     *
     * @param int $daysToKeep Number of days to retain
     * @return array Cleanup statistics
     */
    public function cleanOldMetrics($daysToKeep = 90)
    {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
            $stats = [];

            // Clean transfer metrics
            $query = "DELETE FROM {$this->tables['transfer_metrics']}
                      WHERE created_at < :cutoff_date";
            $params = [':cutoff_date' => $cutoffDate];
            $this->db->execute($query, $params);
            $stats['transfer_metrics_deleted'] = $this->db->rowCount();

            // Clean API usage metrics
            $query = "DELETE FROM {$this->tables['api_usage_metrics']}
                      WHERE created_at < :cutoff_date";
            $this->db->execute($query, $params);
            $stats['api_usage_metrics_deleted'] = $this->db->rowCount();

            // Clean performance metrics
            $query = "DELETE FROM {$this->tables['performance_metrics']}
                      WHERE created_at < :cutoff_date";
            $this->db->execute($query, $params);
            $stats['performance_metrics_deleted'] = $this->db->rowCount();

            return $stats;
        } catch (PDOException $e) {
            error_log("Failed to clean old metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get metric by ID
     *
     * @param string $table Table name
     * @param int $id Metric ID
     * @return array|null
     */
    public function getMetricById($table, $id)
    {
        try {
            if (!isset($this->tables[$table])) {
                throw new Exception("Invalid table: {$table}");
            }

            $query = "SELECT * FROM {$this->tables[$table]} WHERE id = :id";
            $params = [':id' => $id];

            return $this->db->fetchOne($query, $params);
        } catch (Exception $e) {
            error_log("Failed to get metric by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Bulk insert metrics
     *
     * @param string $table Table name
     * @param array $records Array of metric records
     * @return int Number of records inserted
     */
    public function bulkInsertMetrics($table, array $records)
    {
        if (empty($records) || !isset($this->tables[$table])) {
            return 0;
        }

        try {
            $inserted = 0;
            $this->db->beginTransaction();

            foreach ($records as $record) {
                switch ($table) {
                    case 'transfer_metrics':
                        $this->recordTransferMetric($record);
                        break;
                    case 'api_usage_metrics':
                        $this->recordApiUsageMetric($record);
                        break;
                    case 'performance_metrics':
                        $this->recordPerformanceMetric($record);
                        break;
                }
                $inserted++;
            }

            $this->db->commit();
            return $inserted;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to bulk insert metrics: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get table statistics
     *
     * @return array
     */
    public function getTableStatistics()
    {
        try {
            $stats = [];

            foreach ($this->tables as $key => $table) {
                $query = "SELECT COUNT(*) as count FROM {$table}";
                $result = $this->db->fetchOne($query);
                $stats[$key] = $result['count'] ?? 0;
            }

            return $stats;
        } catch (PDOException $e) {
            error_log("Failed to get table statistics: " . $e->getMessage());
            return [];
        }
    }
}
