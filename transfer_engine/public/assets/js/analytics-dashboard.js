/**
 * Analytics Dashboard JavaScript
 *
 * Handles all analytics dashboard functionality including:
 * - Chart rendering with Chart.js
 * - AJAX data loading
 * - Date range selection
 * - Export functionality
 * - Report scheduling
 * - Real-time updates
 *
 * @category   JavaScript
 * @package    VapeshedTransfer
 * @subpackage Analytics
 * @version    1.0.0
 */

(function() {
    'use strict';

    /**
     * Analytics Dashboard Manager
     */
    class AnalyticsDashboard {
        /**
         * Constructor
         */
        constructor() {
            this.charts = {};
            this.data = null;
            this.currentRange = 'last_30_days';
            this.csrfToken = '';

            this.init();
        }

        /**
         * Initialize dashboard
         */
        init() {
            // Load initial data from embedded JSON
            this.loadEmbeddedData();

            // Initialize charts
            this.initializeCharts();

            // Bind event handlers
            this.bindEvents();

            // Load dynamic data
            this.loadAllData();

            console.log('Analytics Dashboard initialized');
        }

        /**
         * Load embedded JSON data
         */
        loadEmbeddedData() {
            const dataElement = document.getElementById('analyticsData');
            if (dataElement) {
                try {
                    this.data = JSON.parse(dataElement.textContent);
                    console.log('Embedded data loaded:', this.data);
                } catch (e) {
                    console.error('Failed to parse embedded data:', e);
                    this.data = {};
                }
            }
        }

        /**
         * Initialize all charts
         */
        initializeCharts() {
            // Transfer Volume Chart (Line Chart)
            this.initTransferVolumeChart();

            // Status Breakdown Chart (Doughnut Chart)
            this.initStatusBreakdownChart();

            // Peak Hours Chart (Bar Chart)
            this.initPeakHoursChart();

            // Response Time Chart (Line Chart with multiple series)
            this.initResponseTimeChart();
        }

        /**
         * Initialize Transfer Volume Chart
         */
        initTransferVolumeChart() {
            const ctx = document.getElementById('transferVolumeChart');
            if (!ctx) return;

            const volumeData = this.data?.trends?.transfer_volume_trend || [];
            const labels = volumeData.map(d => d.date || d.label);
            const data = volumeData.map(d => d.count || d.value || 0);

            this.charts.transferVolume = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Transfers',
                        data: data,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize Status Breakdown Chart
         */
        initStatusBreakdownChart() {
            const ctx = document.getElementById('statusBreakdownChart');
            if (!ctx) return;

            const overview = this.data?.overview || {};
            const successful = overview.successful_transfers || 0;
            const failed = overview.failed_transfers || 0;
            const pending = overview.pending_transfers || 0;

            this.charts.statusBreakdown = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Successful', 'Failed', 'Pending'],
                    datasets: [{
                        data: [successful, failed, pending],
                        backgroundColor: [
                            'rgb(40, 167, 69)',
                            'rgb(220, 53, 69)',
                            'rgb(255, 193, 7)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize Peak Hours Chart
         */
        initPeakHoursChart() {
            const ctx = document.getElementById('peakHoursChart');
            if (!ctx) return;

            // Generate hourly data (0-23 hours)
            const hours = Array.from({length: 24}, (_, i) => i);
            const labels = hours.map(h => `${h}:00`);
            const data = hours.map(() => Math.floor(Math.random() * 100)); // Placeholder

            this.charts.peakHours = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Transfers',
                        data: data,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: 'rgb(102, 126, 234)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize Response Time Chart
         */
        initResponseTimeChart() {
            const ctx = document.getElementById('responseTimeChart');
            if (!ctx) return;

            const performanceData = this.data?.performance?.response_times || [];
            const labels = performanceData.map(d => d.date || d.label);

            this.charts.responseTime = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'P50 (Median)',
                            data: performanceData.map(d => d.p50 || 0),
                            borderColor: 'rgb(40, 167, 69)',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 2,
                            fill: false
                        },
                        {
                            label: 'P95',
                            data: performanceData.map(d => d.p95 || 0),
                            borderColor: 'rgb(255, 193, 7)',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            borderWidth: 2,
                            fill: false
                        },
                        {
                            label: 'P99',
                            data: performanceData.map(d => d.p99 || 0),
                            borderColor: 'rgb(220, 53, 69)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
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
                                text: 'Response Time (ms)'
                            }
                        }
                    }
                }
            });
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Date range selector
            document.querySelectorAll('[data-range]').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const range = el.getAttribute('data-range');
                    this.changeDateRange(range);
                });
            });

            // Custom date range
            const applyBtn = document.getElementById('applyCustomRange');
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    this.applyCustomDateRange();
                });
            }

            // Export buttons
            document.querySelectorAll('[data-export]').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const format = el.getAttribute('data-export');
                    this.exportReport(format);
                });
            });

            // Schedule report button
            const saveScheduleBtn = document.getElementById('saveScheduledReport');
            if (saveScheduleBtn) {
                saveScheduleBtn.addEventListener('click', () => {
                    this.saveScheduledReport();
                });
            }

            // Tab change events
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(el => {
                el.addEventListener('shown.bs.tab', (e) => {
                    const targetId = e.target.getAttribute('data-bs-target');
                    this.onTabChange(targetId);
                });
            });
        }

        /**
         * Change date range
         */
        changeDateRange(range) {
            this.currentRange = range;

            if (range === 'custom') {
                document.getElementById('customDateRange').style.display = 'block';
            } else {
                document.getElementById('customDateRange').style.display = 'none';
                this.loadAllData();
            }
        }

        /**
         * Apply custom date range
         */
        applyCustomDateRange() {
            const startDate = document.getElementById('customStartDate').value;
            const endDate = document.getElementById('customEndDate').value;

            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }

            this.loadAllData(startDate, endDate);
        }

        /**
         * Load all analytics data
         */
        async loadAllData(startDate = null, endDate = null) {
            try {
                // Show loading indicators
                this.showLoading();

                // Calculate dates if not provided
                if (!startDate || !endDate) {
                    const dates = this.calculateDateRange(this.currentRange);
                    startDate = dates.start;
                    endDate = dates.end;
                }

                // Load transfer analytics
                await this.loadTransferAnalytics(startDate, endDate);

                // Load API usage metrics
                await this.loadApiUsageMetrics(startDate, endDate);

                // Load performance metrics
                await this.loadPerformanceMetrics(startDate, endDate);

                // Hide loading indicators
                this.hideLoading();

            } catch (error) {
                console.error('Error loading analytics data:', error);
                this.showError('Failed to load analytics data');
            }
        }

        /**
         * Load transfer analytics
         */
        async loadTransferAnalytics(startDate, endDate) {
            const formData = new FormData();
            formData.append('csrf_token', this.getCsrfToken());
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            const response = await fetch('/admin/analytics/transfer-analytics', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.updateTransferAnalytics(result.data);
            }
        }

        /**
         * Load API usage metrics
         */
        async loadApiUsageMetrics(startDate, endDate) {
            const formData = new FormData();
            formData.append('csrf_token', this.getCsrfToken());
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            const response = await fetch('/admin/analytics/api-usage-metrics', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.updateApiUsageMetrics(result.data);
            }
        }

        /**
         * Load performance metrics
         */
        async loadPerformanceMetrics(startDate, endDate) {
            const formData = new FormData();
            formData.append('csrf_token', this.getCsrfToken());
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            const response = await fetch('/admin/analytics/performance-metrics', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.updatePerformanceMetrics(result.data);
            }
        }

        /**
         * Update transfer analytics display
         */
        updateTransferAnalytics(data) {
            // Update top routes table
            const tbody = document.getElementById('topRoutesTableBody');
            if (tbody && data.top_routes) {
                tbody.innerHTML = '';
                data.top_routes.slice(0, 10).forEach(route => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${this.escapeHtml(route.source)} → ${this.escapeHtml(route.destination)}</td>
                        <td class="text-end">${route.count}</td>
                        <td class="text-end">
                            <span class="badge bg-success">${route.success_rate}%</span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Update peak hours chart
            if (this.charts.peakHours && data.hourly_distribution) {
                this.charts.peakHours.data.datasets[0].data = data.hourly_distribution.map(d => d.count);
                this.charts.peakHours.update();
            }
        }

        /**
         * Update API usage metrics display
         */
        updateApiUsageMetrics(data) {
            // Update endpoint stats table
            const tbody = document.getElementById('endpointStatsTableBody');
            if (tbody && data.endpoint_stats) {
                tbody.innerHTML = '';
                data.endpoint_stats.slice(0, 10).forEach(endpoint => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><code>${this.escapeHtml(endpoint.path)}</code></td>
                        <td class="text-end">${endpoint.count.toLocaleString()}</td>
                        <td class="text-end">${endpoint.avg_response}ms</td>
                        <td class="text-end">
                            <span class="badge bg-${endpoint.error_rate > 5 ? 'danger' : 'success'}">
                                ${endpoint.error_rate}%
                            </span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Update rate limit widget
            const widget = document.getElementById('rateLimitWidget');
            if (widget && data.rate_limit_usage) {
                widget.innerHTML = '';
                Object.keys(data.rate_limit_usage).forEach(provider => {
                    const usage = data.rate_limit_usage[provider];
                    const statusClass = usage.status === 'critical' ? 'danger' : 
                                      usage.status === 'warning' ? 'warning' : 'success';

                    const div = document.createElement('div');
                    div.className = 'mb-3';
                    div.innerHTML = `
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">${provider.toUpperCase()}</small>
                            <small class="fw-bold">${usage.usage_percentage}%</small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-${statusClass}" role="progressbar"
                                 style="width: ${usage.usage_percentage}%"></div>
                        </div>
                        <small class="text-muted">${usage.remaining.toLocaleString()} remaining</small>
                    `;
                    widget.appendChild(div);
                });
            }
        }

        /**
         * Update performance metrics display
         */
        updatePerformanceMetrics(data) {
            // Update slow queries table
            const tbody = document.getElementById('slowQueriesTableBody');
            if (tbody && data.slow_queries) {
                tbody.innerHTML = '';
                data.slow_queries.slice(0, 10).forEach(query => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><code class="text-truncate d-inline-block" style="max-width: 300px;">${this.escapeHtml(query.query_text)}</code></td>
                        <td class="text-end">${query.avg_time}s</td>
                        <td class="text-end">${query.max_time}s</td>
                        <td class="text-end">${query.execution_count}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="alert('Query optimization coming soon')">
                                <i class="fas fa-wrench"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            // Update bottlenecks
            const container = document.getElementById('bottlenecksContainer');
            if (container && data.bottlenecks) {
                container.innerHTML = '';
                if (data.bottlenecks.length === 0) {
                    container.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> No bottlenecks detected!</div>';
                } else {
                    data.bottlenecks.forEach(bottleneck => {
                        const alert = document.createElement('div');
                        alert.className = `alert alert-${bottleneck.severity === 'high' ? 'danger' : 'warning'}`;
                        alert.innerHTML = `
                            <h6 class="alert-heading">${bottleneck.type.replace(/_/g, ' ').toUpperCase()}</h6>
                            <p class="mb-1">${this.escapeHtml(bottleneck.recommendation)}</p>
                            <small class="text-muted">Severity: ${bottleneck.severity}</small>
                        `;
                        container.appendChild(alert);
                    });
                }
            }
        }

        /**
         * Export report
         */
        async exportReport(format) {
            const formData = new FormData();
            formData.append('csrf_token', this.getCsrfToken());
            formData.append('format', format);
            formData.append('report_type', 'full');
            formData.append('start_date', this.data?.dateRange?.start || '');
            formData.append('end_date', this.data?.dateRange?.end || '');

            try {
                const response = await fetch('/admin/analytics/export-report', {
                    method: 'POST',
                    body: formData
                });

                // Handle file download
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `analytics_report.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                this.showSuccess(`Report exported successfully as ${format.toUpperCase()}`);
            } catch (error) {
                console.error('Export error:', error);
                this.showError('Failed to export report');
            }
        }

        /**
         * Save scheduled report
         */
        async saveScheduledReport() {
            const form = document.getElementById('scheduleReportForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('/admin/analytics/schedule-report', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    this.showSuccess('Report scheduled successfully');
                    bootstrap.Modal.getInstance(document.getElementById('scheduleReportModal')).hide();
                    form.reset();
                } else {
                    this.showError(result.message || 'Failed to schedule report');
                }
            } catch (error) {
                console.error('Schedule error:', error);
                this.showError('Failed to schedule report');
            }
        }

        /**
         * Handle tab changes
         */
        onTabChange(targetId) {
            // Refresh charts when tabs are shown
            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].resize();
                }
            });
        }

        /**
         * Calculate date range based on preset
         */
        calculateDateRange(range) {
            const end = new Date();
            let start = new Date();

            switch (range) {
                case 'today':
                    start = new Date();
                    break;
                case 'yesterday':
                    start = new Date(Date.now() - 86400000);
                    end.setTime(start.getTime());
                    break;
                case 'last_7_days':
                    start.setDate(start.getDate() - 7);
                    break;
                case 'last_30_days':
                    start.setDate(start.getDate() - 30);
                    break;
                case 'this_month':
                    start = new Date(start.getFullYear(), start.getMonth(), 1);
                    break;
                case 'last_month':
                    start = new Date(start.getFullYear(), start.getMonth() - 1, 1);
                    end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
                    break;
            }

            return {
                start: this.formatDate(start),
                end: this.formatDate(end)
            };
        }

        /**
         * Format date as YYYY-MM-DD
         */
        formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        /**
         * Get CSRF token
         */
        getCsrfToken() {
            return document.querySelector('input[name="csrf_token"]')?.value || '';
        }

        /**
         * Show loading indicators
         */
        showLoading() {
            document.querySelectorAll('.loading').forEach(el => {
                el.style.display = 'block';
            });
        }

        /**
         * Hide loading indicators
         */
        hideLoading() {
            document.querySelectorAll('.loading').forEach(el => {
                el.style.display = 'none';
            });
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            // Use Bootstrap toast or alert
            alert(message); // Placeholder
        }

        /**
         * Show error message
         */
        showError(message) {
            alert(message); // Placeholder
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.analyticsDashboard = new AnalyticsDashboard();
        });
    } else {
        window.analyticsDashboard = new AnalyticsDashboard();
    }

})();
