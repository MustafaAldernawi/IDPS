# 21 — V1 Trainable AI Module: Implement Now, Activate Based on Evidence

## Approved policy
IDPS V1 must include a working **AI Training Studio** regardless of the volume of existing historical data. **Implementation readiness is independent of dataset adequacy; production accuracy and model promotion are not.**

## V1 functional journey

1. User with `dataset.upload` privilege uploads XLSX/CSV (monthly/daily/shift), never parsed into daily pseudorecords.
2. Source bytes stored immutably with file checksum + upload actor; preview mapping and column normalization.
3. Validator checks dates, granularity, unit conversions, duplicate periods, negative quantities, product identities, null != zero, data availability as-of.
4. Approval produces a frozen dataset snapshot + feature schema hash; all training jobs reference that snapshot.
5. Training Studio selects product/horizon, granularity, feature set, model family and bounded hyperparameters.
6. Async training job uses chronological folds and untouched holdout when feasible; logs runtime, seed, metrics, code/environment hashes.
7. Compares naive/seasonal/historical yield baselines, optional Random Forest and XGBoost (only if split/coverage passes).
8. Candidate model stored as versioned artifact outside MySQL; MySQL keeps metadata, validation, artifact hash, status and run history.
9. Human approver can promote candidate only on validated independent holdout; models are scoped by `(product,granularity,horizon)`.
10. Forecast UI displays `engine_type=ml_model` or `engine_type=statistical/engineering` and data-quality limitations.
11. As new daily data arrive, approved labels enter future snapshots; retraining can be scheduled, never silently promoted.

## Empty / sparse dataset behavior
- Empty history: `NO_DATA`, training UI still visible, allow uploading, show no fictional forecasts.
- Small monthly dataset (e.g. 36 points): allow approved KPI analytics and conservative statistical/engineering forecasts with source/assumptions; mark ML model `NOT_READY` for reliable deployment until a valid backtest is possible.
- Daily history comes later: train and backtest daily model separately, using future-safe features and exactly known input timestamps.
- Test fixtures/synthetic data **only** verify software execution; training scores from fixtures cannot be presented as real refinery performance.

## API and data operations to build
- POST `/api/v1/datasets/upload`, GET `/api/v1/datasets/{id}/quality`, POST `/api/v1/datasets/{id}/approve`; persisted source lineage.
- POST `/api/v1/training-runs`, GET `/api/v1/training-runs/{id}` and compare model candidates.
- GET `/api/v1/models`, POST `/api/v1/models/{id}/promote` (RBAC + single-active transaction).
- POST `/api/v1/forecasts` with explicit method, horizon, as_of, assumptions, model scope or fallback; async status.
- GET audit and evaluation views for every generated forecast.

## Acceptance tests
1. Monthly CSV valid, invalid and re-uploaded: mapping, rejection, versioning and idempotent behavior.
2. Upload 36 monthly rows: Training Studio remains functional, baseline and data quality shown, no false daily model.
3. Upload approved daily fixture with sufficient simulated diversity: run baseline and XGBoost job, compare sealed time-holdout, verify artifact and reproducibility.
4. Backdated correction does not leak a future adjustment into past as_of predictions.
5. A shutdown occurring tomorrow cannot be used as a tomorrow feature in today's training example.
6. Train when the Python service is unavailable: Laravel records queued/failed and retries safely; never reports fabricated completion.
7. One active model per product × granularity × horizon under concurrent promotions (MySQL unique generated scope key).
8. New approved actuals trigger eligibility evaluation and candidate retraining, not blind automatic deployment.

## Explicit no-go
No autonomous refinery control, no fabricated datasets, no unreviewed source correction, no prompt of guaranteed accuracy, no default training with confidential data in external SaaS.


### Real dataset update (v1.2)

Research training with monthly 2021–2023 is possible now and already exists under `services/ai/research_starter`; V1 Training Studio integration and a production-eligible model are **not** already built. See `docs/24_AI_TRAINING_REAL_DATA_SPEC.md`.
