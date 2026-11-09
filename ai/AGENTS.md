# AI Agent — Forecasting & Retraining

- Read root AGENTS, `docs/06_AI_FORECASTING_SPEC.md`, `docs/07_SHUTDOWN_DYNAMIC_REFORECAST.md`, `docs/13_MLOPS_RETRAINING.md`, `docs/17_AI_TRAINING_RUNBOOK.md`.
- Do not train against monthly data while claiming daily horizon; fail readiness checks with clear explanation.
- Define forecast horizon and feature `available_at` explicitly; implement point-in-time feature join.
- Always compare to Seasonal Naive; add suitable Random Forest/XGBoost, choose by rolling-origin tests and operational criteria.
- Do not claim that XGBoost supports true online partial_fit: periodic retraining from curated history is the default.
- Treat planned shutdown hours as known only if plan was approved by prediction time. Unknown unplanned future downtime must be modeled as scenario assumptions, not ground truth.
- Never train on prediction records as labels; label only approved actual measurements.
- Feature preprocessing, imputers and encoders fitted on training split only; inference must use artifact-pinned transforms.
- Record model version, feature schema version, training snapshot checksum, code commit hash, metrics by product and downtime regime.
- Do not promote a challenger unless improvements and guardrails hold on holdout/backtest. Preserve champion, rollback path.
- Produce confidence/uncertainty intervals only if calibrated/tested; otherwise display point forecasts and clear disclaimers.
- Explain drivers as model associations, not verified physical causation.
