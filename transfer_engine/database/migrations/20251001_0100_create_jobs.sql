-- Jobs table (leased job queue with heartbeats & idempotency)
CREATE TABLE IF NOT EXISTS jobs (
  job_id CHAR(36) PRIMARY KEY,
  queue VARCHAR(64) NOT NULL,
  run_id CHAR(40) NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  status ENUM('pending','leased','processing','completed','failed','dead') NOT NULL DEFAULT 'pending',
  payload_json JSON NULL,
  attempt INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 5,
  lease_expires_at DATETIME NULL,
  heartbeat_at DATETIME NULL,
  next_visible_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_jobs_idem (queue, idempotency_key),
  KEY idx_jobs_queue_status (queue, status, next_visible_at),
  KEY idx_jobs_lease (status, lease_expires_at),
  KEY idx_jobs_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
