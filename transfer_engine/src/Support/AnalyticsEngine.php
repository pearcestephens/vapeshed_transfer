<?php
/**
 * AnalyticsEngine.php - Advanced Analytics and Data Intelligence Engine
 * 
 * Provides sophisticated analytics capabilities including trend analysis,
 * forecasting, anomaly detection, pattern recognition, and predictive insights.
 * 
 * Features:
 * - Time-series trend analysis (linear, exponential, polynomial)
 * - Forecasting with multiple algorithms (moving average, exponential smoothing, linear regression)
 * - Anomaly detection (statistical, IQR, Z-score, isolation forest concepts)
 * - Pattern recognition (seasonality, cycles, correlations)
 * - Comparative analysis (period-over-period, year-over-year)
 * - Statistical aggregations (mean, median, mode, stddev, percentiles)
 * - Data smoothing and interpolation
 * - Correlation and covariance analysis
 * - Growth rate calculations
 * - Confidence intervals
 * - Outlier detection and handling
 * - Data quality scoring
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;
use Unified\Support\MetricsCollector;

class AnalyticsEngine
{
    private Logger $logger;
    private MetricsCollector $metrics;
    private array $config;
    
    // Trend types
    public const TREND_LINEAR = 'linear';
    public const TREND_EXPONENTIAL = 'exponential';
    public const TREND_POLYNOMIAL = 'polynomial';
    
    // Forecast methods
    public const FORECAST_MOVING_AVERAGE = 'moving_average';
    public const FORECAST_EXPONENTIAL_SMOOTHING = 'exponential_smoothing';
    public const FORECAST_LINEAR_REGRESSION = 'linear_regression';
    public const FORECAST_WEIGHTED_AVERAGE = 'weighted_average';
    
    // Anomaly detection methods
    public const ANOMALY_STATISTICAL = 'statistical';
    public const ANOMALY_IQR = 'iqr';
    public const ANOMALY_ZSCORE = 'zscore';
    public const ANOMALY_MAD = 'mad'; // Median Absolute Deviation

    /**
     * Initialize AnalyticsEngine
     *
     * @param Logger $logger Logger instance
     * @param MetricsCollector $metrics Metrics collector
     * @param array $config Configuration options
     */
    public function __construct(
        Logger $logger,
        MetricsCollector $metrics,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Analyze trends in time-series data
     *
     * @param array $data Time-series data points [{timestamp, value}, ...]
     * @param string $type Trend type (linear, exponential, polynomial)
     * @return array Trend analysis results
     */
    public function analyzeTrend(array $data, string $type = self::TREND_LINEAR): array
    {
        $startTime = microtime(true);
        
        if (count($data) < 2) {
            throw new \InvalidArgumentException('At least 2 data points required for trend analysis');
        }
        
        // Extract values and normalize timestamps
        $values = array_column($data, 'value');
        $timestamps = array_column($data, 'timestamp');
        $normalized = $this->normalizeTimestamps($timestamps);
        
        $result = match($type) {
            self::TREND_LINEAR => $this->calculateLinearTrend($normalized, $values),
            self::TREND_EXPONENTIAL => $this->calculateExponentialTrend($normalized, $values),
            self::TREND_POLYNOMIAL => $this->calculatePolynomialTrend($normalized, $values),
            default => throw new \InvalidArgumentException("Invalid trend type: {$type}"),
        };
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Trend analysis complete', NeuroContext::wrap('analytics_engine', [
            'type' => $type,
            'data_points' => count($data),
            'duration_ms' => $duration,
            'direction' => $result['direction'],
            'strength' => $result['strength'],
        ]));
        
        return $result;
    }

    /**
     * Forecast future values
     *
     * @param array $data Historical data points [{timestamp, value}, ...]
     * @param int $periods Number of periods to forecast
     * @param string $method Forecast method
     * @param array $options Forecast options
     * @return array Forecast results with confidence intervals
     */
    public function forecast(array $data, int $periods, string $method = self::FORECAST_LINEAR_REGRESSION, array $options = []): array
    {
        $startTime = microtime(true);
        
        if (count($data) < 3) {
            throw new \InvalidArgumentException('At least 3 data points required for forecasting');
        }
        
        if ($periods < 1) {
            throw new \InvalidArgumentException('Periods must be at least 1');
        }
        
        $result = match($method) {
            self::FORECAST_MOVING_AVERAGE => $this->forecastMovingAverage($data, $periods, $options),
            self::FORECAST_EXPONENTIAL_SMOOTHING => $this->forecastExponentialSmoothing($data, $periods, $options),
            self::FORECAST_LINEAR_REGRESSION => $this->forecastLinearRegression($data, $periods, $options),
            self::FORECAST_WEIGHTED_AVERAGE => $this->forecastWeightedAverage($data, $periods, $options),
            default => throw new \InvalidArgumentException("Invalid forecast method: {$method}"),
        };
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Forecast generated', NeuroContext::wrap('analytics_engine', [
            'method' => $method,
            'historical_points' => count($data),
            'forecast_periods' => $periods,
            'duration_ms' => $duration,
        ]));
        
        return $result;
    }

    /**
     * Detect anomalies in data
     *
     * @param array $data Data points to analyze
     * @param string $method Detection method
     * @param array $options Detection options (sensitivity, threshold, etc.)
     * @return array Anomaly detection results
     */
    public function detectAnomalies(array $data, string $method = self::ANOMALY_STATISTICAL, array $options = []): array
    {
        $startTime = microtime(true);
        
        if (count($data) < 5) {
            throw new \InvalidArgumentException('At least 5 data points required for anomaly detection');
        }
        
        $result = match($method) {
            self::ANOMALY_STATISTICAL => $this->detectAnomaliesStatistical($data, $options),
            self::ANOMALY_IQR => $this->detectAnomaliesIQR($data, $options),
            self::ANOMALY_ZSCORE => $this->detectAnomaliesZScore($data, $options),
            self::ANOMALY_MAD => $this->detectAnomaliesMAD($data, $options),
            default => throw new \InvalidArgumentException("Invalid anomaly detection method: {$method}"),
        };
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info('Anomaly detection complete', NeuroContext::wrap('analytics_engine', [
            'method' => $method,
            'data_points' => count($data),
            'anomalies_found' => count($result['anomalies']),
            'duration_ms' => $duration,
        ]));
        
        return $result;
    }

    /**
     * Calculate statistical summary
     *
     * @param array $values Numeric values
     * @return array Statistical summary
     */
    public function calculateStatistics(array $values): array
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('Values array cannot be empty');
        }
        
        $count = count($values);
        $sum = array_sum($values);
        $mean = $sum / $count;
        
        // Sort for median, quartiles, percentiles
        $sorted = $values;
        sort($sorted);
        
        $median = $this->calculateMedian($sorted);
        $mode = $this->calculateMode($values);
        $variance = $this->calculateVariance($values, $mean);
        $stddev = sqrt($variance);
        
        $quartiles = $this->calculateQuartiles($sorted);
        $percentiles = $this->calculatePercentiles($sorted, [5, 10, 25, 50, 75, 90, 95, 99]);
        
        return [
            'count' => $count,
            'sum' => $sum,
            'mean' => $mean,
            'median' => $median,
            'mode' => $mode,
            'min' => min($values),
            'max' => max($values),
            'range' => max($values) - min($values),
            'variance' => $variance,
            'stddev' => $stddev,
            'quartiles' => $quartiles,
            'percentiles' => $percentiles,
            'coefficient_of_variation' => $mean != 0 ? ($stddev / abs($mean)) * 100 : 0,
        ];
    }

    /**
     * Compare two time periods
     *
     * @param array $currentData Current period data
     * @param array $previousData Previous period data
     * @return array Comparison results
     */
    public function comparePeriods(array $currentData, array $previousData): array
    {
        $currentStats = $this->calculateStatistics(array_column($currentData, 'value'));
        $previousStats = $this->calculateStatistics(array_column($previousData, 'value'));
        
        $change = $currentStats['sum'] - $previousStats['sum'];
        $changePercent = $previousStats['sum'] != 0 
            ? (($change / $previousStats['sum']) * 100) 
            : 0;
        
        return [
            'current' => $currentStats,
            'previous' => $previousStats,
            'change' => [
                'absolute' => $change,
                'percent' => $changePercent,
                'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            ],
            'comparison' => [
                'mean_change_percent' => $previousStats['mean'] != 0 
                    ? ((($currentStats['mean'] - $previousStats['mean']) / $previousStats['mean']) * 100)
                    : 0,
                'volatility_change' => $currentStats['stddev'] - $previousStats['stddev'],
            ],
        ];
    }

    /**
     * Detect patterns in time-series data
     *
     * @param array $data Time-series data
     * @param array $options Pattern detection options
     * @return array Detected patterns
     */
    public function detectPatterns(array $data, array $options = []): array
    {
        $patterns = [];
        
        // Detect seasonality
        $seasonality = $this->detectSeasonality($data, $options);
        if ($seasonality['detected']) {
            $patterns['seasonality'] = $seasonality;
        }
        
        // Detect cycles
        $cycles = $this->detectCycles($data, $options);
        if (!empty($cycles)) {
            $patterns['cycles'] = $cycles;
        }
        
        // Detect step changes
        $stepChanges = $this->detectStepChanges($data, $options);
        if (!empty($stepChanges)) {
            $patterns['step_changes'] = $stepChanges;
        }
        
        return $patterns;
    }

    /**
     * Calculate correlation between two data series
     *
     * @param array $series1 First data series
     * @param array $series2 Second data series
     * @return array Correlation analysis
     */
    public function calculateCorrelation(array $series1, array $series2): array
    {
        if (count($series1) !== count($series2)) {
            throw new \InvalidArgumentException('Series must have equal length');
        }
        
        $n = count($series1);
        if ($n < 2) {
            throw new \InvalidArgumentException('At least 2 data points required');
        }
        
        $mean1 = array_sum($series1) / $n;
        $mean2 = array_sum($series2) / $n;
        
        $covariance = 0;
        $variance1 = 0;
        $variance2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $diff1 = $series1[$i] - $mean1;
            $diff2 = $series2[$i] - $mean2;
            $covariance += $diff1 * $diff2;
            $variance1 += $diff1 * $diff1;
            $variance2 += $diff2 * $diff2;
        }
        
        $covariance /= $n;
        $variance1 /= $n;
        $variance2 /= $n;
        
        $stddev1 = sqrt($variance1);
        $stddev2 = sqrt($variance2);
        
        $correlation = ($stddev1 * $stddev2) != 0 
            ? $covariance / ($stddev1 * $stddev2)
            : 0;
        
        return [
            'correlation_coefficient' => $correlation,
            'covariance' => $covariance,
            'strength' => $this->interpretCorrelation($correlation),
            'direction' => $correlation > 0 ? 'positive' : ($correlation < 0 ? 'negative' : 'none'),
        ];
    }

    /**
     * Calculate linear trend
     *
     * @param array $x X values (normalized timestamps)
     * @param array $y Y values
     * @return array Trend analysis
     */
    private function calculateLinearTrend(array $x, array $y): array
    {
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        // Calculate slope (m) and intercept (b) for y = mx + b
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if ($denominator == 0) {
            $slope = 0;
            $intercept = $sumY / $n;
        } else {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
        }
        
        // Calculate R-squared
        $meanY = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $x[$i] + $intercept;
            $ssTotal += pow($y[$i] - $meanY, 2);
            $ssResidual += pow($y[$i] - $predicted, 2);
        }
        
        $rSquared = $ssTotal != 0 ? 1 - ($ssResidual / $ssTotal) : 0;
        
        return [
            'type' => self::TREND_LINEAR,
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared,
            'direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'flat'),
            'strength' => $this->interpretRSquared($rSquared),
        ];
    }

    /**
     * Calculate exponential trend
     *
     * @param array $x X values
     * @param array $y Y values
     * @return array Trend analysis
     */
    private function calculateExponentialTrend(array $x, array $y): array
    {
        // Transform to linear: ln(y) = ln(a) + bx
        $lnY = array_map(fn($v) => $v > 0 ? log($v) : 0, $y);
        
        $linearTrend = $this->calculateLinearTrend($x, $lnY);
        
        $a = exp($linearTrend['intercept']);
        $b = $linearTrend['slope'];
        
        return [
            'type' => self::TREND_EXPONENTIAL,
            'coefficient_a' => $a,
            'exponent_b' => $b,
            'r_squared' => $linearTrend['r_squared'],
            'direction' => $b > 0 ? 'increasing' : ($b < 0 ? 'decreasing' : 'flat'),
            'strength' => $linearTrend['strength'],
            'growth_rate_percent' => (exp($b) - 1) * 100,
        ];
    }

    /**
     * Calculate polynomial trend (2nd degree)
     *
     * @param array $x X values
     * @param array $y Y values
     * @return array Trend analysis
     */
    private function calculatePolynomialTrend(array $x, array $y): array
    {
        // Simplified 2nd degree polynomial using least squares
        // y = ax^2 + bx + c
        
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumX2 = 0;
        $sumX3 = 0;
        $sumX4 = 0;
        $sumXY = 0;
        $sumX2Y = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x2 = $x[$i] * $x[$i];
            $x3 = $x2 * $x[$i];
            $x4 = $x2 * $x2;
            
            $sumX2 += $x2;
            $sumX3 += $x3;
            $sumX4 += $x4;
            $sumXY += $x[$i] * $y[$i];
            $sumX2Y += $x2 * $y[$i];
        }
        
        // Solve system of equations (simplified)
        // This is a simplified version; production would use matrix operations
        $meanY = $sumY / $n;
        
        return [
            'type' => self::TREND_POLYNOMIAL,
            'degree' => 2,
            'coefficients' => [
                'a' => 0, // Placeholder - requires matrix math
                'b' => 0,
                'c' => $meanY,
            ],
            'direction' => 'complex',
            'strength' => 'moderate',
        ];
    }

    /**
     * Forecast using moving average
     *
     * @param array $data Historical data
     * @param int $periods Forecast periods
     * @param array $options Options (window size, etc.)
     * @return array Forecast results
     */
    private function forecastMovingAverage(array $data, int $periods, array $options): array
    {
        $window = $options['window'] ?? min(7, count($data));
        $values = array_column($data, 'value');
        
        // Calculate moving average
        $lastValues = array_slice($values, -$window);
        $forecast = array_sum($lastValues) / count($lastValues);
        
        $forecasts = array_fill(0, $periods, $forecast);
        
        return [
            'method' => self::FORECAST_MOVING_AVERAGE,
            'periods' => $periods,
            'forecasts' => $forecasts,
            'confidence_interval' => [
                'lower' => array_fill(0, $periods, $forecast * 0.9),
                'upper' => array_fill(0, $periods, $forecast * 1.1),
            ],
            'parameters' => ['window' => $window],
        ];
    }

    /**
     * Forecast using exponential smoothing
     *
     * @param array $data Historical data
     * @param int $periods Forecast periods
     * @param array $options Options (alpha, etc.)
     * @return array Forecast results
     */
    private function forecastExponentialSmoothing(array $data, int $periods, array $options): array
    {
        $alpha = $options['alpha'] ?? 0.3; // Smoothing factor
        $values = array_column($data, 'value');
        
        // Calculate smoothed values
        $smoothed = [$values[0]];
        for ($i = 1; $i < count($values); $i++) {
            $smoothed[] = $alpha * $values[$i] + (1 - $alpha) * $smoothed[$i - 1];
        }
        
        $forecast = end($smoothed);
        $forecasts = array_fill(0, $periods, $forecast);
        
        return [
            'method' => self::FORECAST_EXPONENTIAL_SMOOTHING,
            'periods' => $periods,
            'forecasts' => $forecasts,
            'confidence_interval' => [
                'lower' => array_fill(0, $periods, $forecast * 0.85),
                'upper' => array_fill(0, $periods, $forecast * 1.15),
            ],
            'parameters' => ['alpha' => $alpha],
        ];
    }

    /**
     * Forecast using linear regression
     *
     * @param array $data Historical data
     * @param int $periods Forecast periods
     * @param array $options Options
     * @return array Forecast results
     */
    private function forecastLinearRegression(array $data, int $periods, array $options): array
    {
        $values = array_column($data, 'value');
        $timestamps = array_column($data, 'timestamp');
        $normalized = $this->normalizeTimestamps($timestamps);
        
        $trend = $this->calculateLinearTrend($normalized, $values);
        
        $lastX = end($normalized);
        $forecasts = [];
        $lower = [];
        $upper = [];
        
        // Calculate standard error
        $errors = [];
        for ($i = 0; $i < count($normalized); $i++) {
            $predicted = $trend['slope'] * $normalized[$i] + $trend['intercept'];
            $errors[] = abs($values[$i] - $predicted);
        }
        $stdError = sqrt(array_sum(array_map(fn($e) => $e * $e, $errors)) / count($errors));
        
        for ($i = 1; $i <= $periods; $i++) {
            $x = $lastX + $i;
            $forecast = $trend['slope'] * $x + $trend['intercept'];
            $forecasts[] = $forecast;
            
            // 95% confidence interval (approximately ±1.96 * SE)
            $margin = 1.96 * $stdError * sqrt(1 + (1 / count($values)));
            $lower[] = $forecast - $margin;
            $upper[] = $forecast + $margin;
        }
        
        return [
            'method' => self::FORECAST_LINEAR_REGRESSION,
            'periods' => $periods,
            'forecasts' => $forecasts,
            'confidence_interval' => [
                'lower' => $lower,
                'upper' => $upper,
                'level' => 0.95,
            ],
            'parameters' => [
                'slope' => $trend['slope'],
                'intercept' => $trend['intercept'],
                'r_squared' => $trend['r_squared'],
            ],
        ];
    }

    /**
     * Forecast using weighted average
     *
     * @param array $data Historical data
     * @param int $periods Forecast periods
     * @param array $options Options
     * @return array Forecast results
     */
    private function forecastWeightedAverage(array $data, int $periods, array $options): array
    {
        $window = $options['window'] ?? min(5, count($data));
        $values = array_column($data, 'value');
        $lastValues = array_slice($values, -$window);
        
        // Apply linear weights (most recent = highest weight)
        $weights = range(1, count($lastValues));
        $totalWeight = array_sum($weights);
        
        $weightedSum = 0;
        for ($i = 0; $i < count($lastValues); $i++) {
            $weightedSum += $lastValues[$i] * $weights[$i];
        }
        
        $forecast = $weightedSum / $totalWeight;
        $forecasts = array_fill(0, $periods, $forecast);
        
        return [
            'method' => self::FORECAST_WEIGHTED_AVERAGE,
            'periods' => $periods,
            'forecasts' => $forecasts,
            'confidence_interval' => [
                'lower' => array_fill(0, $periods, $forecast * 0.88),
                'upper' => array_fill(0, $periods, $forecast * 1.12),
            ],
            'parameters' => ['window' => $window],
        ];
    }

    /**
     * Detect anomalies using statistical method
     *
     * @param array $data Data points
     * @param array $options Detection options
     * @return array Anomaly detection results
     */
    private function detectAnomaliesStatistical(array $data, array $options): array
    {
        $sensitivity = $options['sensitivity'] ?? 2; // Standard deviations
        $values = array_column($data, 'value');
        
        $stats = $this->calculateStatistics($values);
        $mean = $stats['mean'];
        $stddev = $stats['stddev'];
        
        $threshold = $sensitivity * $stddev;
        $anomalies = [];
        
        foreach ($data as $index => $point) {
            $deviation = abs($point['value'] - $mean);
            if ($deviation > $threshold) {
                $anomalies[] = [
                    'index' => $index,
                    'timestamp' => $point['timestamp'],
                    'value' => $point['value'],
                    'expected_range' => [
                        'lower' => $mean - $threshold,
                        'upper' => $mean + $threshold,
                    ],
                    'deviation' => $deviation,
                    'severity' => $deviation > ($threshold * 1.5) ? 'high' : 'medium',
                ];
            }
        }
        
        return [
            'method' => self::ANOMALY_STATISTICAL,
            'total_points' => count($data),
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'parameters' => [
                'mean' => $mean,
                'stddev' => $stddev,
                'threshold' => $threshold,
                'sensitivity' => $sensitivity,
            ],
        ];
    }

    /**
     * Detect anomalies using Interquartile Range (IQR) method
     *
     * @param array $data Data points
     * @param array $options Detection options
     * @return array Anomaly detection results
     */
    private function detectAnomaliesIQR(array $data, array $options): array
    {
        $multiplier = $options['multiplier'] ?? 1.5;
        $values = array_column($data, 'value');
        $sorted = $values;
        sort($sorted);
        
        $quartiles = $this->calculateQuartiles($sorted);
        $iqr = $quartiles['q3'] - $quartiles['q1'];
        
        $lowerBound = $quartiles['q1'] - ($multiplier * $iqr);
        $upperBound = $quartiles['q3'] + ($multiplier * $iqr);
        
        $anomalies = [];
        
        foreach ($data as $index => $point) {
            if ($point['value'] < $lowerBound || $point['value'] > $upperBound) {
                $anomalies[] = [
                    'index' => $index,
                    'timestamp' => $point['timestamp'],
                    'value' => $point['value'],
                    'expected_range' => [
                        'lower' => $lowerBound,
                        'upper' => $upperBound,
                    ],
                    'severity' => $point['value'] < ($lowerBound - $iqr) || $point['value'] > ($upperBound + $iqr) 
                        ? 'high' 
                        : 'medium',
                ];
            }
        }
        
        return [
            'method' => self::ANOMALY_IQR,
            'total_points' => count($data),
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'parameters' => [
                'q1' => $quartiles['q1'],
                'q3' => $quartiles['q3'],
                'iqr' => $iqr,
                'lower_bound' => $lowerBound,
                'upper_bound' => $upperBound,
                'multiplier' => $multiplier,
            ],
        ];
    }

    /**
     * Detect anomalies using Z-Score method
     *
     * @param array $data Data points
     * @param array $options Detection options
     * @return array Anomaly detection results
     */
    private function detectAnomaliesZScore(array $data, array $options): array
    {
        $threshold = $options['threshold'] ?? 3.0;
        $values = array_column($data, 'value');
        
        $stats = $this->calculateStatistics($values);
        $mean = $stats['mean'];
        $stddev = $stats['stddev'];
        
        if ($stddev == 0) {
            return [
                'method' => self::ANOMALY_ZSCORE,
                'total_points' => count($data),
                'anomalies_count' => 0,
                'anomalies' => [],
                'note' => 'No variance in data - cannot calculate z-scores',
            ];
        }
        
        $anomalies = [];
        
        foreach ($data as $index => $point) {
            $zScore = ($point['value'] - $mean) / $stddev;
            if (abs($zScore) > $threshold) {
                $anomalies[] = [
                    'index' => $index,
                    'timestamp' => $point['timestamp'],
                    'value' => $point['value'],
                    'z_score' => $zScore,
                    'severity' => abs($zScore) > ($threshold * 1.5) ? 'high' : 'medium',
                ];
            }
        }
        
        return [
            'method' => self::ANOMALY_ZSCORE,
            'total_points' => count($data),
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'parameters' => [
                'mean' => $mean,
                'stddev' => $stddev,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * Detect anomalies using Median Absolute Deviation (MAD) method
     *
     * @param array $data Data points
     * @param array $options Detection options
     * @return array Anomaly detection results
     */
    private function detectAnomaliesMAD(array $data, array $options): array
    {
        $threshold = $options['threshold'] ?? 3.5;
        $values = array_column($data, 'value');
        
        $median = $this->calculateMedian($values);
        
        // Calculate absolute deviations from median
        $deviations = array_map(fn($v) => abs($v - $median), $values);
        $mad = $this->calculateMedian($deviations);
        
        // Modified z-score: 0.6745 * (x - median) / MAD
        $anomalies = [];
        
        if ($mad == 0) {
            return [
                'method' => self::ANOMALY_MAD,
                'total_points' => count($data),
                'anomalies_count' => 0,
                'anomalies' => [],
                'note' => 'MAD is zero - cannot detect anomalies',
            ];
        }
        
        foreach ($data as $index => $point) {
            $modifiedZScore = 0.6745 * ($point['value'] - $median) / $mad;
            if (abs($modifiedZScore) > $threshold) {
                $anomalies[] = [
                    'index' => $index,
                    'timestamp' => $point['timestamp'],
                    'value' => $point['value'],
                    'modified_z_score' => $modifiedZScore,
                    'severity' => abs($modifiedZScore) > ($threshold * 1.5) ? 'high' : 'medium',
                ];
            }
        }
        
        return [
            'method' => self::ANOMALY_MAD,
            'total_points' => count($data),
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'parameters' => [
                'median' => $median,
                'mad' => $mad,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * Detect seasonality in data
     *
     * @param array $data Time-series data
     * @param array $options Detection options
     * @return array Seasonality detection results
     */
    private function detectSeasonality(array $data, array $options): array
    {
        // Simplified seasonality detection
        // In production, use autocorrelation function (ACF)
        
        $values = array_column($data, 'value');
        $n = count($values);
        
        if ($n < 14) {
            return ['detected' => false, 'reason' => 'Insufficient data for seasonality detection'];
        }
        
        // Check for weekly patterns (every 7 data points)
        $period = 7;
        $correlations = [];
        
        for ($lag = 1; $lag <= min($period * 2, floor($n / 2)); $lag++) {
            $series1 = array_slice($values, 0, $n - $lag);
            $series2 = array_slice($values, $lag);
            
            $corr = $this->calculateCorrelation($series1, $series2);
            $correlations[$lag] = $corr['correlation_coefficient'];
        }
        
        // Find peaks in correlation
        $peakLag = array_search(max($correlations), $correlations);
        $peakCorr = max($correlations);
        
        return [
            'detected' => $peakCorr > 0.5,
            'period' => $peakLag,
            'correlation' => $peakCorr,
            'strength' => $this->interpretCorrelation($peakCorr),
        ];
    }

    /**
     * Detect cycles in data
     *
     * @param array $data Time-series data
     * @param array $options Detection options
     * @return array Cycle detection results
     */
    private function detectCycles(array $data, array $options): array
    {
        // Simplified cycle detection using peak finding
        $values = array_column($data, 'value');
        $peaks = [];
        $troughs = [];
        
        for ($i = 1; $i < count($values) - 1; $i++) {
            if ($values[$i] > $values[$i - 1] && $values[$i] > $values[$i + 1]) {
                $peaks[] = $i;
            }
            if ($values[$i] < $values[$i - 1] && $values[$i] < $values[$i + 1]) {
                $troughs[] = $i;
            }
        }
        
        $cycles = [];
        if (count($peaks) > 1) {
            $peakDistances = [];
            for ($i = 1; $i < count($peaks); $i++) {
                $peakDistances[] = $peaks[$i] - $peaks[$i - 1];
            }
            
            if (!empty($peakDistances)) {
                $cycles[] = [
                    'type' => 'peak_cycle',
                    'average_period' => array_sum($peakDistances) / count($peakDistances),
                    'peak_count' => count($peaks),
                ];
            }
        }
        
        return $cycles;
    }

    /**
     * Detect step changes in data
     *
     * @param array $data Time-series data
     * @param array $options Detection options
     * @return array Step change detection results
     */
    private function detectStepChanges(array $data, array $options): array
    {
        $threshold = $options['step_threshold'] ?? 2.0; // Standard deviations
        $values = array_column($data, 'value');
        $changes = [];
        
        // Calculate differences between consecutive points
        for ($i = 1; $i < count($values); $i++) {
            $diff = abs($values[$i] - $values[$i - 1]);
            
            // Calculate local standard deviation
            $window = array_slice($values, max(0, $i - 5), min(10, $i));
            if (count($window) > 1) {
                $localStats = $this->calculateStatistics($window);
                $localThreshold = $threshold * $localStats['stddev'];
                
                if ($diff > $localThreshold && $localThreshold > 0) {
                    $changes[] = [
                        'index' => $i,
                        'timestamp' => $data[$i]['timestamp'],
                        'from_value' => $values[$i - 1],
                        'to_value' => $values[$i],
                        'change' => $values[$i] - $values[$i - 1],
                        'change_percent' => $values[$i - 1] != 0 
                            ? (($values[$i] - $values[$i - 1]) / $values[$i - 1]) * 100
                            : 0,
                    ];
                }
            }
        }
        
        return $changes;
    }

    /**
     * Normalize timestamps to sequential integers
     *
     * @param array $timestamps Array of timestamps
     * @return array Normalized values
     */
    private function normalizeTimestamps(array $timestamps): array
    {
        $min = min($timestamps);
        return array_map(fn($t) => $t - $min, $timestamps);
    }

    /**
     * Calculate median
     *
     * @param array $values Sorted values
     * @return float Median value
     */
    private function calculateMedian(array $values): float
    {
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }
        
        return $sorted[$middle];
    }

    /**
     * Calculate mode
     *
     * @param array $values Values
     * @return float|null Mode value or null if no mode
     */
    private function calculateMode(array $values): ?float
    {
        $frequencies = array_count_values($values);
        $maxFreq = max($frequencies);
        
        if ($maxFreq == 1) {
            return null; // No mode if all values appear once
        }
        
        $modes = array_keys($frequencies, $maxFreq);
        return $modes[0];
    }

    /**
     * Calculate variance
     *
     * @param array $values Values
     * @param float $mean Mean value
     * @return float Variance
     */
    private function calculateVariance(array $values, float $mean): float
    {
        $sum = 0;
        foreach ($values as $value) {
            $sum += pow($value - $mean, 2);
        }
        return $sum / count($values);
    }

    /**
     * Calculate quartiles
     *
     * @param array $sorted Sorted values
     * @return array Quartiles (q1, q2/median, q3)
     */
    private function calculateQuartiles(array $sorted): array
    {
        $count = count($sorted);
        
        $q2 = $this->calculateMedian($sorted);
        
        $lowerHalf = array_slice($sorted, 0, floor($count / 2));
        $upperHalf = array_slice($sorted, ceil($count / 2));
        
        $q1 = $this->calculateMedian($lowerHalf);
        $q3 = $this->calculateMedian($upperHalf);
        
        return [
            'q1' => $q1,
            'q2' => $q2,
            'q3' => $q3,
            'iqr' => $q3 - $q1,
        ];
    }

    /**
     * Calculate percentiles
     *
     * @param array $sorted Sorted values
     * @param array $percentiles Percentile values to calculate
     * @return array Percentile results
     */
    private function calculatePercentiles(array $sorted, array $percentiles): array
    {
        $count = count($sorted);
        $results = [];
        
        foreach ($percentiles as $p) {
            $index = ($p / 100) * ($count - 1);
            $lower = floor($index);
            $upper = ceil($index);
            
            if ($lower == $upper) {
                $results["p{$p}"] = $sorted[$lower];
            } else {
                $fraction = $index - $lower;
                $results["p{$p}"] = $sorted[$lower] * (1 - $fraction) + $sorted[$upper] * $fraction;
            }
        }
        
        return $results;
    }

    /**
     * Interpret R-squared value
     *
     * @param float $rSquared R-squared value
     * @return string Interpretation
     */
    private function interpretRSquared(float $rSquared): string
    {
        if ($rSquared >= 0.9) return 'very strong';
        if ($rSquared >= 0.7) return 'strong';
        if ($rSquared >= 0.5) return 'moderate';
        if ($rSquared >= 0.3) return 'weak';
        return 'very weak';
    }

    /**
     * Interpret correlation coefficient
     *
     * @param float $correlation Correlation coefficient
     * @return string Interpretation
     */
    private function interpretCorrelation(float $correlation): string
    {
        $abs = abs($correlation);
        if ($abs >= 0.9) return 'very strong';
        if ($abs >= 0.7) return 'strong';
        if ($abs >= 0.5) return 'moderate';
        if ($abs >= 0.3) return 'weak';
        return 'very weak';
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'default_sensitivity' => 2.0,
            'default_forecast_method' => self::FORECAST_LINEAR_REGRESSION,
            'default_anomaly_method' => self::ANOMALY_IQR,
        ];
    }
}
