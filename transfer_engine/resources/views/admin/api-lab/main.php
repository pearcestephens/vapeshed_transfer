<?php
/**
 * API Testing Lab Main Hub
 * Central navigation and overview for all API testing tools
 */
?>

<div class="api-lab-hub">
    <!-- Header -->
    <div class="lab-header mb-4">
        <div class="row align-items-center">
            <div class="col-8">
                <h3 class="mb-2">
                    <i data-feather="activity" class="me-3"></i>
                    API Testing Laboratory
                </h3>
                <p class="lead text-muted mb-0">
                    Comprehensive testing suite for webhooks, APIs, sync processes, and system integration
                </p>
            </div>
            <div class="col-4 text-end">
                <div class="lab-status">
                    <div class="status-indicator">
                        <span class="badge bg-success">
                            <i data-feather="check-circle" width="14" class="me-1"></i>
                            All Systems Online
                        </span>
                    </div>
                    <small class="text-muted d-block mt-1">Last check: <span id="lastHealthCheck">--</span></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-card bg-primary text-white">
                <div class="metric-value" id="totalTests">0</div>
                <div class="metric-label">Tests Run Today</div>
                <div class="metric-icon">
                    <i data-feather="play-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card bg-success text-white">
                <div class="metric-value" id="successRate">0%</div>
                <div class="metric-label">Success Rate</div>
                <div class="metric-icon">
                    <i data-feather="check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card bg-warning text-white">
                <div class="metric-value" id="activeTests">0</div>
                <div class="metric-label">Active Tests</div>
                <div class="metric-icon">
                    <i data-feather="clock"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card bg-info text-white">
                <div class="metric-value" id="avgDuration">0s</div>
                <div class="metric-label">Avg Duration</div>
                <div class="metric-icon">
                    <i data-feather="zap"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Lab Tools Grid -->
    <div class="row">
        <!-- Webhook Test Lab -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="webhook-lab">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="send" class="me-2"></i>
                                Webhook Test Lab
                            </h6>
                            <span class="tool-status badge bg-light text-dark">Ready</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Test webhook endpoints with custom payloads, event simulation, and response analysis.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="edit-3" width="14" class="me-2"></i>
                                Custom payload editor
                            </div>
                            <div class="feature-item">
                                <i data-feather="play" width="14" class="me-2"></i>
                                Event simulation
                            </div>
                            <div class="feature-item">
                                <i data-feather="eye" width="14" class="me-2"></i>
                                Response analysis
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=webhook-lab" class="btn btn-primary">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Open Webhook Lab
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Last used: 2 hours ago</small>
                            <small class="text-muted">23 tests run</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vend API Tester -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="vend-tester">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="shopping-cart" class="me-2"></i>
                                Vend API Tester
                            </h6>
                            <span class="tool-status badge bg-light text-dark">Connected</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Test Vend API endpoints with authentication, query building, and request history.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="shield" width="14" class="me-2"></i>
                                OAuth2 authentication
                            </div>
                            <div class="feature-item">
                                <i data-feather="search" width="14" class="me-2"></i>
                                Query builder
                            </div>
                            <div class="feature-item">
                                <i data-feather="history" width="14" class="me-2"></i>
                                Request history
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=vend-tester" class="btn btn-success">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Open Vend Tester
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Last used: 45 minutes ago</small>
                            <small class="text-muted">67 requests made</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightspeed Sync Tester -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="lightspeed-tester">
                <div class="card h-100">
                    <div class="card-header bg-warning text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="refresh-cw" class="me-2"></i>
                                Lightspeed Sync Tester
                            </h6>
                            <span class="tool-status badge bg-light text-dark">Monitoring</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Test sync processes with real-time monitoring, progress tracking, and detailed reporting.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="activity" width="14" class="me-2"></i>
                                Real-time monitoring
                            </div>
                            <div class="feature-item">
                                <i data-feather="bar-chart-2" width="14" class="me-2"></i>
                                Progress tracking
                            </div>
                            <div class="feature-item">
                                <i data-feather="file-text" width="14" class="me-2"></i>
                                Detailed reports
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=lightspeed-tester" class="btn btn-warning">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Open Sync Tester
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Last sync: 1 hour ago</small>
                            <small class="text-muted">12 syncs today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Job Tester -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="queue-tester">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="layers" class="me-2"></i>
                                Queue Job Tester
                            </h6>
                            <span class="tool-status badge bg-light text-dark">Processing</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Test job queues with stress testing, worker monitoring, and job management tools.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="cpu" width="14" class="me-2"></i>
                                Stress testing
                            </div>
                            <div class="feature-item">
                                <i data-feather="monitor" width="14" class="me-2"></i>
                                Worker monitoring
                            </div>
                            <div class="feature-item">
                                <i data-feather="settings" width="14" class="me-2"></i>
                                Job management
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=queue-tester" class="btn btn-info">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Open Queue Tester
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Queue size: 3 jobs</small>
                            <small class="text-muted">87 jobs processed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Suite Runner -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="api-suite">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="check-square" class="me-2"></i>
                                Test Suite Runner
                            </h6>
                            <span class="tool-status badge bg-light text-dark">Ready</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Execute comprehensive test suites with parallel execution and detailed reporting.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="fast-forward" width="14" class="me-2"></i>
                                Parallel execution
                            </div>
                            <div class="feature-item">
                                <i data-feather="list" width="14" class="me-2"></i>
                                Test suite management
                            </div>
                            <div class="feature-item">
                                <i data-feather="bar-chart" width="14" class="me-2"></i>
                                Detailed reporting
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=api-suite" class="btn btn-dark">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Open Suite Runner
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Last run: 3 hours ago</small>
                            <small class="text-muted">15 suites available</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Code Snippet Library -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="lab-tool-card" data-tool="code-snippets">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="code" class="me-2"></i>
                                Code Snippet Library
                            </h6>
                            <span class="tool-status badge bg-light text-dark">57 Snippets</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Browse and copy code examples with integrated testing capabilities.
                        </p>
                        <div class="tool-features">
                            <div class="feature-item">
                                <i data-feather="copy" width="14" class="me-2"></i>
                                Copy-paste ready
                            </div>
                            <div class="feature-item">
                                <i data-feather="search" width="14" class="me-2"></i>
                                Searchable library
                            </div>
                            <div class="feature-item">
                                <i data-feather="play" width="14" class="me-2"></i>
                                Integrated testing
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="?page=code-snippets" class="btn btn-secondary">
                                <i data-feather="arrow-right" class="me-1"></i>
                                Browse Snippets
                            </a>
                        </div>
                        <div class="tool-stats mt-2 d-flex justify-content-between">
                            <small class="text-muted">Most popular: Transfer API</small>
                            <small class="text-muted">234 copies today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i data-feather="activity" class="me-2"></i>
                Recent Testing Activity
            </h6>
        </div>
        <div class="card-body">
            <div id="recentActivity" class="activity-feed">
                <!-- Activity items will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- API Lab Hub Styles -->
