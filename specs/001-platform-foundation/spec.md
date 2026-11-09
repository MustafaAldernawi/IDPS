# SPEC — 001. Platform, Security and Governance

**Status:** proposed; approval required  
**Phase:** P1  
**Domain owner:** Backend + Security  
**Dependency:** P0 domain workshop  
**Requirements:** FR-001–008, FR-052–056

## Goal and precise scope

Laravel API skeleton, role-based access, secure identity, facility/unit scoping, audit, approval state machine, background job/outbox, initial CI and service health.

## Supported user journey

Admin signs in → assigns facility-scoped roles → producer submits a draft → independent reviewer approves → immutable audit entry is available to auditor.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-001–008, FR-052–056) and root `SPEC.md`.

## Exceptions / failure cases

Unauthorized 401, insufficient permissions 403, stale approval 409, duplicate submission idempotent, DB unavailable 503; never return stack trace.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

API denies unauthorized actions; approved content only is in analytics views; approvals are logged with actor/reason/time; tests run on an empty initialized schema.

### Traceable deliverables

- **RF-001** — Repo structure, ADR and version constraints: Repository builds, minimal CI and documented ADR-001.
- **RF-002** — Identity and authentication: 401 unauthenticated; no secret leakage, secure auth tests.
- **RF-003** — Scoped RBAC and policies: 403 forbidden unit and role; normal authorized requests work.
- **RF-004** — Audit log and source revision services: Mutations create audit; history cannot be edited via API.
- **RF-005** — Approval workflow state machine: No unapproved fact in analytics; concurrent approval protected.
- **RF-006** — Facilities, units and reference catalogs: Unique codes and scoped listing; no orphan references.
- **RF-007** — Outbox and background job framework: Duplicate command never double executes; transient failure retry works.
- **RF-008** — Health endpoints and structured logs: Health distinguishes component failure and no credentials in logs.
- **RF-009** — Foundation automated test suite: CI test job green; failures documented if environment blocked.
- **RF-010** — Base CI/CD and deployment baseline: Staging smoke test, migration rollback documented.

