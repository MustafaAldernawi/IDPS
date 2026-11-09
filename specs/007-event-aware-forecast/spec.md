# SPEC — 007. Dynamic Reforecast and Event Impact

**Status:** proposed; approval required  
**Phase:** P7  
**Domain owner:** Backend + ML + Operations  
**Dependency:** 003;006  
**Requirements:** FR-044–045, FR-027

## Goal and precise scope

Reforecast on planned/unplanned events/ETA/restart, immutable previous run comparison, scenario explorer for unknown ETA, event impact alerts with comparable horizon.

## Supported user journey

Event known → queue one idempotent reforecast → point-in-time model inputs → assumption branch for ETA unknown → immutable results → before/after comparison → authorized alert acknowledgment.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-044–045, FR-027) and root `SPEC.md`.

## Exceptions / failure cases

No model, no ETA, stale model, changed target plan, multiple affected products, overlapping event, no forecast to compare, queue down.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Events stored even when AI unavailable; all new predictions timestamped; ETA unknown yields labeled scenario outputs; no causal lost production claim.

### Traceable deliverables

- **RF-070** — Event-driven forecast orchestrator: One run per unique version/trigger; retries safe.
- **RF-071** — Planned shutdown known-as-of features: Historical run not changed by later schedule edit.
- **RF-072** — Trip reforecast trigger: Old run unchanged; new parent_run_id and event_id.
- **RF-073** — ETA-null scenario engine: No fake precise restart probability; scenario choice audited.
- **RF-074** — ETA update and restart rerun: Before/after captures new as_of without overwrites.
- **RF-075** — Event impact comparison rules: Delta is estimate difference, not observed lost ton.
- **RF-076** — Event impact detail UI: Uncertain/missing state clearly signaled.
- **RF-077** — Alerts & acknowledgment: Alerts scoped/acknowledgment versioned.
- **RF-078** — Failure/fallback and latency tests: No invented output and no event loss.
- **RF-079** — Operations incident demo UAT: Owner approves text and semantics.

