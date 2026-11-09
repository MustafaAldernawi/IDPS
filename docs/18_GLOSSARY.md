# 18 — Glossary and Canonical Vocabulary

| Term | Meaning in project |
|---|---|
| Actual | Approved observed quantity at specific date/unit/product scope |
| Target | Versioned planned quantity for matching scope and period |
| Throughput | Crude quantity processed through unit/refinery, not product yield |
| Product yield | Product mass relative to input mass, same approved definition |
| Blend | Mixing multiple streams/additives into final product; separate from refinery process stream |
| LPG | Liquefied Petroleum Gas / غاز النفط المسال |
| Reformate | Reformed naphtha stream; may feed gasoline blend |
| MTBE | Gasoline oxygenate/octane improver, blended additive mass |
| SEC | Specific Energy Consumption, generally GJ/metric tonne on specified basis |
| Loss | Defined loss category, **not** automatically combined with fuel |
| Trip | Unplanned unit interruption/alarm event |
| Planned Shutdown | Downtime recorded in approved calendar before it happens |
| Unplanned Shutdown | Interruption not included in approved plan at prediction time |
| ETA | Expected Time of Return/Restart, may be null or revised |
| Reforecast | New prediction after fresh known information; previous run retained |
| `as_of` | Prediction's knowledge cutoff timestamp |
| `available_at` | Time source feature first became usable/known |
| Feature | Historical/operational variable model may use |
| Label | Approved observed production to predict |
| Lag | Prior observed values, e.g. production D−1 |
| Rolling Mean | Mean over historical window ending before target |
| Backtesting | Evaluating time-aware model on past unseen sequential windows |
| Baseline | Simple benchmark like seasonal naive |
| XGBoost | Gradient-boosted decision trees used as tabular candidate |
| LSTM | Recurrent neural network; optional if rich sequence data |
| SHAP | Explanation of feature contributions, not proof of causality |
| MAE | Mean Absolute Error, production ton error magnitude |
| RMSE | Root Mean Squared Error, penalizes large errors |
| WAPE | Weighted Absolute Percentage Error; undefined for zero total actual |
| Drift | Change in input distribution and/or predictive performance |
| Champion | Current approved deployed model |
| Challenger | Newly trained candidate under comparison |
| Idempotency | Repeated same command should not duplicate effects |
| Outbox | Reliable event dispatch pattern coupled with DB transaction |
| Reconciliation | Check that balance/declared totals are internally consistent |
| Synthetic | Man-made illustrative data, **not** observed refinery readings |
