# PLAN — 001. Platform, Security and Governance

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **P0 domain workshop** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-001: Repo structure, ADR and version constraints.** Create backend/frontend/ai/infra layout, lock runtime compatibility, review hosting prerequisites. Confirm acceptance: Repository builds, minimal CI and documented ADR-001.
2. **RF-002: Identity and authentication.** Implement login/session or token as approved by IT; password policy, expiry, CSRF where applicable. Confirm acceptance: 401 unauthenticated; no secret leakage, secure auth tests.
3. **RF-003: Scoped RBAC and policies.** Create role permission matrix, facility/unit scope, server-side policies. Confirm acceptance: 403 forbidden unit and role; normal authorized requests work.
4. **RF-004: Audit log and source revision services.** Append actor action before/after reference and approval reason to audit store. Confirm acceptance: Mutations create audit; history cannot be edited via API.
5. **RF-005: Approval workflow state machine.** Draft→Submitted→Approved/Rejected, corrections via revisions, reviewer conditions. Confirm acceptance: No unapproved fact in analytics; concurrent approval protected.
6. **RF-006: Facilities, units and reference catalogs.** Create schemas/endpoints for facility/unit/product/crude/unit dimension with soft-disabling. Confirm acceptance: Unique codes and scoped listing; no orphan references.
7. **RF-007: Outbox and background job framework.** Idempotent async job service + retry and dead letter with trace IDs. Confirm acceptance: Duplicate command never double executes; transient failure retry works.
8. **RF-008: Health endpoints and structured logs.** Readiness checks for API/DB/jobs/AI connectivity; request_id and sanitized logs. Confirm acceptance: Health distinguishes component failure and no credentials in logs.
9. **RF-009: Foundation automated test suite.** Unit feature RBAC, state transitions, auth, rollback and isolation. Confirm acceptance: CI test job green; failures documented if environment blocked.
10. **RF-010: Base CI/CD and deployment baseline.** Create migrations build/test, staging config, rollback and secret injection plan. Confirm acceptance: Staging smoke test, migration rollback documented.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Backend + Security** with realistic approved fixtures.

## Exit gate

API denies unauthorized actions; approved content only is in analytics views; approvals are logged with actor/reason/time; tests run on an empty initialized schema.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
