# IDPS / AI Module — Agent Instructions

Scope: **initial operationally safe research ML starter**, attached to the existing IDPS MySQL 8.4 / Laravel blueprint. Do not replace the original root AGENTS.md; merge these instructions when integrating.

## Invariants
- Project: IDPS — Integrated Data and Production System, Zawiya Refinery.
- Use **MySQL 8.4 LTS**. Do NOT introduce PostgreSQL.
- Historic source is 36 **monthly** rows (2021-01 to 2023-12), status `PROVISIONAL`. Do not assert that these are daily readings or approved actuals.
- Do not synthesize historical daily rows or fill missing months with fabricated values.
- Keep actual, target, semi-actual, final-actual and quality-review states distinct; preserve source_file, revisions and original quantities.
- Assay documents are static crude properties; do not confuse laboratory cut yields with plant final product actuals or independent training rows.
- For next-month prediction, use only facts known as of the forecast timestamp: past monthly actuals and properly versioned plans. **Never** include same-month actual crude, same-month product actual, same-month losses or unplanned future shutdown duration.
- Every data export is provisional until operations/process engineering has signed off. `review_status=approved` in a local CSV is not authorization; the Laravel RBAC-backed approval API must enforce it.
- Before retraining, require contiguous approved monthly history with defined units and revisions. Record dataset SHA, code version, date ranges, features and metrics.
- Select on validation period, report untouched chronological holdout, do not select on holdout; holdout size here is only 12 months and conclusions remain tentative.
- Do not automatically activate a retrained model. Champion/challenger human review required with rollback.
- Model artifacts are loaded only from trusted locally generated paths. Never unpickle a model uploaded by users.
- Operational forecasts must show the basis (`baseline`, `ML`, or `scenario`), as-of timestamp, review quality, and data vintage; fail closed on missing data.

## Task order
1. Run `pytest -q` and `python cli.py train` and inspect `results/research_report.json`.
2. Review source QA and reconcile annual discrepancies with engineers.
3. Add MySQL monthly ingestion migration and Laravel review workflow (staged → approved).
4. Implement canonical monthly export from approved MySQL observations. Never use staging rows in retraining.
5. Wire export to `cli.py retrain-approved`; store artifacts and metrics in existing `dataset_snapshots`, `training_runs`, `model_versions` tables.
6. Implement Laravel queue job for scheduled/approved-data-triggered retraining; review and promotion via role permission.
7. Add daily record collection, aggregate daily actuals only after complete month close and unit review, retaining daily series for future daily models.

## Definition of Done
- All tests pass; no leakage; no unreviewed deployment; retryable jobs and audit logs; monthly production and units cross-checked; documented rollback; data versioning and dashboard explain results clearly.
