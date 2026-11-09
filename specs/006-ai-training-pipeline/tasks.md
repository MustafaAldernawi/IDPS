# TASKS — 006. Dataset Readiness, Baselines and Model Training

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-055 — Daily dataset readiness gate** (critical, 8 points; depends `RF-019;RF-031`)  
  **Implement:** Coverage/approval/variance/label time measurement by product.  
  **Accept:** Monthly-only data yields DAILY_MODEL_NOT_READY and honest monthly baseline eligibility; Training Studio stays operational in V1.
- [ ] **RF-056 — Point-in-time feature join** (critical, 13 points; depends `RF-055;RF-024`)  
  **Implement:** Build feature rows using available_at<=as_of and approved plan versions.  
  **Accept:** Future trip/edit does not alter historical feature snapshot.
- [ ] **RF-057 — Lag and rolling features** (critical, 8 points; depends `RF-056`)  
  **Implement:** Lag1/7/14/28 and historical 7/28 windows with closed boundaries.  
  **Accept:** Numerical tests show no inclusion of target day.
- [ ] **RF-058 — Chronological CV/test setup** (critical, 5 points; depends `RF-057`)  
  **Implement:** Create rolling-origin validation and untouched test manifest.  
  **Accept:** No random split; sealed test not used in hyperparams.
- [ ] **RF-059 — Seasonal naive baselines** (critical, 5 points; depends `RF-058`)  
  **Implement:** Last value/lag7 and metrics by product/horizon.  
  **Accept:** Baseline results reproducible and report published.
- [ ] **RF-060 — Random Forest candidate** (medium, 5 points; depends `RF-059`)  
  **Implement:** Train comparator with validated tuning range and metadata.  
  **Accept:** Same snapshots/folds/metrics as baseline.
- [ ] **RF-061 — XGBoost candidate per product** (critical, 8 points; depends `RF-059`)  
  **Implement:** Train and store artifact, config/hashes, hyperparameter search controlled.  
  **Accept:** No invented accuracy; models may be rejected.
- [ ] **RF-062 — Model evaluation & segmented metrics** (critical, 8 points; depends `RF-060;RF-061`)  
  **Implement:** MAE/RMSE/WAPE normal/Trip/startup and horizon slices.  
  **Accept:** Sparse regimes explicitly flagged as insufficient.
- [ ] **RF-063 — Explainability and error analysis** (high, 5 points; depends `RF-062`)  
  **Implement:** SHAP and error breakdown, avoid causal wording.  
  **Accept:** Each forecast displays model version and drivers.
- [ ] **RF-064 — Model serving with immutable forecast run** (critical, 13 points; depends `RF-061;RF-007`)  
  **Implement:** Validated inputs, FastAPI endpoint, Laravel job, result storing.  
  **Accept:** Responses include as_of/features/quality and no model when not ready.
- [ ] **RF-065 — Month-end aggregation** (critical, 5 points; depends `RF-064`)  
  **Implement:** Actual-approved-to-date + future forecasts for unobserved days.  
  **Accept:** No double counted days or future actual feature.
- [ ] **RF-066 — Target risk classification** (high, 5 points; depends `RF-065;RF-023`)  
  **Implement:** Compare forecast with matching plan version and thresholds.  
  **Accept:** N/A for target=0, no unsupported certainty.
- [ ] **RF-067 — ML reproducibility tests** (critical, 8 points; depends `RF-062;RF-064`)  
  **Implement:** Hash manifest/seed/folds/leakage and staging smoke tests.  
  **Accept:** T-ML-01..06 and quality thresholds.
- [ ] **RF-068 — Optional LSTM decision gate** (low, 3 points; depends `RF-055`)  
  **Implement:** Evaluate if data density permits; otherwise reject with rationale.  
  **Accept:** No mandatory LSTM without enough data.
- [ ] **RF-069 — Engineering model review** (critical, 3 points; depends `RF-067`)  
  **Implement:** Sign model usefulness/scope limitations, identify shutdown coverage gaps.  
  **Accept:** Signed model evaluation and acceptance or not-ready.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________

### v1.2 added actual-data tickets

Implement `RF-110..116` to integrate research-only code with production-gated Training Studio and monthly forecast.

- [ ] **RF-110 — Migrate existing research starter with separation from production AI** (P5)
  - Dependencies: RF-109
  - Acceptance: Research results preserved and tagged; no arbitrary joblib loading.

- [ ] **RF-111 — Dataset Upload/Profile/Mapping UI and API** (P5)
  - Dependencies: RF-109
  - Acceptance: Validated CSV XLSX, QA preview and reasoned rejection.

- [ ] **RF-112 — Dataset snapshot with approval and timestamp governance** (P5)
  - Dependencies: RF-111
  - Acceptance: Immutable snapshot SHA and source lineage, no future values.

- [ ] **RF-113 — Async baselines and Ridge/RF/XGB training jobs** (P6)
  - Dependencies: RF-110;RF-112
  - Acceptance: Same temporal folds, deterministic seeds, job status and metrics.

- [ ] **RF-114 — Monthly leak-free evaluation 2021/2022/2023** (P6)
  - Dependencies: RF-113
  - Acceptance: 2023 sealed test, as_of safe lags, counts and WAPE denominator.
