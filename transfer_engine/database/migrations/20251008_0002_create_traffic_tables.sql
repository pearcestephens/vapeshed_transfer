-- Traffic metrics schema for Section 11 monitoring
-- Deploy via: mysql -u jcepnzzkmj -p jcepnzzkmj < this_file.sql

-- Raw request tracking (slim, fast inserts)
CREATE TABLE IF NOT EXISTS traffic_requests (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ts            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  method        VARCHAR(8) NOT NULL,
  endpoint      VARCHAR(120) NOT NULL,
  status        SMALLINT UNSIGNED NOT NULL,
  ms            INT UNSIGNED NOT NULL,
  ip            VARBINARY(16) NULL,
  ua_hash       BINARY(16) NULL,
  corr          CHAR(16) NULL,
  bytes_out     INT UNSIGNED NULL,
  err           TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_ts (ts),
  KEY idx_ep (endpoint),
  KEY idx_err (err, ts),
  KEY idx_status (status)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED;

-- Minute-level aggregates (future use for dashboards)
CREATE TABLE IF NOT EXISTS traffic_counters (
  bucket        DATETIME NOT NULL,               -- YYYY-MM-DD HH:MM:00
  endpoint      VARCHAR(120) NOT NULL,
  method        VARCHAR(8) NOT NULL,
  hits          INT UNSIGNED NOT NULL,
  errs          INT UNSIGNED NOT NULL,
  p50_ms        SMALLINT UNSIGNED NOT NULL,
  p95_ms        SMALLINT UNSIGNED NOT NULL,
  p99_ms        SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (bucket, endpoint, method)
) ENGINE=InnoDB;