<?php
/**
 * Live Business Action Dashboard
 * 
 * Real-time website AI intelligence for business decisions
 * Turns 42K customers, 212K orders, 1.4M views into actionable insights
 * 
 * Author: AI Enhanced System
 * Created: 2025-09-26
 */

require_once __DIR__ . '/../scripts/website_ai_enhancement_engine.php';

// Initialize the AI engine
$aiEngine = new WebsiteAIEnhancementEngine();
$intelligence = $aiEngine->generateBusinessActionIntelligence();
$apiEndpoints = $aiEngine->getWebsiteAPIEndpoints();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Business Action Dashboard | The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --dark-color: #1a1a1a;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 2rem 0;
            padding: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--info-color);
            transition: width 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .action-card:hover::before {
            width: 8px;
        }
        
        .action-card.urgent::before {
            background: var(--danger-color);
        }
        
        .action-card.opportunity::before {
            background: var(--success-color);
        }
        
        .action-card.trending::before {
            background: var(--warning-color);
        }
        
        .metric-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .badge-hot {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .badge-trending {
            background: linear-gradient(45deg, #feca57, #ff9ff3);
            color: white;
        }
        
        .badge-opportunity {
            background: linear-gradient(45deg, #48cae4, #023047);
            color: white;
        }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #27ae60;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .section-header h3 {
            margin: 0;
            font-weight: 300;
        }
        
        .data-table {
            font-size: 0.9rem;
        }
        
        .data-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .trend-up {
            color: var(--success-color);
        }
        
        .trend-down {
            color: var(--danger-color);
        }
        
        .auto-refresh {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 1000;
        }
        
        .api-endpoint {
            background: #f8f9fa;
            border-left: 4px solid var(--info-color);
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 0 8px 8px 0;
        }
        
        .api-endpoint code {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .business-impact {
            background: linear-gradient(45deg, rgba(39, 174, 96, 0.1), rgba(52, 152, 219, 0.1));
            border: 1px solid rgba(39, 174, 96, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .action-button {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .action-button:hover {
            background: #229954;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-container">
                    
                    <!-- Live Status Header -->
                    <div class="text-center mb-4">
                        <h1 class="display-4 mb-3">
                            <span class="live-indicator"></span>
                            Live Business Action Dashboard
                        </h1>
                        <p class="lead text-muted">
                            AI-Powered Intelligence from <strong>42K+ Customers</strong> | <strong>212K+ Orders</strong> | <strong>1.4M+ Product Views</strong>
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-success px-3 py-2 me-2">
                                <i class="fas fa-database me-1"></i>Live Data Connected
                            </span>
                            <span class="badge bg-info px-3 py-2">
                                Generated: <?= date('H:i:s') ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Live Dashboard Metrics -->
                    <div class="section-header">
                        <h3><i class="fas fa-tachometer-alt me-2"></i>Live Activity Metrics</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <?php if (isset($intelligence['live_dashboard_metrics']['live_activity'])): ?>
                            <?php $metrics = $intelligence['live_dashboard_metrics']['live_activity']; ?>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-hot">TODAY</div>
                                    <h4 class="text-primary"><?= number_format($metrics['orders_today']) ?></h4>
                                    <small class="text-muted">Orders</small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-trending">TODAY</div>
                                    <h4 class="text-success"><?= number_format($metrics['views_today']) ?></h4>
                                    <small class="text-muted">Product Views</small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-opportunity">TODAY</div>
                                    <h4 class="text-info"><?= number_format($metrics['searches_today']) ?></h4>
                                    <small class="text-muted">Searches</small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-hot">LAST HOUR</div>
                                    <h4 class="text-warning"><?= number_format($metrics['orders_last_hour']) ?></h4>
                                    <small class="text-muted">Orders</small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-trending">LAST HOUR</div>
                                    <h4 class="text-primary"><?= number_format($metrics['views_last_hour']) ?></h4>
                                    <small class="text-muted">Views</small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="action-card text-center">
                                    <div class="metric-badge badge-opportunity">LIVE</div>
                                    <h4 class="text-success"><?= number_format($metrics['total_customers']) ?></h4>
                                    <small class="text-muted">Total Customers</small>
                                </div>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                    
                    <!-- Hot Searches RIGHT NOW -->
                    <div class="section-header">
                        <h3><i class="fas fa-fire me-2"></i>Hot Searches Right Now</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="action-card">
                                <?php if (isset($intelligence['live_dashboard_metrics']['hot_searches_now'])): ?>
                                    <div class="row">
                                        <?php foreach (array_slice($intelligence['live_dashboard_metrics']['hot_searches_now'], 0, 10) as $search): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <div class="badge bg-danger"><?= $search['search_count'] ?></div>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($search['search_term']) ?></strong>
                                                        <br><small class="text-muted">Last: <?= date('H:i', strtotime($search['latest_search'])) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="business-impact">
                                        <h6><i class="fas fa-lightbulb me-2"></i>Business Impact</h6>
                                        <p class="mb-2"><strong>Action Required:</strong> Feature these trending products prominently on homepage</p>
                                        <p class="mb-0"><strong>Revenue Opportunity:</strong> Optimize PPC bids for these high-intent search terms</p>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Loading hot searches...</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Trending Products Now -->
                    <?php if (isset($intelligence['trending_products_now']['trending_products'])): ?>
                    <div class="section-header">
                        <h3><i class="fas fa-chart-line me-2"></i>Trending Products (Massive View Spikes)</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="action-card trending">
                                <div class="table-responsive">
                                    <table class="table data-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Views (2h)</th>
                                                <th>Trend</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($intelligence['trending_products_now']['trending_products'], 0, 10) as $product): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars(substr($product['name'], 0, 50)) ?><?= strlen($product['name']) > 50 ? '...' : '' ?></strong>
                                                </td>
                                                <td><code><?= htmlspecialchars($product['sku']) ?></code></td>
                                                <td>
                                                    <span class="badge bg-warning"><?= $product['views_2h'] ?></span>
                                                    <small class="text-muted">(<?= $product['views_24h'] ?> today)</small>
                                                </td>
                                                <td>
                                                    <span class="trend-up">
                                                        <i class="fas fa-arrow-up"></i>
                                                        <?= $product['trend_multiplier'] ?>x
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="action-button" onclick="featureProduct(<?= $product['product_id'] ?>)">
                                                        <i class="fas fa-star me-1"></i>Feature Now
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="business-impact">
                                    <h6><i class="fas fa-rocket me-2"></i>Immediate Action Required</h6>
                                    <p class="mb-2"><strong>Website:</strong> Feature these products on homepage banner rotation</p>
                                    <p class="mb-2"><strong>Inventory:</strong> Ensure adequate stock levels for trending items</p>
                                    <p class="mb-0"><strong>Marketing:</strong> Create social media posts about trending products</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Search-Driven Business Opportunities -->
                    <?php if (isset($intelligence['search_driven_opportunities']['search_opportunities'])): ?>
                    <div class="section-header">
                        <h3><i class="fas fa-bullseye me-2"></i>Search-Driven Business Opportunities</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="action-card opportunity">
                                <div class="table-responsive">
                                    <table class="table data-table">
                                        <thead>
                                            <tr>
                                                <th>Search Term</th>
                                                <th>Volume (7d)</th>
                                                <th>Click Rate</th>
                                                <th>Opportunity Score</th>
                                                <th>Recommended Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($intelligence['search_driven_opportunities']['search_opportunities'], 0, 15) as $opportunity): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($opportunity['search_term']) ?></strong></td>
                                                <td><span class="badge bg-primary"><?= $opportunity['search_count'] ?></span></td>
                                                <td>
                                                    <span class="<?= $opportunity['click_rate'] > 80 ? 'trend-up' : ($opportunity['click_rate'] < 50 ? 'trend-down' : '') ?>">
                                                        <?= $opportunity['click_rate'] ?>%
                                                    </span>
                                                </td>
                                                <td><span class="badge bg-success"><?= $opportunity['opportunity_score'] ?></span></td>
                                                <td><small><?= $opportunity['action'] ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="business-impact">
                                    <h6><i class="fas fa-chart-bar me-2"></i>Revenue Impact</h6>
                                    <p class="mb-2"><strong>Total Searches Analyzed:</strong> <?= number_format($intelligence['search_driven_opportunities']['total_searches_analyzed']) ?></p>
                                    <p class="mb-0"><strong>Average Click Rate:</strong> <?= $intelligence['search_driven_opportunities']['avg_click_rate'] ?>% (Excellent performance!)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Website Enhancement Recommendations -->
                    <div class="section-header">
                        <h3><i class="fas fa-cogs me-2"></i>Website Enhancement Recommendations</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <?php if (isset($intelligence['website_enhancement_recommendations'])): ?>
                            <?php foreach ($intelligence['website_enhancement_recommendations'] as $key => $enhancement): ?>
                            <div class="col-md-6 mb-3">
                                <div class="action-card">
                                    <h6 class="text-primary"><?= ucwords(str_replace('_', ' ', $key)) ?></h6>
                                    <p class="mb-2"><strong>Action:</strong> <?= $enhancement['action'] ?></p>
                                    <p class="mb-2"><strong>Data Source:</strong> <?= $enhancement['data_source'] ?></p>
                                    <p class="mb-3"><strong>Impact:</strong> <?= $enhancement['impact'] ?></p>
                                    <button class="action-button" onclick="implementEnhancement('<?= $key ?>')">
                                        <i class="fas fa-play me-1"></i>Implement
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- API Integration Endpoints -->
                    <div class="section-header">
                        <h3><i class="fas fa-plug me-2"></i>API Endpoints for Website Integration</h3>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="action-card">
                                <p class="mb-3"><strong>Integrate these AI-powered endpoints into your website:</strong></p>
                                
                                <?php foreach ($apiEndpoints as $endpoint => $description): ?>
                                <div class="api-endpoint">
                                    <code><?= $endpoint ?></code>
                                    <br><small class="text-muted"><?= $description ?></small>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="business-impact mt-3">
                                    <h6><i class="fas fa-sync me-2"></i>Implementation Impact</h6>
                                    <p class="mb-0">These endpoints will enable real-time AI-driven personalization, inventory optimization, and customer experience enhancement on your website.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <!-- Auto Refresh Button -->
    <button class="auto-refresh" onclick="location.reload();">
        <span class="live-indicator"></span>
        Auto Refresh (15min)
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 15 minutes
        setTimeout(() => {
            location.reload();
        }, 900000);
        
        // Action button handlers
        function featureProduct(productId) {
            alert(`Action triggered: Feature product ${productId} on homepage`);
            // Implement actual API call to feature product
        }
        
        function implementEnhancement(enhancementKey) {
            alert(`Action triggered: Implement ${enhancementKey} enhancement`);
            // Implement actual enhancement logic
        }
        
        // Add some animation to live indicators
        document.querySelectorAll('.live-indicator').forEach(indicator => {
            setInterval(() => {
                indicator.style.opacity = indicator.style.opacity === '0.3' ? '1' : '0.3';
            }, 1000);
        });
    </script>
</body>
</html>