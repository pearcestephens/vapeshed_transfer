<?php
declare(strict_types=1);
/**
 * Unified Retail Intelligence Platform - Main Dashboard
 * Enterprise-grade control center for The Vape Shed
 * 
 * Covers ALL system functionality:
 * - Transfer Engine (stock optimization)
 * - Pricing Intelligence (competitive analysis)
 * - Market Crawler (competitor monitoring)
 * - Matching & Synonyms (product normalization)
 * - Forecast & Demand (heuristics & ML)
 * - Insights & Analytics (neuro intelligence)
 * - Guardrails & Policy (safety controls)
 * - Configuration Management
 * - System Health & Monitoring
 */

// Bootstrap the application
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/template.php';

// Security: Verify authenticated session
if (!isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Unified Intelligence Platform';
$currentModule = 'dashboard';
$currentUser = getCurrentUser();

// Include header template
include __DIR__ . '/../templates/header.php';
?>

<!-- Main Dashboard Container -->
<div class="dashboard-wrapper">
    
    <!-- Top Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="active-transfers">0</div>
                <div class="stat-label">Active Transfers</div>
            </div>
        </div>
        
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="pricing-proposals">0</div>
                <div class="stat-label">Pricing Proposals</div>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="active-alerts">0</div>
                <div class="stat-label">Active Alerts</div>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="fas fa-brain"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="insights-today">0</div>
                <div class="stat-label">Insights Today</div>
            </div>
        </div>
        
        <div class="stat-card stat-secondary">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="system-health">100%</div>
                <div class="stat-label">System Health</div>
            </div>
        </div>
    </div>

    <!-- Main Module Grid -->
    <div class="module-grid">
        
        <!-- Transfer Engine Module -->
        <div class="module-card" data-module="transfer">
            <div class="module-header">
                <div class="module-icon transfer-icon">
                    <i class="fas fa-truck-loading"></i>
                </div>
                <h3 class="module-title">Transfer Engine</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">Stock transfer optimization with DSR calculations and intelligent allocation.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Pending</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Today</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/transfer/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Pricing Intelligence Module -->
        <div class="module-card" data-module="pricing">
            <div class="module-header">
                <div class="module-icon pricing-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3 class="module-title">Pricing Intelligence</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">Competitive pricing analysis with margin-safe guardrails and market intelligence.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Proposals</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Applied</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/pricing/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Market Crawler Module -->
        <div class="module-card" data-module="crawler">
            <div class="module-header">
                <div class="module-icon crawler-icon">
                    <i class="fas fa-spider"></i>
                </div>
                <h3 class="module-title">Market Crawler</h3>
                <span class="module-status status-planned">Planned</span>
            </div>
            <div class="module-body">
                <p class="module-description">Competitor monitoring, price tracking, and market intelligence gathering.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Sites</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Products</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/crawler/" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> Coming Soon
                </a>
            </div>
        </div>

        <!-- Matching & Normalization Module -->
        <div class="module-card" data-module="matching">
            <div class="module-header">
                <div class="module-icon matching-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h3 class="module-title">Matching & Synonyms</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">Product identity resolution with brand normalization and fuzzy matching.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Matches</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0.00</span>
                        <span class="stat-text">Avg Conf</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/matching/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Forecast & Demand Module -->
        <div class="module-card" data-module="forecast">
            <div class="module-header">
                <div class="module-icon forecast-icon">
                    <i class="fas fa-chart-area"></i>
                </div>
                <h3 class="module-title">Forecast & Demand</h3>
                <span class="module-status status-beta">Beta</span>
            </div>
            <div class="module-body">
                <p class="module-description">Demand forecasting with heuristics, ML models, and safety stock calculations.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">SKUs</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0.0</span>
                        <span class="stat-text">WAPE</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/forecast/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Insights & Analytics Module -->
        <div class="module-card" data-module="insights">
            <div class="module-header">
                <div class="module-icon insights-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3 class="module-title">Neuro Insights</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">AI-powered pattern detection, anomaly alerts, and strategic recommendations.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Patterns</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Alerts</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/insights/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Guardrails & Policy Module -->
        <div class="module-card" data-module="guardrails">
            <div class="module-header">
                <div class="module-icon guardrails-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="module-title">Guardrails & Policy</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">Safety controls, policy enforcement, and automated decision governance.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Blocks</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0.0%</span>
                        <span class="stat-text">Rate</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/guardrails/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Image Clustering Module -->
        <div class="module-card" data-module="images">
            <div class="module-header">
                <div class="module-icon images-icon">
                    <i class="fas fa-images"></i>
                </div>
                <h3 class="module-title">Image Clustering</h3>
                <span class="module-status status-beta">Beta</span>
            </div>
            <div class="module-body">
                <p class="module-description">Perceptual hashing, duplicate detection, and BK-tree integrity checks.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Images</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Clusters</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/images/" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Configuration Module -->
        <div class="module-card" data-module="config">
            <div class="module-header">
                <div class="module-icon config-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3 class="module-title">Configuration</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">System configuration, namespace management, and audit trails.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Keys</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Changes</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/config/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- System Health Module -->
        <div class="module-card" data-module="health">
            <div class="module-header">
                <div class="module-icon health-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3 class="module-title">System Health</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">Real-time monitoring, performance metrics, and system diagnostics.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number" id="health-uptime">0.0%</span>
                        <span class="stat-text">Uptime</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number" id="health-latency">0ms</span>
                        <span class="stat-text">Latency</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/health/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Drift Monitoring Module -->
        <div class="module-card" data-module="drift">
            <div class="module-header">
                <div class="module-icon drift-icon">
                    <i class="fas fa-wave-square"></i>
                </div>
                <h3 class="module-title">Drift Monitoring</h3>
                <span class="module-status status-active">Active</span>
            </div>
            <div class="module-body">
                <p class="module-description">PSI calculations, distribution shift detection, and model degradation alerts.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0.00</span>
                        <span class="stat-text">PSI</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Alerts</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/drift/" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> View Module
                </a>
            </div>
        </div>

        <!-- Simulation Harness Module -->
        <div class="module-card" data-module="simulation">
            <div class="module-header">
                <div class="module-icon simulation-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3 class="module-title">Simulation Harness</h3>
                <span class="module-status status-planned">Planned</span>
            </div>
            <div class="module-body">
                <p class="module-description">What-if analysis, scenario testing, and safe replay of historical decisions.</p>
                <div class="module-stats">
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Scenarios</span>
                    </div>
                    <div class="module-stat">
                        <span class="stat-number">0</span>
                        <span class="stat-text">Replays</span>
                    </div>
                </div>
            </div>
            <div class="module-footer">
                <a href="/dashboard/simulation/" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-right"></i> Coming Soon
                </a>
            </div>
        </div>

    </div>

    <!-- Live Activity Feed -->
    <div class="activity-section">
        <div class="section-header">
            <h4><i class="fas fa-stream"></i> Live Activity Feed</h4>
            <div class="activity-controls">
                <button class="btn btn-sm btn-outline-secondary" id="pause-feed">
                    <i class="fas fa-pause"></i> Pause
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="clear-feed">
                    <i class="fas fa-eraser"></i> Clear
                </button>
            </div>
        </div>
        <div class="activity-feed" id="activity-feed">
            <div class="activity-item activity-info">
                <div class="activity-icon"><i class="fas fa-info-circle"></i></div>
                <div class="activity-content">
                    <div class="activity-title">Dashboard Loaded</div>
                    <div class="activity-time"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- SSE Connection Indicator -->
<div class="sse-indicator" id="sse-indicator">
    <div class="sse-dot"></div>
    <span class="sse-text">Connecting...</span>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
