# 05 — Data Ingestion, ETL and Quality Controls

## 1. Sources actually received

- `المقارنة لسنة كاملة 2021افعلي.xlsx`: workbook with **9 sheets**, including `المستهدف`, `الفعلي`, `الربع الاول`, `الربع الاول (2)`, `الاسفلت`, `حساب الاسعار`, `ورقة1`–`ورقة3`. The first two use numbered **monthly columns 1–12**, with rows for crude types and products. This is not a 2021 daily timeseries.
- `تقرير الإنتاج لشهر ديسمبر لسنة 2025م.pdf`: scanned 15-page report containing actual/target summaries, energy, loss, chemicals, asphalt and cumulative metrics. Data requires **manual or reviewed extraction**, not assumed OCR fidelity.
- **Not yet received/verified in this pack:** 3 years of daily/shift-level data, event timestamps/ETA, DCS historian, unit shutdown log, quality results, numerical electrical data, reference calorific conversion standards.

## 2. ETL stages

```text
Raw (immutable source + SHA256)
   ↓
Preflight (file type, extension, size, malware/safety if infrastructure supports)
   ↓
Parse (Excel sheets, CSV rows, approved PDF transcription)
   ↓
Staging (raw columns + source locator; nothing overwritten)
   ↓
Mapping review (Arabic aliases → canonical unit/product/metric)
   ↓
Validation and reconciliation
   ↓
Reviewer approval
   ↓
Canonical approved observations + versioned lineage
   ↓
Analytics / Daily feature dataset if daily input exists
```

## 3. Source-to-canonical mapping example

| Source header | Canonical | Transform | Required review |
|---|---|---|---|
| الشرارة | crude_code=`SHARARA` | source cell numeric in `metric_tonne` | crude owner |
| الحمادة | crude_code=`HAMADA` | numeric/nullable | crude owner |
| غاز النفط المسال | product=`LPG` | ton metric | production owner |
| زيت ديزل | product=`DIESEL` | ton metric | production owner |
| جازولين | `GASOLINE_REFINERY_STREAM` or `FINAL_GASOLINE_BLEND` | **cannot decide automatically** | process engineer |
| زيت وقود ثقيل | `HEAVY_FUEL_OIL` | ton metric | process engineer |
| الفاقد الغير منظور | `UNACCOUNTED_LOSS` | ton metric | process engineer |
| محسن الجازولين (MTBE) | `MTBE_ADDITIVE` | ton metric; distinguish blended addition from refinery yield | blending owner |

## 4. Hard validation errors (block approval)

- Missing period/product/unit/source locator on required data.
- Quantity negative without explicitly classified adjustment or approved accounting policy.
- Invalid date, nonexistent unit/unknown product mapping.
- Duplicate source fingerprint/row (may be idempotent no-op rather than error).
- Multiple active approved target plans for same scope/validity period.
- Nonfinite input, incompatible unit conversions, unsatisfied foreign keys.
- Daily observation impossible because source aggregates only monthly (flag granularity conflict).

## 5. Warnings (review allowed, never silently overwrite)

- Out-of-range production yield, suspicious `Actual / Target`, missing feed/energy for day.
- Material balance difference exceeding **configurable, approved tolerance**, including possible components absent from report.
- Unknown ETA or incomplete unit status calendar.
- Duplicate labels representing different product process stages.
- Historical gap and unusually long runs of zeros.

## 6. Missing vs zero vs not applicable

- `NULL`: unknown/not supplied.
- `0`: operator/source explicitly states zero.
- `N/A`: indicator not defined (e.g. target=0).
- `not operational`: unit shutdown that may legitimately produce zero.

Never fill missing raw daily production with zero. Any imputation belongs to an explicit training feature pipeline and is fit on training data only.

## 7. Idempotent reimport

Fingerprint original file SHA256; each row uses `batch_id + sheet + row + metric_code + revision`. Repeating same file returns previous import outcome; changing an approved value creates revision with audit and downstream recalculation queue, not silent UPDATE.

## 8. Historical workbook normalization (2021)

Input: each metric row with month columns. Output `monthly_observations` long format:

`(year=2021, month=1..12, metric, crude/product, actual_or_target, quantity, unit, original_sheet, original_row, original_column)`.

**Never** generate 365 daily rows by spreading a monthly total; that is fabricated training data. It may be used for descriptive monthly comparison or suitably constrained small-sample baselines with caution.

## 9. Daily request template

`data/samples/daily_template.csv` is a column blueprint, not verified refinery readings. Request from operations:

`business_date,unit_code,crude_type,crude_feed_t,product_code,production_t,operating_hours,planned_downtime_hours,unplanned_downtime_hours,fuel_gas_t,fuel_oil_t,electricity_kwh,loss_t,source_ref,approved_at`.

For real ML, avoid features not available by `as_of`. Add `observed_at`/`available_at` per measurement when sampling intraday.

## 10. Data readiness gates

- Minimum agreed horizon/granularity (daily 3 years is the target request, **not a proof of adequacy**).
- Sufficient valid non-missing `approved` labels per product and regime, measured after excluding gaps.
- Clear units and stable product scope; no unresolved severe quality errors.
- At least one realistic holdout period containing normal and shutdown behavior for meaningful backtest; if not, mark regime evaluation insufficient.
- Operational features have point-in-time availability metadata or use safe historical lags.
- Baseline beatable evidence before advertising AI; failed gate → use descriptive dashboards, no misleading accuracy claim.

## 11. Data contracts and quality automation

- `contracts/daily_record.schema.json`: canonical row schema.
- `scripts/validate_daily_csv.py`: lightweight syntax checks on sample daily CSVs.
- Production ETL shall have property-based tests, reconciliation error queue and approval workflow.

## 12. Deliverables from data audit workshop

Signed product/stream taxonomy, mass-balance ledger definitions, energy factors, file-mapping matrix, exact unit hierarchy, daily sample records (redacted as needed), missingness report, source revisions policy and AI readiness verdict.
