-- 20251003_0002_create_guardrail_traces.sql (M11)
-- Forward: create guardrail_traces to persist chain evaluation results.
-- Rollback: DROP TABLE guardrail_traces;
CREATE TABLE IF NOT EXISTS guardrail_traces (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id BIGINT UNSIGNED NULL, -- nullable until proposal persisted
  run_id CHAR(36) NULL,
  sequence SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  status ENUM('PASS','WARN','BLOCK') NOT NULL,
  message VARCHAR(255) NULL,
  meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_run (run_id),
  KEY idx_code_status (code, status),
  KEY idx_proposal (proposal_id),
  CONSTRAINT fk_guardrail_traces_proposal FOREIGN KEY (proposal_id) REFERENCES proposal_log(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
