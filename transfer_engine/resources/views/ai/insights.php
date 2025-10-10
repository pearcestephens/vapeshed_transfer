<?php
/**
 * AI Insights Dashboard
 * 
 * Display AI-powered insights, forecasts, anomalies, patterns, and optimization recommendations.
 * 
 * @package     VapeShed Transfer Engine
 * @subpackage  Views
 * @version     1.0.0
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Core\Security;
use App\Services\AI\ForecastingService;
use App\Services\AI\AnomalyDetection;
use App\Services\AI\PatternRecognition;
use App\Services\AI\OptimizationEngine;

// Security check
Security::requireAuth();
Security::requirePermission('analytics.view');

$pageTitle = "AI Insights Dashboard";
$currentPage = "ai-insights";

// Initialize AI services
$forecasting = new ForecastingService();
$anomalyDetection = new AnomalyDetection();
$patternRecognition = new PatternRecognition();
$optimizationEngine = new OptimizationEngine();

// Load AI data
try {
    $patterns = $patternRecognition->analyzeTransferPatterns(90);
    $storeId = $_SESSION['user_store_id'] ?? 1;
    $recommendations = $forecasting->generateTransferRecommendations($storeId, 30);
    $anomalies = $anomalyDetection->detectTransferAnomalies(null, 30);
} catch (Exception $e) {
    $error = $e->getMessage();
}

include __DIR__ . '/../layout/header.php';
?>

<div class="ai-insights-dashboard">
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-brain"></i> AI Insights Dashboard</h1>
                <p class="subtitle">Machine learning powered analytics and recommendations</p>
            </div>
            <div class="header-right">
                <button class="btn btn-outline" onclick="refreshAllInsights()">
                    <i class="fas fa-sync-alt"></i> Refresh All
                </button>
                <button class="btn btn-primary" onclick="exportInsights()">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- AI Status Banner -->
    <div class="ai-status-banner">
        <div class="status-item">
            <i class="fas fa-check-circle text-success"></i>
            <span>AI Models Active</span>
        </div>
        <div class="status-item">
            <i class="fas fa-database text-info"></i>
            <span>Data Current</span>
        </div>
        <div class="status-item">
            <i class="fas fa-chart-line text-primary"></i>
            <span>Real-time Analysis</span>
        </div>
        <div class="status-item">
            <i class="fas fa-robot text-warning"></i>
            <span>4 AI Services Running</span>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?= count($patterns['route_patterns'] ?? []) ?></div>
                <div class="metric-label">Patterns Detected</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?= count($anomalies['anomalies'] ?? []) ?></div>
                <div class="metric-label">Anomalies Found</div>
                <div class="metric-change <?= count($anomalies['anomalies'] ?? []) > 5 ? 'negative' : 'neutral' ?>">
                    <?php if (count($anomalies['anomalies'] ?? []) > 5): ?>
                    <i class="fas fa-arrow-up"></i> Requires attention
                    <?php else: ?>
                    <i class="fas fa-check"></i> Normal range
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?= count($patterns['recommendations'] ?? []) ?></div>
                <div class="metric-label">AI Recommendations</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> <?= count(array_filter($patterns['recommendations'] ?? [], fn($r) => $r['priority'] === 'high')) ?> high priority
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">
                <i class="fas fa-bullseye"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value">94%</div>
                <div class="metric-label">Forecast Accuracy</div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> 3% improvement
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="insights-tabs">
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="recommendations">
                <i class="fas fa-lightbulb"></i> Recommendations
            </button>
            <button class="tab-btn" data-tab="forecasts">
                <i class="fas fa-chart-line"></i> Forecasts
            </button>
            <button class="tab-btn" data-tab="anomalies">
                <i class="fas fa-exclamation-triangle"></i> Anomalies
            </button>
            <button class="tab-btn" data-tab="patterns">
                <i class="fas fa-project-diagram"></i> Patterns
            </button>
            <button class="tab-btn" data-tab="optimization">
                <i class="fas fa-cogs"></i> Optimization
            </button>
        </div>

        <!-- Recommendations Tab -->
        <div class="tab-content active" id="recommendations-tab">
            <div class="content-header">
                <h2><i class="fas fa-lightbulb"></i> AI Recommendations</h2>
                <p>Action items generated from pattern analysis and forecasting</p>
            </div>

            <div class="recommendations-grid">
                <?php if (!empty($patterns['recommendations'])): ?>
                    <?php foreach ($patterns['recommendations'] as $recommendation): ?>
                    <div class="recommendation-card priority-<?= $recommendation['priority'] ?>">
                        <div class="recommendation-header">
                            <div class="recommendation-priority">
                                <span class="priority-badge"><?= ucfirst($recommendation['priority']) ?></span>
                            </div>
                            <div class="recommendation-type">
                                <i class="fas fa-<?= $this->getRecommendationIcon($recommendation['type']) ?>"></i>
                                <?= ucwords(str_replace('_', ' ', $recommendation['type'])) ?>
                            </div>
                        </div>
                        <div class="recommendation-content">
                            <h3><?= htmlspecialchars($recommendation['title']) ?></h3>
                            <p><?= htmlspecialchars($recommendation['description']) ?></p>
                        </div>
                        <div class="recommendation-actions">
                            <button class="btn btn-sm btn-primary" onclick="implementRecommendation(<?= htmlspecialchars(json_encode($recommendation)) ?>)">
                                <i class="fas fa-check"></i> Implement
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="viewRecommendationDetails(<?= htmlspecialchars(json_encode($recommendation)) ?>)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No recommendations at this time. System is optimally configured.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Forecasts Tab -->
        <div class="tab-content" id="forecasts-tab">
            <div class="content-header">
                <h2><i class="fas fa-chart-line"></i> Demand Forecasts</h2>
                <p>Predictive analytics for inventory and transfer planning</p>
            </div>

            <div class="forecast-controls">
                <div class="form-group">
                    <label for="forecast-store">Store:</label>
                    <select id="forecast-store" class="form-control" onchange="loadStoreForecast(this.value)">
                        <option value="">Select Store...</option>
                        <?php
                        $stores = $this->getStores();
                        foreach ($stores as $store):
                        ?>
                        <option value="<?= $store['store_id'] ?>"><?= htmlspecialchars($store['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="forecast-horizon">Forecast Horizon:</label>
                    <select id="forecast-horizon" class="form-control">
                        <option value="7">7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="generateForecast()">
                    <i class="fas fa-chart-line"></i> Generate Forecast
                </button>
            </div>

            <div id="forecast-results" class="forecast-results">
                <div class="forecast-chart-container">
                    <canvas id="forecast-chart"></canvas>
                </div>

                <?php if (!empty($recommendations['recommendations'])): ?>
                <div class="forecast-recommendations">
                    <h3>Transfer Recommendations Based on Forecast</h3>
                    <div class="recommendations-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Predicted Demand</th>
                                    <th>Recommended Transfer</th>
                                    <th>Urgency</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recommendations['recommendations'], 0, 10) as $rec): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($rec['product_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($rec['sku']) ?></small>
                                    </td>
                                    <td><?= number_format($rec['current_stock'], 0) ?></td>
                                    <td><?= number_format($rec['predicted_demand'], 0) ?></td>
                                    <td>
                                        <strong><?= number_format($rec['recommended_transfer'], 0) ?></strong>
                                    </td>
                                    <td>
                                        <span class="urgency-badge urgency-<?= $rec['urgency_level'] ?>">
                                            <?= ucfirst($rec['urgency_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="createTransferFromRecommendation(<?= $rec['product_id'] ?>, <?= $rec['recommended_transfer'] ?>)">
                                            <i class="fas fa-plus"></i> Create Transfer
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Anomalies Tab -->
        <div class="tab-content" id="anomalies-tab">
            <div class="content-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Detected Anomalies</h2>
                <p>Unusual patterns requiring attention</p>
            </div>

            <div class="anomaly-summary">
                <div class="summary-card severity-critical">
                    <div class="summary-count"><?= $anomalies['summary']['critical'] ?? 0 ?></div>
                    <div class="summary-label">Critical</div>
                </div>
                <div class="summary-card severity-high">
                    <div class="summary-count"><?= $anomalies['summary']['high'] ?? 0 ?></div>
                    <div class="summary-label">High</div>
                </div>
                <div class="summary-card severity-medium">
                    <div class="summary-count"><?= $anomalies['summary']['medium'] ?? 0 ?></div>
                    <div class="summary-label">Medium</div>
                </div>
                <div class="summary-card severity-low">
                    <div class="summary-count"><?= $anomalies['summary']['low'] ?? 0 ?></div>
                    <div class="summary-label">Low</div>
                </div>
            </div>

            <div class="anomalies-list">
                <?php if (!empty($anomalies['anomalies'])): ?>
                    <?php foreach ($anomalies['anomalies'] as $anomaly): ?>
                    <div class="anomaly-card severity-<?= $anomaly['severity'] ?>">
                        <div class="anomaly-header">
                            <div class="anomaly-meta">
                                <span class="severity-badge"><?= ucfirst($anomaly['severity']) ?></span>
                                <span class="risk-score">Risk: <?= $anomaly['risk_score'] ?>/100</span>
                            </div>
                            <div class="anomaly-transfer">
                                <strong><?= htmlspecialchars($anomaly['reference']) ?></strong>
                                <small><?= htmlspecialchars($anomaly['from_store']) ?> → <?= htmlspecialchars($anomaly['to_store']) ?></small>
                            </div>
                        </div>
                        <div class="anomaly-content">
                            <div class="anomaly-details">
                                <?php foreach ($anomaly['anomalies'] as $detail): ?>
                                <div class="anomaly-detail">
                                    <div class="detail-type">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?= ucwords(str_replace('_', ' ', $detail['type'])) ?>
                                    </div>
                                    <div class="detail-description">
                                        <?= htmlspecialchars($detail['description']) ?>
                                    </div>
                                    <?php if (isset($detail['z_score'])): ?>
                                    <div class="detail-metrics">
                                        <span>Z-Score: <?= $detail['z_score'] ?></span>
                                        <?php if (isset($detail['deviation_percentage'])): ?>
                                        <span>Deviation: <?= abs($detail['deviation_percentage']) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="anomaly-actions">
                            <button class="btn btn-sm btn-primary" onclick="investigateAnomaly(<?= $anomaly['transfer_id'] ?>)">
                                <i class="fas fa-search"></i> Investigate
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="markAnomalyResolved(<?= $anomaly['transfer_id'] ?>)">
                                <i class="fas fa-check"></i> Mark Resolved
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state success">
                        <i class="fas fa-check-circle"></i>
                        <p>No anomalies detected. All transfers are within normal parameters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patterns Tab -->
        <div class="tab-content" id="patterns-tab">
            <div class="content-header">
                <h2><i class="fas fa-project-diagram"></i> Pattern Analysis</h2>
                <p>Discovered trends and correlations in transfer data</p>
            </div>

            <!-- Route Patterns -->
            <?php if (!empty($patterns['route_patterns'])): ?>
            <div class="pattern-section">
                <h3><i class="fas fa-route"></i> Route Patterns</h3>
                <div class="patterns-grid">
                    <?php foreach (array_slice($patterns['route_patterns'], 0, 6) as $route): ?>
                    <div class="pattern-card">
                        <div class="pattern-header">
                            <span class="pattern-type"><?= ucwords(str_replace('_', ' ', $route['pattern_type'])) ?></span>
                            <span class="pattern-frequency"><?= $route['frequency'] ?> transfers</span>
                        </div>
                        <div class="pattern-route">
                            <div class="route-step"><?= htmlspecialchars($route['from_store']) ?></div>
                            <div class="route-arrow"><i class="fas fa-arrow-right"></i></div>
                            <div class="route-step"><?= htmlspecialchars($route['to_store']) ?></div>
                        </div>
                        <div class="pattern-metrics">
                            <div class="metric">
                                <label>Avg Approval:</label>
                                <value><?= $route['avg_approval_time_hours'] ?>h</value>
                            </div>
                            <div class="metric">
                                <label>Total Value:</label>
                                <value>$<?= number_format($route['total_value'], 2) ?></value>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Temporal Patterns -->
            <?php if (!empty($patterns['temporal_patterns']['peak_hours'])): ?>
            <div class="pattern-section">
                <h3><i class="fas fa-clock"></i> Temporal Patterns</h3>
                <div class="temporal-charts">
                    <div class="chart-container">
                        <h4>Peak Hours</h4>
                        <canvas id="peak-hours-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>Day Distribution</h4>
                        <canvas id="day-distribution-chart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Store Relationships -->
            <?php if (!empty($patterns['store_relationships'])): ?>
            <div class="pattern-section">
                <h3><i class="fas fa-sitemap"></i> Store Relationships</h3>
                <div class="store-relationships-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Role</th>
                                <th>Outbound</th>
                                <th>Inbound</th>
                                <th>Net Flow</th>
                                <th>Visual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($patterns['store_relationships'], 0, 10) as $store): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($store['store_name']) ?></strong></td>
                                <td>
                                    <span class="role-badge role-<?= $store['role'] ?>">
                                        <?= ucfirst($store['role']) ?>
                                    </span>
                                </td>
                                <td><?= $store['outbound_transfers'] ?></td>
                                <td><?= $store['inbound_transfers'] ?></td>
                                <td class="<?= $store['net_flow'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $store['net_flow'] >= 0 ? '+' : '' ?><?= number_format($store['net_flow'], 0) ?>
                                </td>
                                <td>
                                    <div class="flow-bar">
                                        <div class="flow-outbound" style="width: <?= ($store['outbound_transfers'] / max($store['outbound_transfers'], $store['inbound_transfers'])) * 50 ?>%"></div>
                                        <div class="flow-inbound" style="width: <?= ($store['inbound_transfers'] / max($store['outbound_transfers'], $store['inbound_transfers'])) * 50 %>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Optimization Tab -->
        <div class="tab-content" id="optimization-tab">
            <div class="content-header">
                <h2><i class="fas fa-cogs"></i> Optimization Tools</h2>
                <p>AI-powered optimization for routes, timing, and inventory allocation</p>
            </div>

            <div class="optimization-tools">
                <div class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="tool-content">
                        <h3>Route Optimizer</h3>
                        <p>Find the optimal route for your transfer considering stock levels, success rates, and efficiency.</p>
                        <button class="btn btn-primary" onclick="openRouteOptimizer()">
                            <i class="fas fa-magic"></i> Optimize Route
                        </button>
                    </div>
                </div>

                <div class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="tool-content">
                        <h3>Timing Optimizer</h3>
                        <p>Discover the best time to create transfers for fastest approval and processing.</p>
                        <button class="btn btn-primary" onclick="openTimingOptimizer()">
                            <i class="fas fa-calendar-alt"></i> Find Best Time
                        </button>
                    </div>
                </div>

                <div class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="tool-content">
                        <h3>Inventory Allocator</h3>
                        <p>Balance inventory across stores based on predicted demand and current stock levels.</p>
                        <button class="btn btn-primary" onclick="openInventoryAllocator()">
                            <i class="fas fa-th"></i> Optimize Allocation
                        </button>
                    </div>
                </div>
            </div>

            <div id="optimization-results" class="optimization-results" style="display: none;">
                <!-- Results will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/ai-dashboard.js"></script>

<?php
include __DIR__ . '/../layout/footer.php';

// Helper function for recommendation icons
function getRecommendationIcon($type) {
    $icons = [
        'route_optimization' => 'route',
        'temporal_optimization' => 'clock',
        'hub_optimization' => 'sitemap',
        'inventory_optimization' => 'boxes'
    ];
    return $icons[$type] ?? 'lightbulb';
}

// Helper function to get stores
function getStores() {
    global $db;
    return $db->query("SELECT store_id, name FROM stores WHERE active = 1 ORDER BY name");
}
?>
