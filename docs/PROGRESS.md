# IDPS V1 execution ledger

Approved execution: 2026-11-09, continuous dependency-driven batches. No repeated approval gates for routine local development. Production deployment and engineering signoff are separate.

## Resume instructions

Read this file, `planning/DECISIONS.md`, `TASKS.md`, and `git log -8 --oneline`. Resume the first incomplete batch; do not recreate installed applications or rerun archived training into its original output directory. Never run destructive migrations against an existing database. Local secrets and datasets are git-ignored.

## Batch 1 — RF-001 runtime foundation — VERIFIED LOCALLY

- Existing Laravel 13.40 / PHP 8.4.12; Vue 3 / TS 6; MySQL 8.4.3 installed in Laragon.
- Baseline: backend 2 tests; frontend build/typecheck + 1 test; Python 7 tests; blueprint/static SQL checks pass.
- Initial MySQL process stopped; Laravel uses SQLite. No IDPS business migrations applied.
- Frontend audit: 16 moderate development dependency findings, zero runtime findings. Fix under compatibility checks.
- Branch: `codex/v1`. Existing workspace retained because all source is initially untracked and local Laragon/dependencies already configured.
- MySQL running on loopback 3306; scoped app/migrator/test accounts created, existing schemas/SQLite untouched. Connection timezone UTC, InnoDB and utf8mb4; test asserts exact DECIMAL(20,6), null and DATETIME(6).
- RED: connectivity test failed because driver was SQLite. GREEN: backend 3 tests / 11 assertions on real MySQL. Missing testing APP_KEY fixed using key:generate, no secret output.
- Frontend `npm run check` passes (lint, 1 test, typecheck/build); full npm audit 0. Scoped patched minimatch/glob overrides; no framework major changes.
- Composer Boost installed per local instructions; generated guidelines reviewed. Composer audit no advisories. CI added (not remotely executed).
- HTTP smoke: both backend 8000 and Vue 5173 returned 200. Commit `6434e55` records verified foundation (initial safe source baseline `541658c`).

## Batch 2 — governance / imports / operational modules — IN PROGRESS

- Governance implemented: UUID users, hashed expiring/revocable bearer tokens, explicit role/permission matrix and facility/unit scopes; independent process approval; append audit API. No production self-approval permissions for SYSTEM_ADMIN or ML_OPERATOR.
- Core+initial operations tests GREEN: 8 / 52 assertions (agent); root governance+import+precision GREEN: 6 / 38 assertions, real MySQL. Import tests cover replay, lineage, independent review, stale lock rejection, no invented daily rows. New revision/unique approved-scope safeguards require next run.
- Audit SQL triggers rejected because binary logging requires SUPER; do not weaken global MySQL settings. Use runtime per-table SELECT/INSERT-only audit/approval grants after migrations; migrator remains separate. `scripts/harden_local_mysql.php` pending local verification.
- Parser: 2021,2022,2023 original workbooks each 336 long observations (28 measurements per month). Label/header guards; source locators; raw precision retained and rounding flagged; ambiguous fuel/loss semantics and source disagreements stay in reconciliation.
- Shared test DB concurrency caused transient reset collisions between agents. Central root-owned DB token now serializes all MySQL suites; no results from collided runs counted.
- Python service: new integrity/availability/serialization tests + research regression pass; Laravel queue integration initially GREEN 4/16. Full integrated run pending. Missing historical availability => `not_ready`, never backdated.
- Current work: reports/engineering definitions; full RTL frontend; final async integration, real source staging (unapproved), permissions hardening, backup/restore and cross-service smoke.

## Remaining batches

2. RF-106 + identity/RBAC/audit/approvals/reference catalogs/outbox (RF-002..010).
3. RF-107..109 + source mappings, reconciliation, staging/revisions and authorized monthly import.
4. Planning, production, quality/blending, energy/losses/chemicals, events and engineering KPIs.
5. RF-110..116 Dataset/Training Studio, asynchronous Python jobs, evaluation, registry and monthly forecast.
6. RF-117..119 new data, governed retraining, event reforecast; reporting, RTL UX, security, restore and UAT.

