# SPEC — 008. Retraining, Monitoring and Model Governance

**Status:** proposed; approval required  
**Phase:** P8  
**Domain owner:** ML Engineer + Operations  
**Dependency:** 006;007  
**Requirements:** FR-048–051

## Goal and precise scope

Monthly retrain, dataset snapshot governance, candidate/champion approval, no blind auto-promotion, error/drift monitoring, model rollback.

## Supported user journey

New approved actuals → eligible snapshot → retrain challenger → compare on same temporal folds → process/management signoff → publish/rollback.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-048–051) and root `SPEC.md`.

## Exceptions / failure cases

Insufficient new rows, labels pending approval, model worse in shutdown subset, artifact hash mismatch, current champion unavailable, drift false alarms.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Version/metrics/snapshot complete; rejected candidate stays archived and champion unchanged; real test evidence before promotion.

### Traceable deliverables

- **RF-080** — Training dataset registry: Every model version references exact approved snapshot.
- **RF-081** — Model registry and signed metadata: No artifact serving if checksum or feature schema incompatible.
- **RF-082** — Monthly retraining scheduler: Job not triggered for just one unapproved row.
- **RF-083** — Champion challenger evaluation: Worse/inconclusive candidate rejected.
- **RF-084** — Manual promotion & rollback: Past forecasts retain original version ref.
- **RF-085** — Prediction actual evaluation: Forecast records immutable; evaluations separate and versioned.
- **RF-086** — Data & model drift monitoring: No claim statistically meaningful on 1–2 records.
- **RF-087** — Model governance dashboard: Only ML operator sees training; manager can approve.
- **RF-088** — ML pipeline failure drills: Safe fallback and signed event trail.
- **RF-089** — MLOps owner signoff: Owner accepts gating and rollback.

