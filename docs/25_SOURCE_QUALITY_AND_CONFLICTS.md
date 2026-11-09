# 25 — Current data quality, known discrepancies and unresolved definitions

| Issue | Source / period | Rule for developer |
|---|---|---|
| Annual crude sum vs `semi-actual` presentation | 2023: Excel month sum about 4,957,110.798 t; presentation semi-actual ~4,918,909 t | Keep both with status/issue date; no overwrite until engineering reconciliation |
| 1-ton monthly component differences | Selected 2022/2023 workbook rows | Flag `needs_review` preserving source cell and aggregate formula |
| Six-ton fuel annual mismatch | 2023 workbook | Do not silently overwrite; show discrepancy in reconciliation UI |
| `#REF!` formulas | 2023 price worksheet | Exclude from forecast dataset |
| Old 2022-12-28 refinery outage | 2022 December PDF | Event date verified; **exact downtime duration is unknown** |
| Flare meter reliability | 2022 report indicates flare meters malfunction | Missing/zero is not evidence of zero physical flare |
| Assay vs plant Yield | Lab 2019/2020 Sharara/Hamada assays | Do not use distillation cuts as direct final product yield or as time-changing readings |
| 2024/2025 files | 2024 annual, 2025 Jan–Aug cumulative, Dec monthly | No fabrication of missing months; only explicitly month-scoped facts become month rows |
| Metric `total_actual_t` in provisional CSV | Column from extraction | Must verify mapping to total fuel consumption versus reported total before import |
| Blended vs unblended gasoline | 2025 December shows extra MTBE after refinery stream | Separate stage/product type when reconciling target and actual; avoid double counting |

## What must be approved before live operations

1. The definitive semantic mapping for all 35 columns and original worksheet cells.
2. Product taxonomy: crude processing stage, distillation streams, blended final products.
3. KPI denominator / fuel energy conversion / loss measurement scope (flare/non-visible).
4. Which annual/semi-actual reports supersede which releases; date of each report.
5. Source event timestamp and accuracy; *no* inferred hours of shutdown.

**Until then:** all 2021–2023 data and trained research artifacts remain `provisional`/`research_only`. No false precision in management dashboard.
