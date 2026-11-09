# 06 — AI/ML Forecasting Specification

## 1. Realistic objective

Support **trainable monthly forecasting in V1 using real 2021–2023 monthly observations, and trainable daily/shift forecasting whenever genuine observations become available**. Where clean day-level labels exist, forecast future daily mass output for available products: `LPG`, `NAPHTHA`, `GASOLINE`, `KEROSENE`, `DIESEL`, `HEAVY_FUEL_OIL`, and `ASPHALT` **for a daily model only when corresponding clean daily labels exist**. For each product, predict daily `t` at a fixed `as_of` cut-off and aggregate to remaining month. The actual product label must be declared (refinery stream vs saleable blended product).

**When the only approved history is monthly**, offer explicitly marked engineering/seasonal baselines and an upload/train interface; attempt ML experiments only if chronological folds and holdout are viable, and **never claim reliable ML accuracy from 36 monthly samples**. No claim of predicting machine breakdowns. Planned shutdowns known by `as_of` may be included; future unplanned shutdown actuals may NOT be included.

## 2. Prediction contracts

- `prediction_horizon`: **next calendar month (M+1)** is the first research/training scope supported by 2021–2023 monthly data; daily tomorrow (`D+1`) and 7–31 day forecasts are conditional on genuine future daily records and their own validation.
- `forecast_as_of`: instant (UTC) of all knowledge allowed at inference.
- `forecast_business_date`: facility local date predicted.
- `product_id`, `unit_id` (if per unit), `forecast_quantity_t`, `model_version_id`, `scenario_id`, `feature_schema_version`, `target_plan_version`.
- Return `data_quality_status` and `uncertainty_status` or calibrated interval if validated.
- Month-end estimate = approved actuals so far + **future predicted values for unobserved dates only**. Partial-day policies require explicit cut-off and avoid double counting.

## 3. Data eligibility and split

Data preparation:
1. Import raw records with declared `monthly` or `daily` granularity; monthly 2021–2023 is currently available, daily is a separate future scope; ensure product scope, units, approval, date, source and provenance.
2. Build facility/unit calendar; inactive days remain explicit `operating_status`, not automatically imputed.
3. Align feature availability `available_at <= as_of`.
4. Create shifted monthly `lag_1`, `lag_3`, `lag_12` and rolling windows for current monthly scope; use lag_1/7/14/28 **days** only for genuine daily scope. All windows close **before target**.
5. Add known calendar features (`month` for monthly; `weekday` for daily only), planned availability if published by cutoff.
6. Add lagged feed, lagged fuel/energy/loss, previous approved statuses, recorded events.
7. Exclude event effects that became known only after the forecast timestamp.
8. Store dataset snapshot SHA256 + code revision.

**Time-series split:** earliest history as train; contiguous later validation; last untouched time block as test. Rolling-origin folds are required for tuning. Example (only if 2023–2025 daily data available): Train 2023–Sep 2024, validate Oct–Dec 2024; 2025 test. Choose exact windows after missingness audit and protect test from tuning decisions.

## 4. Models and comparison

| Model | Role | Strength | Concern |
|---|---|---|---|
| Last-value / Seasonal Naive | mandatory baseline | simple, honest | won't use operations features |
| Historical seasonal mean / regularized linear | optional baseline | interpretable | limited nonlinear relationships |
| RandomForestRegressor | comparator | robust small tabular datasets | weaker extrapolation and temporal shifts |
| XGBoostRegressor | **initial candidate** | nonlinear tabular + engineered lags | overfit and drift risk |
| LSTM | **optional research comparator** | sequence modeling | more data and validation needed; not default |

Train a separate model for each product and time granularity initially, keeping exact comparable splits/feature availability. If some product has insufficient data, publish `not_ready` for that product and do not fabricate production values.

## 5. Experimental design

For each product/horizon:

