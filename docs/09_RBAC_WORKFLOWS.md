# 09 — RBAC, Approval Workflows and Audit Policy

## Suggested roles (not identical to employment titles)

`SYSTEM_ADMIN`, `OPERATIONS_MANAGER`, `PRODUCTION_ENGINEER`, `SHIFT_SUPERVISOR`, `FIELD_OPERATOR`, `PROCESS_ENGINEER`, `PLANNING_ENGINEER`, `LAB_ENGINEER`, `MAINTENANCE_ENGINEER`, `HSE_ENGINEER`, `MANAGEMENT_VIEWER`, `AUDITOR`, `ML_OPERATOR`.

## Resource/action matrix (proposal)

| Resource / Action | Production | Shift supervisor | Process eng. | Planning | Operations mgr | ML operator | Viewer |
|---|---|---|---|---|---|---|---|
| Production/Create draft | Yes | Yes | No | No | No | No | No |
| Production/Approve | No | Proposed | No | No | Proposed | No | No |
| Production/View | Own/site scope | Yes | Yes | Yes | Yes | Yes (approved only) | Scoped |
| Targets/Edit draft | No | No | Review | Yes | Review | No | No |
| Targets/Approve | No | No | No | Proposed | Yes | No | No |
| Event/Start Trip | Yes | Yes | No | No | Yes | No | No |
| Event/Update ETA | Proposed | Yes | View | Proposed with approval | Yes | No | No |
| KPI/View | Yes | Yes | Yes | Yes | Yes | Yes | Scoped |
| KPI/Definition Change | No | No | Propose | No | Approve | No | No |
| Forecast/Request | No | Yes | Yes | Yes | Yes | Yes | No |
| Forecast/Read | Scoped | Yes | Yes | Yes | Yes | Yes | Scoped |
| Model/Train | No | No | No | No | No | Yes | No |
| Model/Promote | No | No | Review | No | Approve | Propose | No |
| Data export | Scoped | Scoped | Scoped | Scoped | Yes | Approved data only | No by default |

`Proposed` = pending official authorization definition; no permission is enabled in production without ratification. Always enforce server-side policy, including unit-based scope.

## Approval state machines

- Production, consumption and targets: `draft → submitted → approved`; rejected may lead to corrected draft/revision. Approved values immutable.
- Import: `uploaded → parsed → mapped → validated → awaiting_review → approved/rejected`.
- Incident: `reported → acknowledged → investigating → resolved → closed`; closed correction requires audit and reviewer.
- Model: `trained → evaluated → candidate → approved → active → archived`; never mark active without approved gate.

## Separation of duties (SoD)

If organization approves SoD, author cannot approve their own production entries; model trainer cannot promote their own model without operations/engineering sign-off. Admin can manage accounts but cannot silently change measured data. Define emergency access with explicit expiry/audit.

## Audit record schema

`actor_id, action, resource_type, resource_id, before_hash/value_safe, after_hash/value_safe, effective_at, occurred_at, source_ip, request_id, change_reason, approved_by`. Any correction includes references to source evidence and superseding record.

## Auth and privacy

Use secure sessions/Tokens, HTTPS, CSRF appropriate to auth mode, per-request authorization, audit of exports and model deployments, secure attachment access and source redaction. Password/MFA/SSO policies approved by organization.
