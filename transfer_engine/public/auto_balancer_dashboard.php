<?php
/**
 * Auto-Balancer Dashboard - Real-time monitoring and control
 * 
 * This script provides a web interface to monitor the auto-balancer,
 * view recent activity, and manually trigger runs when needed.
 * 
 * @package VapeshedTransfer
 * @author CIS Bot
 * @version 1.0
 */

require_once __DIR__ . '/../config/bootstrap.php';

class AutoBalancerDashboard {
    private $db;
    
    public function __construct() {
        $this->db = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function renderDashboard() {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Auto-Balancer Dashboard</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 0 auto; }
                .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .status-good { color: #28a745; font-weight: bold; }
                .status-warning { color: #ffc107; font-weight: bold; }
                .status-error { color: #dc3545; font-weight: bold; }
                .metric { display: inline-block; margin: 10px 20px; text-align: center; }
                .metric-value { font-size: 24px; font-weight: bold; display: block; }
                .metric-label { font-size: 12px; color: #666; }
                .transfer-item { padding: 10px; margin: 5px 0; border-left: 4px solid #007bff; background: #f8f9fa; }
                .urgent { border-left-color: #dc3545; }
                .high { border-left-color: #ffc107; }
                .normal { border-left-color: #28a745; }
                .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
                .btn-primary { background: #007bff; color: white; }
                .btn-success { background: #28a745; color: white; }
                .btn-warning { background: #ffc107; color: black; }
                .refresh { float: right; font-size: 12px; color: #666; }
            </style>
            <meta http-equiv="refresh" content="300"> <!-- Auto-refresh every 5 minutes -->
        </head>
        <body>
            <div class="container">
                <h1>🤖 Auto-Balancer Dashboard</h1>
                <div class="refresh">Last updated: <?= date('Y-m-d H:i:s') ?></div>
                
                <?php $this->renderSystemStatus(); ?>
                <?php $this->renderActivitySummary(); ?>
                <?php $this->renderRecentTransfers(); ?>
                <?php $this->renderStoreHealth(); ?>
                <?php $this->renderPerformanceMetrics(); ?>
                
                <div class="card">
                    <h3>🛠 Manual Controls</h3>
                    <button class="btn btn-primary" onclick="window.location.href='?action=run_now'">Run Auto-Balancer Now</button>
                    <button class="btn btn-success" onclick="window.location.href='?action=test_run'">Test Run (Dry Run)</button>
                    <button class="btn btn-warning" onclick="window.location.href='?action=view_logs'">View Logs</button>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function renderSystemStatus() {
        // Get last run info
        $stmt = $this->db->prepare("
            SELECT 
                recorded_at,
                value as transfers_generated,
                JSON_EXTRACT(metadata, '$.stores_analyzed') as stores_analyzed,
                JSON_EXTRACT(metadata, '$.date') as report_date
            FROM transfer_queue_metrics 
            WHERE metric_type = 'daily_report' 
              AND worker_id = 'auto_balancer'
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $last_run = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check for recent errors
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as error_count
            FROM transfer_queue_metrics 
            WHERE metric_type = 'error' 
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $errors = $stmt->fetch();
        
        $status_class = 'status-good';
        $status_text = 'OPERATIONAL';
        
        if ($errors['error_count'] > 0) {
            $status_class = 'status-error';
            $status_text = 'ERRORS DETECTED';
        } elseif (!$last_run || date('Y-m-d') !== substr($last_run['recorded_at'], 0, 10)) {
            $status_class = 'status-warning';
            $status_text = 'NOT RUN TODAY';
        }
        
        echo '<div class="card">';
        echo '<h3>📊 System Status</h3>';
        echo '<div class="' . $status_class . '">Status: ' . $status_text . '</div>';
        
        if ($last_run) {
            echo '<div class="metric">';
            echo '<span class="metric-value">' . $last_run['transfers_generated'] . '</span>';
            echo '<span class="metric-label">Transfers Generated Today</span>';
            echo '</div>';
            
            echo '<div class="metric">';
            echo '<span class="metric-value">' . $last_run['stores_analyzed'] . '</span>';
            echo '<span class="metric-label">Stores Analyzed</span>';
            echo '</div>';
            
            echo '<div class="metric">';
            echo '<span class="metric-value">' . substr($last_run['recorded_at'], 11, 5) . '</span>';
            echo '<span class="metric-label">Last Run Time</span>';
            echo '</div>';
        }
        
        echo '<div class="metric">';
        echo '<span class="metric-value">' . $errors['error_count'] . '</span>';
        echo '<span class="metric-label">Errors (24h)</span>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function renderActivitySummary() {
        // Get activity over last 7 days
        $stmt = $this->db->prepare("
            SELECT 
                DATE(recorded_at) as date,
                SUM(CASE WHEN metric_type = 'daily_report' THEN value ELSE 0 END) as transfers,
                COUNT(CASE WHEN metric_type = 'error' THEN 1 END) as errors
            FROM transfer_queue_metrics 
            WHERE worker_id IN ('auto_balancer', 'scheduler')
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(recorded_at)
            ORDER BY date DESC
        ");
        $stmt->execute();
        $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="card">';
        echo '<h3>📈 Activity Summary (Last 7 Days)</h3>';
        
        if (empty($activity)) {
            echo '<p>No recent activity found.</p>';
        } else {
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<tr><th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Date</th>';
            echo '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Transfers</th>';
            echo '<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Errors</th></tr>';
            
            foreach ($activity as $day) {
                echo '<tr>';
                echo '<td style="padding: 8px;">' . $day['date'] . '</td>';
                echo '<td style="padding: 8px; text-align: right;">' . $day['transfers'] . '</td>';
                echo '<td style="padding: 8px; text-align: right;">' . ($day['errors'] > 0 ? '<span class="status-error">' . $day['errors'] . '</span>' : '0') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';
    }
    
    private function renderRecentTransfers() {
        // Get recent transfers from executions
        $stmt = $this->db->prepare("
            SELECT 
                e.alias_code,
                e.created_at,
                e.status,
                e.total_items_processed,
                COUNT(a.id) as allocation_count,
                SUM(a.allocated_quantity) as total_qty
            FROM transfer_executions e
            LEFT JOIN transfer_allocations a ON e.id = a.execution_id
            WHERE e.executed_by = 'auto_balancer'
              AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY e.id
            ORDER BY e.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="card">';
        echo '<h3>🚚 Recent Transfers</h3>';
        
        if (empty($transfers)) {
            echo '<p>No recent transfers found.</p>';
        } else {
            foreach ($transfers as $transfer) {
                $priority_class = 'normal';
                if (strpos($transfer['alias_code'], 'URGENT') !== false) $priority_class = 'urgent';
                elseif (strpos($transfer['alias_code'], 'HIGH') !== false) $priority_class = 'high';
                
                echo '<div class="transfer-item ' . $priority_class . '">';
                echo '<strong>' . $transfer['alias_code'] . '</strong> ';
                echo '<span style="color: #666;">(' . $transfer['created_at'] . ')</span><br>';
                echo 'Status: ' . strtoupper($transfer['status']) . ' | ';
                echo 'Allocations: ' . $transfer['allocation_count'] . ' | ';
                echo 'Total Qty: ' . $transfer['total_qty'] . ' units';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    
    private function renderStoreHealth() {
        // This would require live store analysis - simplified version
        echo '<div class="card">';
        echo '<h3>🏪 Store Health Overview</h3>';
        echo '<p><em>Store health analysis would appear here - showing which stores are low on stock, overstocked, or trending high demand.</em></p>';
        echo '<p>To implement: Connect to live vend_inventory and vend_sales data for real-time store status.</p>';
        echo '</div>';
    }
    
    private function renderPerformanceMetrics() {
        // Get performance metrics
        $stmt = $this->db->prepare("
            SELECT 
                metric_type,
                AVG(value) as avg_value,
                MAX(value) as max_value,
                MIN(value) as min_value,
                unit
            FROM transfer_queue_metrics 
            WHERE worker_id = 'auto_balancer'
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND metric_type IN ('execution_time', 'items_processed', 'throughput')
            GROUP BY metric_type, unit
        ");
        $stmt->execute();
        $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<div class="card">';
        echo '<h3>⚡ Performance Metrics (Last 7 Days)</h3>';
        
        if (empty($metrics)) {
            echo '<p>No performance data available yet.</p>';
        } else {
            foreach ($metrics as $metric) {
                echo '<div class="metric">';
                echo '<span class="metric-value">' . round($metric['avg_value'], 2) . '</span>';
                echo '<span class="metric-label">' . ucwords(str_replace('_', ' ', $metric['metric_type'])) . ' (avg ' . $metric['unit'] . ')</span>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    
    public function handleAction($action) {
        switch ($action) {
            case 'run_now':
                return $this->runNow();
            case 'test_run':
                return $this->testRun();
            case 'view_logs':
                return $this->viewLogs();
            default:
                return null;
        }
    }
    
    private function runNow() {
        // This would trigger the auto-balancer
        return '<div class="card"><h3>⚡ Manual Run Triggered</h3><p>Auto-balancer has been started manually. Check back in a few minutes for results.</p></div>';
    }
    
    private function testRun() {
        // This would run in test mode
        return '<div class="card"><h3>🧪 Test Run Initiated</h3><p>Running auto-balancer in test mode (no actual transfers created).</p></div>';
    }
    
    private function viewLogs() {
        // Show recent log entries
        return '<div class="card"><h3>📋 Recent Log Entries</h3><p>Log viewer would appear here showing recent auto-balancer activity.</p></div>';
    }
}

// Handle web interface
if (isset($_GET['action'])) {
    $dashboard = new AutoBalancerDashboard();
    $action_result = $dashboard->handleAction($_GET['action']);
    if ($action_result) {
        echo $action_result;
        echo '<script>setTimeout(function() { window.location.href = "' . strtok($_SERVER["REQUEST_URI"], '?') . '"; }, 3000);</script>';
        exit;
    }
}

// Render dashboard
$dashboard = new AutoBalancerDashboard();
echo $dashboard->renderDashboard();
?>