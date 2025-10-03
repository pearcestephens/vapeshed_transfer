<?php
/**
 * Cross-Platform Business Intelligence Dashboard
 * 
 * Web interface for multi-database AI analytics
 * Displays insights from CIS, VapeShed, and Vaping Kiwi databases
 * 
 * Author: AI Enhanced System
 * Created: 2025-09-26
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/multi_database_ai_engine.php';

// Initialize the multi-database AI engine
$aiEngine = new MultiDatabaseAIEngine();
$businessIntelligence = $aiEngine->generateBusinessIntelligence();
$healthStatus = $aiEngine->healthCheck();
$schemas = $aiEngine->discoverAllSchemas();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Database AI Analytics | The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
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
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .metric-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-connected {
            background: var(--success-color);
            color: white;
        }
        
        .status-disconnected {
            background: var(--danger-color);
            color: white;
        }
        
        .status-initializing {
            background: var(--warning-color);
            color: white;
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .section-header h3 {
            margin: 0;
            font-weight: 300;
        }
        
        .data-source-card {
            border-left: 5px solid;
            padding-left: 1rem;
        }
        
        .cis-border { border-left-color: var(--primary-color); }
        .vapeshed-border { border-left-color: var(--secondary-color); }
        .vapingkiwi-border { border-left-color: var(--success-color); }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .table {
            margin: 0;
        }
        
        .table thead {
            background: var(--primary-color);
            color: white;
        }
        
        .json-display {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: var(--primary-color);
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-container">
                    
                    <!-- Header -->
                    <div class="text-center mb-5">
                        <h1 class="display-4 mb-3">
                            <i class="fas fa-brain text-primary me-3"></i>
                            Multi-Database AI Analytics
                        </h1>
                        <p class="lead text-muted">
                            Cross-platform business intelligence for The Vape Shed ecosystem
                        </p>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <span class="badge bg-primary px-3 py-2">CIS Database</span>
                            <span class="badge bg-info px-3 py-2">VapeShed Website</span>
                            <span class="badge bg-success px-3 py-2">Vaping Kiwi</span>
                        </div>
                    </div>
                    
                    <!-- Health Status Overview -->
                    <div class="section-header">
                        <h3><i class="fas fa-heartbeat me-2"></i>System Health Status</h3>
                    </div>
                    
                    <div class="row mb-5">
                        <?php foreach ($healthStatus as $key => $status): ?>
                            <?php if ($key === 'overall_status' || $key === 'checked_at') continue; ?>
                            <div class="col-md-4">
                                <div class="metric-card text-center">
                                    <?php
                                    $icon = 'fas fa-database';
                                    $color = 'text-secondary';
                                    $badgeClass = 'status-disconnected';
                                    
                                    if (is_array($status)) {
                                        if ($status['status'] === 'connected') {
                                            $color = 'text-success';
                                            $badgeClass = 'status-connected';
                                        } elseif ($status['status'] === 'error') {
                                            $color = 'text-danger';
                                            $badgeClass = 'status-disconnected';
                                        }
                                    }
                                    
                                    // Set specific icons and borders
                                    if (strpos($key, 'cis') !== false) {
                                        $icon = 'fas fa-cash-register';
                                    } elseif (strpos($key, 'vapeshed') !== false) {
                                        $icon = 'fas fa-globe-americas';
                                    } elseif (strpos($key, 'vapingkiwi') !== false) {
                                        $icon = 'fas fa-kiwi-bird';
                                    }
                                    ?>
                                    
                                    <div class="metric-icon <?= $color ?>">
                                        <i class="<?= $icon ?>"></i>
                                    </div>
                                    
                                    <div class="metric-value <?= $color ?>">
                                        <?= is_array($status) ? $status['status'] : $status ?>
                                    </div>
                                    
                                    <div class="metric-label">
                                        <?= ucwords(str_replace('_', ' ', $key)) ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <span class="status-badge <?= $badgeClass ?>">
                                            <?= is_array($status) ? $status['status'] : 'unknown' ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (is_array($status) && isset($status['type'])): ?>
                                        <div class="mt-2 text-muted small">
                                            <?= $status['type'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Database Schema Discovery -->
                    <div class="section-header">
                        <h3><i class="fas fa-sitemap me-2"></i>Database Schema Discovery</h3>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-4">
                            <div class="metric-card data-source-card cis-border">
                                <h5><i class="fas fa-cash-register me-2"></i>CIS Database</h5>
                                <?php if (isset($schemas['cis']['tables'])): ?>
                                    <div class="metric-value text-primary">
                                        <?= count($schemas['cis']['tables']) ?>
                                    </div>
                                    <div class="metric-label">Tables Available</div>
                                    <div class="mt-3 small">
                                        <strong>Key Tables:</strong><br>
                                        <?php 
                                        $keyTables = array_filter($schemas['cis']['tables'], function($table) {
                                            return strpos($table, 'vend_') !== false || strpos($table, 'transfer_') !== false;
                                        });
                                        echo implode(', ', array_slice($keyTables, 0, 5));
                                        if (count($keyTables) > 5) echo '...';
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-danger">Connection Error</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="metric-card data-source-card vapeshed-border">
                                <h5><i class="fas fa-globe-americas me-2"></i>VapeShed Website</h5>
                                <?php if (isset($schemas['vapeshed']['tables'])): ?>
                                    <div class="metric-value text-info">
                                        <?= count($schemas['vapeshed']['tables']) ?>
                                    </div>
                                    <div class="metric-label">Tables Available</div>
                                    <div class="mt-3 small">
                                        <strong>First 5 Tables:</strong><br>
                                        <?= implode(', ', array_slice($schemas['vapeshed']['tables'], 0, 5)) ?>
                                        <?php if (count($schemas['vapeshed']['tables']) > 5) echo '...'; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-danger">Connection Error</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="metric-card data-source-card vapingkiwi-border">
                                <h5><i class="fas fa-kiwi-bird me-2"></i>Vaping Kiwi</h5>
                                <?php if (isset($schemas['vapingkiwi']['tables'])): ?>
                                    <div class="metric-value text-success">
                                        <?= count($schemas['vapingkiwi']['tables']) ?>
                                    </div>
                                    <div class="metric-label">Tables Available</div>
                                    <div class="mt-3 small">
                                        <strong>First 5 Tables:</strong><br>
                                        <?= implode(', ', array_slice($schemas['vapingkiwi']['tables'], 0, 5)) ?>
                                        <?php if (count($schemas['vapingkiwi']['tables']) > 5) echo '...'; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-danger">Connection Error</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Intelligence Preview -->
                    <div class="section-header">
                        <h3><i class="fas fa-chart-line me-2"></i>Business Intelligence Preview</h3>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h5><i class="fas fa-users me-2"></i>Customer Intelligence</h5>
                                <div class="json-display">
                                    <?= json_encode($businessIntelligence['customer_intelligence'], JSON_PRETTY_PRINT) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="metric-card">
                                <h5><i class="fas fa-box me-2"></i>Product Intelligence</h5>
                                <div class="json-display">
                                    <?= json_encode($businessIntelligence['product_intelligence'], JSON_PRETTY_PRINT) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI Capabilities Overview -->
                    <div class="section-header">
                        <h3><i class="fas fa-robot me-2"></i>AI Analytics Capabilities</h3>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="metric-card">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="metric-icon text-primary">
                                            <i class="fas fa-brain"></i>
                                        </div>
                                        <h6>Cross-Platform Analytics</h6>
                                        <p class="small text-muted">Analyze data across CIS, VapeShed, and Vaping Kiwi</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="metric-icon text-info">
                                            <i class="fas fa-chart-area"></i>
                                        </div>
                                        <h6>Predictive Intelligence</h6>
                                        <p class="small text-muted">Machine learning for demand forecasting and trends</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="metric-icon text-success">
                                            <i class="fas fa-sync-alt"></i>
                                        </div>
                                        <h6>Real-Time Optimization</h6>
                                        <p class="small text-muted">Automatic inventory and transfer recommendations</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="metric-icon text-warning">
                                            <i class="fas fa-bullseye"></i>
                                        </div>
                                        <h6>Customer Targeting</h6>
                                        <p class="small text-muted">Personalized recommendations and marketing insights</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Information -->
                    <div class="row">
                        <div class="col-12">
                            <div class="metric-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6><i class="fas fa-info-circle me-2"></i>System Information</h6>
                                        <p class="small text-muted mb-0">
                                            Generated: <?= $businessIntelligence['generated_at'] ?> | 
                                            Status: <?= $healthStatus['overall_status'] ?> |
                                            Active Data Sources: <?= count(array_filter($businessIntelligence['data_sources'], function($status) { return $status === 'connected'; })) ?>/3
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge bg-success px-3 py-2">
                                            Multi-Database AI Operational
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
        
        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>