<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

/**
 * Reports Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles transfer reports and analytics
 */
class ReportsController extends BaseController
{
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    /**
     * Show reports dashboard
     */
    public function index(): void
    {
        $pageTitle = 'Reports & Analytics';
        $currentPage = 'reports';
        
        $this->render('reports/index', [
            'title' => $pageTitle,
            'currentPage' => $currentPage
        ]);
    }

    /**
     * Export reports in various formats
     */
    public function export(): void
    {
        $format = $_GET['format'] ?? 'csv';
        $date_range = $_GET['date_range'] ?? 'week';
        $config_filter = $_GET['config_filter'] ?? '';
        $mode_filter = $_GET['mode_filter'] ?? '';

        try {
            $data = $this->getReportData([
                'date_range' => $date_range,
                'config_filter' => $config_filter,
                'mode_filter' => $mode_filter
            ]);

            switch ($format) {
                case 'csv':
                    $this->exportCSV($data);
                    break;
                case 'json':
                    $this->exportJSON($data);
                    break;
                case 'pdf':
                    $this->exportPDF($data);
                    break;
                default:
                    throw new \Exception('Unsupported export format');
            }

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Full-feature dynamic report viewer
     */
    public function viewer(): void
    {
        $pageTitle = 'Full Report Viewer';
        $currentPage = 'reports';
        $this->render('reports/viewer', [
            'title' => $pageTitle,
            'currentPage' => $currentPage,
            'additional_js' => [asset('js/reports-viewer.js')],
            'additional_css' => [asset('css/reports-viewer.css')],
        ]);
    }

    /**
     * Get real report data from database
     */
    private function getReportData(array $filters): array
    {
        try {
            $db = Database::getInstance();
            
            // Build WHERE clause from filters
            $where = ['1=1']; // Always true base condition
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $where[] = "e.created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "e.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($filters['status'])) {
                $where[] = "e.execution_status = ?";
                $params[] = $filters['status'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Get summary statistics
            $summaryStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_transfers,
                    AVG(CASE WHEN execution_status = 'completed' THEN 1 ELSE 0 END) * 100 as success_rate,
                    SUM(products_processed) as total_products,
                    AVG(execution_duration) as avg_execution_time
                FROM transfer_executions e
                WHERE {$whereClause}
            ");
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get transfer details
            $transfersStmt = $db->prepare("
                SELECT 
                    e.run_id,
                    e.created_at as timestamp,
                    CASE WHEN e.simulation_mode = 1 THEN 'Simulation' ELSE 'Live' END as mode,
                    e.products_processed as products,
                    CONCAT(e.execution_duration, 's') as duration,
                    CASE 
                        WHEN e.execution_status = 'completed' THEN 'Success'
                        WHEN e.execution_status = 'failed' THEN 'Failed'
                        ELSE 'Pending'
                    END as status
                FROM transfer_executions e
                WHERE {$whereClause}
                ORDER BY e.created_at DESC
                LIMIT 50
            ");
            $transfersStmt->execute($params);
            $transfers = $transfersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'summary' => [
                    'total_transfers' => (int)($summary['total_transfers'] ?? 0),
                    'success_rate' => round($summary['success_rate'] ?? 0, 1),
                    'total_products' => (int)($summary['total_products'] ?? 0),
                    'avg_execution_time' => round($summary['avg_execution_time'] ?? 0, 1)
                ],
                'transfers' => $transfers ?: []
            ];
            
        } catch (Exception $e) {
            error_log("Reports: Failed to fetch data: " . $e->getMessage());
            return [
                'summary' => [
                    'total_transfers' => 0,
                    'success_rate' => 0,
                    'total_products' => 0,
                    'avg_execution_time' => 0
                ],
                'transfers' => []
            ];
        }
    }

    /**
     * Export data as CSV
     */
    private function exportCSV(array $data): void
    {
        $filename = 'transfer_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // Write header
        fputcsv($output, ['Run ID', 'Timestamp', 'Mode', 'Products', 'Duration', 'Status']);
        
        // Write data
        foreach ($data['transfers'] as $transfer) {
            fputcsv($output, [
                $transfer['run_id'],
                $transfer['timestamp'],
                $transfer['mode'],
                $transfer['products'],
                $transfer['duration'],
                $transfer['status']
            ]);
        }
        
        fclose($output);
    }

    /**
     * Export data as JSON
     */
    private function exportJSON(array $data): void
    {
        $filename = 'transfer_report_' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Export data as PDF (placeholder)
     */
    private function exportPDF(array $data): void
    {
        // For a real implementation, you would use a library like TCPDF or DomPDF
        $filename = 'transfer_report_' . date('Y-m-d') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // For now, just return a placeholder message
        echo "PDF export functionality would be implemented here with a proper PDF library.";
    }
    
    /**
     * Generate reports based on POST data
     */
    public function generate(): void
    {
        header('Content-Type: application/json');
        
        try {
            $reportType = $_POST['type'] ?? 'summary';
            $dateRange = $_POST['date_range'] ?? 'week';
            $format = $_POST['format'] ?? 'json';
            
            // Generate report based on parameters
            $data = $this->generateReportData($reportType, $dateRange);
            
            if ($format === 'json') {
                echo json_encode([
                    'ok' => true,
                    'data' => $data,
                    'generated_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Redirect to export with parameters
                header("Location: /reports/export?format={$format}&type={$reportType}&date_range={$dateRange}");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function generateReportData($reportType, $dateRange): array
    {
        // Placeholder report generation logic
        return [
            'type' => $reportType,
            'date_range' => $dateRange,
            'summary' => [
                'total_transfers' => rand(50, 200),
                'success_rate' => rand(85, 99) / 100,
                'avg_duration' => rand(30, 120)
            ]
        ];
    }
}