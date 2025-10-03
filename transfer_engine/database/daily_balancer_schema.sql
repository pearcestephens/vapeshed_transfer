-- AUTOMATED DAILY STOCK BALANCER DATABASE SCHEMA
-- Tables to support automated daily transfer generation

-- Transfer batches for grouping daily transfers
CREATE TABLE IF NOT EXISTS transfer_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) UNIQUE NOT NULL,
    batch_type ENUM('DAILY_AUTO', 'MANUAL', 'EMERGENCY', 'OPTIMIZATION') NOT NULL,
    total_lines INT NOT NULL DEFAULT 0,
    total_quantity INT NOT NULL DEFAULT 0,
    status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_by VARCHAR(50) DEFAULT 'system'
);

-- Daily transfers table for automated stock balancing
CREATE TABLE IF NOT EXISTS daily_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(200),
    from_outlet VARCHAR(20) NOT NULL,
    to_outlet VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    priority ENUM('CRITICAL', 'HIGH', 'MEDIUM', 'LOW') NOT NULL DEFAULT 'MEDIUM',
    reason TEXT,
    days_of_stock DECIMAL(5,2),
    status ENUM('PENDING', 'PICKED', 'IN_TRANSIT', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    picked_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    picked_by VARCHAR(50),
    delivered_by VARCHAR(50),
    notes TEXT,
    
    FOREIGN KEY (batch_id) REFERENCES transfer_batches(batch_id) ON DELETE CASCADE,
    INDEX idx_product_outlet (product_id, to_outlet),
    INDEX idx_status_priority (status, priority),
    INDEX idx_created_date (created_at)
);

-- Stock alerts for monitoring critical situations
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    outlet_id VARCHAR(20) NOT NULL,
    alert_type ENUM('CRITICAL', 'LOW', 'OVERSTOCK', 'ZERO') NOT NULL,
    current_stock INT NOT NULL,
    threshold_value INT NOT NULL,
    days_of_stock DECIMAL(5,2),
    status ENUM('ACTIVE', 'RESOLVED', 'IGNORED') NOT NULL DEFAULT 'ACTIVE',
    first_detected TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    transfer_generated BOOLEAN DEFAULT FALSE,
    notes TEXT,
    
    UNIQUE KEY unique_product_outlet_alert (product_id, outlet_id, alert_type, status),
    INDEX idx_alert_status (alert_type, status),
    INDEX idx_outlet_alerts (outlet_id, status)
);

-- Daily balancer execution log
CREATE TABLE IF NOT EXISTS daily_balancer_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    execution_date DATE NOT NULL,
    execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    products_analyzed INT NOT NULL DEFAULT 0,
    critical_alerts INT NOT NULL DEFAULT 0,
    low_stock_alerts INT NOT NULL DEFAULT 0,
    overstock_alerts INT NOT NULL DEFAULT 0,
    transfers_generated INT NOT NULL DEFAULT 0,
    total_quantity INT NOT NULL DEFAULT 0,
    execution_time_ms DECIMAL(10,2),
    batch_id VARCHAR(50),
    status ENUM('SUCCESS', 'PARTIAL', 'FAILED') NOT NULL,
    error_message TEXT,
    
    UNIQUE KEY unique_execution_date (execution_date),
    INDEX idx_execution_status (status, execution_date)
);

-- Update transfer_configurations to add daily balancer settings
ALTER TABLE transfer_configurations 
ADD COLUMN IF NOT EXISTS daily_balancer_enabled TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS min_transfer_threshold INT DEFAULT 2,
ADD COLUMN IF NOT EXISTS critical_stock_days DECIMAL(3,1) DEFAULT 2.0,
ADD COLUMN IF NOT EXISTS low_stock_days DECIMAL(3,1) DEFAULT 5.0,
ADD COLUMN IF NOT EXISTS max_daily_transfers INT DEFAULT 500;

-- Add configuration_id to transfer_allocations if it doesn't exist
ALTER TABLE transfer_allocations 
ADD COLUMN IF NOT EXISTS configuration_id INT,
ADD FOREIGN KEY IF NOT EXISTS (configuration_id) REFERENCES transfer_configurations(id);

-- Insert default daily balancer configuration
INSERT IGNORE INTO transfer_configurations 
(name, description, allocation_method, power_factor, min_allocation_pct, max_allocation_pct, 
 rounding_method, is_preset, is_active, enable_safety_checks, enable_logging, 
 daily_balancer_enabled, min_transfer_threshold, critical_stock_days, low_stock_days, max_daily_transfers,
 created_at, updated_at, created_by, updated_by) 
VALUES (
    'daily_balancer_default',
    'Default configuration for automated daily stock balancing',
    1,      -- allocation_method (1 = balanced)
    1.80,   -- power_factor
    15.00,  -- min_allocation_pct (15% warehouse reserve)
    25.00,  -- max_allocation_pct (25% proportional share)
    1,      -- rounding_method (1 = standard rounding)
    1,      -- is_preset
    1,      -- is_active
    1,      -- enable_safety_checks
    1,      -- enable_logging
    1,      -- daily_balancer_enabled
    2,      -- min_transfer_threshold
    2.0,    -- critical_stock_days
    5.0,    -- low_stock_days
    500,    -- max_daily_transfers
    NOW(), NOW(), 'system', 'system'
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_vend_inventory_product_outlet ON vend_inventory(product_id, outlet_id);
CREATE INDEX IF NOT EXISTS idx_vend_sales_date ON vend_sales(sale_date);
CREATE INDEX IF NOT EXISTS idx_vend_sales_line_items_product ON vend_sales_line_items(product_id);

-- Views for daily balancer reporting
CREATE OR REPLACE VIEW v_daily_transfer_summary AS
SELECT 
    DATE(created_at) as transfer_date,
    batch_id,
    COUNT(*) as total_transfers,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN priority = 'CRITICAL' THEN 1 ELSE 0 END) as critical_transfers,
    SUM(CASE WHEN priority = 'LOW' THEN 1 ELSE 0 END) as low_transfers,
    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_transfers
FROM daily_transfers 
GROUP BY DATE(created_at), batch_id
ORDER BY transfer_date DESC;

CREATE OR REPLACE VIEW v_current_stock_alerts AS
SELECT 
    sa.*,
    p.name as product_name,
    b.name as brand_name,
    o.name as outlet_name
FROM stock_alerts sa
LEFT JOIN vend_products p ON sa.product_id = p.product_id
LEFT JOIN vend_brands b ON p.brand_id = b.brand_id
LEFT JOIN vend_outlets o ON sa.outlet_id = o.outlet_id
WHERE sa.status = 'ACTIVE'
ORDER BY sa.alert_type, sa.days_of_stock ASC;