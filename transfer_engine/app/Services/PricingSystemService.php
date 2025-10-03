<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Pricing System Service
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Service for managing automated pricing system
 */
class PricingSystemService extends BaseService
{
    private string $pricingPath;
    
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->pricingPath = dirname(__DIR__, 2) . '/CORE_PROJECTS/PRICING_SYSTEM';
    }

    /**
     * Get pricing system status
     */
    public function getStatus(): array
    {
        try {
            // Check if pricing engine is running
            $isRunning = $this->isProcessRunning('automated_pricing_engine.php');
            
            // Get recent price updates
            $recentUpdates = $this->getTodayUpdates();
            
            return [
                'status' => $isRunning ? 'running' : 'idle',
                'health' => 'healthy',
                'updates_today' => $recentUpdates,
                'last_analysis' => $this->getLastAnalysisTime(),
                'active_recommendations' => $this->getActiveRecommendationCount()
            ];
            
        } catch (\Exception $e) {
            $this->logError('Failed to get pricing system status', $e);
            return [
                'status' => 'error',
                'health' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run pricing analysis
     */
    public function runAnalysis(array $productIds = []): array
    {
        try {
            $command = "cd {$this->pricingPath} && php automated_pricing_engine.php";
            
            if (!empty($productIds)) {
                $command .= ' --products=' . implode(',', $productIds);
            }
            
            $result = $this->executeCommand($command);
            
            // Log the analysis
            $this->logActivity('pricing_analysis', [
                'product_ids' => $productIds,
                'success' => $result['success'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError('Failed to run pricing analysis', $e);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get today's price updates count
     */
    public function getTodayUpdates(): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM price_updates 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
            
        } catch (\Exception $e) {
            $this->logError('Failed to get today updates', $e);
            return 0;
        }
    }

    /**
     * Get pricing recommendations
     */
    public function getRecommendations(?string $category = null): array
    {
        try {
            $sql = "
                SELECT 
                    product_id,
                    current_price,
                    recommended_price,
                    reason,
                    potential_impact,
                    created_at
                FROM pricing_recommendations 
                WHERE status = 'active'
            ";
            
            $params = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY potential_impact DESC LIMIT 20";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $this->logError('Failed to get recommendations', $e);
            return [];
        }
    }

    /**
     * Get price history for product
     */
    public function getPriceHistory(?string $productId = null): array
    {
        try {
            if ($productId) {
                $stmt = $this->db->prepare("
                    SELECT 
                        price,
                        change_reason,
                        created_at
                    FROM price_history 
                    WHERE product_id = ?
                    ORDER BY created_at DESC 
                    LIMIT 50
                ");
                $stmt->execute([$productId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT 
                        product_id,
                        price,
                        change_reason,
                        created_at
                    FROM price_history 
                    ORDER BY created_at DESC 
                    LIMIT 100
                ");
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $this->logError('Failed to get price history', $e);
            return [];
        }
    }

    /**
     * Get recent analyses
     */
    public function getRecentAnalyses(int $limit = 15): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    analysis_type,
                    products_analyzed,
                    recommendations_generated,
                    execution_time,
                    created_at
                FROM pricing_analyses 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $this->logError('Failed to get recent analyses', $e);
            return [];
        }
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_analyses,
                    AVG(execution_time) as avg_execution_time,
                    SUM(recommendations_generated) as total_recommendations,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_analyses
                FROM pricing_analyses 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_analyses' => (int)$summary['total_analyses'],
                'avg_execution_time' => round((float)$summary['avg_execution_time'], 2),
                'total_recommendations' => (int)$summary['total_recommendations'],
                'weekly_analyses' => (int)$summary['weekly_analyses']
            ];
            
        } catch (\Exception $e) {
            $this->logError('Failed to get performance summary', $e);
            return [
                'total_analyses' => 0,
                'avg_execution_time' => 0,
                'total_recommendations' => 0,
                'weekly_analyses' => 0
            ];
        }
    }

    /**
     * Get configuration
     */
    public function getConfiguration(): array
    {
        try {
            $configFiles = [
                'main' => $this->pricingPath . '/config/pricing.php',
                'house_juice' => $this->pricingPath . '/config/house_juice.php'
            ];
            
            $config = ['files' => []];
            
            foreach ($configFiles as $type => $file) {
                if (file_exists($file)) {
                    $config['files'][$type] = [
                        'exists' => true,
                        'path' => $file,
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                } else {
                    $config['files'][$type] = [
                        'exists' => false,
                        'path' => $file
                    ];
                }
            }
            
            return $config;
            
        } catch (\Exception $e) {
            $this->logError('Failed to get configuration', $e);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get quick status for API
     */
    public function getQuickStatus(): array
    {
        return [
            'status' => $this->isProcessRunning('automated_pricing_engine.php') ? 'running' : 'idle',
            'updates_today' => $this->getTodayUpdates(),
            'health' => 'healthy'
        ];
    }

    // Private helper methods
    
    private function getLastAnalysisTime(): ?string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT created_at 
                FROM pricing_analyses 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetchColumn();
            return $result ?: null;
            
        } catch (\Exception $e) {
            $this->logError('Failed to get last analysis time', $e);
            return null;
        }
    }
    
    private function getActiveRecommendationCount(): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM pricing_recommendations 
                WHERE status = 'active'
            ");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
            
        } catch (\Exception $e) {
            $this->logError('Failed to get active recommendation count', $e);
            return 0;
        }
    }
}