-- ============================================================================
-- Security & Audit Database Schema
-- ============================================================================
-- Purpose: Database tables for security middleware and audit logging
-- Version: 1.0.0
-- Created: 2025-01-XX
-- ============================================================================

-- ============================================================================
-- Audit Log Table
-- ============================================================================
-- Stores all security events and audit trails
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` VARCHAR(64) PRIMARY KEY COMMENT 'Unique audit entry ID',
  `timestamp` DOUBLE NOT NULL COMMENT 'Unix timestamp with microseconds',
  `datetime` DATETIME NOT NULL COMMENT 'Human-readable datetime',
  `category` VARCHAR(50) NOT NULL COMMENT 'Event category (auth, authz, data, etc.)',
  `action` VARCHAR(100) NOT NULL COMMENT 'Action performed',
  `severity` VARCHAR(20) NOT NULL COMMENT 'Severity level (debug, info, warning, etc.)',
  `severity_level` TINYINT NOT NULL DEFAULT 1 COMMENT 'Numeric severity (0-7)',
  `user_id` INT NULL COMMENT 'User ID who performed action',
  `user_ip` VARCHAR(45) NOT NULL COMMENT 'Client IP address',
  `session_id` VARCHAR(128) NULL COMMENT 'Session ID',
  `details` JSON NULL COMMENT 'Additional event details',
  `user_agent` VARCHAR(500) NULL COMMENT 'Browser user agent',
  `request_uri` VARCHAR(500) NULL COMMENT 'Request URI',
  `request_method` VARCHAR(10) NULL COMMENT 'HTTP method',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
  
  INDEX `idx_category` (`category`),
  INDEX `idx_action` (`action`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_severity` (`severity_level`),
  INDEX `idx_timestamp` (`timestamp`),
  INDEX `idx_datetime` (`datetime`),
  INDEX `idx_category_datetime` (`category`, `datetime`),
  INDEX `idx_user_datetime` (`user_id`, `datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security audit log';

-- ============================================================================
-- Failed Login Attempts Table
-- ============================================================================
-- Tracks failed authentication attempts for lockout/ban enforcement
-- ============================================================================

CREATE TABLE IF NOT EXISTS `failed_login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'Client IP address',
  `username` VARCHAR(255) NULL COMMENT 'Attempted username',
  `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Time of attempt',
  `user_agent` VARCHAR(500) NULL COMMENT 'Browser user agent',
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is IP/user locked out',
  `locked_until` DATETIME NULL COMMENT 'Lockout expiration time',
  
  INDEX `idx_ip` (`ip_address`),
  INDEX `idx_username` (`username`),
  INDEX `idx_attempt_time` (`attempt_time`),
  INDEX `idx_locked` (`is_locked`, `locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Failed login tracking';

-- ============================================================================
-- IP Blacklist/Whitelist Table
-- ============================================================================
-- Persistent storage for IP filtering rules (complements JSON file storage)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ip_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(100) NOT NULL COMMENT 'IP address, CIDR, or range',
  `rule_type` ENUM('whitelist', 'blacklist') NOT NULL COMMENT 'Rule type',
  `reason` VARCHAR(500) NULL COMMENT 'Reason for rule',
  `added_by` INT NULL COMMENT 'User ID who added rule',
  `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When rule was added',
  `expires_at` DATETIME NULL COMMENT 'Expiration time (NULL = permanent)',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is rule active',
  
  UNIQUE KEY `uk_ip_type` (`ip_address`, `rule_type`),
  INDEX `idx_rule_type` (`rule_type`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP filtering rules';

-- ============================================================================
-- Session Management Table
-- ============================================================================
-- Stores active sessions for tracking and security
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(128) PRIMARY KEY COMMENT 'Session ID',
  `user_id` INT NULL COMMENT 'User ID',
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'Client IP',
  `user_agent` VARCHAR(500) NULL COMMENT 'Browser user agent',
  `payload` TEXT NOT NULL COMMENT 'Session data',
  `last_activity` INT UNSIGNED NOT NULL COMMENT 'Unix timestamp of last activity',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Session creation time',
  
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Session storage';

-- ============================================================================
-- Rate Limit Tracking Table (Optional - file-based is primary)
-- ============================================================================
-- Database-backed rate limiting (alternative to Redis/file storage)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(255) NOT NULL COMMENT 'Rate limit key (IP, user, endpoint)',
  `tokens` INT NOT NULL DEFAULT 0 COMMENT 'Available tokens',
  `window_start` INT UNSIGNED NOT NULL COMMENT 'Window start timestamp',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uk_key` (`key`),
  INDEX `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limit tracking';

-- ============================================================================
-- Security Events Table
-- ============================================================================
-- High-priority security incidents requiring immediate attention
-- ============================================================================

CREATE TABLE IF NOT EXISTS `security_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(100) NOT NULL COMMENT 'Type of security event',
  `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `description` TEXT NOT NULL COMMENT 'Event description',
  `ip_address` VARCHAR(45) NULL COMMENT 'Associated IP',
  `user_id` INT NULL COMMENT 'Associated user',
  `details` JSON NULL COMMENT 'Additional details',
  `status` ENUM('open', 'investigating', 'resolved', 'false_positive') NOT NULL DEFAULT 'open',
  `resolved_by` INT NULL COMMENT 'User who resolved',
  `resolved_at` DATETIME NULL COMMENT 'Resolution time',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_severity` (`severity`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security incidents';

-- ============================================================================
-- CSRF Tokens Table
-- ============================================================================
-- Stores CSRF tokens for validation
-- ============================================================================

CREATE TABLE IF NOT EXISTS `csrf_tokens` (
  `token` VARCHAR(64) PRIMARY KEY COMMENT 'CSRF token',
  `session_id` VARCHAR(128) NOT NULL COMMENT 'Associated session',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Token creation time',
  `expires_at` DATETIME NOT NULL COMMENT 'Token expiration',
  `used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Has token been used',
  
  INDEX `idx_session` (`session_id`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CSRF token storage';

-- ============================================================================
-- Password History Table
-- ============================================================================
-- Prevents password reuse
-- ============================================================================

CREATE TABLE IF NOT EXISTS `password_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL COMMENT 'User ID',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When password was set',
  
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password history';

-- ============================================================================
-- API Tokens Table
-- ============================================================================
-- Manages API authentication tokens
-- ============================================================================

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL COMMENT 'User ID',
  `token` VARCHAR(128) NOT NULL COMMENT 'API token',
  `name` VARCHAR(255) NULL COMMENT 'Token name/description',
  `scopes` JSON NULL COMMENT 'Token permissions',
  `last_used_at` DATETIME NULL COMMENT 'Last usage timestamp',
  `expires_at` DATETIME NULL COMMENT 'Expiration time',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is token active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uk_token` (`token`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API authentication tokens';

-- ============================================================================
-- Data Cleanup & Maintenance
-- ============================================================================
-- These events should be scheduled via cron or systemd timers
-- ============================================================================

-- Cleanup old audit logs (older than retention period)
-- Run daily: DELETE FROM audit_log WHERE datetime < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Cleanup old failed login attempts (older than 30 days)
-- Run daily: DELETE FROM failed_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Cleanup expired sessions
-- Run hourly: DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Cleanup expired CSRF tokens
-- Run hourly: DELETE FROM csrf_tokens WHERE expires_at < NOW();

-- Cleanup resolved security events (older than 1 year)
-- Run weekly: DELETE FROM security_events WHERE status = 'resolved' AND resolved_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- ============================================================================
-- Initial Security Configuration
-- ============================================================================

-- Insert default admin IP whitelist (adjust as needed)
-- INSERT IGNORE INTO ip_rules (ip_address, rule_type, reason, is_active) 
-- VALUES ('127.0.0.1', 'whitelist', 'Localhost', 1);

-- ============================================================================
-- End of Schema
-- ============================================================================
