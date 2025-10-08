# Project Knowledge Base

## Overview
Unified Retail Intelligence Platform (isolated transfer engine evolved): stock balancing, pricing analysis, market planning, heuristic forecasting, synonym + image normalization, neuro insights, guardrailed automation.

## Architecture Summary
Domains: Transfer, Pricing, Crawler (planner scaffold), Matching/Synonyms, Image Clustering, Insights (Neuro), Policy Scoring, Realtime (SSE), Support (Config/DB/HTTP), Security (Idempotency/CSRF), Optional Forecast (heuristics baseline).

## Directory Map (Current + Planned)
- transfer_engine/app, src: legacy + refactor base
- transfer_engine/bin: operational scripts
- transfer_engine/database: schema + views (pending unified views)
- unified/src/Support: Config, Env, Pdo, Logger, Http, Validator, Idem, Util
- unified/src/Transfer: BalancerEngine, Repository, Service
- unified/src/Pricing: MatchEngine, RulesEngine, AnalyzerService
- unified/src/Crawler: Planner, NormalizeService (future), SiteHealth
- unified/src/Insights: InsightWriter, KpiReporter
- unified/src/Realtime: EventBus, Streams
- unified/src/Queue (adapter) & Security (Signer, Csrf)
- unified/resources/views/dashboard: overview, stores, pricing, alerts

## Key Components
- Guardrail Chain (pricing + transfers) with pass/warn/block semantics
- Policy Scoring Strategy (baseline scoring thresholds)
- Config namespace `neuro.unified.*` fully reserved
- Matching pipeline (brand canonicalization + tokenization + fuzzy weighting)
- Heuristic demand forecast (rolling weighted means) placeholder for ML
- Insights feed merging patterns / anomalies / alerts with acknowledgment

## Recent Consolidated Changes (2025-10-02)
- Namespaced configuration plan established
- Unified specification merged (pricing, crawler, insights, policy scoring)
- Expansion of acceptance criteria & KPI set
- Planning for materialized view optional layer (mv_* shadow tables)

## Pending Enhancements (Prioritized)
1. P0 Support scaffolding & config loader with fallback audit
2. P1 Balancer migration into unified module + guardrail instrumentation
3. P2 Pricing analyzer (deterministic + fuzzy matching stage) & proposals
4. P3 Forecast heuristic integration (views + rolling metrics)
5. P4 Policy scoring & auto-apply gating
6. P5 Crawler planner + normalization outline
7. P6 Dashboard pages + SSE counters
8. P7 Drift metrics & champion/challenger extension

## Guardrail Definitions (Canonical)
Rule | Purpose | Outcome
---- | ------- | -------
Cost Floor | Prevent pricing below cost multiplier | BLOCK
Min Margin | Maintain gross margin threshold | BLOCK/WARN
Delta Cap | Limit abrupt price change magnitude | BLOCK
Cooloff | Avoid immediate competitor oscillation reaction | BLOCK/SKIP
Price War | Suppress downward moves in war conditions | BLOCK
Elasticity Gating | Require model confidence for elastic rules | SKIP/BLOCK
Donor Floor (Transfer) | Protect donor store DSR floor | BLOCK
Receiver Overshoot | Avoid pushing receiver above max buffer | BLOCK
ROI Viability | Enforce non-negative aggregate expected uplift | BLOCK
Line Cap | Bound daily operational workload | BLOCK
Scoring Band | Determine action path (auto/propose/discard) | PASS (band)

## Policy Scoring Bands
- Auto: score ≥ auto_apply_min (default 0.65)
- Propose: score ≥ propose_min (0.15) and < auto_apply_min
- Discard: score < propose_min
- Block override: ROI < 0 or guardrail fatal failure

## Config Namespace (Excerpts)
- neuro.unified.balancer.* (target_dsr, daily_line_cap, dsr limits)
- neuro.unified.pricing.* (floors, margins, delta_caps, war thresholds)
- neuro.unified.matching.* (min_confidence, token cache)
- neuro.unified.elasticity.* (confidence gating)
- neuro.unified.views.materialize.* (view toggles)
- neuro.unified.policy.* (score strategy, negative ROI block)
- neuro.unified.drift.* (psi thresholds)
- neuro.unified.redis.* (enabled / locks / sse counters)

## Matching Pipeline Stages
1. Brand canonicalization + synonym expansion
2. Name normalization (lowercase, punctuation strip, unit harmonization)
3. Token extraction (size_ml, strength_mg_ml, pack_qty, flavor stem)
4. Deterministic composite fingerprint equality check
5. Fuzzy similarity (trigram/Jaro) with weighted attributes
6. Score + feature contributions JSON
7. Confidence gating (≥ 0.82 default)
8. Persist match & explanation factors

## Heuristic Forecast (Placeholder)
- Weighted mean demand: 7/14/28 day windows (weights 0.5/0.3/0.2)
- Stockout risk proxy: logistic(ds_target - dsr / volatility_adjust)
- Elasticity: null until model ready (guardrail skip path)

## Insights Feed Taxonomy
Type | Example
---- | -------
pattern | "DSR variance reduced across Balancer run"
anomaly | "Price war condition spike brand X"
recommendation | "Increase min_margin_pct for category Pods"
alert | "SSE heartbeat misses > threshold"

## Logs & Telemetry Fields
Field | Description
----- | -----------
run_id | Unique per module/run cycle
module | transfer/pricing/crawler/etc
stage | pipeline phase
severity | info/warning/critical
correlation_id | ties related events
metrics.duration_ms | wall time
metrics.sql_count | query volume
guardrail.result | PASS/WARN/BLOCK
score.value | final policy score

## Performance Targets (Adjustable)
Component | Target
--------- | ------
Balancer | <5s (10k SKUs)
Pricing Compare | <8s (5k candidates)
Crawler Plan | <1s cycle
Forecast Heuristic | <12s batch
SSE Latency | ≤ keepalive + 2s

## Materialized Views Strategy
- Controlled by `neuro.unified.views.materialize.<view>` boolean
- mv_* tables refreshed manually/by job with metadata row

## Migration & Rollback Outline
- Config rename window (legacy -> neuro.unified.*) documented separately
- Fallback loader emits single WARN per legacy key used
- Rollback: raise thresholds, disable auto-apply, revert snapshot

## KPIs Baseline
KPI | Description
--- | -----------
stockout_hours_reduced | DSR threshold comparison
pricing_uplift_margin_delta | Accepted vs baseline margin impact
guardrail_block_rate | BLOCK actions / total actions
heuristic_wape_baseline | Pre-ML accuracy marker
sse_uptime_percent | SSE reliability

## Open Work (Tracked)
- Guardrail trace persistence decision (table vs sampled logs)
- View SQL finalization & deployment bundle
- Token cache table DDL & maintenance CLI
- Forecast provider interface & eventual ML swap
- Pricing rule revision ledger (future)

## Change Log
- 2025-10-02: Unified expansion (config namespace, guardrails, scoring, roadmap integration)

