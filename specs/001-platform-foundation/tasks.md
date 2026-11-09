# TASKS — 001. Platform, Security and Governance

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-001 — Repo structure, ADR and version constraints** (high, 3 points; depends `P0`)  
  **Implement:** Create backend/frontend/ai/infra layout, lock runtime compatibility, review hosting prerequisites.  
  **Accept:** Repository builds, minimal CI and documented ADR-001.
- [ ] **RF-002 — Identity and authentication** (critical, 5 points; depends `RF-001`)  
  **Implement:** Implement login/session or token as approved by IT; password policy, expiry, CSRF where applicable.  
  **Accept:** 401 unauthenticated; no secret leakage, secure auth tests.
- [ ] **RF-003 — Scoped RBAC and policies** (critical, 8 points; depends `RF-002`)  
  **Implement:** Create role permission matrix, facility/unit scope, server-side policies.  
  **Accept:** 403 forbidden unit and role; normal authorized requests work.
- [ ] **RF-004 — Audit log and source revision services** (critical, 5 points; depends `RF-003`)  
  **Implement:** Append actor action before/after reference and approval reason to audit store.  
  **Accept:** Mutations create audit; history cannot be edited via API.
- [ ] **RF-005 — Approval workflow state machine** (critical, 8 points; depends `RF-004`)  
  **Implement:** Draft→Submitted→Approved/Rejected, corrections via revisions, reviewer conditions.  
  **Accept:** No unapproved fact in analytics; concurrent approval protected.
- [ ] **RF-006 — Facilities, units and reference catalogs** (high, 5 points; depends `RF-001`)  
  **Implement:** Create schemas/endpoints for facility/unit/product/crude/unit dimension with soft-disabling.  
  **Accept:** Unique codes and scoped listing; no orphan references.
- [ ] **RF-007 — Outbox and background job framework** (high, 8 points; depends `RF-004`)  
  **Implement:** Idempotent async job service + retry and dead letter with trace IDs.  
  **Accept:** Duplicate command never double executes; transient failure retry works.
- [ ] **RF-008 — Health endpoints and structured logs** (high, 3 points; depends `RF-007`)  
  **Implement:** Readiness checks for API/DB/jobs/AI connectivity; request_id and sanitized logs.  
  **Accept:** Health distinguishes component failure and no credentials in logs.
- [ ] **RF-009 — Foundation automated test suite** (critical, 5 points; depends `RF-002;RF-003;RF-005`)  
  **Implement:** Unit feature RBAC, state transitions, auth, rollback and isolation.  
  **Accept:** CI test job green; failures documented if environment blocked.
- [ ] **RF-010 — Base CI/CD and deployment baseline** (high, 5 points; depends `RF-009`)  
  **Implement:** Create migrations build/test, staging config, rollback and secret injection plan.  
  **Accept:** Staging smoke test, migration rollback documented.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________
