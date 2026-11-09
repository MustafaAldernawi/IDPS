# 17 — Practical AI Training Runbook

## Prerequisites

- Curated, approved records for a **declared granularity (monthly/daily/shift)**. Daily history over multiple years is desirable but **NOT a prerequisite to implement the Training Module in V1**.
- Definitive per-product label (unit and process stage) and forecast horizon (D+1 baseline, month-end derived).
- Versioned calendar/approved shutdown plan and `available_at` provenance.
- Read-only dataset snapshot plus checksum, environment versions, deterministic seed.
- ML inference not responsible for plant control.

## Step-by-step experiment

1. **EDA:** measure date coverage, duplicate days, missing values, outliers, zero-output intervals, downtime share, crude/product correlations and regime changes.
2. **Readiness decision:** If only monthly totals, report `DAILY_MODEL_NOT_READY`; allow monthly engineering/statistical baselines and evaluate whether a monthly research candidate has enough chronological holdout (do not claim production-grade validity from 36 rows).
3. **Define target:** one product/period `approved_production_t` (daily or monthly, consistent within each experiment) with decision on blend/additive inclusion.
4. **Define as-of:** e.g. day `D` close for daily model/predict `D+1`, or month `M` close for monthly model/predict `M+1`; only features available by cutoff are allowed.
5. **Feature transforms:** for daily models `lag_1,lag_7,lag_14,lag_28`, rolling past 7/28; for monthly data use small historical lags with no leakage; never assume 7 monthly rows mean 7 days, known planned availability, current unit status, lagged feed/energy/loss, weekday/month.
6. **Split chronological:** rolling train/validation windows and protected final test. Freeze test timestamps before model tuning.
7. **Baseline:** last daily production and same-weekday lag 7; report MAE/RMSE/WAPE.
8. **RF/XGBoost:** fit on same folds and snapshot; tune small bounded grid; save hyperparams/seed.
9. **Scenarios:** evaluate shutdown subset separately; if few samples show `insufficient_evidence_for_shutdown_regime`.
10. **Nonnegativity and scope:** evaluate outputs; never silently truncate unacceptable model results without declaring postprocessor.
11. **Explainability:** validate SHAP dependencies and show feature driver contributions, no causal wording.
12. **One-step serving simulation:** replay historical as_of dates and query model using only known features; compare actual outcomes after dates.
13. **Select challenger:** use held-out error, robustness, baseline delta and domain review; record reason.
14. **Register artifact:** serialize model and preprocessing with code/features/snapshot hashes, signed metadata, loading smoke test.
15. **MLOps:** add new approved actuals to next monthly snapshot → retrain candidate → approve if better with rollback; not per-row partial fit.

## Example versioned directory under an actual implementation

```text
ai/
  src/
    data/ingest.py
    features/point_in_time.py
    models/baselines.py
    models/xgb.py
    evaluation/backtest.py
    serving/predict.py
  config/
    product_definitions.yaml
    experiment.yaml
  tests/
    test_no_leakage.py
    test_shutdown_features.py
    test_predict_schema.py
```

The files listed are **proposed** for the coding agent; this blueprint does not falsely claim they exist.

## Model acceptance report template

| Item | Required |
|---|---|
| Product/horizon/scope | explicit |
| Dataset period, rows and missingness | verified |
| Feature available_at policy | documented |
| Exact baseline and holdout | reproducible |
| MAE/RMSE/WAPE vs baseline | included |
| Shutdown subset metrics | included or insufficient |
| Explainability sample | included |
| Model/training/feature versions & hashes | included |
| Human sign-off and rollback readiness | required |

## Minimum no-leakage tests

- Artificial future change must not alter as-of past training record feature values.
- A future unplanned Trip entered later cannot appear in prior forecast feature snapshot.
- Fitting scaler/imputer on validation/test history causes test failure.
- Month-end actuals never include dates after as_of.
- Plan retroactive correction not treated as knowledge available before actual approval.

## V1 acceptance without a large historical Dataset

1. Upload a valid fixture CSV; map columns + unit + period + provenance.
2. Reject 3 invalid cases: duplicate period/product, unknown unit, malformed date; retain raw file.
3. Show chronological split, no random split, train baseline and candidate asynchronously.
4. Save experiment metrics, seed, config, model and dataset hashes; preserve immutable versions.
5. With 36 monthly rows, show coverage and limitations; never auto-approve weak candidate.
6. Demonstrate forecast baseline with explicit `engine_type=engineering_baseline`, different from `engine_type=ml_model`.
7. After independent approved daily data arrives, train/evaluate a daily candidate and publish only on signed domain acceptance.
