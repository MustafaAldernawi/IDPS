# Decision Register (ADR-style)

## Execution rulings — 2026-11-09

- ADR-021: User approved continuous V1 batches, superseding per-ticket approval stops. Keep engineering/data/model signoffs; routine technical choices recorded here.
- ADR-022: Preserve installed Laravel 13/Vue 3 applications. Work on `codex/v1` in existing workspace; no second checkout of confidential evidence/dependency trees.
- ADR-023: Root AGENTS/SPEC then v1.2 decisions govern older drafts. Process engineering approves production facts; producers and ML operators cannot self-approve. Source `actual/target` and `actual/semi_actual` are independent dimensions.
- ADR-024: Canonical AI routes will use `/api/v1/ai/*`. Review states remain domain-specific (import staging versus operational drafts), never interchangeable with source report status.
- ADR-025: Local confidential-data research is permitted by explicit project authorization; CI uses synthetic fixtures only. No refinery data goes to external services. Package registries receive dependency metadata only.
- ADR-026: MySQL `idps_dev` / `idps_test` isolated databases; preserve existing Laragon databases and SQLite files. No destructive resets outside the dedicated test schema.
- ADR-027: Engineering definitions not ratified remain pending with N/A/quality flags. Building a workflow does not approve source facts or models.

| ID | Choice | Status | Owner / action |
|---|---|---|---|
| ADR-001 | Laravel API + Vue 3 TypeScript + **MySQL 8.4 LTS** + Python FastAPI | **DATABASE APPROVED** | MySQL explicitly selected; finalize Laravel/PHP hosting versions separately |
| ADR-002 | Initial data ingestion: audited Excel/CSV + manual forms, no DCS write | APPROVED IN DESIGN | Validate refinery operational policy |
| ADR-003 | **V1 trainable AI module** with monthly/daily/shift datasets; daily production forecast enabled only once a suitable dataset is validated | **APPROVED IN DESIGN** | Actual model promotion requires independent backtest |
| ADR-004 | Re-train monthly after data approval; not per-row online training | APPROVED IN DESIGN | Confirm resources & cadence |
| ADR-005 | Champion–Challenger with rollback; no automatic untested promotion | APPROVED IN DESIGN | Agree business acceptance thresholds |
| ADR-006 | Event-aware reforecast after planned/unplanned shutdown known; no claim of clairvoyance | APPROVED IN DESIGN | Validate event capture mechanism |
| ADR-007 | Specific energy consumption in GJ/t after approved calorific conversion | REQUIRES DECISION | Process engineering / energy team |
| ADR-008 | Loss Rate definition: unaccounted loss / crude feed, separate from fuel + loss | REQUIRES DECISION | Process engineering / finance reconciliation |
| ADR-009 | Product scope: refinery streams vs blended saleable product, avoid double count | REQUIRES DECISION | Production + process engineering |
| ADR-010 | Role approval hierarchy and record lock/revision policy | REQUIRES DECISION | Operations management |
| ADR-011 | DCS read-only interface, if any, is future-phase regulated integration | PROPOSED / FUTURE | OT Security + operations |
| ADR-012 | Data classification, residency, retention, hosting and backup intervals | REQUIRES DECISION | IT/security |
| ADR-013 | Production-impact attribution is scenario-based; never falsely claim causal measurement | APPROVED IN DESIGN | Review forecast UI copy |
| ADR-014 | Unknown restart: best / central / worst scenario with explicit assumptions | APPROVED IN DESIGN | Process engineering to set bounds |
| ADR-015 | Model comparisons use rolling origin, sealed test period and deployment acceptance gates | APPROVED IN DESIGN | Approve minimum KPI targets after baseline |

| ADR-016 | MySQL 8.4 LTS / InnoDB, CHAR(36) ASCII UUIDs via Laravel, UTC DATETIME(6), generated-scope unique indexes | **APPROVED** | Implement migrations/tests; no PostgreSQL dialect permitted |
| ADR-017 | AI Training Studio, dataset imports, experiment runs, evaluation, version registry and rollout gates in **V1**, with monthly and daily modes | **APPROVED** | Use baseline fallback; do not fabricate daily records |

## Architecture decision format

- Context and problem
- Options considered
- Decision + date + approver
- Rationale and trade-offs
- Affected specs, contract versions, migrations and tests
- Rollback strategy / re-review trigger

| ADR-018 | Use **actual monthly 2021–2023, 36 x 35 provisional** as first research-grade dataset, not daily proxy | APPROVED IN DESIGN | Historical reviewer must sign before operational activation |
| ADR-019 | Consolidate 2021–2023 audit, 2024 annual/2025 cumulative context, refinery crude assays and nonproduction AI research in unified IDPS V1.2 | APPROVED IN DESIGN | Do not turn cumulative into monthly records |
| ADR-020 | Stage/review new months, then governed periodic retraining; preserve gaps and audit revisions | APPROVED IN DESIGN | ML promotion remains gated |