<style>
.api-lab-hub .lab-header {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
}

.api-lab-hub .metric-card {
    border-radius: 0.5rem;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.api-lab-hub .metric-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.api-lab-hub .metric-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.api-lab-hub .metric-icon {
    position: absolute;
    top: 1rem;
    right: 1rem;
    opacity: 0.3;
}

.api-lab-hub .metric-icon i {
    width: 2rem;
    height: 2rem;
}

.api-lab-hub .lab-tool-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.api-lab-hub .lab-tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.api-lab-hub .tool-features {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.api-lab-hub .feature-item {
    font-size: 0.875rem;
    color: #6c757d;
    display: flex;
    align-items: center;
}

.api-lab-hub .tool-stats {
    font-size: 0.75rem;
}

.api-lab-hub .activity-feed {
    max-height: 300px;
    overflow-y: auto;
}

.api-lab-hub .activity-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.api-lab-hub .activity-item:last-child {
    border-bottom: none;
}

.api-lab-hub .activity-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.api-lab-hub .activity-content {
    flex-grow: 1;
}

.api-lab-hub .activity-time {
    font-size: 0.75rem;
    color: #6c757d;
    margin-left: 1rem;
}

@media (max-width: 768px) {
    .api-lab-hub .metric-card {
        margin-bottom: 1rem;
    }

    .api-lab-hub .lab-tool-card {
        margin-bottom: 1rem;
    }
}
</style>

<!-- API Lab Hub JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ApiLabHub = {
        init() {
            this.loadStats();
            this.loadRecentActivity();
            this.setupAutoRefresh();
        },

        async loadStats() {
            try {
                const response = await fetch('/admin/api/dashboard/metrics', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    this.updateStats(result.result);
                }

            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        },

        updateStats(stats) {
            document.getElementById('totalTests').textContent = stats.total_tests || 0;
            document.getElementById('successRate').textContent = (stats.success_rate || 0) + '%';
            document.getElementById('activeTests').textContent = stats.active_tests || 0;
            document.getElementById('avgDuration').textContent = (stats.avg_duration || 0) + 's';
            document.getElementById('lastHealthCheck').textContent =
                stats.last_health_check || 'Never';
        },

        async loadRecentActivity() {
            const activities = this.getMockActivity();
            this.renderActivity(activities);
        },

        getMockActivity() {
            return [
                {
                    type: 'webhook',
                    icon: 'send',
                    color: 'primary',
                    message: 'Webhook test completed successfully',
                    details: 'Transfer webhook → Outlet 5',
                    time: '2 minutes ago'
                },
                {
                    type: 'vend',
                    icon: 'shopping-cart',
                    color: 'success',
                    message: 'Vend API request executed',
                    details: 'GET /products → 200 OK',
                    time: '15 minutes ago'
                },
                {
                    type: 'sync',
                    icon: 'refresh-cw',
                    color: 'warning',
                    message: 'Lightspeed sync initiated',
                    details: 'Full inventory sync started',
                    time: '32 minutes ago'
                },
                {
                    type: 'queue',
                    icon: 'layers',
                    color: 'info',
                    message: 'Queue stress test completed',
                    details: '100 jobs processed in 45s',
                    time: '1 hour ago'
                },
                {
                    type: 'suite',
                    icon: 'check-square',
                    color: 'dark',
                    message: 'Test suite execution finished',
                    details: 'Transfer suite: 9/9 tests passed',
                    time: '2 hours ago'
                },
                {
                    type: 'snippet',
                    icon: 'code',
                    color: 'secondary',
                    message: 'Code snippet copied',
                    details: 'Transfer API example',
                    time: '3 hours ago'
                }
            ];
        },

        renderActivity(activities) {
            const container = document.getElementById('recentActivity');
            container.innerHTML = activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon bg-${activity.color}">
                        <i data-feather="${activity.icon}" width="16" height="16" class="text-white"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">${activity.message}</div>
                        <small class="text-muted">${activity.details}</small>
                    </div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            `).join('');

            feather.replace();
        },

        setupAutoRefresh() {
            // Refresh stats every 30 seconds
            setInterval(() => {
                this.loadStats();
            }, 30000);

            // Refresh activity every 2 minutes
            setInterval(() => {
                this.loadRecentActivity();
            }, 120000);
        }
    };

    // Initialize
    ApiLabHub.init();
});
</script>