## Engineering gates (do not guess)

Unresolved source totals and 2023 semi-actual conflicts; final product stages; fuel/loss scope and energy factors; model promotion thresholds. Software must expose pending/not-ready and preserve original values. These do not prevent implementing review workflows.

## Checkpoint — 2026-11-10

- Local source staging executed: 12 original hashes verified, 3 workbook batches /1008 long facts over36 months; replay returned existing batches for all3. No engineering approval performed.
- Original-workbook versus provisional-CSV comparison: 956/1008 equal at6 decimals; 52 differences retained for review (precision differences plus2 original missing cells). Never replace originals or fill missing raw cells from CSV.
- Live HTTP smoke: Laravel+MySQL+FastAPI health up. Research snapshot created through authenticated API, public training returned queued, real queue worker persisted not_ready/missing_historical_availability_or_quantity/can_deploy=false. This is expected truthful gating, not failed infrastructure and not evidence of a trained refinery model.
- Runtime grants hardened: audit_logs/approval_actions SELECT+INSERT only, separate migration account. Harden script rerun is idempotent (handles missing broad grants1141).
- Frontend `npm run check` GREEN:6 tests, lint/typecheck/build. Python current synthetic service+parser suite14 passing; prior combined research regression21 passing. Formatting applied; final combined backend run in progress.
- Added durable outbox consumer command `php artisan idps:process-outbox` scheduled every minute; queue worker handles immutable event revisions and retry idempotency. Event-adjusted quantities remain unavailable without signed assumptions.
- Remaining: final integrated backend checks/review, reference catalogs/calendar/unit timeline, automated local start/backup+restore drill, scope completeness/UAT matrix. V1 is NOT declared complete.
- Final checkpoint backend run GREEN:27 tests /187 assertions on real MySQL;40 API routes registered. Includes governance, imports, operations/engineering/report outputs, AI activation/retry and reforecast suites. Local owner UAT and completeness gates remain open.

## Batch 3 in progress — retrospective research and readiness

- Starting point `31870c4`; no application recreation. Readiness matrix now covers all 119 RF tickets conservatively in `docs/V1_READINESS_MATRIX.md` and records pending engineering decisions separately.
- Research-only training now accepts provisional monthly rows with unknown availability without inventing timestamps; chronological prior-month-only features, 2021/2022/2023 split, explicit `retrospective_unknown_not_point_in_time` warning and permanent promotion block. Known late availability remains excluded. Operational training continues to require actual availability and approval.
- Python service/parser `python -B -m pytest -q -p no:cacheprovider services/ai/tests tests/test_monthly_parser.py`: 15 passed. Laravel integration test updated; real-MySQL run pending shared test database release.
- Work underway: scoped quality/unit/shift reference APIs and unified runtime/backup. Do not treat unfinished concurrent files as completed. Next: run AI integration tests after idps_test is free, review these changes and update evidence/commit; then integrate reference module and runtime scripts with their tests.

## Batch 3 — 2026-11-10: retrospective research, references, local recovery

- `docs/V1_READINESS_MATRIX.md` covers 119 tickets; conservative evidence assessment, not UAT.
- Research-only training accepts unknown availability without inventing dates: strictly prior monthly features and 2021/2022/2023 split, explicit unverified availability assumption and permanent promotion block; operational training still requires real availability/approval. Laravel integration 8 tests /48 assertions, Python service/parser 15 tests passed.
- Added scoped shift/calendar/unit-status/quality-specification reference APIs and independent approval; quality conformance returns not_evaluated when missing approved evidence. MySQL migration 150000 applied to local development; access grants refreshed. Full backend suite: 30 tests /206 assertions passed on MySQL after routes added.
- Local runtime start/status/stop checked; stops only processes launched by its own script. MySQL DPAPI-encrypted backup restored to isolated `idps_restore_528f3a49b20446d6`: 50 tables, 1008 monthly facts, zero orphan source references. Restore database retained. Backup does not yet include original files, attachments or model artifacts.
- Remaining: full protected backup of files/artifacts; frontend for new references, time-safe retrospective UI, full E2E/UAT, owner approval, evaluation of all partially implemented/missing tickets. No source facts or models approved.
- Next: inspect readiness matrix and add frontend workflows for reference APIs; exercise cross-service retrospective training, then continue remaining gaps. Use MySQL test schema only for automated tests.

