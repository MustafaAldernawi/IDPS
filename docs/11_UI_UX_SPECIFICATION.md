# 11 — UI/UX Specification: Industrial Operations Console

## UX principles

- Clean, professional, consistent RTL Arabic; English technical identifiers unambiguous; no unnecessary animation/glass gimmicks.
- Desktop primary 1440px for engineers, tablet/mobile responsive for managers and supervisors; consistent reading hierarchy.
- Never show demonstration forecast numbers unmarked; always distinguish `Verified Actual`, `Approved Target`, `Model Forecast`, `Assumption-based Scenario`, `Stale`, `Not Ready`.
- Display freshness and source timestamps prominently; last imported report != live operational dashboard.
- One shared design system: 8px spacing, 4/8/12 column grid as appropriate, neutral grayscale, accent only for meaningful statuses.

## Screens and their modules

| Screen ID | Page | Key elements | Personas |
|---|---|---|---|
| UI-01 | Sign-in | secure login + 2FA if approved | all |
| UI-02 | Executive Overview | 3 KPIs, monthly risk, source freshness, alerts | manager |
| UI-03 | Production Register | date/shift/unit/product, submit/approve | production |
| UI-04 | Crude Feed | by crude source + unit + day | production |
| UI-05 | Units State | unit cards/timeline running/shutdown/startup | production/manager |
| UI-06 | Events & Shutdown | list/detail/ETA/restart, before-after | production/manager |
| UI-07 | Targets & Calendar | plan version, targets, planned downtime | planning |
| UI-08 | Actual vs Target | table, stacked/trend chart by product, variance | engineering |
| UI-09 | Energy | fuel gas/oil/electricity, SEC if validated | engineering |
| UI-10 | Losses | loss rate, classification, reconciliation flag | engineering |
| UI-11 | Chemicals | item actual/target/deviation, notes | engineering |
| UI-12 | Blending & Quality | component sources/specs, status | process/lab |
| UI-13 | AI Forecast | day/month end, horizon, model metadata, data quality | engineering/manager |
| UI-14 | Event Impact | event linked, prior/current forecast, assumptions | engineering |
| UI-15 | Scenarios | known vs unknown ETA, bounds, provenance, compare | engineering/planning |
| UI-16 | Data Imports | upload, mapping, preview, reject queue, approve | admin/data |
| UI-17 | Dataset Readiness | daily coverage, gaps, feature availability, blockers | ML/process |
| UI-18 | Model Governance | version, backtest vs baseline, drift, promote | ML/manager |
| UI-19 | Reports | report templates, versioned exports, approver | all scoped |
| UI-20 | Users/Roles/Audit | permission editor, audit view | admin/auditor |

## Design example of AI card

Display: `Diesel — Forecast 2026-11 / as-of 2026-11-08 20:00 Libya / Model Diesel v3 / planned ETA confirmed / predicted range if calibrated / Target version 5 / Source coverage 98%`. If fields missing, do not guess.

## UX of unplanned Trip

1. Global banner: unit + reported time + known/unverified status.
2. Before/after forecast with matching scope/horizon, exact run timestamps.
3. Unknown ETA → three **clearly simulated** scenarios and their manually approved assumptions (not 'probability').
4. Reforecast queue shows `Waiting for inputs → Running → Completed/Unreliable/Failed`.
5. Button to view event timeline and reasons.
6. On restart, new updated forecast; old snapshots remain browsable.

## State matrix

- Empty: no approved data; action to import.
- Incomplete: data coverage gauge + missing fields.
- Loading: skeleton or progress not fake numbers.
- Error: concise reason + retry with request_id.
- Permission denied: 403 explanation.
- Model not ready: show exact conditions and non-AI KPI still usable.
- Stale: last run timestamp and why not current.
- Reconciliation pending: anomaly flag without automatically repairing.

## Charts and accessibility

Charts must provide textual/table alternative, labeled units/periods, no misleading truncated axis, not rely on red/green alone. Ensure tab order and focus outlines, contrast AA, readable Arabic numerals/Latin unit labels. Print styles for reports; export page includes data provenance.

## UI route suggestions

`/overview`, `/production`, `/feed`, `/units`, `/events`, `/planning/targets`, `/engineering/actual-vs-target`, `/engineering/energy`, `/engineering/losses`, `/engineering/chemicals`, `/engineering/blending`, `/ai/forecast`, `/ai/events`, `/ai/models`, `/data/imports`, `/data/readiness`, `/reports`, `/admin/users`, `/admin/audit`.
