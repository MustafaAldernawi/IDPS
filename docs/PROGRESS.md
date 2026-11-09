# IDPS V1 execution ledger

Approved execution: 2026-11-09, continuous dependency-driven batches. No repeated approval gates for routine local development. Production deployment and engineering signoff are separate.

## Resume instructions

Read this file, `planning/DECISIONS.md`, `TASKS.md`, and `git log -8 --oneline`. Resume the first incomplete batch; do not recreate installed applications or rerun archived training into its original output directory. Never run destructive migrations against an existing database. Local secrets and datasets are git-ignored.

## Batch 1 — RF-001 runtime foundation — IN PROGRESS

- Existing Laravel 13.40 / PHP 8.4.12; Vue 3 / TS 6; MySQL 8.4.3 installed in Laragon.
- Baseline: backend 2 tests; frontend build/typecheck + 1 test; Python 7 tests; blueprint/static SQL checks pass.
- Initial MySQL process stopped; Laravel uses SQLite. No IDPS business migrations applied.
- Frontend audit: 16 moderate development dependency findings, zero runtime findings. Fix under compatibility checks.
- Branch: `codex/v1`. Existing workspace retained because all source is initially untracked and local Laragon/dependencies already configured.
- Next: start existing MySQL safely, create isolated IDPS databases/accounts, test connectivity, CI and source exclusion; commit verified foundation.

## Remaining batches

2. RF-106 + identity/RBAC/audit/approvals/reference catalogs/outbox (RF-002..010).
3. RF-107..109 + source mappings, reconciliation, staging/revisions and authorized monthly import.
4. Planning, production, quality/blending, energy/losses/chemicals, events and engineering KPIs.
5. RF-110..116 Dataset/Training Studio, asynchronous Python jobs, evaluation, registry and monthly forecast.
6. RF-117..119 new data, governed retraining, event reforecast; reporting, RTL UX, security, restore and UAT.

## Engineering gates (do not guess)

Unresolved source totals and 2023 semi-actual conflicts; final product stages; fuel/loss scope and energy factors; model promotion thresholds. Software must expose pending/not-ready and preserve original values. These do not prevent implementing review workflows.
