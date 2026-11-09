# SPEC — 009. User Interface, Reporting, UAT and Release

**Status:** proposed; approval required  
**Phase:** P9  
**Domain owner:** Frontend + QA + IT  
**Dependency:** 001–008 with conditional AI readiness  
**Requirements:** FR-027, FR-033–036, FR-051–056

## Goal and precise scope

Arabic RTL enterprise dashboard, role-specific screens, truthful data freshness/status, reports, accessibility, security/hardening and pilot release.

## Supported user journey

Each role logs in → sees only authorized data/forms → approves or studies status → generates auditable report → observes queued/completed/stale forecast with explicit sources.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-027, FR-033–036, FR-051–056) and root `SPEC.md`.

## Exceptions / failure cases

Empty/no data/no approved target, AI not-ready, slow report, incomplete import, permission deny, RTL chart labels, stale source, bad network.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Core UAT passes on source-fixtured scenarios, mobile RTL validated, backup restore tested, production deployment only with signed management authorization.

### Traceable deliverables

- **RF-090** — Frontend shell and RTL design system: Accessible RTL desktop/mobile no decoration overload.
- **RF-091** — Role-specific dashboard: No fake live numbers or unmarked demo chart.
- **RF-092** — Production and planning forms: Server-side authorization enforced beyond UI.
- **RF-093** — Engineering analytics pages: Unit/source/period filters and N/A warnings.
- **RF-094** — Events and impact interface: See original forecast and new versions, unknown ETA clearly.
- **RF-095** — AI forecast and model governance UI: V1 Training Studio is operational; ML readiness and baseline status are distinguished truthfully.
- **RF-096** — Report templates and authorized exports: Export access controlled and audit recorded.
- **RF-097** — Frontend accessibility and responsive QA: Screen-level accessibility checks and screenshot evidence.
- **RF-098** — API and DB performance hardening: Measured timings, no blocking event write.
- **RF-099** — Security penetration and permission tests: Critical findings fixed, policies audited.
- **RF-100** — Backup and restore drills: Documented restore verified and rollback checked.
- **RF-101** — Cross-department UAT: Role owners sign tracked UAT outcomes.
- **RF-102** — Deploy staging and controlled pilot: Management and IT approve pilot release.
- **RF-103** — Operational runbook and team handover: Trained nominated owners and documented contact/escalation.
- **RF-104** — End-to-end traceability and docs: Each FR has relevant test or explicit blocked status.
- **RF-105** — Scope-2 backlog grooming: No future feature silently treated as MVP done.

