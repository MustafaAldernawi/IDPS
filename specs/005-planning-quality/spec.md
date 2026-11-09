# SPEC — 005. Planning, Target Versions and Optional Quality

**Status:** proposed; approval required  
**Phase:** P2-P4  
**Domain owner:** Backend + Planning + Engineering  
**Dependency:** 001; 002  
**Requirements:** FR-009, FR-017, FR-020, FR-032

## Goal and precise scope

Monthly targets by version and product/unit, approved outage planning calendar and optionally lab/blend data where data ownership and specifications are agreed.

## Supported user journey

Planner creates plan draft with targets and planned windows → reviewer approves version → forecast uses only plan known by as_of; new version supersedes the plan without editing prior forecasts.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-009, FR-017, FR-020, FR-032) and root `SPEC.md`.

## Exceptions / failure cases

Plan overlaps, target=0, conflicting product stages, unauthorized update, late retroactive changes, unknown MTBE/total blend scope.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Unique approved plan per scope/period, plan version/time recorded and target-linked forecasts remain reproducible.

### Traceable deliverables

- **RF-021** — Approve product/stream catalog: Owner-signed hierarchy; prevent aggregate double count.
- **RF-022** — Versioned target plans: Same period cannot have conflicting active target.
- **RF-023** — Plan approval and supersession: Old forecast references historical plan version.
- **RF-024** — Planned outage schedule: Only pre-known outages enter earlier as_of feature.
- **RF-025** — Production plan dashboard: Planners can filter, compare and see version state.
- **RF-026** — Chemical targets and variance: Unit mismatch blocked; separate target and actual.
- **RF-027** — Quality parameter catalog optional: Spec/version/data source displayed, not inferred.
- **RF-028** — Blending batch model optional: No silently mixing blend output with raw gasoline stream.
- **RF-029** — Planning temporal leakage tests: Historical snapshot unchanged by later revisions.
- **RF-030** — Planning UAT and signoff: Planner/manager approve workflow; decisions updated.

