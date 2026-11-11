# TASKS.md — IDPS prioritized start checklist v1.2

> مصدر التذاكر الكامل: `planning/BACKLOG.csv` (119 ticket = 105 قديمة + 14 إضافية). المهام الجديدة مرتبطة باستلام بيانات 2021–2023 وتجارب AI الواقعية. هذه **خطة** وليست شهادة تنفيذ.

## Track A — Foundation

- [x] RF-001 — Repository & runtime conventions (Laravel / Vue / Python / MySQL 8.4). Local builds, MySQL precision/timezone test, dependency audit and minimal CI configuration verified; remote CI not run. Evidence: `docs/PROGRESS.md`.
- [ ] RF-002 — Authentication + policy.
- [ ] RF-003 — Roles and unit-scoped RBAC.
- [ ] RF-004 — Approval & immutable audit trail.
- [ ] RF-005..RF-010 — تكملة أساس المستودع والاختبارات وCI.

## Track B — Real historical records (الأولوية مع الأساس)

- [ ] RF-106 — تحقق من original source manifest وSHA256.
- [ ] RF-107 — Monthly 2021–2023 Excel field mapping و36-row data contract.
- [ ] RF-108 — Reconcile flagged monthly sums و2023 semi-actual conflict؛ لا overwrite.
- [ ] RF-109 — MySQL migration/import/approval for monthly observations, API, rollback & idempotency tests.

## Track C — Trainable AI from V1

- [ ] RF-110 — Unite research starter as non-production component and package dependencies.
- [ ] RF-111 — Dataset Upload/Profile/Mapping UI/API, validation errors.
- [ ] RF-112 — Approved vs research dataset snapshots, as-of tracking.
- [ ] RF-113 — Async training jobs + baselines/Ridge/RF/XGB reproducible.
- [ ] RF-114 — Chronological train/validate/test & leakage tests monthly.
- [ ] RF-115 — Registry + candidate vs champion + explicit ML_NOT_READY.
- [ ] RF-116 — Monthly forecast API and compare Actual/Target, with actuals only at correct timestamp.
- [ ] RF-117 — Staging of newly entered months, revisions, gap handling (2024–25).
- [ ] RF-118 — Governed monthly retrain and audit/promotion/rollback.
- [ ] RF-119 — Event-aware reforecast adapters; unknown ETA scenarios and replay tests.

## Track D — Remaining full product

- [ ] 002 Data Import + 003 Operations/Events + 004 KPI + 005 Planning/Quality.
- [ ] 007 Reforecast + 008 MLOps + 009 UI, reports, UAT and deployment readiness.

### Done means

API+DB migration+UI+tests+docs updated and exact command logs; no future actual features, no fabricated days, no unsafe DCS write access. `research_report.json` remains research until signed acceptance.

## Continuous execution checkpoint 2026-11-10

- [x] Integrated software verification checkpoint: backend27 tests/187 assertions, frontend6 tests/build, service+parser14 tests. Evidence in docs/PROGRESS.md.
- [ ] Full V1 acceptance remains open: pending reference/calendar workflows, restore drill, independent review and departmental UAT. Passing implementation tests does not approve refinery data or models.

## Integration readiness — 2026-11-10

- [ ] Complete all 119-ticket assessments and acceptance evidence in `docs/V1_READINESS_MATRIX.md`; review is conservative and not UAT.
- [ ] Verify retrospective research Laravel-to-Python integration on MySQL, then independently review calendar/quality APIs and backup/restore workflow.

- [x] Retrospective research assumptions, reference APIs, local runtime and isolated MySQL restore software checks. See docs/PROGRESS.md Batch 3.
- [ ] Complete file/artifact backup, reference user interfaces, full E2E, domain approvals and UAT; see docs/V1_READINESS_MATRIX.md.
- [x] Local retrospective DIESEL research run through Laravel/Python completed with explicit availability assumption and deploy blocked; see docs/PROGRESS.md.
- [x] Protected file/artifact backup and isolated SHA256 restore check (19 files); DB/file consistency drill remains open. See docs/PROGRESS.md Batch 4.
- [x] Arabic reference interfaces for shifts/calendar/unit status/quality, with 8 frontend tests and build; owner UAT remains pending.
- [x] Synthetic MySQL end-to-end workflow: 1 test /37 assertions; AI boundary mocked not_ready. Real approved-data UAT remains pending.
- [x] Isolated DB/files restore consistency checks: 12 real source hashes and one synthetic model artifact, plus missing-file negative tests; alternate-environment path mapping remains pending.
- [x] Daily data coverage API/UI, guarded source mapping API, model evaluation and governed scheduled candidate checks; technical tests documented in Batch 7.
- [ ] Integrate mapping dictionary with importer and review UI; expose model monitoring policies and evaluations in UI; complete approved-data scientific validation and UAT.
- [x] Monitoring/retraining policy view and forecast evaluation action; 11 frontend tests/build and 39 backend tests passed. Engineering thresholds and owner UAT remain open.
- [x] Reviewed CSV alias mapping integrated with import and Arabic review UI; MySQL synthetic mapping workflow passes. Arbitrary XLSX mapping and engineering source approval remain pending.
- [x] Explicit flat monthly XLSX sheet mapping and cell lineage with independent review and idempotent replay; arbitrary workbook/daily/shift mappings remain open.
- [x] Informational trip alerts with scoped acknowledgment and audit, 42 backend /13 frontend tests. Escalation and target risk policies remain pending.
- [x] Chemical target drafts, independent approval and revisions, plus exact-scope monthly actual variance and Arabic UI. RF-026/RF-048 remain partial pending approved thresholds, normative definitions and owner UAT; see `docs/PROGRESS.md` Batch 12.
- [x] Scope catalog reads to authorized facilities and units, including unit-scoped users; full MySQL regression 44 tests /373 assertions. RF-003/RF-006 remain partial pending broader security and reference lifecycle acceptance.
- [x] Show source references and target revision for approved chemical comparisons; retain explicit ambiguity for multiple actuals. Updated stale readiness entries to match existing scheduler, evaluation, coverage and restore evidence.
- [x] Daily coverage now separates multiple approved full-day records as ambiguous instead of summing or treating them as observed; targeted MySQL and UI tests passed. Full daily profiling remains partial.

- [x] Dashboard register totals and distinct shared list failure states; production/planning CRUD acceptance remains open.

- [x] Author draft edits and rejected-record corrections as append-only revisions; scoped operational list search. Full CRUD acceptance remains open.

- [x] Scoped operational version and approval history, independent planning replacement lifecycle test and scoped dashboard summaries/trends; departmental acceptance remains pending.
