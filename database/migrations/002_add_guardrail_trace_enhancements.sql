-- Migration: Add Guardrail Trace Enhancements
-- Description: Adds severity, reason, and duration_ms columns to guardrail_traces table
-- Sprint: 2, Phase 2
-- Author: GitHub Copilot
-- Date: 2025-10-10
-- Related PR: #2 - GuardrailChain: Determinism, Severity, Rich Tracing

-- ============================================================================
-- FORWARD MIGRATION
-- ============================================================================

-- Add new columns for enhanced guardrail tracing
ALTER TABLE guardrail_traces 
    ADD COLUMN severity VARCHAR(16) NOT NULL DEFAULT 'INFO' AFTER status
        COMMENT 'Severity level: INFO, WARN, BLOCK',
    ADD COLUMN reason VARCHAR(128) DEFAULT NULL AFTER severity
        COMMENT 'Machine-friendly reason code (e.g., below_cost_floor)',
    ADD COLUMN duration_ms DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER meta
        COMMENT 'Rail execution time in milliseconds';

-- Add indexes for common query patterns
CREATE INDEX idx_guardrail_traces_severity ON guardrail_traces(severity);
CREATE INDEX idx_guardrail_traces_reason ON guardrail_traces(reason);
CREATE INDEX idx_guardrail_traces_duration ON guardrail_traces(duration_ms);

-- Add composite index for severity + status queries
CREATE INDEX idx_guardrail_traces_severity_status ON guardrail_traces(severity, status);

-- ============================================================================
-- DATA BACKFILL (Optional)
-- ============================================================================

-- Derive severity from existing status values
UPDATE guardrail_traces 
SET severity = CASE 
    WHEN status = 'PASS' THEN 'INFO'
    WHEN status = 'WARN' THEN 'WARN'
    WHEN status = 'BLOCK' THEN 'BLOCK'
    ELSE 'INFO'
END
WHERE severity = 'INFO' AND status IN ('PASS', 'WARN', 'BLOCK');

-- Derive reason from existing message (basic extraction)
-- This is a best-effort backfill; new traces will have proper reasons
UPDATE guardrail_traces 
SET reason = LOWER(
    REPLACE(
        REPLACE(
            SUBSTRING(message, 1, 64),
            ' ', '_'
        ),
        '-', '_'
    )
)
WHERE reason IS NULL AND message IS NOT NULL AND message != '';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Verify column additions
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'guardrail_traces'
  AND COLUMN_NAME IN ('severity', 'reason', 'duration_ms')
ORDER BY ORDINAL_POSITION;

-- Verify index creation
SHOW INDEX FROM guardrail_traces 
WHERE Key_name IN (
    'idx_guardrail_traces_severity',
    'idx_guardrail_traces_reason',
    'idx_guardrail_traces_duration',
    'idx_guardrail_traces_severity_status'
);

-- Verify data distribution
SELECT 
    severity,
    COUNT(*) as count,
    AVG(duration_ms) as avg_duration_ms,
    MAX(duration_ms) as max_duration_ms
FROM guardrail_traces
GROUP BY severity
ORDER BY severity;

-- ============================================================================
-- ROLLBACK SCRIPT (run if migration needs to be reverted)
-- ============================================================================

-- DROP INDEX idx_guardrail_traces_severity_status ON guardrail_traces;
-- DROP INDEX idx_guardrail_traces_duration ON guardrail_traces;
-- DROP INDEX idx_guardrail_traces_reason ON guardrail_traces;
-- DROP INDEX idx_guardrail_traces_severity ON guardrail_traces;
-- 
-- ALTER TABLE guardrail_traces 
--     DROP COLUMN duration_ms,
--     DROP COLUMN reason,
--     DROP COLUMN severity;

-- ============================================================================
-- MIGRATION LOG (Update after running)
-- ============================================================================

-- INSERT INTO schema_migrations (migration, applied_at, status) VALUES
-- ('002_add_guardrail_trace_enhancements', NOW(), 'applied');
