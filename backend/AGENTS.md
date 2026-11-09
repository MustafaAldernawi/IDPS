# Backend Agent — Laravel API

- Read root `AGENTS.md`, `docs/03_TECHNICAL_ARCHITECTURE.md`, `docs/04_DATA_MODEL_AND_DICTIONARY.md`, `docs/09_RBAC_WORKFLOWS.md`, `contracts/openapi.yaml` before implementation.
- Use layered structure: HTTP requests/controllers → application actions/services → domain rules → repositories/models → event/outbox/queue.
- Do not put KPI calculations, downtime accounting, or AI prediction calls directly in controllers.
- Use transactions and unique idempotency constraints for upserts/events; audit corrections with previous/new values.
- Data policies: draft → submitted → verified → approved/rejected → revised, not a bare boolean.
- All numerical fields with appropriate decimal scales and unit identifiers. Persist source references on imports.
- Handle stale updates via `lock_version` or equivalent optimistic concurrency for approval/event updates.
- Create Laravel migrations & factories/test fixtures only when implementing the relevant slice. Keep rollback safe and reversible where feasible.
- Return consistent RFC-style problem payloads; never leak stack traces.
- Observability: structured logs with request_id, job_id, forecast_run_id, event_id; no confidential raw values or secrets.
- Tests: Feature/Unit permission denials, quantity precision, duplicate imports, month boundary, timezone, overlapping downtime, correction audit.
