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
