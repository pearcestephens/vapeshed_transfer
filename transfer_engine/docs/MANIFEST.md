# MANIFEST

Generated: 2025-10-02
Total Files: 220

## Top-Level Directories
- app
- bin
- config
- database
- docs
- public
- resources
- routes
- scripts
- src
- storage

## Highlights
- CLI Bootstrap: bin/_cli_bootstrap.php
- Knowledge Base: docs/KNOWLEDGE_BASE.md
- Specification: docs/PROJECT_SPECIFICATION.md
- OpenAPI (placeholder/spec evolving): docs/API_OPENAPI_SPEC.yaml
- Schema: database/SCHEMA, database/SCHEMA.SQL

## CLI Scripts (bin/ excerpt)
 - auto_tune_patch_generator.php
 - build_image_clusters_bktree.php
 - build_image_clusters.php
 - _cli_bootstrap.php
 - cluster_integrity_check.php
 - feature_flags_cli.php
 - feature_store_updater.php
 - image_fetch_worker.php
 - job_worker.php
 - match_calibrate.php
 - match_evaluate.php
 - match_event_rollup.php
 - matching_worker.php
 - match_readiness.php
 - match_threshold_drift_monitor.php
 - outbox_dispatcher.php
 - seed_brand_synonyms.php
 - session_enrichment.php
 - synonym_auto_promote.php
 - synonym_candidates_cli.php
...

## Storage Structure (storage/)
 - 
 - backups

## Notes
- Full file inventory can be generated with: find . -type f | sort > FULL_FILELIST.txt

## Consolidation (Phase M1 Added Support Layer)
Date: 2025-10-02
Action: Introduced unified Support namespace inside `transfer_engine/src/Support/` (Config, Env, Pdo, Logger, Validator, Idem, Http, Util) copied from sandbox `unified/` to begin consolidation.
Rollback: Remove directory `src/Support` and revert MANIFEST change.
Next: Adapter wrap of balancer logic + guardrail chain scaffolding (Phase M2/M3).

## Consolidation (Phase M2 Adapter Scaffold)
Date: 2025-10-02
Action: Added `src/Support/BalancerAdapter.php` (placeholder simulatePlan) and `bin/unified_adapter_smoke.php` for Support layer validation. No legacy logic altered.
Rollback: Delete new adapter + smoke script; remove this section.
Next: Integrate guardrail chain skeleton + config fallback shim (Phase M3/M4).

## Consolidation (Phase M3 Guardrail Chain Scaffold)
Date: 2025-10-02
Action: Added guardrail framework (`src/Guardrail/*`), extended smoke script to evaluate CostFloor + DeltaCap guardrails via chain. Non-invasive placeholder context.
Rollback: Remove `src/Guardrail` directory + revert smoke script changes + manifest section.
Next: Config fallback shim + integration of additional guardrails (ROI viability, line cap, donor/receiver).

## Consolidation (Phase M4 Guardrails Expansion & Config Lint)
Date: 2025-10-03
Action: Added ROI, Donor Floor, Receiver Overshoot guardrails; expanded smoke script; introduced config fallback enhancements & `bin/unified_config_lint.php`.
Rollback: Remove added guardrail classes, revert Support/Config changes, delete lint script, revert manifest section.
Next: Introduce scoring engine scaffold + insights emission stub (Phase M5).

## Consolidation (Phase M5 Scoring & Insights Stub)
Date: 2025-10-03
Action: Added scoring engine (`src/Scoring/ScoringEngine.php`) and insights emitter (`src/Insights/InsightEmitter.php`); updated smoke script to include scoring + insight emission.
Rollback: Remove added directories/files and revert smoke script & manifest section.
Next: Phase M6 SSE scaffolding + heartbeat emitter.

## Consolidation (Phase M6 Realtime SSE Scaffold)
Date: 2025-10-03
Action: Added realtime SSE components (`src/Realtime/EventStream.php`, `HeartbeatEmitter.php`), public endpoint `public/sse.php`, and smoke script heartbeat test.
Rollback: Remove added realtime files + sse.php + revert smoke & manifest.
Next: Phase M7 drift metrics integration & materialized view toggle logic.

## Consolidation (Phase M7 Drift & Materialization)
Date: 2025-10-03
Action: Added PSI calculator, view materializer, drift smoke script, config defaults for drift & view materialization.
Rollback: Remove `src/Drift`, `src/Views`, drift smoke script, revert config edits.
Next: Phase M8 health & readiness endpoints.

## Consolidation (Phase M8 Health Endpoint)
Date: 2025-10-03
Action: Added `HealthProbe` and `public/health.php` JSON health check.
Rollback: Remove `src/Health`, `public/health.php` and manifest section.
Next: Phase M9 persistence scaffolding.

## Consolidation (Phase M9 Persistence Scaffolds)
Date: 2025-10-03
Action: Added `ProposalStore`, `RunLogWriter` (stdout placeholders). Extended smoke script with persistence + drift + materialization outputs.
Rollback: Remove `src/Persistence` files + revert smoke script section.
Next: Phase M10 (to be added) - Policy application orchestration stub.

## Consolidation (Phase M10 Policy Orchestrator Skeleton)
Date: 2025-10-03
Action: Added `PolicyOrchestrator` coordinating guardrails + scoring + proposal persistence; smoke script extended with policy result.
Rollback: Remove `src/Policy/PolicyOrchestrator.php` + revert smoke modifications + manifest section.
Next: Future phases will replace placeholder persistence and wire real pricing/transfer contexts.

## Consolidation (Phase M11 Migrations Added)
Date: 2025-10-03
Action: Added migration files for proposal_log, guardrail_traces, insights_log, run_log, config_audit, drift_metrics.
Rollback: Drop tables in reverse dependency order (drift_metrics, config_audit, run_log, insights_log, guardrail_traces, proposal_log).
Next: Phase M12 implement real persistence adapters writing to these tables.

