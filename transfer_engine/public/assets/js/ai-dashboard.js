/**
 * AI Dashboard JavaScript
 * 
 * Handles AI insights dashboard interactions, chart rendering, and real-time updates.
 * 
 * @package     VapeShed Transfer Engine
 * @subpackage  Assets
 * @version     1.0.0
 */

(function() {
    'use strict';

    // Chart instances
    let forecastChart = null;
    let peakHoursChart = null;
    let dayDistributionChart = null;

    /**
     * Initialize dashboard on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        initializeTabs();
        initializeCharts();
        setupEventListeners();
        startAutoRefresh();
    });

    /**
     * Initialize tab navigation
     */
    function initializeTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');

                // Update active states
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');

                // Load tab-specific data
                loadTabData(tabName);
            });
        });
    }

    /**
     * Initialize charts
     */
    function initializeCharts() {
        // Initialize forecast chart if canvas exists
        const forecastCanvas = document.getElementById('forecast-chart');
        if (forecastCanvas) {
            initializeForecastChart(forecastCanvas);
        }

        // Initialize peak hours chart
        const peakHoursCanvas = document.getElementById('peak-hours-chart');
        if (peakHoursCanvas) {
            initializePeakHoursChart(peakHoursCanvas);
        }

        // Initialize day distribution chart
        const dayDistCanvas = document.getElementById('day-distribution-chart');
        if (dayDistCanvas) {
            initializeDayDistributionChart(dayDistCanvas);
        }
    }

    /**
     * Initialize forecast chart
     */
    function initializeForecastChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        forecastChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Predicted Demand',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Upper Confidence',
                    data: [],
                    borderColor: 'rgba(75, 192, 192, 0.3)',
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                }, {
                    label: 'Lower Confidence',
                    data: [],
                    borderColor: 'rgba(75, 192, 192, 0.3)',
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Demand Forecast (30 Days)'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize peak hours chart
     */
    function initializePeakHoursChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        peakHoursChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    label: 'Transfer Count',
                    data: [],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Transfers by Hour of Day'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Transfers'
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize day distribution chart
     */
    function initializeDayDistributionChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        dayDistributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(199, 199, 199, 0.5)'
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 206, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(255, 159, 64)',
                        'rgb(199, 199, 199)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Transfers by Day of Week'
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Forecast generation
        const generateBtn = document.querySelector('[onclick="generateForecast()"]');
        if (generateBtn) {
            generateBtn.addEventListener('click', generateForecast);
        }
    }

    /**
     * Load tab-specific data
     */
    function loadTabData(tabName) {
        switch (tabName) {
            case 'forecasts':
                loadForecastData();
                break;
            case 'patterns':
                loadPatternData();
                break;
            case 'anomalies':
                // Anomalies loaded on page load
                break;
            case 'optimization':
                // Tools loaded on page load
                break;
        }
    }

    /**
     * Load forecast data
     */
    function loadForecastData() {
        const storeSelect = document.getElementById('forecast-store');
        const horizonSelect = document.getElementById('forecast-horizon');
        
        if (!storeSelect.value) {
            return;
        }

        const storeId = storeSelect.value;
        const horizon = horizonSelect.value;

        showLoading('forecast-results');

        fetch(`/api/ai/forecast?store_id=${storeId}&horizon=${horizon}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateForecastChart(data.data);
                    hideLoading('forecast-results');
                } else {
                    showError('forecast-results', data.error.message);
                }
            })
            .catch(error => {
                console.error('Forecast load error:', error);
                showError('forecast-results', 'Failed to load forecast data');
            });
    }

    /**
     * Update forecast chart with data
     */
    function updateForecastChart(forecastData) {
        if (!forecastChart) return;

        const dates = forecastData.dates || [];
        const predictions = forecastData.predictions || {};
        const confidenceIntervals = forecastData.confidence_intervals || {};

        const predictionValues = dates.map(date => predictions[date] || 0);
        const upperBounds = dates.map(date => confidenceIntervals[date]?.upper || 0);
        const lowerBounds = dates.map(date => confidenceIntervals[date]?.lower || 0);

        forecastChart.data.labels = dates;
        forecastChart.data.datasets[0].data = predictionValues;
        forecastChart.data.datasets[1].data = upperBounds;
        forecastChart.data.datasets[2].data = lowerBounds;
        
        forecastChart.update();
    }

    /**
     * Load pattern data
     */
    function loadPatternData() {
        // Peak hours chart
        if (peakHoursChart && window.temporalPatterns) {
            const hourData = window.temporalPatterns.hour_distribution || [];
            const hourCounts = new Array(24).fill(0);
            
            hourData.forEach(item => {
                hourCounts[item.hour] = item.transfer_count;
            });
            
            peakHoursChart.data.datasets[0].data = hourCounts;
            peakHoursChart.update();
        }

        // Day distribution chart
        if (dayDistributionChart && window.temporalPatterns) {
            const dayData = window.temporalPatterns.day_distribution || [];
            const dayCounts = dayData.map(item => item.transfer_count);
            
            dayDistributionChart.data.datasets[0].data = dayCounts;
            dayDistributionChart.update();
        }
    }

    /**
     * Generate forecast
     */
    window.generateForecast = function() {
        loadForecastData();
    };

    /**
     * Load store forecast
     */
    window.loadStoreForecast = function(storeId) {
        if (storeId) {
            loadForecastData();
        }
    };

    /**
     * Implement recommendation
     */
    window.implementRecommendation = function(recommendation) {
        if (confirm(`Implement recommendation: ${recommendation.title}?`)) {
            showLoading('recommendations-tab');
            
            fetch('/api/ai/implement-recommendation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify(recommendation)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading('recommendations-tab');
                if (data.success) {
                    showToast('Recommendation implemented successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error.message, 'error');
                }
            })
            .catch(error => {
                hideLoading('recommendations-tab');
                console.error('Implementation error:', error);
                showToast('Failed to implement recommendation', 'error');
            });
        }
    };

    /**
     * View recommendation details
     */
    window.viewRecommendationDetails = function(recommendation) {
        const modal = createModal('Recommendation Details');
        modal.setContent(`
            <div class="recommendation-details">
                <h3>${recommendation.title}</h3>
                <p>${recommendation.description}</p>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Type:</label>
                        <value>${recommendation.type}</value>
                    </div>
                    <div class="detail-item">
                        <label>Priority:</label>
                        <value class="priority-${recommendation.priority}">${recommendation.priority}</value>
                    </div>
                    ${recommendation.affected_routes ? `
                    <div class="detail-item">
                        <label>Affected Routes:</label>
                        <value>${recommendation.affected_routes}</value>
                    </div>
                    ` : ''}
                </div>
            </div>
        `);
        modal.show();
    };

    /**
     * Investigate anomaly
     */
    window.investigateAnomaly = function(transferId) {
        window.location.href = `/transfers/${transferId}?highlight=anomaly`;
    };

    /**
     * Mark anomaly as resolved
     */
    window.markAnomalyResolved = function(transferId) {
        if (confirm('Mark this anomaly as resolved?')) {
            fetch(`/api/anomalies/${transferId}/resolve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Anomaly marked as resolved', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error.message, 'error');
                }
            })
            .catch(error => {
                console.error('Resolve error:', error);
                showToast('Failed to resolve anomaly', 'error');
            });
        }
    };

    /**
     * Create transfer from recommendation
     */
    window.createTransferFromRecommendation = function(productId, quantity) {
        window.location.href = `/transfers/create?product_id=${productId}&quantity=${quantity}&source=ai`;
    };

    /**
     * Open route optimizer
     */
    window.openRouteOptimizer = function() {
        const modal = createModal('Route Optimizer');
        modal.setContent(`
            <div class="optimizer-form">
                <div class="form-group">
                    <label>Product:</label>
                    <input type="text" id="opt-product" class="form-control" placeholder="Search product...">
                </div>
                <div class="form-group">
                    <label>From Store:</label>
                    <select id="opt-from-store" class="form-control">
                        <option value="">Select...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>To Store:</label>
                    <select id="opt-to-store" class="form-control">
                        <option value="">Select...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" id="opt-quantity" class="form-control" min="1">
                </div>
                <button class="btn btn-primary" onclick="runRouteOptimization()">
                    <i class="fas fa-magic"></i> Optimize Route
                </button>
            </div>
            <div id="route-optimization-results"></div>
        `);
        modal.show();
        
        // Load stores for selects
        loadStoresForOptimizer();
    };

    /**
     * Open timing optimizer
     */
    window.openTimingOptimizer = function() {
        const modal = createModal('Timing Optimizer');
        modal.setContent(`
            <div class="optimizer-form">
                <div class="form-group">
                    <label>From Store:</label>
                    <select id="timing-from-store" class="form-control">
                        <option value="">Select...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>To Store:</label>
                    <select id="timing-to-store" class="form-control">
                        <option value="">Select...</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="runTimingOptimization()">
                    <i class="fas fa-calendar-alt"></i> Find Optimal Time
                </button>
            </div>
            <div id="timing-optimization-results"></div>
        `);
        modal.show();
        
        loadStoresForOptimizer();
    };

    /**
     * Open inventory allocator
     */
    window.openInventoryAllocator = function() {
        const modal = createModal('Inventory Allocator');
        modal.setContent(`
            <div class="optimizer-form">
                <div class="form-group">
                    <label>Product:</label>
                    <input type="text" id="alloc-product" class="form-control" placeholder="Search product...">
                </div>
                <button class="btn btn-primary" onclick="runInventoryAllocation()">
                    <i class="fas fa-th"></i> Optimize Allocation
                </button>
            </div>
            <div id="allocation-optimization-results"></div>
        `);
        modal.show();
    };

    /**
     * Refresh all insights
     */
    window.refreshAllInsights = function() {
        showToast('Refreshing all insights...', 'info');
        
        fetch('/api/ai/refresh-all', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Insights refreshed successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.error.message, 'error');
            }
        })
        .catch(error => {
            console.error('Refresh error:', error);
            showToast('Failed to refresh insights', 'error');
        });
    };

    /**
     * Export insights report
     */
    window.exportInsights = function() {
        showToast('Generating report...', 'info');
        window.location.href = '/api/ai/export-report';
    };

    /**
     * Start auto-refresh (every 5 minutes)
     */
    function startAutoRefresh() {
        setInterval(() => {
            // Refresh current tab data silently
            const activeTab = document.querySelector('.tab-btn.active');
            if (activeTab) {
                const tabName = activeTab.getAttribute('data-tab');
                loadTabData(tabName);
            }
        }, 300000); // 5 minutes
    }

    /**
     * Utility: Show loading indicator
     */
    function showLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.add('loading');
        }
    }

    /**
     * Utility: Hide loading indicator
     */
    function hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.classList.remove('loading');
        }
    }

    /**
     * Utility: Show error message
     */
    function showError(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
        }
    }

    /**
     * Utility: Get CSRF token
     */
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Utility: Show toast notification
     */
    function showToast(message, type = 'info') {
        // Implementation depends on your toast library
        console.log(`[${type}] ${message}`);
    }

    /**
     * Utility: Create modal
     */
    function createModal(title) {
        // Implementation depends on your modal library
        return {
            setContent: function(html) {
                console.log('Modal content:', html);
            },
            show: function() {
                console.log('Showing modal:', title);
            }
        };
    }

    /**
     * Load stores for optimizer selects
     */
    function loadStoresForOptimizer() {
        fetch('/api/stores')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stores = data.data;
                    const selects = document.querySelectorAll('#opt-from-store, #opt-to-store, #timing-from-store, #timing-to-store');
                    selects.forEach(select => {
                        stores.forEach(store => {
                            const option = document.createElement('option');
                            option.value = store.store_id;
                            option.textContent = store.name;
                            select.appendChild(option);
                        });
                    });
                }
            });
    }

})();