## Research integration smoke — 2026-11-10

- Authenticated Laravel API created/reused a DIESEL research snapshot from the staged 2021–2023 monthly facts. A queued run was retried through a dedicated loopback FastAPI worker using the updated code. Result: `completed`, `availability_policy=retrospective_unknown_not_point_in_time`, `can_deploy=false`; no timestamps fabricated. This is research evidence, not an approved operational accuracy claim.
- The first automatic attempt reached an older still-running worker and was recorded as `AI_WORKER_FAILED`; the explicit retry succeeded with the new service. Unified startup must ensure processes actually run the current release. Do not silently report old process code as updated.

## Batch 4 — protected files backup and actual research run

- Actual local DIESEL research candidate completed via Laravel and loopback Python worker, tagged `retrospective_unknown_not_point_in_time`, `can_deploy=false`. The first retry hit an old service process, then succeeded with the new worker; restart/replace only service processes owned by IDPS startup.
- Added DPAPI-protected backup of raw sources, private attachments and local AI artifacts with per-file SHA256 and source-change detection; isolated restore verified 19/19 hashes without altering originals. Database and file backup are separate: quiesce writes and cross-check references before relying on a combined restore; DPAPI depends on the Windows account and excludes credentials.
- Next: independently check frontend reference workflow and run npm suite, then exercise combined database/file recovery and remaining RF gaps. Keep owner UAT and engineering signoff pending.

## Batch 5 — reference interfaces

- Added Arabic RTL workflows for shift definitions, operating calendar, unit state intervals, quality specifications and approvals, conformance and downtime; permissions, missing data and pending approvals are visible. Training Studio explicitly warns that unknown historical availability makes retrospective research an assumption, never a promotable model.
- `npm run check`: lint, 8 tests, TypeScript and Vite build passed; `npm audit --omit=dev` reported zero vulnerabilities. No refinery UAT or engineering signoff is implied.
- Next: full end-to-end workflow with real local queue and isolated fixtures; close data mapping, monitoring/retraining, full recovery consistency and security gaps documented in `docs/V1_READINESS_MATRIX.md`.

## Batch 6 — end-to-end synthetic integration and service freshness

- Added an interconnected Laravel/MySQL synthetic workflow: actual bearer login, import/review/approval, plan and production approval, KPI, event/outbox scenarios, dataset snapshot and retraining while preserving a synthetic champion. MySQL E2E test: 1 test /37 assertions passed. AI response was mocked `not_ready`; this does not establish a real approved-model retraining or owner UAT.
- Health endpoint now reports the training code hash captured at service startup; startup script checks it against the current local file and rejects stale/unknown AI listeners before starting workers. Python tests 15 passed. Confirmed prior listener stale, safely stopped the specific IDPS process started in the earlier session after PID/command/log verification; restarted managed worker/scheduler with current AI health. PowerShell 7 requirement documented.
- Next: run full backend suite including E2E (previous full suite30/206 preceded new E2E), inspect readiness matrix for remaining gaps, real approved-data retraining after engineering approval, and combined DB/files recovery consistency. V1 still incomplete.
- Full backend regression after E2E integration: `php vendor/bin/phpunit --do-not-cache-result` passed 31 tests /243 assertions on dedicated MySQL test schema.

## Restore consistency drill — 2026-11-10

