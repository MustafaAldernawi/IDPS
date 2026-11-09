# 23 — 2021–2023 Historical Data Integration / MySQL implementation spec

## Purpose

Import authoritative historical evidence into MySQL with source provenance, careful reconciliation, and review state; **do not migrate a provisional CSV into final operational truth without approval**.

## Sources

1. `data/raw-sources/2021_actual_target.xlsx` — 12 months, `الفعلي`, `المستهدف`.
2. `data/raw-sources/2022_actual_target.xlsx` — 12 months.
3. `data/raw-sources/2023_actual_target.xlsx` — 12 months.
4. `data/raw-sources/2023_monthly_targets.pdf` — independent target report, used for cross-check, not extra actual rows.
5. `data/raw-sources/2022_dec_production_report.pdf` and 2025 report — monthly event/context, not independent daily records.
6. `data/raw-sources/*assay*.pdf` — crude reference specs, not target labels or extra timestamps.
7. 2023 presentation `semi_actual` ≠ approved Excel actual; do not overwrite version or pretend equal. 2024 annual/2025 cumulative not appended to monthly series.

## Current CSV mapping (36 rows / 35 columns)

- Period: `month` `YYYY-MM`; `period_granularity=month` (canonical `monthly` in DB); `period_status=actual`; source files + actual/target sheet names.
- `crude_{sharara,hamada,total}_{actual,target}_t` → item kind `feed`, crude kind; preserve separate values.
- `product_{lpg,unprocessed_naphtha,gasoline,kerosene,diesel,heavy_fuel_oil}_{actual,target}_t` → product output measures, pending source scope review.
- `product_total_{actual,target}_t` → reported aggregate only, not additional product.
- `fuel_gas_actual_t`, `lpg_used_actual_t`, `diesel_used_actual_t`, `hfo_used_actual_t` → fuel measures.
- `total_actual_t` → **ambiguous generic column from original extraction; map only after checking it's aggregate fuel consumption, do not treat as total production**.
- `loss_reported_actual_t`, `flare_gas_reported_actual_t`, `unaccounted_and_flare_reported_actual_t` → loss/flare; avoid double-counting overlapping semantics; require subject-matter review.
- `qa_flags` → provenance and reconciliation status, never training feature.

## DB contract

Use canonical existing `contracts/schema.sql` for tables `source_files,import_batches,import_rows,monthly_observations,approval_actions,audit_logs,reconciliation_cases`. Use Laravel migrations. Existing `monthly_observations` has `metric_code`, `item_code`, `actual_or_target`, `period_month`, `quantity`, `unit_code`, `source_file_id`, `import_row_id`, `revision_no`, `status`; additionally preserve report status (`actual` vs `semi_actual`) **via an approved migration to field** if absent, rather than replacing `actual_or_target` (different semantic dimension).

**Legacy caution:** `services/ai/research_starter/sql/01_monthly_observation_staging_mysql84.sql` independently defines `monthly_observations` with different columns. It is a historical research draft **DO NOT RUN alongside canonical schema**. Build one authoritative Laravel migration after reconciliation of both definitions.

## Data control and transactions

1. Hash source SHA256 + store original metadata and actor. Repeat same source idempotently or create an explicit revision when file changes.
2. Extract line values into staged records with source sheet/cell/row context; preserve decimal precision (tonnes as DECIMAL).
3. Validate parse, month continuity, duplicate item/month, target/actual labels, unit, Null/0 ambiguity, per-category totals, report status.
4. Flag anomalies rather than changing source values; signed reviewer can approve with documented reason or reject / request correction.
5. Approved corrected facts create append-only revision and supersede prior version; train only on selected approved snapshot; research mode permits explicit provisional data with warning.
6. Time-safe events: real 2022-12-28 power outage source; duration `NULL`, causes documented, no invented unit operating hours.
7. After engineering sign-off, build an immutable monthly dataset snapshot for each eligible product and horizon.

## Acceptance tests

- Import 36 months twice → same logical observations, no duplicates.
- Import file with changed month → new revision with source lineage, not silent overwrite.
- Reject mixing annual 2024 cumulative and monthly rows; reject duplicate period/product stage.
- `target=0` and loss `0` remain recorded distinctly from missing; ambiguous loss flag displayed.
- Feature export only with approved fields available before `as_of`; research mode explicitly visible.
