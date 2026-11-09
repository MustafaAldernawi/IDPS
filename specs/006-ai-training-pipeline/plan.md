# PLAN — 006. Dataset Readiness, Baselines and Model Training

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **001;002 and test fixtures** (real history is a model approval gate, not a development dependency) and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-055: Daily dataset readiness gate.** Coverage/approval/variance/label time measurement by product. Confirm acceptance: Monthly-only data yields DAILY_MODEL_NOT_READY; allow scoped monthly baselines and training job experimentation, not fake daily ML accuracy.
2. **RF-056: Point-in-time feature join.** Build feature rows using available_at<=as_of and approved plan versions. Confirm acceptance: Future trip/edit does not alter historical feature snapshot.
3. **RF-057: Lag and rolling features.** Lag1/7/14/28 and historical 7/28 windows with closed boundaries. Confirm acceptance: Numerical tests show no inclusion of target day.
4. **RF-058: Chronological CV/test setup.** Create rolling-origin validation and untouched test manifest. Confirm acceptance: No random split; sealed test not used in hyperparams.
5. **RF-059: Seasonal naive baselines.** Last value/lag7 and metrics by product/horizon. Confirm acceptance: Baseline results reproducible and report published.
6. **RF-060: Random Forest candidate.** Train comparator with validated tuning range and metadata. Confirm acceptance: Same snapshots/folds/metrics as baseline.
7. **RF-061: XGBoost candidate per product.** Train and store artifact, config/hashes, hyperparameter search controlled. Confirm acceptance: No invented accuracy; models may be rejected.
8. **RF-062: Model evaluation & segmented metrics.** MAE/RMSE/WAPE normal/Trip/startup and horizon slices. Confirm acceptance: Sparse regimes explicitly flagged as insufficient.
9. **RF-063: Explainability and error analysis.** SHAP and error breakdown, avoid causal wording. Confirm acceptance: Each forecast displays model version and drivers.
10. **RF-064: Model serving with immutable forecast run.** Validated inputs, FastAPI endpoint, Laravel job, result storing. Confirm acceptance: Responses include as_of/features/quality and no model when not ready.
11. **RF-065: Month-end aggregation.** Actual-approved-to-date + future forecasts for unobserved days. Confirm acceptance: No double counted days or future actual feature.
12. **RF-066: Target risk classification.** Compare forecast with matching plan version and thresholds. Confirm acceptance: N/A for target=0, no unsupported certainty.
13. **RF-067: ML reproducibility tests.** Hash manifest/seed/folds/leakage and staging smoke tests. Confirm acceptance: T-ML-01..06 and quality thresholds.
14. **RF-068: Optional LSTM decision gate.** Evaluate if data density permits; otherwise reject with rationale. Confirm acceptance: No mandatory LSTM without enough data.
15. **RF-069: Engineering model review.** Sign model usefulness/scope limitations, identify shutdown coverage gaps. Confirm acceptance: Signed model evaluation and acceptance or not-ready.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **ML Engineer + Data Engineer** with realistic approved fixtures.

## Exit gate

V1 training pipeline and UI are fully implemented and tested with fixtures; a production ML model requires evaluated real approved history, and cannot be claimed complete until that gate passes.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
