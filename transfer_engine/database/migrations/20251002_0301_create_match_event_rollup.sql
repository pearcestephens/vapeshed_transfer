-- Daily rollup of match events to control growth
CREATE TABLE IF NOT EXISTS product_candidate_match_event_rollup (
  rollup_date DATE NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  events INT NOT NULL,
  PRIMARY KEY (rollup_date,event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Daily rollup of match events to control growth
CREATE TABLE IF NOT EXISTS product_candidate_match_event_rollup (
  rollup_date DATE NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  events INT NOT NULL,
  PRIMARY KEY (rollup_date,event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
