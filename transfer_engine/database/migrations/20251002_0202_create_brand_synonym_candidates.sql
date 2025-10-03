-- Candidate brand synonym tokens discovered from accepted matches
CREATE TABLE IF NOT EXISTS brand_synonym_candidates (
  candidate_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(160) NOT NULL,
  occurrences INT NOT NULL DEFAULT 1,
  sample_candidate_ref CHAR(36) NULL,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  flagged TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_token (token),
  KEY idx_occurrences (occurrences)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