- `scripts/local-verify-restore.ps1` cross-checked isolated MySQL and isolated file restore: 12/12 actual source hashes matched. No model rows existed in that backup; an isolated synthetic model/artifact/approval fixture then verified 1/1. Empty restore directory negative tests correctly detected missing sources and model and returned failure.
- Restored database paths still refer to original storage paths; the checker does not rewrite them. A usable alternate environment requires explicit, controlled path mapping and a quiesced consistent backup pair. No original files or existing databases deleted.

## Batch 7 — daily readiness, source mapping foundation and AI monitoring

- Daily readiness API/UI reports approved full-day observations, zero versus missing and missing dates; monthly totals and individual shifts are not treated as complete days. Coverage alone remains DAILY_MODEL_NOT_READY.
- Added versioned source mapping review API with scope/unit/granularity, independent approval and unresolved queue. CSV parser rejects unknown headers rather than silently discarding them. Connecting approved mappings and unresolved queue to the importer and UI remains incomplete; do not mark RF-015 done.
- Added forecast-versus-approved-actual evaluations, monthly candidate retraining policies and conservative monitoring. One earliest-as-of forecast per month is counted; mixed engine/model scope returns ambiguous_scope until filtered. A sample minimum of 12 is a configurable technical floor, not an approved scientific threshold; drift remains unclaimed without a signed threshold.
- MySQL migrations 160000/170000 applied locally and scoped grants refreshed. `php vendor/bin/phpunit --do-not-cache-result`: 39 tests /309 assertions; Python service/parser16 tests; frontend npm check10 tests and build. All on local/test only.
- Next: integrate approved source dictionary with CSV/XLSX importer and reviewer interface, expose retraining/monitoring UI, then full real queue/restore/E2E, security and remaining readiness matrix gaps. No engineering or model approval inferred.

## Batch 8 — monitoring UI and regression

- Added Arabic view for evaluation summaries and governed monthly retraining policies; users select facility/product and may filter engine/model. Insufficient samples and ambiguous scopes are explicit; no statistical drift claim is shown. Added evaluation action for completed forecasts against matching approved actuals.
- `npm run check`: 11 tests, lint, TypeScript and Vite build passed. Full MySQL backend regression: 39 tests /309 assertions passed. Current FastAPI health training SHA matches source. Scheduler and worker restarted under managed local runtime after checking process identities.
- Remaining: integrate unknown-source mapping with importer and reviewer UI, verify real approved-data retraining only when owner approvals/adequate data exist, complete operational/safety/security and full UAT gates in readiness matrix. No model promotion performed.

## Batch 9 — reviewed CSV alias integration

- Unknown CSV headers stop import before any observation is created. The uploader explicitly supplies monthly scope, tonne unit and product stage; the source is fingerprinted, unknown mapping enters review, and an independent process engineer approves its version. Re-upload with the same context resolves the approved mapping and records the exact mapping version in raw lineage. Arabic review UI supports contexts, proposals and decisions. Unknown semantics are not guessed.
- Excel workbooks retain the fixed verified adapter; arbitrary XLSX mapping and refinery-owner mapping signoff remain pending. No engineering data was approved during this batch.
- MySQL full backend regression `php vendor/bin/phpunit --do-not-cache-result`: 40 tests /319 assertions passed including new end-to-end synthetic mapping workflow. `npm run check`: 12 tests, lint, TypeScript and Vite build passed.
- Next: arbitrary XLSX field mapping and robust import quality reporting; then remaining dashboard, retraining comparison, UAT and owner decisions per readiness matrix. Keep pending where engineering semantics are unsettled.

## Batch 10 — reviewed flat XLSX source mapping

- Added explicit sheet selection for flat monthly XLSX files with `month` header, bounded workbook expansion and size, unknown-column rejection/review, separate mapping versions and worksheet/cell lineage. The legacy historical adapter remains selected when no flat sheet is specified.
- MySQL synthetic CSV/XLSX mapping tests: 2 tests /18 assertions passed, including independent reviewer and replay idempotency. Python service/parser:17 tests passed; historical source parsing remains336 facts for each 2021–2023 year. `npm run check`:12 frontend tests, typecheck/lint/build passed.
- This is monthly flat-sheet mapping, not arbitrary workbook interpretation, daily/shift dataset profiling, or engineering mapping signoff. Next: complete missing source workflows and daily ingestion, then approved-data evaluation and UAT/security gates.

