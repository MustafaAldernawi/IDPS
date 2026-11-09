# SPEC — 006. Dataset Readiness, Baselines and Model Training

**Status:** proposed; approval required  
**Phase:** P5-P6  
**Domain owner:** ML Engineer + Data Engineer  
**Dependency:** 001;002 for coding, fixture datasets; real history required only for production model acceptance  
**Requirements:** FR-037–043, FR-046–047

## Goal and precise scope

Build a point-in-time **monthly/daily** product dataset, training/evaluation pipeline, seasonal naive, Random Forest and XGBoost per-product, SHAP and sealed holdout evidence.

## Supported user journey

Approved facts of selected granularity → readiness → snapshot → lag features point-in-time → chronological split → baseline → candidates → test report → candidate artifact.

## Business contract

- API values must be authorized, validated, versioned and traceable to data source/actor.
- Critical state changes require audit, approvals and idempotent/revision-aware semantics.
- Never substitute synthetic/demo sources for verified facility readings.
- Never implement industrial control commands or plant setpoint writes.
- For specific requirements read `docs/02_FUNCTIONAL_SPECIFICATION.md` (FR-037–043, FR-046–047) and root `SPEC.md`.

## Exceptions / failure cases

Sparse monthly data, train/test leakage, label duplicates, rare shutdowns, bad product stages, unknown future feed and future unplanned Trip information.

## Cross-service expectations

- Durable database transaction and outbox for async side effects; client receives status not fake completion.
- UI supports loading/empty/not-ready/error/partial, explicit source freshness and approved/estimated badges.
- Granularity (hour/shift/day/month), physical units and product processing stage are not collapsed silently.
- Scoped RBAC and audit on all user mutations and exports.

## Acceptance criteria

Implement full Training Studio/registry/API and validate with fixtures in V1; train real candidate when approved eligible dataset exists, with no future leakage and published baseline comparison. Production ML rollout requires real holdout and sign-off.

### Traceable deliverables

- **RF-055** — Daily dataset readiness gate: Monthly-only data yields DAILY_MODEL_NOT_READY, while monthly baselines remain available and Training Studio is functional.
- **RF-056** — Point-in-time feature join: Future trip/edit does not alter historical feature snapshot.
- **RF-057** — Lag and rolling features: Numerical tests show no inclusion of target day.
- **RF-058** — Chronological CV/test setup: No random split; sealed test not used in hyperparams.
- **RF-059** — Seasonal naive baselines: Baseline results reproducible and report published.
- **RF-060** — Random Forest candidate: Same snapshots/folds/metrics as baseline.
- **RF-061** — XGBoost candidate per product: No invented accuracy; models may be rejected.
- **RF-062** — Model evaluation & segmented metrics: Sparse regimes explicitly flagged as insufficient.
- **RF-063** — Explainability and error analysis: Each forecast displays model version and drivers.
- **RF-064** — Model serving with immutable forecast run: Responses include as_of/features/quality and no model when not ready.
- **RF-065** — Month-end aggregation: No double counted days or future actual feature.
- **RF-066** — Target risk classification: N/A for target=0, no unsupported certainty.
- **RF-067** — ML reproducibility tests: T-ML-01..06 and quality thresholds.
- **RF-068** — Optional LSTM decision gate: No mandatory LSTM without enough data.
- **RF-069** — Engineering model review: Signed model evaluation and acceptance or not-ready.



## v1.2 current real data execution contract

Use genuine 2021–2023 `monthly` staging data first. Train research candidates now with `research_only` flag and preserve 2023 chronological test. Implement V1 Training Studio regardless of sample size; **live model approval remains separate**. See `docs/24_AI_TRAINING_REAL_DATA_SPEC.md` and backlog `RF-110..116`.
