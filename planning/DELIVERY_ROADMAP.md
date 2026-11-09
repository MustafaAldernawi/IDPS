# Delivery Roadmap — from approved design to pilot

## Planning assumptions

Plan is **dependency driven**, not a commitment to a fixed completion date. Team count, source availability, hosting and OT approvals are unknown. Sprints shown as order rather than promises. Multiple workstreams can overlap after critical interfaces are stable.

| Wave | Epics | Target capabilities | External gate |
|---|---|---|---|
| W0 | 001 + data workshop | repo, permissions, conventions, signed taxonomy | data owners/IT approve assumptions |
| W1 | 002 + 005 | monthly 2021–2023 verified-period provisional import, plans versioning, source lineage | monthly source samples validated |
| W2 | 003 | actual feed/production, event timeline, ETA/restart | operational form sign-off |
| W3 | 004 | KPI actual-target/energy/loss & monthly reports | formulas, energy factors / fallback signed |
| W4 | 006 | **V1 Train Studio + Dataset Upload + baseline/RF/XGBoost pipelines + registry** | fixture tests first; real ML activation requires eligible dataset |
| W5 | 007 | event-aware reforecast and scenarios | event feature mapping and ETA bounds reviewed |
| W6 | 008 | monthly retrain, champion/challenger, drift, rollback | enough newly approved months or genuine days / realistic time-safe backtest |
| W7 | 009 | UI polish, security, UAT, piloting, docs | owners sign acceptance |

## Recommended release slices

- **Release A — Operational data foundation:** secure platform, catalogs, import, approval, targets and simple Actual/Target. Valuable even with monthly-only history.
- **Release B — Engineering/Operations:** live-entered daily records, events, validated KPIs, reporting and alerts.
- **Release C — Trainable AI in V1:** dataset upload, Train/Eval/Registry, monthly baseline and event-aware forecast; ML activation only if actual dataset passes independent validation.
- **Release D — Controlled continuous improvement (V1 infrastructure):** periodic retraining, challenger evaluation, drift and governed model rollout.

## Quality gates for wave promotion

W0: detailed source mapping and FR owners. W1: import idempotency/reconciliation. W2: valid states/events, no duplicate downtime. W3: signed KPI outputs. W4: reproducible no-leakage holdout. W5: immutable forecast and unknown ETA. W6: versioning/rollback. W7: UAT/performance/security/DR.

## First 10 action tickets

1. RF-001 approve repo naming/runtime (provisional). 2. RF-002 define roles & unit scope. 3. RF-003 create audit/approval foundations. 4. RF-011 data mapping workshop. 5. RF-012 2021–2023 workbook mapper. 6. RF-013 scanned PDF reviewed extraction. 7. RF-014 import staging/review. 8. RF-021 product taxonomy/units. 9. RF-022 versioned targets. 10. RF-031 daily production/fuel/energy forms.

## Weekly review agenda

Demonstrate one real vertical slice, list exact tests, risks and source/corrections, review AI readiness count, sign decisions, allocate next tickets. Track unexpected data semantics as change requests, not silent implementation.
