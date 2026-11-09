# 15 — Assumptions, Risks, Required Decisions

## What is already decided in design discussions

- Four principal responsibilities: production captures operations; process engineering analyses; planning targets/shutdown schedules; operations management coordinates and reviews.
- At least three historical years sought, preferably daily; initial monthly files alone are inadequate for robust daily ML.
- Independent product forecasts, XGBoost candidate with baselines and chronological evaluation.
- A monthly retraining **process** on newly approved data; Champion–Challenger with rollback.
- Event-aware rolling production forecast after known planned/unplanned shutdown and ETA updates.
- Core KPI trio: production achievement, specific energy/approved fuel fallback, unaccounted loss rate.
- Entire system supports decisions, **no industrial control**.

## Decisions required before true production deployment

| Decision ID | Question | Owner | Risk if deferred |
|---|---|---|---|
| D-001 | Exact legal unit names, product hierarchy, input/output stages | Process & production | duplicate product mass / wrong yield |
| D-002 | What counts as operational day / shift, timezone and handover | Production | timestamp misalignment |
| D-003 | Daily historical feed and product data exist for which 3 years? | Data steward | AI data readiness unknown |
| D-004 | Shutdown logs timestamp resolution, ETA validity, classification | Production/maintenance | event-aware features incomplete |
| D-005 | Formal loss and energy KPI equations & LHV/HHV conversions | Engineering | misleading indicators |
| D-006 | Approval ownership and separation of duties | Operations management | audit/safety governance |
| D-007 | Precise monthly planned target source and version policy | Planning | target mismatch |
| D-008 | Final fuel/stream/product aggregation and MTBE addition logic | Engineering | double counting |
| D-009 | Use of electricity import/utility energy data; units & conversion | Energy team | incomplete SEC |
| D-010 | Allowed hosting/data residency/external model services | IT Security | data security risk |
| D-011 | Model release performance acceptance (relative baseline) | Engineering/management | overclaiming AI |
| D-012 | Scenario bounds/ETA assumptions approved | Operations | false incident impact precision |
| D-013 | Report export retention and confidential designation | Management/IT | leakage |
| D-014 | Framework/runtime versions confirmed available | IT/dev team | incompatibility |

## Risks and mitigations

| Risk | Probability (preliminary) | Impact | Mitigation |
|---|---|---|---|
| Only monthly historical data | High until verified | Very high on daily AI | Build platform first; evaluate simpler monthly baseline honestly |
| Source inconsistency and rounding | Observed in Dec 2025 | Medium | reconciliation queue and signed source corrections |
| Target leakage in AI | Medium | Critical academic validity | point-in-time feature checks and frozen test set |
| Rare shutdown events in 3 years | Unknown | High on incident scenarios | explicit uncertainty, conservative scenario bands, data collection |
| Unknown event ETA | Common plausible | High on numeric estimates | best/central/worst assumptions + warning |
| Product blending duplicates | Medium | High | signed scope/taxonomy and reconciliation |
| Unauthorized editing or exports | Medium | High | RBAC, append-only revisions, audit, encryption |
| Hardware/network outages | Medium | Medium | degraded mode, retries, tested restore |
| Model drift due to catalyst/crude changes | Unknown | Medium/high | monitoring and periodic controlled retraining |
| DCS integration scope creep | Medium | Very high safety | read-only future-phase boundary |

## Acceptance gate for new scope

Every new feature: documented operational owner, inputs/outputs, source evidence, safety boundary, API/data effects, timeline, risks, tests, and sign-off. No unapproved 'smart automatic plant control'.
