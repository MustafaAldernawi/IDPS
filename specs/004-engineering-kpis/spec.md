# SPEC — 004. Process Engineering Analytics and Reports

**Status:** proposed; approval required  
**Phase:** P4  
**Domain owner:** Backend + Process Engineering  
**Dependency:** 002;003;005  
**Requirements:** FR-028–036

## Goal and precise scope

Actual-target product drilldowns, KPI 1 achievement, KPI 2 energy or fuel ratio, KPI 3 unaccounted loss, chemical deviations and source reconciliation.

## Supported user journey

Approved measurements + target version → calculation service → persist versioned result → engineering annotation → dashboard/report and source links.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-028–036) and root `SPEC.md`.

## Exceptions / failure cases

Target=0, missing source, inventory/MTBE duplication, mismatched unit/scope, electricity factors missing, printed totals not summing.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

December 2025 anchored sample matches source reporting within approved rounding; non-comparable aggregation shows warnings; source differences never silently edited.

### Traceable deliverables

- **RF-043** — KPI definition registry: KPI cannot silently change historical definition.
- **RF-044** — Actual vs Target engine: December diesel 105798/132339 ≈79.94%, with source.
- **RF-045** — SEC/fuel ratio implementation: Missing factor = SEC N/A, no unit mismatch.
- **RF-046** — Unaccounted loss KPI: 12051/432116≈2.79%, source not fabricated.
- **RF-047** — Mass balance/reconciliation review: Dec 2025 fuel + fuel oil mismatch flagged, original values preserved.
- **RF-048** — Chemical variance analytics: No merging kg vs liters, thresholds versioned.
- **RF-049** — Engineering analysis annotations: Audit trail and source links in report.
- **RF-050** — Three KPI dashboard API: N/A/stale/reconciliation badges exposed to UI.
- **RF-051** — Monthly report export: Export user action audited; Arabic numbers readable.
- **RF-052** — Engineering tests: KPI tests T-KPI-01..03 pass.
- **RF-053** — Engineering owner review: Signed approval on published KPI formulas and thresholds.
- **RF-054** — Analytic read performance: Queries validated under representative size.

