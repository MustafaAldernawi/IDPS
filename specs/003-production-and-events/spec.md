# SPEC — 003. Production Records, Unit States, Trips and Restart

**Status:** proposed; approval required  
**Phase:** P3  
**Domain owner:** Backend + Production  
**Dependency:** 001;002;005  
**Requirements:** FR-013–016, FR-019–027

## Goal and precise scope

Actual daily/shift capture, unit status and unexpected trip lifecycle, ETA revisions, downtime union, event audit and async recalculation event publication.

## Supported user journey

Operator records feed/output → supervisor approves → operator registers Trip → event stored/queue pending → ETA revised/restart confirmed → downtime reconciled.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-013–016, FR-019–027) and root `SPEC.md`.

## Exceptions / failure cases

Duplicate Trip idempotency key, overlapping event windows, unknown ETA, timezone boundary, stale updates, unapproved retrospective changes.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Approved facts are versioned, event timeline immutable in history, calculations do not double count overlap, incidents saved even when AI is unavailable.

### Traceable deliverables

- **RF-031** — Daily production record endpoint/form: Only approved values in analytical read; draft/approve lifecycle.
- **RF-032** — Crude feed records: Valid feed units and no unknown crude labels.
- **RF-033** — Unit status intervals: No invalid negative windows; timezone tested.
- **RF-034** — Event intake & idempotency: Duplicate event returns same ID; persisted before worker.
- **RF-035** — ETA revision workflow: Unknown ETA valid, changes create event revision.
- **RF-036** — Restart/close workflow: Prior forecasts untouched; actual downtime correct.
- **RF-037** — Downtime union calculator: Overlapping intervals counted once, unit/day clipped.
- **RF-038** — Operational event UI: Manager sees verified vs pending and event history.
- **RF-039** — Energy/loss/chem actual capture: No premature SEC GJ/t if conversion factors missing.
- **RF-040** — Realtime trigger semantics: Trip saved if AI down, job retries safely.
- **RF-041** — Event state & overlap tests: Acceptance cases T-EVT-01..08 all pass.
- **RF-042** — Production/maintenance UAT: Owner signs event fields and state transitions.

