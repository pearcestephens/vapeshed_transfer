/**
 * Chart Components for Analytics Dashboard
 *
 * Reusable Chart.js configuration components for:
 * - Line charts (trends, time series)
 * - Bar charts (distributions, comparisons)
 * - Doughnut charts (proportions, breakdowns)
 * - Mixed charts (multiple metrics)
 *
 * @category   JavaScript
 * @package    VapeshedTransfer
 * @subpackage Analytics/Charts
 * @version    1.0.0
 */

(function(window) {
    'use strict';

    /**
     * Chart configuration factory
     */
    const ChartComponents = {
        /**
         * Default color palette
         */
        colors: {
            primary: 'rgb(102, 126, 234)',
            success: 'rgb(40, 167, 69)',
            danger: 'rgb(220, 53, 69)',
            warning: 'rgb(255, 193, 7)',
            info: 'rgb(23, 162, 184)',
            secondary: 'rgb(108, 117, 125)',
            light: 'rgb(248, 249, 250)',
            dark: 'rgb(52, 58, 64)'
        },

        /**
         * Get color with alpha
         */
        rgba: function(color, alpha) {
            return color.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
        },

        /**
         * Default chart options
         */
        defaultOptions: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1
                }
            }
        },

        /**
         * Create line chart configuration
         */
        lineChart: function(config) {
            const defaults = {
                type: 'line',
                data: {
                    labels: config.labels || [],
                    datasets: []
                },
                options: {
                    ...this.defaultOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: config.precision || 0
                            },
                            title: {
                                display: !!config.yAxisLabel,
                                text: config.yAxisLabel || ''
                            }
                        },
                        x: {
                            title: {
                                display: !!config.xAxisLabel,
                                text: config.xAxisLabel || ''
                            }
                        }
                    }
                }
            };

            // Add datasets
            if (config.datasets) {
                config.datasets.forEach((dataset, index) => {
                    const color = dataset.color || Object.values(this.colors)[index % Object.keys(this.colors).length];
                    defaults.data.datasets.push({
                        label: dataset.label || `Series ${index + 1}`,
                        data: dataset.data || [],
                        borderColor: color,
                        backgroundColor: this.rgba(color, 0.1),
                        borderWidth: 2,
                        fill: dataset.fill !== undefined ? dataset.fill : true,
                        tension: dataset.tension || 0.4,
                        pointRadius: dataset.pointRadius || 3,
                        pointHoverRadius: dataset.pointHoverRadius || 5
                    });
                });
            }

            return defaults;
        },

        /**
         * Create bar chart configuration
         */
        barChart: function(config) {
            const defaults = {
                type: 'bar',
                data: {
                    labels: config.labels || [],
                    datasets: []
                },
                options: {
                    ...this.defaultOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: config.precision || 0
                            },
                            title: {
                                display: !!config.yAxisLabel,
                                text: config.yAxisLabel || ''
                            }
                        },
                        x: {
                            title: {
                                display: !!config.xAxisLabel,
                                text: config.xAxisLabel || ''
                            }
                        }
                    }
                }
            };

            // Add datasets
            if (config.datasets) {
                config.datasets.forEach((dataset, index) => {
                    const color = dataset.color || Object.values(this.colors)[index % Object.keys(this.colors).length];
                    defaults.data.datasets.push({
                        label: dataset.label || `Series ${index + 1}`,
                        data: dataset.data || [],
                        backgroundColor: this.rgba(color, 0.7),
                        borderColor: color,
                        borderWidth: 1,
                        barThickness: dataset.barThickness || 'flex',
                        maxBarThickness: dataset.maxBarThickness || 50
                    });
                });
            }

            return defaults;
        },

        /**
         * Create horizontal bar chart configuration
         */
        horizontalBarChart: function(config) {
            const barConfig = this.barChart(config);
            barConfig.options.indexAxis = 'y';
            return barConfig;
        },

        /**
         * Create doughnut chart configuration
         */
        doughnutChart: function(config) {
            const colors = config.colors || [
                this.colors.success,
                this.colors.danger,
                this.colors.warning,
                this.colors.info,
                this.colors.primary
            ];

            return {
                type: 'doughnut',
                data: {
                    labels: config.labels || [],
                    datasets: [{
                        data: config.data || [],
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: config.maintainAspectRatio !== undefined ? config.maintainAspectRatio : true,
                    plugins: {
                        legend: {
                            display: config.showLegend !== undefined ? config.showLegend : true,
                            position: config.legendPosition || 'bottom'
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
            };
        },

        /**
         * Create pie chart configuration
         */
        pieChart: function(config) {
            const doughnutConfig = this.doughnutChart(config);
            doughnutConfig.type = 'pie';
            return doughnutConfig;
        },

        /**
         * Create mixed chart configuration (line + bar)
         */
        mixedChart: function(config) {
            const defaults = {
                type: 'bar',
                data: {
                    labels: config.labels || [],
                    datasets: []
                },
                options: {
                    ...this.defaultOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: !!config.yAxisLabel,
                                text: config.yAxisLabel || ''
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: !!config.y2AxisLabel,
                                text: config.y2AxisLabel || ''
                            }
                        }
                    }
                }
            };

            // Add datasets
            if (config.datasets) {
                config.datasets.forEach((dataset, index) => {
                    const color = dataset.color || Object.values(this.colors)[index % Object.keys(this.colors).length];
                    const baseDataset = {
                        label: dataset.label || `Series ${index + 1}`,
                        data: dataset.data || [],
                        yAxisID: dataset.yAxis || 'y'
                    };

                    if (dataset.type === 'line') {
                        defaults.data.datasets.push({
                            ...baseDataset,
                            type: 'line',
                            borderColor: color,
                            backgroundColor: this.rgba(color, 0.1),
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4
                        });
                    } else {
                        defaults.data.datasets.push({
                            ...baseDataset,
                            backgroundColor: this.rgba(color, 0.7),
                            borderColor: color,
                            borderWidth: 1
                        });
                    }
                });
            }

            return defaults;
        },

        /**
         * Create stacked bar chart configuration
         */
        stackedBarChart: function(config) {
            const barConfig = this.barChart(config);
            barConfig.options.scales.x.stacked = true;
            barConfig.options.scales.y.stacked = true;
            return barConfig;
        },

        /**
         * Create area chart configuration
         */
        areaChart: function(config) {
            const lineConfig = this.lineChart(config);
            lineConfig.data.datasets.forEach(dataset => {
                dataset.fill = true;
                dataset.backgroundColor = this.rgba(dataset.borderColor, 0.2);
            });
            return lineConfig;
        },

        /**
         * Create radar chart configuration
         */
        radarChart: function(config) {
            const colors = config.colors || [this.colors.primary, this.colors.success];

            return {
                type: 'radar',
                data: {
                    labels: config.labels || [],
                    datasets: (config.datasets || []).map((dataset, index) => {
                        const color = dataset.color || colors[index % colors.length];
                        return {
                            label: dataset.label || `Dataset ${index + 1}`,
                            data: dataset.data || [],
                            borderColor: color,
                            backgroundColor: this.rgba(color, 0.2),
                            borderWidth: 2,
                            pointBackgroundColor: color,
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: color
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: config.showLegend !== undefined ? config.showLegend : true,
                            position: 'top'
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: config.stepSize || undefined
                            }
                        }
                    }
                }
            };
        },

        /**
         * Create scatter chart configuration
         */
        scatterChart: function(config) {
            return {
                type: 'scatter',
                data: {
                    datasets: (config.datasets || []).map((dataset, index) => {
                        const color = dataset.color || Object.values(this.colors)[index % Object.keys(this.colors).length];
                        return {
                            label: dataset.label || `Dataset ${index + 1}`,
                            data: dataset.data || [],
                            backgroundColor: color,
                            borderColor: color,
                            borderWidth: 1,
                            pointRadius: dataset.pointRadius || 5,
                            pointHoverRadius: dataset.pointHoverRadius || 7
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: config.showLegend !== undefined ? config.showLegend : true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom',
                            title: {
                                display: !!config.xAxisLabel,
                                text: config.xAxisLabel || ''
                            }
                        },
                        y: {
                            title: {
                                display: !!config.yAxisLabel,
                                text: config.yAxisLabel || ''
                            }
                        }
                    }
                }
            };
        },

        /**
         * Create bubble chart configuration
         */
        bubbleChart: function(config) {
            return {
                type: 'bubble',
                data: {
                    datasets: (config.datasets || []).map((dataset, index) => {
                        const color = dataset.color || Object.values(this.colors)[index % Object.keys(this.colors).length];
                        return {
                            label: dataset.label || `Dataset ${index + 1}`,
                            data: dataset.data || [],
                            backgroundColor: this.rgba(color, 0.6),
                            borderColor: color,
                            borderWidth: 1
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: config.showLegend !== undefined ? config.showLegend : true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: !!config.xAxisLabel,
                                text: config.xAxisLabel || ''
                            }
                        },
                        y: {
                            title: {
                                display: !!config.yAxisLabel,
                                text: config.yAxisLabel || ''
                            }
                        }
                    }
                }
            };
        },

        /**
         * Update chart data
         */
        updateChart: function(chart, newData) {
            if (!chart) return;

            if (newData.labels) {
                chart.data.labels = newData.labels;
            }

            if (newData.datasets) {
                newData.datasets.forEach((dataset, index) => {
                    if (chart.data.datasets[index]) {
                        chart.data.datasets[index].data = dataset.data;
                        if (dataset.label) {
                            chart.data.datasets[index].label = dataset.label;
                        }
                    }
                });
            }

            chart.update();
        },

        /**
         * Destroy chart
         */
        destroyChart: function(chart) {
            if (chart) {
                chart.destroy();
            }
        },

        /**
         * Resize chart
         */
        resizeChart: function(chart) {
            if (chart) {
                chart.resize();
            }
        },

        /**
         * Export chart as image
         */
        exportChart: function(chart, filename) {
            if (!chart) return;

            const url = chart.toBase64Image();
            const link = document.createElement('a');
            link.download = filename || 'chart.png';
            link.href = url;
            link.click();
        },

        /**
         * Get chart data as CSV
         */
        getChartDataAsCsv: function(chart) {
            if (!chart) return '';

            let csv = 'Label,' + chart.data.datasets.map(d => d.label).join(',') + '\n';

            chart.data.labels.forEach((label, index) => {
                const row = [label];
                chart.data.datasets.forEach(dataset => {
                    row.push(dataset.data[index] || 0);
                });
                csv += row.join(',') + '\n';
            });

            return csv;
        },

        /**
         * Download CSV data
         */
        downloadCsv: function(chart, filename) {
            const csv = this.getChartDataAsCsv(chart);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = filename || 'chart-data.csv';
            link.href = url;
            link.click();
            URL.revokeObjectURL(url);
        }
    };

    // Export to global scope
    window.ChartComponents = ChartComponents;

})(window);
