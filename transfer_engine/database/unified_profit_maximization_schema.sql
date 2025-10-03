-- 🚀 UNIFIED PROFIT MAXIMIZATION ENGINE DATABASE SCHEMA
-- Database tables to support the combined intelligence system
-- WITH REAL SALES HISTORY INTEGRATION! 🎉

-- Table for storing unified optimization runs
CREATE TABLE IF NOT EXISTS unified_optimization_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL UNIQUE,
    execution_time DECIMAL(8,3) NOT NULL,
    transfers_executed INT DEFAULT 0,
    prices_optimized INT DEFAULT 0,
    profit_impact DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('running', 'completed', 'failed', 'stopped') DEFAULT 'running',
    results_data JSON,
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_profit_impact (profit_impact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced sales velocity tracking (using existing sales history)
CREATE TABLE IF NOT EXISTS sales_velocity_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    store_id INT NOT NULL,
    analysis_date DATE NOT NULL,
    velocity_7d DECIMAL(8,2) DEFAULT 0.00,
    velocity_30d DECIMAL(8,2) DEFAULT 0.00,
    velocity_trend ENUM('increasing', 'stable', 'decreasing') DEFAULT 'stable',
    seasonal_factor DECIMAL(4,2) DEFAULT 1.00,
    demand_score DECIMAL(4,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_store_date (product_id, store_id, analysis_date),
    INDEX idx_velocity_7d (velocity_7d),
    INDEX idx_demand_score (demand_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitive intelligence data (from web crawler)
CREATE TABLE IF NOT EXISTS competitive_intelligence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    competitor_name VARCHAR(100) NOT NULL,
    competitor_price DECIMAL(10,2) NOT NULL,
    our_price DECIMAL(10,2) NOT NULL,
    price_difference DECIMAL(10,2) NOT NULL,
    price_difference_percent DECIMAL(5,2) NOT NULL,
    competitive_advantage DECIMAL(10,2) DEFAULT 0.00,
    market_position ENUM('leader', 'competitive', 'follower', 'premium') DEFAULT 'competitive',
    crawl_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confidence_score DECIMAL(3,2) DEFAULT 1.00,
    source_url VARCHAR(500),
    INDEX idx_product_id (product_id),
    INDEX idx_competitor (competitor_name),
    INDEX idx_crawl_timestamp (crawl_timestamp),
    INDEX idx_competitive_advantage (competitive_advantage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unified decision matrix storage
CREATE TABLE IF NOT EXISTS unified_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL,
    decision_type ENUM('transfer', 'pricing', 'hybrid') NOT NULL,
    product_id INT NOT NULL,
    recommendation JSON NOT NULL,
    priority ENUM('HIGH', 'MEDIUM', 'LOW') DEFAULT 'MEDIUM',
    estimated_profit_impact DECIMAL(10,2) DEFAULT 0.00,
    execution_status ENUM('pending', 'executed', 'failed', 'skipped') DEFAULT 'pending',
    execution_result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_decision_type (decision_type),
    INDEX idx_priority (priority),
    INDEX idx_profit_impact (estimated_profit_impact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock imbalance alerts based on sales velocity
CREATE TABLE IF NOT EXISTS stock_imbalance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    overstocked_store_id INT NOT NULL,
    understocked_store_id INT NOT NULL,
    current_stock_overstocked INT NOT NULL,
    current_stock_understocked INT NOT NULL,
    recommended_transfer_qty INT NOT NULL,
    sales_velocity_basis DECIMAL(8,2) NOT NULL,
    profit_potential DECIMAL(10,2) DEFAULT 0.00,
    urgency_score DECIMAL(3,2) DEFAULT 1.00,
    alert_status ENUM('new', 'processed', 'transferred', 'ignored') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_product_id (product_id),
    INDEX idx_urgency_score (urgency_score),
    INDEX idx_alert_status (alert_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price optimization history
CREATE TABLE IF NOT EXISTS price_optimization_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    old_price DECIMAL(10,2) NOT NULL,
    new_price DECIMAL(10,2) NOT NULL,
    price_change_percent DECIMAL(5,2) NOT NULL,
    optimization_reason TEXT,
    competitive_factor DECIMAL(3,2) DEFAULT 0.00,
    sales_factor DECIMAL(3,2) DEFAULT 0.00,
    expected_profit_increase DECIMAL(10,2) DEFAULT 0.00,
    actual_profit_increase DECIMAL(10,2) NULL,
    success_rate DECIMAL(3,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_product_id (product_id),
    INDEX idx_expected_profit (expected_profit_increase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfer optimization history
CREATE TABLE IF NOT EXISTS transfer_optimization_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    from_store_id INT NOT NULL,
    to_store_id INT NOT NULL,
    quantity_transferred INT NOT NULL,
    transfer_reason TEXT,
    sales_velocity_from DECIMAL(8,2) DEFAULT 0.00,
    sales_velocity_to DECIMAL(8,2) DEFAULT 0.00,
    expected_profit_increase DECIMAL(10,2) DEFAULT 0.00,
    actual_profit_increase DECIMAL(10,2) NULL,
    transfer_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_product_id (product_id),
    INDEX idx_from_store (from_store_id),
    INDEX idx_to_store (to_store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance metrics for the unified engine
CREATE TABLE IF NOT EXISTS unified_performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    total_optimizations INT DEFAULT 0,
    successful_transfers INT DEFAULT 0,
    successful_price_changes INT DEFAULT 0,
    total_profit_generated DECIMAL(12,2) DEFAULT 0.00,
    average_execution_time DECIMAL(8,3) DEFAULT 0.000,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    competitive_advantage_score DECIMAL(5,2) DEFAULT 0.00,
    sales_velocity_improvement DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_metric_date (metric_date),
    INDEX idx_profit_generated (total_profit_generated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced competitor tracking
CREATE TABLE IF NOT EXISTS competitor_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competitor_name VARCHAR(100) NOT NULL,
    competitor_url VARCHAR(500) NOT NULL,
    crawl_frequency_hours INT DEFAULT 24,
    last_crawl_timestamp TIMESTAMP NULL,
    next_crawl_timestamp TIMESTAMP NULL,
    crawl_success_rate DECIMAL(5,2) DEFAULT 100.00,
    products_monitored INT DEFAULT 0,
    avg_response_time_ms INT DEFAULT 0,
    stealth_status ENUM('undetected', 'suspicious', 'blocked') DEFAULT 'undetected',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_competitor_url (competitor_name, competitor_url),
    INDEX idx_next_crawl (next_crawl_timestamp),
    INDEX idx_stealth_status (stealth_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unified alerts and notifications
CREATE TABLE IF NOT EXISTS unified_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('profit_opportunity', 'competitive_threat', 'stock_imbalance', 'system_error') NOT NULL,
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    product_id INT NULL,
    store_id INT NULL,
    estimated_impact DECIMAL(10,2) DEFAULT 0.00,
    action_required BOOLEAN DEFAULT FALSE,
    alert_status ENUM('new', 'acknowledged', 'resolved', 'dismissed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_alert_status (alert_status),
    INDEX idx_estimated_impact (estimated_impact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views for easy data access

-- View: Current competitive positioning
CREATE OR REPLACE VIEW v_competitive_positioning AS
SELECT 
    ci.product_id,
    p.product_name,
    ci.our_price,
    AVG(ci.competitor_price) as avg_competitor_price,
    MIN(ci.competitor_price) as min_competitor_price,
    MAX(ci.competitor_price) as max_competitor_price,
    AVG(ci.competitive_advantage) as avg_competitive_advantage,
    COUNT(ci.competitor_name) as competitors_tracked,
    MAX(ci.crawl_timestamp) as last_updated
FROM competitive_intelligence ci
LEFT JOIN products p ON ci.product_id = p.id
WHERE ci.crawl_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ci.product_id, p.product_name, ci.our_price;

-- View: Sales velocity with stock levels
CREATE OR REPLACE VIEW v_sales_velocity_stock AS
SELECT 
    sva.product_id,
    p.product_name,
    sva.store_id,
    s.store_name,
    sva.velocity_7d,
    sva.velocity_30d,
    sva.demand_score,
    COALESCE(st.current_stock, 0) as current_stock,
    CASE 
        WHEN sva.velocity_7d > 0 THEN COALESCE(st.current_stock, 0) / sva.velocity_7d
        ELSE 999
    END as days_of_stock,
    sva.updated_at
FROM sales_velocity_analysis sva
LEFT JOIN products p ON sva.product_id = p.id
LEFT JOIN stores s ON sva.store_id = s.id
LEFT JOIN stock st ON sva.product_id = st.product_id AND sva.store_id = st.store_id
WHERE sva.analysis_date = CURDATE();

-- View: Profit opportunities dashboard
CREATE OR REPLACE VIEW v_profit_opportunities AS
SELECT 
    'price_optimization' as opportunity_type,
    poh.product_id,
    p.product_name,
    poh.expected_profit_increase as potential_profit,
    'pricing' as action_required,
    poh.created_at
FROM price_optimization_history poh
LEFT JOIN products p ON poh.product_id = p.id
WHERE poh.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND poh.expected_profit_increase > 0

UNION ALL

SELECT 
    'transfer_optimization' as opportunity_type,
    toh.product_id,
    p.product_name,
    toh.expected_profit_increase as potential_profit,
    'transfer' as action_required,
    toh.created_at
FROM transfer_optimization_history toh
LEFT JOIN products p ON toh.product_id = p.id
WHERE toh.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND toh.expected_profit_increase > 0

ORDER BY potential_profit DESC;

-- Indexes for performance
CREATE INDEX idx_competitive_intelligence_recent ON competitive_intelligence (crawl_timestamp DESC, product_id);
CREATE INDEX idx_sales_velocity_current ON sales_velocity_analysis (analysis_date DESC, demand_score DESC);
CREATE INDEX idx_unified_runs_recent ON unified_optimization_runs (created_at DESC, profit_impact DESC);

-- Sample data for competitors
INSERT IGNORE INTO competitor_monitoring (competitor_name, competitor_url, crawl_frequency_hours) VALUES
('Vaping Kiwi', 'https://vapingkiwi.co.nz', 12),
('Vapour Eyes', 'https://vapoureyes.co.nz', 12),
('NZ Vapor', 'https://nzvapor.com', 24),
('Shosha', 'https://shosha.co.nz', 24),
('Cosmic', 'https://cosmic.co.nz', 24);

-- Initial unified performance metrics
INSERT IGNORE INTO unified_performance_metrics (metric_date) VALUES (CURDATE());

-- Procedures for unified operations

DELIMITER //

-- Procedure to update sales velocity analysis
CREATE PROCEDURE UpdateSalesVelocityAnalysis()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id, v_store_id INT;
    DECLARE v_velocity_7d, v_velocity_30d, v_demand_score DECIMAL(8,2);
    
    DECLARE velocity_cursor CURSOR FOR 
        SELECT 
            product_id,
            store_id,
            COALESCE(SUM(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN quantity_sold ELSE 0 END) / 7, 0) as velocity_7d,
            COALESCE(SUM(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN quantity_sold ELSE 0 END) / 30, 0) as velocity_30d,
            COALESCE(SUM(CASE WHEN sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN quantity_sold ELSE 0 END) / 7 * 10, 0) as demand_score
        FROM sales_data 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY product_id, store_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN velocity_cursor;
    
    velocity_loop: LOOP
        FETCH velocity_cursor INTO v_product_id, v_store_id, v_velocity_7d, v_velocity_30d, v_demand_score;
        
        IF done THEN
            LEAVE velocity_loop;
        END IF;
        
        INSERT INTO sales_velocity_analysis (
            product_id, store_id, analysis_date, velocity_7d, velocity_30d, demand_score
        ) VALUES (
            v_product_id, v_store_id, CURDATE(), v_velocity_7d, v_velocity_30d, v_demand_score
        ) ON DUPLICATE KEY UPDATE
            velocity_7d = VALUES(velocity_7d),
            velocity_30d = VALUES(velocity_30d),
            demand_score = VALUES(demand_score),
            updated_at = CURRENT_TIMESTAMP;
            
    END LOOP;
    
    CLOSE velocity_cursor;
END //

DELIMITER ;

-- Events for automated updates
CREATE EVENT IF NOT EXISTS update_sales_velocity_daily
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL UpdateSalesVelocityAnalysis();

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;