# 08 — Three KPIs and Reconciliation Methodology

## 1. KPI-01: Production Target Achievement

**Definition:** `Achievement% = 100 × Actual Approved Quantity / Active Approved Target Quantity` for identical *product / aggregation level / period / physical unit*.

- If target=0: `N/A` with clear reason; if actual=0 and target>0 result=0%.
- Report each product separately to prevent mixing intermediate and final blended flows.
- Aggregate KPI requires a **signed product group** where streams don't overlap; do not blindly sum LPG/naphtha/reformate/gasoline if blending reuses intermediate streams.
- Actual and target versions & timestamps recorded.

**Grounded December 2025 illustration:** Report shows `Total products: actual 400,720 t; target 445,283 t`, about `89.99%` using report's scope. Crude throughput uses another scope `432,116/465,000 = 92.93%`. These are **not interchangeable KPIs** and scope must be stated.

## 2. KPI-02: Specific Energy Consumption

**Preferred:** `SEC = total energy in GJ / crude throughput in metric tonne`, at the same approved plant/unit scope and period.

Formula for input carriers only after factors approved:

`Energy_GJ = Σ(FuelMass_t × LHV_GJ_per_t) + Electricity_kWh × approved_GJ_per_kWh + other approved carriers`

- Decide **LHV or HHV** basis, location of measurement (units vs facilities), and whether imported power uses site or source energy basis.
- Don't add fuel tons + kWh; if factors not approved, display separate fuel gas/fuel oil masses and `Fuel Consumption Ratio% = 100 × fuel_t / crude_t` clearly **not SEC**.
- December 2025 report: fuel gas `11,904 t`; fuel oil `4,305 t`; reported total `16,208 t` (sum by printed components = `16,209 t`; one-ton difference suggests rounding, source transcription or definition to investigate, not to correct silently), reported fuel ratio `3.75%`. Preserve original totals with reconciliation flag.

## 3. KPI-03: Refinery Loss Rate

**Candidate formula, subject to process-engineer sign-off:**

`Unaccounted Loss% = 100 × Unaccounted Loss_t / Crude Processed_t`, aligned to scope/period.

**Grounded December 2025 report:** `12,051 t / 432,116 t ≈ 2.79%` for the report's `الفاقد غير منظور`. The report also shows fuel + loss combined (`28,259 t`, 6.54%), which is **not identical to unaccounted loss**. Keep these as two separate indicators.

- Track flare, unexplained losses, feed/receipt differences, tank inventory, transfers, product/quality stage separately; mass balance must account for stock changes, transfers, recycles and additives (e.g. MTBE).
- `Total Loss and Fuel` is a separate explanatory metric, never silently renamed `Loss`.

## 4. Reconciliation framework

For each scope/period:

`Opening inventory + receipts + feed/additives = end-product deliveries + internal fuel + physical losses + closing inventory + internal transfers/recycles (net) + reconciliation difference`

The exact refinery-approved flow schema must be signed; examples are conceptual, not an operational accounting assertion.

`difference = measured_input − measured_output_adjusted`, compare to **approved tolerance** (may depend on scope and measurement quality). Do not force differences to zero. Raise a `reconciliation_case` linked to source pages/cells and reviewer notes.

## 5. KPI display and governance

Show: KPI title, value+unit, period, actual/target denominator, scope, trend, freshness, calculation version, source/approval state and thresholds. Thresholds (green/amber/red) are configuration chosen with stakeholder sign-off—not arbitrary universal oil-industry standards.

## 6. Unit tests

- Standard: actual=900, target=1000 →90%.
- target=0 →N/A; positive actual with target zero →explicit exception.
- missing actual ->N/A, not 0%.
- negative adjustment/return not silently treated as production.
- 12,051/432,116*100 within correct displayed precision.
- 11,904+4,305 discrepancy with printed 16,208 raised as source reconciliation flag, not auto-repaired.
- SEC fails if energy factors missing instead of returning nonsense.
- Multiple units/product tiers do not double count intermediary streams.
