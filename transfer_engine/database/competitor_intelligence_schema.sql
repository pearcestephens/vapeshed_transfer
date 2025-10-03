-- Competitor Intelligence Database Schema
-- For automated competitor price monitoring and analysis

-- Competitor prices table
CREATE TABLE IF NOT EXISTS competitor_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competitor_id VARCHAR(50) NOT NULL,
    competitor_product_name VARCHAR(255) NOT NULL,
    our_product_id VARCHAR(100),
    price DECIMAL(10,2) NOT NULL,
    brand VARCHAR(100),
    url TEXT,
    image_url TEXT,
    confidence_score DECIMAL(3,2) DEFAULT 0.5,
    crawled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_competitor_id (competitor_id),
    INDEX idx_our_product_id (our_product_id),
    INDEX idx_crawled_at (crawled_at),
    INDEX idx_price (price),
    UNIQUE KEY unique_competitor_product (competitor_id, competitor_product_name, crawled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitor definitions table
CREATE TABLE IF NOT EXISTS competitors (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    crawl_frequency_hours INT DEFAULT 4,
    last_crawled_at TIMESTAMP NULL,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    total_crawls INT DEFAULT 0,
    successful_crawls INT DEFAULT 0,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_is_active (is_active),
    INDEX idx_last_crawled (last_crawled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitor crawl logs
CREATE TABLE IF NOT EXISTS competitor_crawl_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id VARCHAR(100) NOT NULL,
    competitor_id VARCHAR(50) NOT NULL,
    status ENUM('started', 'completed', 'failed') NOT NULL,
    products_found INT DEFAULT 0,
    execution_time DECIMAL(8,3) DEFAULT 0.000,
    error_message TEXT NULL,
    pages_crawled INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_run_id (run_id),
    INDEX idx_competitor_id (competitor_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product mappings for competitor matching
CREATE TABLE IF NOT EXISTS product_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    our_product_id VARCHAR(100) NOT NULL,
    competitor_id VARCHAR(50) NOT NULL,
    competitor_product_id VARCHAR(255) NOT NULL,
    competitor_product_name VARCHAR(255) NOT NULL,
    confidence_score DECIMAL(3,2) DEFAULT 0.5,
    mapping_method ENUM('manual', 'fuzzy_match', 'ai_match') DEFAULT 'fuzzy_match',
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_our_product_id (our_product_id),
    INDEX idx_competitor_id (competitor_id),
    INDEX idx_verified (verified),
    UNIQUE KEY unique_mapping (our_product_id, competitor_id, competitor_product_id),
    FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Autonomous engine runs
CREATE TABLE IF NOT EXISTS autonomous_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id VARCHAR(100) NOT NULL UNIQUE,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    execution_time DECIMAL(8,3) DEFAULT 0.000,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    transfers_executed INT DEFAULT 0,
    price_changes INT DEFAULT 0,
    clearance_items INT DEFAULT 0,
    profit_impact DECIMAL(10,2) DEFAULT 0.00,
    revenue_opportunity DECIMAL(10,2) DEFAULT 0.00,
    products_analyzed INT DEFAULT 0,
    competitors_crawled INT DEFAULT 0,
    error_message TEXT NULL,
    config JSON,
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_run_id (run_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time),
    INDEX idx_profit_impact (profit_impact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competitive analysis results
CREATE TABLE IF NOT EXISTS competitive_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id VARCHAR(100) NOT NULL,
    our_product_id VARCHAR(100) NOT NULL,
    competitor_id VARCHAR(50) NOT NULL,
    our_price DECIMAL(10,2) NOT NULL,
    competitor_price DECIMAL(10,2) NOT NULL,
    price_difference DECIMAL(10,2) NOT NULL,
    price_difference_percent DECIMAL(5,2) NOT NULL,
    market_position ENUM('premium', 'above_market', 'competitive', 'below_market', 'discount') NOT NULL,
    threat_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    opportunity_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    recommended_action VARCHAR(255),
    confidence_score DECIMAL(3,2) DEFAULT 0.5,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_analysis_id (analysis_id),
    INDEX idx_our_product_id (our_product_id),
    INDEX idx_competitor_id (competitor_id),
    INDEX idx_threat_level (threat_level),
    INDEX idx_opportunity_level (opportunity_level),
    INDEX idx_analyzed_at (analyzed_at),
    FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default competitors
INSERT INTO competitors (id, name, base_url, config) VALUES 
('shosha', 'Shosha', 'https://www.shosha.co.nz', '{"anti_detection": true, "request_delay": 3}'),
('cosmic', 'Cosmic', 'https://www.cosmic.co.nz', '{"anti_detection": true, "request_delay": 2}'),
('vapo', 'Vapo', 'https://www.vapo.co.nz', '{"anti_detection": true, "request_delay": 2}'),
('alt_nz', 'Alt NZ', 'https://www.alt.co.nz', '{"anti_detection": true, "request_delay": 3}')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    base_url = VALUES(base_url),
    config = VALUES(config);