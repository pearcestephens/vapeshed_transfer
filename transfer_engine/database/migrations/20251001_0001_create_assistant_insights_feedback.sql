-- Create assistant_insights_feedback table (idempotent)
CREATE TABLE IF NOT EXISTS assistant_insights_feedback (
  feedback_id VARCHAR(40) PRIMARY KEY,
  insight_id VARCHAR(40) NOT NULL,
  user_id INT NOT NULL,
  reaction ENUM('up','down') NOT NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (insight_id),
  INDEX (user_id),
  INDEX (reaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
