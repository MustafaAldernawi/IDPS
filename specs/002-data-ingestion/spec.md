# SPEC — 002. Historical Source Ingestion and Data Quality

**Status:** proposed; approval required  
**Phase:** P2  
**Domain owner:** Data Engineer + Backend  
**Dependency:** 001; signed source mapping  
**Requirements:** FR-010–018, FR-052

## Goal and precise scope

Raw Excel/CSV upload preserving original bytes, 2021 monthly source normalization, reviewed transcription path for scanned 2025 PDF, staging/mapping/rejects/approval and source lineage.

## Supported user journey

Upload source → fingerprint → parse to staging → map Arabic labels/units → reject invalid rows → show preview → reviewer approves → canonical versioned records committed.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-010–018, FR-052) and root `SPEC.md`.

## Exceptions / failure cases

Incorrect period granularity, unknown metric label, missing unit, malformed dates, duplicate source, negative qty, PDF image with no validated transcription.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Same source twice yields same result without duplicate facts; 2021 becomes 12 monthly periods not daily; source file hash, sheet/row/cell for each accepted value.

### Traceable deliverables

- **RF-011** — Source mapping workshop: Process owner signs taxonomy and conflict list.
- **RF-012** — 2021 Excel monthly mapper: 12 months per mapped metric, no artificial daily expansion.
- **RF-013** — 2025 scanned report reviewed entry: Two-person review and page provenance; no blind OCR assumption.
- **RF-014** — Upload fingerprint staging: Original immutable and duplicate file idempotent.
- **RF-015** — Mapping dictionary: Unknown alias cannot auto map and approve.
- **RF-016** — Data validation engine: Reject reason includes original cell/source and rule id.
- **RF-017** — Preview and approval flow: No partial approvals and errors remain visible.
- **RF-018** — Reconciliation cases: 1 t component mismatch flagged without altering values.
- **RF-019** — Daily input quality profiler: Readiness report correctly fails monthly-only data.
- **RF-020** — Data import regression suite: All sample fixtures pass/fail expected cases in CI.

