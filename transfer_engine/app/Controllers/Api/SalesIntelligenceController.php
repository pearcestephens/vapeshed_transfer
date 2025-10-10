<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Analytics\SalesDataEngine;
use App\Services\Pricing\PriceIntelligenceEngine;
use App\Services\TransferEngineService;

/**
 * Sales Intelligence API Controller
 *
 * Advanced sales analytics and forecasting endpoints
 *
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class SalesIntelligenceController extends BaseController
{
    private SalesDataEngine $salesEngine;
    private PriceIntelligenceEngine $priceEngine;
    private TransferEngineService $transferEngine;

    public function __construct()
    {
        parent::__construct();
        $this->salesEngine = new SalesDataEngine();
        $this->priceEngine = new PriceIntelligenceEngine();
        $this->transferEngine = new TransferEngineService();
    }

    /**
     * GET /api/sales/patterns
     * Comprehensive sales pattern analysis
     */
    public function getPatterns(): array
    {
        try {
            $this->validateBrowseMode('Sales pattern analysis requires authentication');

            $config = [
                'analysis_days' => (int)($_GET['days'] ?? 90),
                'outlets' => $this->parseOutletFilter(),
                'include_predictions' => (bool)($_GET['predictions'] ?? true)
            ];

            $patterns = $this->salesEngine->analyzeSalesPatterns($config);

            if (isset($patterns['error'])) {
                return $this->errorResponse($patterns['error'], 500);
            }

            // Enhance with transfer recommendations if requested
            if ($config['include_predictions']) {
                $patterns['transfer_recommendations'] = $this->generateTransferRecommendations($patterns);
            }

            return $this->successResponse($patterns, 'Sales patterns analyzed successfully');

        } catch (\Exception $e) {
            $this->logger->error('Sales patterns API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Sales pattern analysis failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/sales/forecast/{productId}
     * Product demand forecasting
     */
    public function getForecast(string $productId): array
    {
        try {
            $this->validateBrowseMode('Demand forecasting requires authentication');

            if (empty($productId)) {
                return $this->errorResponse('Product ID is required', 400);
            }

            $forecastDays = (int)($_GET['days'] ?? 30);
            $outlets = $this->getActiveOutlets();

            $forecast = $this->salesEngine->forecastDemand($productId, $outlets, $forecastDays);

            if (isset($forecast['error'])) {
                return $this->errorResponse($forecast['error'], 500);
            }

            // Add transfer optimization based on forecast
            $transferOptimization = $this->optimizeTransfersForForecast($productId, $forecast);
            $forecast['transfer_optimization'] = $transferOptimization;

            return $this->successResponse($forecast, 'Demand forecast generated successfully');

        } catch (\Exception $e) {
            $this->logger->error('Demand forecast API error', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Demand forecasting failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/sales/optimize-transfers
     * Velocity-based transfer optimization
     */
    public function optimizeTransfers(): array
    {
        try {
            $this->validateBrowseMode('Transfer optimization requires authentication');
            $this->validateCsrfToken();

            $input = $this->getJsonInput();

            if (empty($input['products'])) {
                return $this->errorResponse('Products list is required', 400);
            }

            $products = $input['products'];
            $outlets = $this->getActiveOutlets();

            // Run velocity-based optimization
            $optimization = $this->salesEngine->optimizeTransfersByVelocity($products, $outlets);

            if (isset($optimization['error'])) {
                return $this->errorResponse($optimization['error'], 500);
            }

            // Enhance with pricing intelligence
            foreach ($optimization['optimizations'] as &$opt) {
                $priceAnalysis = $this->priceEngine->analyzeCompetitivePosition($opt['product_id']);
                $opt['pricing_insights'] = $priceAnalysis;
            }

            return $this->successResponse($optimization, 'Transfer optimization completed successfully');

        } catch (\Exception $e) {
            $this->logger->error('Transfer optimization API error', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Transfer optimization failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/sales/velocity/{outletId}
     * Outlet velocity analysis
     */
    public function getOutletVelocity(string $outletId): array
    {
        try {
            $this->validateBrowseMode('Velocity analysis requires authentication');

            if (empty($outletId)) {
                return $this->errorResponse('Outlet ID is required', 400);
            }

            $days = (int)($_GET['days'] ?? 30);
            $includeForecasts = (bool)($_GET['forecasts'] ?? false);

            // Get outlet sales patterns
            $patterns = $this->salesEngine->analyzeSalesPatterns([
                'analysis_days' => $days,
                'outlets' => [['outlet_id' => $outletId]]
            ]);

            if (isset($patterns['error'])) {
                return $this->errorResponse($patterns['error'], 500);
            }

            $velocityData = [
                'outlet_id' => $outletId,
                'analysis_period_days' => $days,
                'patterns' => $patterns['outlet_patterns'][$outletId] ?? [],
                'metrics' => $patterns['sales_metrics'][$outletId] ?? [],
                'recommendations' => $this->generateOutletRecommendations($outletId, $patterns)
            ];

            // Add demand forecasts if requested
            if ($includeForecasts) {
                $velocityData['demand_forecasts'] = $this->getOutletDemandForecasts($outletId);
            }

            return $this->successResponse($velocityData, 'Outlet velocity analysis completed');

        } catch (\Exception $e) {
            $this->logger->error('Outlet velocity API error', [
                'outlet_id' => $outletId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Outlet velocity analysis failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/sales/intelligence-dashboard
     * Real-time sales intelligence dashboard data
     */
    public function getIntelligenceDashboard(): array
    {
        try {
            $this->validateBrowseMode('Sales intelligence dashboard requires authentication');

            $startTime = microtime(true);

            // Parallel data gathering for dashboard
            $dashboardData = [];

            // Key metrics
            $dashboardData['key_metrics'] = $this->getKeyMetrics();

            // Top performing products
            $dashboardData['top_products'] = $this->getTopPerformingProducts();

            // Outlet performance summary
            $dashboardData['outlet_performance'] = $this->getOutletPerformanceSummary();

            // Recent trends
            $dashboardData['trends'] = $this->getRecentTrends();

            // Transfer opportunities
            $dashboardData['transfer_opportunities'] = $this->getTransferOpportunities();

            // Pricing insights
            $dashboardData['pricing_insights'] = $this->getPricingInsights();

            // System health
            $dashboardData['system_health'] = [
                'data_freshness' => $this->checkDataFreshness(),
                'analysis_performance' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ];

            return $this->successResponse($dashboardData, 'Sales intelligence dashboard loaded');

        } catch (\Exception $e) {
            $this->logger->error('Sales intelligence dashboard error', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Dashboard loading failed: ' . $e->getMessage(), 500);
        }
    }

    // Private helper methods

    private function generateTransferRecommendations(array $patterns): array
    {
        $recommendations = [];

        foreach ($patterns['sales_metrics'] as $outletId => $metrics) {
            if ($metrics['velocity_trend'] === 'increasing' && $metrics['stock_level'] === 'low') {
                $recommendations[] = [
                    'outlet_id' => $outletId,
                    'outlet_name' => $patterns['outlet_patterns'][$outletId]['name'] ?? $outletId,
                    'recommendation' => 'increase_stock',
                    'reason' => 'Increasing sales velocity with low stock levels',
                    'priority' => 'high',
                    'suggested_action' => 'Priority transfer needed'
                ];
            }
        }

        return $recommendations;
    }

    private function optimizeTransfersForForecast(string $productId, array $forecast): array
    {
        $optimization = [];

        foreach ($forecast['outlet_forecasts'] as $outletId => $outletForecast) {
            if ($outletForecast['confidence'] > 0.7 && $outletForecast['forecast_demand'] > 0) {
                $currentStock = $this->getCurrentStockLevel($productId, $outletId);
                $forecastDemand = $outletForecast['forecast_demand'];

                if ($currentStock < $forecastDemand * 0.8) { // Less than 80% of forecasted demand
                    $optimization[$outletId] = [
                        'current_stock' => $currentStock,
                        'forecast_demand' => $forecastDemand,
                        'recommended_transfer' => ceil($forecastDemand - $currentStock),
                        'urgency' => $currentStock <= $forecastDemand * 0.5 ? 'high' : 'medium'
                    ];
                }
            }
        }

        return $optimization;
    }

    private function getKeyMetrics(): array
    {
        // Real-time key business metrics
        return [
            'today_sales' => $this->getTodaySales(),
            'week_vs_last_week' => $this->getWeekOverWeekChange(),
            'top_velocity_products' => $this->getHighVelocityProducts(5),
            'low_stock_alerts' => $this->getLowStockAlerts(),
            'transfer_efficiency' => $this->getTransferEfficiencyMetrics()
        ];
    }

    private function getTopPerformingProducts(int $limit = 10): array
    {
        $sql = "
            SELECT
                p.product_id,
                p.name,
                p.brand,
                SUM(s.quantity) as total_quantity,
                SUM(s.total_amount) as total_revenue,
                COUNT(DISTINCT s.outlet_id) as outlet_count,
                AVG(s.unit_price) as avg_price
            FROM sales_transactions s
            JOIN products p ON p.product_id = s.product_id
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY p.product_id
            ORDER BY total_revenue DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function getTodaySales(): array
    {
        $sql = "
            SELECT
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_revenue,
                SUM(quantity) as total_quantity,
                COUNT(DISTINCT outlet_id) as active_outlets
            FROM sales_transactions
            WHERE DATE(sale_date) = CURDATE()
        ";

        $result = $this->db->query($sql);
        return $result->fetch_assoc();
    }

    private function parseOutletFilter(): array
    {
        $outletFilter = $_GET['outlets'] ?? '';

        if (empty($outletFilter)) {
            return $this->getActiveOutlets();
        }

        $outletIds = explode(',', $outletFilter);
        $outlets = [];

        foreach ($outletIds as $outletId) {
            $outlets[] = ['outlet_id' => trim($outletId)];
        }

        return $outlets;
    }

    private function getActiveOutlets(): array
    {
        $sql = "
            SELECT outlet_id, name, store_code
            FROM outlets
            WHERE deleted_at IS NULL
                AND is_active = 1
                AND is_warehouse = 0
        ";

        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}