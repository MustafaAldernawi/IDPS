# 10 — Backend API Contract Specification

**Base:** `/api/v1`, JSON UTF-8, authenticated; UTC ISO 8601 timestamps + facility local date. Draft OpenAPI at `contracts/openapi.yaml`. API names are proposed and should be versioned; they are not implemented.

## Core resources

| Endpoint | Method | Purpose | Typical roles |
|---|---|---|---|
| `/auth/me` | GET | identity, current permissions | any auth |
| `/facilities` | GET | facility definitions | scoped users |
| `/units` | GET/POST | units + unit hierarchy | view/admin |
| `/products` | GET/POST | product and stage catalog | view/admin |
| `/plans` | POST/GET | target plan drafts & versions | planning |
| `/plans/{id}/submit` | POST | approval request | planning |
| `/plans/{id}/approve` | POST | approved effective version | manager |
| `/imports` | POST | upload and parse batch | data import |
| `/imports/{id}/preview` | GET | rows, unknown mappings, validation | reviewer |
| `/imports/{id}/approve` | POST | import canonical commit | reviewer |
| `/production-records` | GET/POST | actual product outputs | production |
| `/production-records/{id}/submit` | POST | submit for approval | production |
| `/production-records/{id}/approve` | POST | approve immutable fact | supervisor |
| `/crude-feed-records` | GET/POST | crude input | production |
| `/energy-records` | GET/POST | fuels and power | production/engineering |
| `/loss-records` | GET/POST | loss and flare | production/engineering |
| `/events` | GET/POST | planned/unplanned event intake | production/planning |
| `/events/{id}/eta` | PATCH | planned/expected restart updates | authorized |
| `/events/{id}/restart` | POST | actual restart closure | authorized |
| `/kpis` | GET | 3 KPIs and breakdowns | scoped |
| `/comparisons/actual-target` | GET | product/month/unit comparison | scoped |
| `/forecasts` | POST | queue inference/reforecast | process/planning |
| `/forecasts/{run_id}` | GET | run result metadata and points | scoped |
| `/forecasts/{run_id}/impact` | GET | event impact if comparable | scoped |
| `/models` | GET | registry metadata | ML/process |
| `/models/retrain` | POST | controlled retrain enqueue | ML_OPERATOR |
| `/models/{id}/promote` | POST | controlled promotion | management |
| `/alerts` | GET | alerts with severity | scoped |
| `/alerts/{id}/acknowledge` | POST | acknowledge, reason | scoped |
| `/reports/monthly` | POST | report generation job | scoped |
| `/jobs/{id}` | GET | async job progress | scoped |

## Data contracts (summaries)

### Start unplanned trip

```json
{
  "idempotency_key": "unit-trip-source-unique-id",
  "unit_id": "uuid",
  "kind": "unplanned",
  "type": "trip",
  "started_at": "2026-11-08T14:35:00Z",
  "expected_end_at": null,
  "source": "manual",
  "notes": "operator reason if known"
}
```

Returns `202 {event_id, event_status, reforecast_job_id, forecast_status:'queued'}` or `409` if inconsistent duplicate/stale state. Repeated identical idempotency key returns existing event without duplicate interval.

### Forecast trigger

```json
{
  "as_of": "2026-11-08T18:00:00Z",
  "product_codes": ["DIESEL","KEROSENE"],
  "horizon": "month_end",
  "target_plan_version_id": "uuid",
  "reason": "event_changed",
  "event_id": "uuid",
  "scenario_inputs": {"eta_status":"unknown"}
}
```

Returns job reference, **not** fabricated predictions. Worker creates scenario set when needed.

### KPI response

```json
{
 "code":"PRODUCTION_TARGET_ACHIEVEMENT",
 "scope":{"facility_id":"uuid","product_code":"DIESEL","period":"2025-12"},
 "actual":105798,"target":132339,"unit":"t",
 "value":79.94,"value_unit":"percent","status":"valid",
 "calculation_version":"v1","source_status":"approved"
}
```

The values above are drawn from the Dec 2025 report but the UUID/scope approval shown is a **contract illustration**; do not treat this as an actual stored API record.

## Contracts and versioning

Use pagination/filter semantics, sorting whitelist, request_id, ETag/lock_version for concurrency, validation errors with field paths, idempotency for imports/events, stable unit names and model versions. Exports require audit and authorization. OpenAPI is a seed to extend during actual code work; endpoints may have schemas pending implementation acceptance.

## IDPS V1 additions (approved)

- `/datasets/upload` (POST multipart), `/datasets/{dataset_id}/quality` (GET), `/datasets/{dataset_id}/approve` (POST).
- `/training-runs` (POST async job), `/training-runs/{training_run_id}` (GET status/metrics).
- `model_versions` scoped by product + granularity + horizon; training pipeline available in V1 independently of data coverage.
- Model serving response differentiates `engine_type: ml_model | statistical_baseline | engineering_baseline | scenario_estimate | unavailable`.
- API requires MySQL 8.4 LTS as underlying database; no external AI API may access confidential data without consent.