- Baseline B0 seasonal naive on identical test timestamps.
- Candidate C1 Random Forest with bounded grid/seed.
- Candidate C2 XGBoost with early stopping by rolling validation, modest hyperparameter ranges (depth, estimators, learning rate, subsample, regularization).
- Optional C3 LSTM only if enough chronological dense observations.
- Track MAE in tonne/day, RMSE, WAPE with denominator, negative forecasts, shutdown/day subset, seasonality subsets, interval calibration (if supported), run duration, model size, drift sensitivity.
- Never use test block for tuning. Final result is documented test score plus baseline delta.
- Define minimum practical effect threshold **after** baseline is known, with process-owner approval; accuracy is not a pre-promised number.
- For known zero periods use both whole-series WAPE and separate MAE by operating-state regime; WAPE denominator=0 yields N/A.

## 6. Explainability

For tree model, compute SHAP per prediction or aggregated rolling period. Explainable drivers include lagged output, planned downtime, known prior feed, unit availability. Show `model association`, not causal guarantee. Do not attribute 34%/28% shutdown impact without validated decomposition.

## 7. Future known vs unknown handling

**Known at prediction time**: plan version approved by `as_of`, weekday/month, current unit status, confirmed ETA published by `as_of`, historical actuals approved before cutoff.

**Unknown**: tomorrow's actual diesel, today's unapproved results at cutoff, tomorrow's unplanned Trip hours, correction not yet entered, future fuel consumption.

For month-end projection, unknown future fuel/feeder variables require either (A) a separately forecast trajectory, (B) plan estimates flagged as assumption, or (C) a reduced safe feature set using lags. Do not pass secretly realized future actuals.

## 8. Event-aware inference

On `TRIP/PLANNED_START/ETA_UPDATED/RESTARTED`, create an immutable new forecast run: `parent_run_id`, `event_id`, `as_of`, status, approved plan, assumption set. For unknown ETA, branch into clear bounded scenarios; report a range rather than fabricated precision. `Event Impact = NewForecast − LastComparableForecast`, matching identical target scope/horizon and marked `estimated`, not observed causal loss.

## 9. Result schema example (synthetic, not actual forecast)

```json
{
  "run_id": "example-only",
  "model_version": "demo-v1",
  "product_code": "DIESEL",
  "as_of": "2026-02-10T10:00:00Z",
  "horizon": "month_end",
  "scenario": "planned_only",
  "forecast_t": 110000,
  "target_t": 132000,
  "achievement_pct": 83.3333,
  "risk": "high",
  "quality": "illustrative_not_trained",
  "assumptions": ["no further unexpected trip"]
}
```

## 10. Gate to live model

**Fail closed for production ML deployment:** insufficient rows for the selected dataset granularity or unknown labeling → `ML_NOT_READY`, while training UI stays active and an explicitly sourced statistical/engineering baseline may be shown separately. Never return pseudo-ML results. Candidate must have reproducible backtest vs baseline, signed feature schema, safe latency, and process review on shutdown regimes. Promotion/rollback in `docs/13_MLOPS_RETRAINING.md`.

## 11. V1 training UI and modes (approved)

- `dataset_upload -> profile -> review -> approve -> immutable snapshot` for CSV/XLSX. Raw PDF scanning may be manual or staged with human validation.
- Dataset requires `granularity=monthly|daily|shift`, product/stage, period range, source lineage, timestamp semantics and unit harmonization.
- Separate trained scopes: `(product_id, granularity, prediction_horizon)`. Prevent replacing a monthly baseline with a daily model or vice versa.
- Training Studio submits asynchronous candidate jobs; shows preprocessing options, train/validation/test timeline, detailed errors and baselines. No HTTP blocking and no automatic deployment.
- Small dataset: validated baseline/engineering estimate and report that no trustworthy ML model is available; optional research candidate trained but cannot promote without independent holdout.
- Model artifacts outside MySQL; registry, run status, dataset hashes and serving metadata inside MySQL.
- Source data entered daily must accumulate in DB with `approved_at/available_at`; periodic retraining when enough approved examples accrue.


## 12. Verified current monthly experiment and migration notes (2026-11-09)

See `docs/24_AI_TRAINING_REAL_DATA_SPEC.md` and `services/ai/research_starter/results/research_report.json` (36 monthly rows, real research experiment, `can_deploy=false`). Existing reference to daily horizon is *conditional*, not the first supported trained scope. Never claim daily training has been performed with current historical material.
