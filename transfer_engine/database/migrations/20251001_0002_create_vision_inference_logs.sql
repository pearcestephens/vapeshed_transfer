-- Create vision_inference_logs table (idempotent)
CREATE TABLE IF NOT EXISTS vision_inference_logs (
  inference_id VARCHAR(40) PRIMARY KEY,
  provider VARCHAR(64) NOT NULL,
  model VARCHAR(96) NULL,
  vision_type VARCHAR(32) NULL,
  source_url TEXT NULL,
  frame_hash CHAR(64) NULL,
  status VARCHAR(32) NOT NULL,
  latency_ms INT NULL,
  tokens_in INT NULL,
  tokens_out INT NULL,
  cost_usd DECIMAL(10,5) NULL,
  meta_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (provider),
  INDEX (status),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