## Consolidation (Phase M12 Persistence Implementation)
Date: 2025-10-03
Action: Added repositories (Proposal, GuardrailTrace, Insight, RunLog, DriftMetrics, Db wrapper); updated ProposalStore & PolicyOrchestrator to begin DB writes (proposal id currently opaque) and extended smoke script wiring.
Rollback: Remove added repository classes; revert PolicyOrchestrator & ProposalStore; optional truncate new tables.
Next: Phase M13 pricing engine skeleton + linking proposal IDs to traces.

## Consolidation (Phase M13 Pricing Skeleton & Trace Linkage)
Date: 2025-10-03
Action: Added Pricing module skeleton (`src/Pricing/{CandidateBuilder,RuleEvaluator,PricingEngine}.php`); modified `ProposalStore` to return inserted ID; updated `PolicyOrchestrator` to persist guardrail traces with real proposal_id; extended smoke script to exercise pricing run producing proposals and trace linkage.
Rollback: Delete `src/Pricing` directory and revert changes to ProposalStore, PolicyOrchestrator, and smoke script (restore prior commit); guardrail_traces rows with proposal_id set remain valid but orphan-safe.
Next: Phase M14 transfer integration refactor + real data sourcing for pricing candidates.

## Consolidation (Phase M14 Transfer Integration Skeleton)
Date: 2025-10-03
Action: Added `src/Transfer/{DsrCalculator,LegacyAdapter,TransferService}.php`; updated smoke script to execute transfer run producing propose-only transfer proposals (type=transfer) through policy pipeline. No schema changes required.
Rollback: Remove `src/Transfer` directory and revert smoke script modifications in `bin/unified_adapter_smoke.php` Phase M14 block.
Next: Phase M15 Matching normalization utilities (brand/token extraction) and integration stub.

## Consolidation (Phase M15 Matching Utilities Skeleton)
Date: 2025-10-03
Action: Added `src/Matching/{BrandNormalizer,TokenExtractor,FuzzyMatcher}.php`; smoke script extended with normalization + similarity example.
Rollback: Remove `src/Matching` directory and revert matching block in smoke script.
Next: Phase M16 forecast heuristic provider.

## Consolidation (Phase M16 Forecast Heuristic Provider)
Date: 2025-10-03
Action: Added `src/Forecast/HeuristicProvider.php`; smoke script now produces summary stats (avg, sma_3, sma_7, safety_stock) for sample history.
Rollback: Remove `src/Forecast/HeuristicProvider.php` and revert forecast block in smoke script.
Next: Phase M17 insight enrichment linking proposals to drift & demand anomalies.

## Consolidation (Phase M17 Insight Enrichment Snapshot)
Date: 2025-10-03
Action: Added `src/Insights/InsightEnricher.php`; smoke script extended to output enrichment snapshot linking recent proposals with last drift metric.
Rollback: Remove InsightEnricher file and strip enrichment section from smoke script.
Next: Phase M18 policy auto-apply pilot (narrow scope, cooloff enforcement) — will introduce auto_apply flag + cooloff tracking.

## Consolidation (Phase M18 Policy Auto-Apply Pilot Placeholder)
Date: 2025-10-03
Action: Extended `PolicyOrchestrator::process` to include optional auto-apply pilot logic for pricing promote band behind config flag `neuro.unified.policy.auto_apply_pricing` (no side-effects yet, only metadata fields returned).
Rollback: Remove auto-apply block in `PolicyOrchestrator` and manifest section.
Next: Implement cooloff repository + action router + audit log expansion.

### Enhancement: Cooloff Infrastructure Groundwork
Date: 2025-10-03
Action: Added migration `20251003_0007_create_cooloff_log.sql`, `CooloffRepository`, wired optional cooloff + config-driven auto-apply gating into `PolicyOrchestrator` and smoke script.
Rollback: Drop `cooloff_log` table, remove repository file, revert orchestrator & smoke script changes referencing cooloff.

### Enhancement: Config Defaults Extended (Auto-Apply & Cooloff)
Date: 2025-10-03
Action: Added defaults `neuro.unified.policy.auto_apply_pricing=false`, `neuro.unified.policy.cooloff_hours=24` in `src/Support/Config.php`.
Rollback: Remove these keys from config prime() array if reverting feature.

### Enhancement: Action Audit Infrastructure & Dashboard
Date: 2025-10-03
Action: Added migration `20251003_0008_create_action_audit.sql`, `ActionAuditRepository`, unified dashboard endpoint (`public/unified_dashboard.php`), validation CLI (`bin/validate_system.php`). Updated `PolicyOrchestrator` to record audit entries on auto-apply. Smoke script now wires audit repo.
Rollback: Drop `action_audit` table, remove `ActionAuditRepository.php`, remove `public/unified_dashboard.php`, remove `bin/validate_system.php`, revert `PolicyOrchestrator` & smoke script audit wiring.

### Enhancement: Real Data Sourcing Infrastructure
Date: 2025-10-03
Action: Added crawler components (`src/Crawler/{HttpClient,ProductScraper}.php`), real pricing candidate builder (`src/Pricing/RealCandidateBuilder.php`), test scripts (`bin/test_crawler.php`, `bin/test_url_scrape.php`, `bin/test_real_matching.php`). HttpClient uses Chrome user-agent for scraping; ProductScraper fetches from Google; RealCandidateBuilder queries DB for low-margin products. All tested and functional.
Rollback: Remove `src/Crawler` directory, remove `src/Pricing/RealCandidateBuilder.php`, remove test scripts.
Status: Ready for integration into pricing/transfer candidate generation; currently parallel to static samples.
