# 12 — Test Strategy, UAT and Definition of Done

## Test pyramid

1. **Unit:** source parser, unit normalization, KPI math, shortage/overlap handling, timezone dates, schema validation.
2. **Integration:** Laravel endpoints/MySQL 8.4 LTS transactions/outbox/async worker/permissions/inference boundary.
3. **ML tests:** row provenance, time-split, feature availability, baseline comparison, reproducible inference, leakage guards, drift.
4. **End-to-end:** planning → production approval → analysis → Trip → reforecast → restart → new actual → report.
5. **UAT:** realistic operator/engineer workflows with traceable signed fixtures, no actual refinery controls.
6. **Security/performance:** denial cases, data leakage, file parsing, authorization, isolation, worker retries, read-heavy dashboards.

## Core functional tests

| Test ID | Given / When | Expected |
|---|---|---|
| T-AUTH-01 | Unauthenticated GET/POST | 401, no confidential information |
| T-AUTH-02 | Operator attempts model promotion | 403 and audit event |
| T-IMP-01 | Upload same original XLSX twice | same batch/no duplicate facts |
| T-IMP-02 | Raw monthly file mapped as daily | rejected as wrong granularity |
| T-IMP-03 | XLSX contains formula payload or CSV injection | escaped/export sanitization & safe parse |
| T-IMP-04 | Arabic source product alias unknown | staging reject/request mapping, no guess |
| T-REC-01 | Report fuel total differs from component sum | flag discrepancy, preserve original numbers |
| T-REC-02 | Material balance missing tank change | open reconciliation case; no forced zero loss |
| T-APP-01 | Approved actual corrected | new revision, audit intact, old revision preserved |
| T-PLAN-01 | Two active target versions overlap | reject conflicting activation |
| T-KPI-01 | Diesel 105,798/132,339 | ≈79.94%, scopes explicit |
| T-KPI-02 | Target=0 | N/A rather than divide-by-zero |
| T-KPI-03 | Unknown energy factors | SEC N/A; fuel ratio separately |
| T-EVT-01 | Duplicate Trip event idempotency key | one active event/downtime interval |
| T-EVT-02 | ETA unknown | scenario assumptions displayed, no fake point confidence |
| T-EVT-03 | Trip overlap with unit maintenance | downtime union, no 2× hours |
| T-EVT-04 | ETA revised twice | version history + two distinct forecast runs |
| T-EVT-05 | Restart before planned ETA | updated effective calendar and new run |
| T-EVT-06 | Event retrospectively reported | known_at respected in historical backtest |
| T-ML-01 | Tomorrow's unplanned downtime fed as feature for historical prediction | leakage test fails/feature excluded |
| T-ML-02 | Future actual production/energy in input | block by availability policy |
| T-ML-03 | 36 monthly rows as '3 years of daily' | AI_NOT_READY |
| T-ML-04 | Candidate beats baseline in training but not holdout | reject promotion |
| T-ML-05 | Retrain unapproved labels included | exclusion + quality alert |
| T-ML-06 | Training rerun same snapshot/seed | reproducible metrics within tolerance |
| T-ML-07 | Champion API unavailable | no fabricated forecast; dashboards still usable |
| T-ML-08 | Forecast before/after different target version | comparison flagged not equivalent |
| T-UI-01 | Open app RTL in mobile/desktop | alignment, accessible labels, readable charts |
| T-OPS-01 | Database worker restart during reforecast | idempotent retry/no duplicate run |
| T-OPS-02 | Model rollback | previous version restored + events audited |

## Integration / unit specifics

- Use fixed facility timezone Africa/Tripoli and explicit UTC timestamp for API; test leap-day, midnight and month boundaries.
- Maintain fixture variants: normal production, partial unit derating, complete unit shutdown, unknown ETA, recovery, data lag and no target.
- Measure approximate job latency on production-like size; assign SLO from measurement instead of fabricated timing promise.
- Assert all official KPI decimals rounded at **display only**; calculation uses source precision with approved rounding policy.

## ML acceptance protocol

1. Create manifest: raw approved source SHA256, dataset snapshot sha, target definition, horizon, feature schema, code commit, train/validation/test dates.
2. Fit transformations only on training folds, not entire history.
3. Evaluate baseline and candidate on identical folds, then sealed test.
4. Break down errors: monthly, normal operations, active shutdown, startup/return, high-loss periods; if subset small mark not statistically meaningful.
5. Check prediction stability with known inputs and missing features.
6. Review domain plausibility, nonnegativity, product mass constraints, scenario confidence labels.
7. Archive evaluation report and approval/refusal reason.

## UAT walkthrough

- Operator creates an approved daily record.
- Planning enters monthly targets and a planned outage (with versioning).
- Engineering sees actual/target comparison and three KPIs (including `N/A` when energy factors unapproved).
- Operator reports unexpected Trip. UI acknowledges immediately; worker produces immutable reforecast if model ready.
- ETA unknown triggers scenario view with clearly labeled assumptions.
- Restart updates actual downtime and produces new forecast without overwriting previous.
- Month-end actuals are recorded, errors computed, optional monthly retraining proposed.

## Release / DoD rules

A feature is **done** only when domain spec and ACs are approved, code passes security checks, migrations are reversible/tested, unit+integration tests pass, RBAC is enforced, operational logs present, UX states complete, docs and contracts are updated, and UAT owner signs off. AI accuracy is never marked complete on synthetic data alone.
