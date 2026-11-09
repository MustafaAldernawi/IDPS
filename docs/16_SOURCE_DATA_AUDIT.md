# 16 — Verified Source Data Audit (2021 workbook + December 2025 report)

## Sources supplied by user

1. **Excel:** `المقارنة لسنة كاملة 2021افعلي.xlsx` (existing workbook read in source environment, not modified).
2. **PDF:** `تقرير الإنتاج لشهر ديسمبر لسنة 2025م.pdf` (15 pages, scanned; visual manual reading needed).
3. **HTML:** `refinery_ai_project_study_v2.html` (previous study, included in pack as `PROJECT_STUDY_V2.html`).

## Workbook structural verification

Workbook read-only inspection with `artifact_tool` revealed **nine worksheets**:

| Sheet | Verified general contents | Granularity |
|---|---|---|
| `المستهدف` | monthly 2021 targets by crude/product | monthly |
| `الفعلي` | monthly 2021 actuals by crude/product | monthly |
| `الربع الاول` | quarter summary for Q1 2021 | aggregated |
| `الربع الاول (2)` | cumulative 2021 comparison | aggregated |
| `الاسفلت` | asphalt unit monthly/annual target calculations | aggregate |
| `حساب الاسعار` | pricing calculations; not automatically training input | TBD |
| `ورقة1`–`ورقة3` | additional worksheets not fully semantically audited | TBD |

Verified sample sheet source headers: `الشرارة`, `الحمادة`, `المجموع`, `غاز النفط المسال`. Month columns are numeric 1–12. E.g., `الفعلي` shows monthly actual crude totals for early months. **These are not 365 daily observations**.

## 2025 PDF — examples visually confirmed

| Fact | Value | Provenance note |
|---|---:|---|
| Processed crude actual | 432,116 metric t | Dec 2025, report body/actual-v-target |
| Processed crude target | 465,000 metric t | Dec 2025 |
| Crude target achievement | 92.93% | monthly report |
| Total report products actual | 400,720 metric t | product comparison table |
| Total report products target | 445,283 metric t | product comparison table |
| Products target achievement | 89.99% | monthly report; scope must be confirmed |
| LPG actual | 4,220 metric t | report product table |
| Naphtha actual | 54,382 metric t | report product table |
| Gasoline refinery production actual | 53,504 metric t | report product table; separate from MTBE-inclusive final total |
| Kerosene actual | 61,312 metric t | report product table |
| Diesel actual | 105,798 metric t | report product table |
| Heavy fuel actual | 121,505 metric t | report product table |
| Gas fuel reported | 11,904 metric t | monthly fuel table |
| Fuel oil reported | 4,305 metric t | monthly fuel table |
| Total fuel reported | 16,208 metric t | monthly fuel table; arithmetic difference 1 t vs components |
| Unaccounted loss reported | 12,051 metric t | losses table |
| Loss rate reported | 2.79% | report summary |
| Total fuel and loss reported | 28,259 metric t | report fuel/loss section |
| MTBE added to gasoline pool (monthly) | approx. 7,270.6 metric t | separate pool addition from refinery stream |

**Critical caution:** the scanned PDF has multiple tables, including cumulative year-to-date and asphalt. Avoid mixing scopes; some printed component sums/percentage values may be rounded or transcribed inconsistently. All values must retain page/source locators in actual importer and be reconciled with engineers.

## Missing for AI training as currently supplied

- 3 confirmed calendar years of daily/shift production/feeding.
- Historical unit operating status, Trip logs and exact downtime start/end.
- Feature observation availability timestamps.
- Validated source and bounds for electricity, energy GJ conversions and unit-linked chemical effects.
- Per-unit mapping of product stage (raw/reformate/blend/final).

## What can be built and demonstrated now

- Source import screen and monthly comparison.
- Core data dictionary, masters and approval process.
- Three KPI methodology with report examples, subject to sign-off.
- Frontend and API skeleton.
- **Illustrative** shutdown impact calculator with clearly labeled assumptions (not trained inference).

## What cannot honestly be declared done now

- A trained and validated daily XGBoost production forecast.
- Production-grade confidence interval or quantified shutdown losses.
- Automated DCS anomaly/failure detection.
- Proven continuous learning from newly approved daily actuals.

## Next source collection request

Ask production/engineering for a redacted sample of 30 daily records first (include quiet and shutdown days), then 3 years complete history, plus source documentation and approval status. Standardize template and assess percent missing per field before committing to model architecture.


## Current authoritative audit appendix (2026-11-09)

The earlier audit predates current new sources. For actual imported 2021–2023 monthly Excel and flagged reconciliation issues, use `data/audits/IDPS_DATA_AUDIT_2021_2023.md` and `docs/25_SOURCE_QUALITY_AND_CONFLICTS.md`; do not rely solely on older 2021/2025 examples.
