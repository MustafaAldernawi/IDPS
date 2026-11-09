# 24 — AI research starter → V1 Training Studio integration spec

## What is implemented (research only)

`services/ai/research_starter/` contains Python `idps_ai/pipeline.py`, CLI, minimal tests, a 36-month provisional dataset and archived artifacts; `results/research_report.json` explicitly sets `can_deploy=false`. XGBoost/RF/Ridge candidates were tried historically and compared with last-month/3-month/seasonal baselines on 2022 validation, 2023 test. These scores are *research-only on limited data*, not a production certification.

## V1 machine-learning use cases

UC-AI-01 Import CSV/XLSX dataset; UC-AI-02 map fields/time/unit/product; UC-AI-03 validation and QA review; UC-AI-04 choose granularity monthly/day/shift and output scope; UC-AI-05 create SHA256 immutable snapshot; UC-AI-06 start async experiment; UC-AI-07 view chronological folds and feature schema; UC-AI-08 metrics and baseline comparison per product; UC-AI-09 register version with artifact checksum; UC-AI-10 review/promotion/rollback; UC-AI-11 forecast as-of with model/engine tagged; UC-AI-12 stage new approved actual and retrain after verification; UC-AI-13 manage missing months and versioned source revisions.

## Point-in-time time-series contract

At `as_of = end(2023-04)` predicting May production, use month/calendar and *April or earlier* approved actuals; do not include actual May crude/production/fuel/Trip. If May planned crude is published/approved before April cutoff, it may be included as a clearly labeled plan assumption. For monthly research first evaluate `lag_1,lag_3,lag_12`, rolling means shifted by at least one period and calendar features; fit normalizers/imputers on training folds only; avoid useless high-dimensional assay features with 36 records.

## Experiment protocol (do not change holdout in place)

- Monthly 2021 → initial training window; 2022 for model selection (chronological), 2023 sealed test (12 months). If a model cannot supply valid lag features under this split, report reduced eligible sample count; don't fabricate imputed months.
- Candidate families: Last-known, 3-month mean, seasonal naive 12, Ridge, Random Forest, XGBoost. Compare on identical eligible dates; time-series split/rolling origin if enough samples.
- Metrics MAE (tonne/month), RMSE, WAPE (0 denominator → N/A), negative prediction flags, anomaly/shift/zero-product subsets where sufficiently observed.
- Interpret the selected method as **research best on validation**; do not re-select on 2023 test because that leaks test decisions. Store test results and fail deployment if unstable/unfit.
- Do not train daily models on 36 monthly values; require true daily records and a fresh feature contract/test.

## Approved workflow with new data

`month M entered → staging → reviewer approval → late-arriving corrections preserved → snapshot vN → run challenger in monthly scope → compare with champion & baseline on a held-out future-safe window → engineer accepts/rejects → registry activates or keeps old`.

Unknown missing 2024–2025 means **never interpolate/impute full gap as ground truth**. For lagged monthly models, require contiguous history for specific features, or explicitly reduce feature set, showing eligible sample count. Retain each source revision and `effective_at/available_at`.

## Serving API design

Laravel `/api/v1/ai/datasets` (+ profile/approve), `/api/v1/ai/training-runs`, `/api/v1/ai/models`, `/api/v1/ai/forecasts`; internal FastAPI `POST /internal/train`, `/internal/evaluate`, `/internal/predict`, each authenticated by service credential/mTLS according to IT. Job IDs durable and idempotent. The archived OpenAPI contract requires reconciliation when coding; don't claim that these endpoints exist today.

Results must carry `product`, `time_granularity`, `prediction_horizon`, `as_of`, `dataset_sha`, `source_approval_status`, `model_version`, `engine_type`, `prediction`, `unit`, `target` (if authorized), `evaluation`, `quality_warning`. `engine_type=research_only` must not become publicly approved live model.

## Shutdowns

- Planned shutdown as-of → scenario input; newly observed unplanned Trip triggers new forecast and preserves prior run.
- Unknown ETA → 1/3/7-day **illustrative scenarios only** unless a validated duration distribution exists; not exact incident prediction.
- With monthly-only labels, event impact is primarily engineering scenario, not an ML causal conclusion. When unit-level daily data arrive, train new scoped models and evaluate.

## Lifecycle and security

- Serving/loading `.joblib` is code execution risk if untrusted: only locally trusted, hash-verified artifacts, never user-uploaded arbitrary serialized models.
- Register provenance, code revision, environment, hyperparameters, metrics, signoff; activation manual and audit-logged.
- No automatic online learning based on input entry; monthly scheduled retraining is proposed cadence, tunable after data volume confirms value.