## Batch 11 — scoped trip alerts

- A reported unplanned trip creates one informational alert in the same transaction as its event/outbox. Replaying the event does not duplicate it. Authorized scoped users can list and acknowledge with a reason and audit entry; other scopes cannot access it. No risk severity or target-risk threshold is invented.
- Added Arabic alert view and OpenAPI descriptions. Local migration 180000 applied and runtime grants refreshed. Full MySQL backend suite: 42 tests /338 assertions; frontend lint/typecheck/build and 13 tests passed; blueprint/OpenAPI verification passed.
- RF-077 remains partial: escalation, notification delivery and target-risk thresholds require approved definitions and further workflow. Next address source-controlled thresholds and other independent tasks in the readiness matrix; no external messages were sent.

## Batch 12 — chemical target and monthly variance

- Added separate monthly planning target drafts, independent approval, scoped permissions, audit, unique approved scopes and immutable revision history. Approved targets and approved full-month actuals are compared by exact facility/unit/month/chemical/measurement unit; missing or multiple actuals yield no variance. No engineering threshold or norm is inferred. Arabic UI supports entry, approval and review. Migration 190000 applied to local MySQL and runtime privileges refreshed.
- MySQL regression before the final additional mismatched-unit assertion: 43 tests /353 assertions passed; focused regression including that assertion: 1 test /19 assertions passed. Frontend `npm run check`: 14 tests, lint, type checking and production build passed. `python scripts/verify_blueprint.py` passed.
- RF-026/RF-048 remain partial: owner-approved norms and versioned alert thresholds, UAT, and approved daily/shift aggregation rules are not supplied. No real data or model was approved.
- Next: continue independent readiness gaps including versioned reviewed thresholds, reports and remaining source ingestion workflows; preserve all blocked engineering choices.

## Batch 13 — scoped catalog access

- Fixed catalog read authorization for facility- and unit-scoped roles. Facilities and operational units are filtered to assigned scopes; shared product, crude and measurement-unit definitions remain readable with catalog permission. Catalog mutations remain globally restricted. Added negative and facility/unit/global scope coverage.
- Full MySQL regression `php vendor/bin/phpunit --do-not-cache-result`: 44 tests /373 assertions passed. No migration or data changes. RF-003/RF-006 remain partial pending broader security testing, catalog lifecycle and owner acceptance.
- Next: continue independent V1 gaps, particularly approved threshold configuration and reports; do not invent engineering limits or approve refinery data.

## Batch 14 — chemical comparison provenance and readiness corrections

- Chemical variance now returns source references and record IDs for unique approved inputs, plus target revision; the Arabic comparison shows both source references. Ambiguous actual groups deliberately expose no selected actual source. Updated stale RF-019/082/085/086/100 readiness entries based on already verified code and drills; none is claimed complete.
- Focused MySQL chemical test: 1 test /22 assertions passed. Frontend `npm run check`: 14 tests, lint, typecheck and build passed. No migration, source change or approval of refinery data.
- Next: implement other independent V1 gaps and maintain owner-dependent engineering thresholds and UAT as pending.

## Batch 15 — daily coverage ambiguity

- Daily coverage now counts a day with multiple approved full-day records as ambiguous, excludes it from observed coverage and zero counts, and reports its date for review. It does not add records together or treat ambiguity as missing. Arabic UI presents ambiguous counts and dates.
- Focused MySQL regression: 1 test /15 assertions passed, including null, zero, shifts and duplicate approved daily records. `npm run check`: 14 frontend tests, lint, typecheck and build passed. No source data changed.
- Next: continue daily/shift data profiling, source ingestion and independent V1 gaps; preserve engineering definition blockers and do not infer records from monthly data.
