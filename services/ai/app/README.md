# IDPS private monthly worker

From `services/ai`, run `python -m uvicorn app.main:app --host 127.0.0.1 --port 8001`.
Set `IDPS_AI_SERVICE_KEY` in the process environment before starting. Optional
`IDPS_AI_ARTIFACT_DIR` defaults to the repository's git-ignored `.local/ai-artifacts`.
Never expose this worker directly to browsers. Laravel owns authentication,
record approvals, async jobs, active-model selection and audit.

All `/internal/*` calls require `X-IDPS-Service-Key`. Missing service configuration
fails closed with 503; incorrect credentials return 401. `/health` contains no data.

## Training contract

`POST /internal/train`: `{snapshot_id,dataset_sha256,product_code,seed,mode,rows}`.
`mode` is `approved` or `research_only`. Each row has `period_month` (`YYYY-MM`),
`quantity` (decimal string), `available_at` (UTC ISO timestamp or null), `approved_at` (UTC
ISO timestamp or null), and `status` (`approved` or `provisional`). Supply all row
keys, including null approval timestamps. Rows must be sorted by month and unique.

The dataset hash is SHA256 of UTF-8 JSON rows with recursively sorted object keys,
compact separators, unescaped Unicode, and no NaN/Infinity. Preserve decimal strings
and exact timestamp text when hashing. No upload path or serialized model is accepted.

The response is `status=completed` plus `model_id`, `artifact_sha256`, `dataset_sha256`,
`feature_schema`, selected method, per-method validation/test metrics, split dates,
seed, code/environment metadata and `promotion_status=awaiting_human_review`.
Insufficient eligible history returns `status=not_ready`, without fabricated metrics.

Research uses 2021 training / 2022 validation / 2023 sealed test. Approved retraining
uses the last 12 eligible months as test and preceding 12 as validation, recording
the new protocol window; it does not modify archived research. Every comparator uses
the same validation/test dates. Features need three actual contiguous previous months;
seasonal comparison additionally requires the same month in the previous year.
Late availability/approval excludes historical features. Unknown historical availability
must never be backdated just to make training eligible.

For `research_only`, provisional rows with unknown availability may be used in a
retrospective chronological experiment under an explicit assumption that earlier
monthly values were available. The result says `retrospective_unknown_not_point_in_time`:
it cannot establish historical point-in-time validity or be promoted. Known late
availability is still respected. Operational training requires real availability
and approval timestamps, with no substitution or invented dates.

Models fit against train+validation for holdout assessment; no test refit occurs.
Selection uses validation only. Negative predictions are counted rather than silently
clipped. There are no claimed calibrated confidence intervals or deployment thresholds.

## Model governance and prediction

`POST /internal/evaluate`: `{model_id,artifact_sha256}` returns the frozen report.

`POST /internal/promote` and `/internal/rollback`:
`{model_id,artifact_sha256,approval_reference,approved_by}`. Laravel must first verify
the independent human decision and persist it in its own registry/audit transaction.
These endpoints cannot promote research-only models. The service stores only a
serving authorization reference; the active model pointer and rollback history remain
authoritative in MySQL. No training result auto-activates a model.

`POST /internal/predict`: `{model_id,artifact_sha256,dataset_sha256,product_code,
feature_schema,period_month,as_of,rows}`. `dataset_sha256` identifies the training
snapshot; current input history is separately hashed in the response. Rejects
unapproved models, checksum/scope mismatches, target/future actual rows, incomplete
contiguous lags and facts unknown at `as_of`. The response includes engine type,
prediction, tonnes, period/horizon, source status, model/snapshot/input hashes and
the explicit `uncertainty_status=not_calibrated`.

Artifacts contain only JSON data (Ridge parameters, forest tree arrays, native
XGBoost JSON), not Python pickle/joblib. Atomic immutable publication makes retries
safe. Files cannot be supplied or selected by arbitrary paths.

## Verification

From repository root:
`python -B -m pytest -q -p no:cacheprovider services/ai/tests services/ai/research_starter/tests`.
Service tests use synthetic fixtures only. Passing them establishes software behavior,
not refinery forecast accuracy or engineering acceptance. Current worker scope is
monthly; it does not manufacture daily/shift records from monthly aggregates.
