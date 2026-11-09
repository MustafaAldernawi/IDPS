# TASKS — 004. Process Engineering Analytics and Reports

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-043 — KPI definition registry** (critical, 5 points; depends `RF-005;RF-021`)  
  **Implement:** Version formulas, scope, threshold and approval.  
  **Accept:** KPI cannot silently change historical definition.
- [ ] **RF-044 — Actual vs Target engine** (critical, 8 points; depends `RF-022;RF-031`)  
  **Implement:** Compute per product/unit/period actual target variance.  
  **Accept:** December diesel 105798/132339 ≈79.94%, with source.
- [ ] **RF-045 — SEC/fuel ratio implementation** (critical, 8 points; depends `RF-039;RF-043`)  
  **Implement:** Convert fuel/electricity via approved energy factors or fall back to fuel ratio.  
  **Accept:** Missing factor = SEC N/A, no unit mismatch.
- [ ] **RF-046 — Unaccounted loss KPI** (critical, 5 points; depends `RF-039;RF-043`)  
  **Implement:** Compute loss fraction by approved scope/period; separate fuel.  
  **Accept:** 12051/432116≈2.79%, source not fabricated.
- [ ] **RF-047 — Mass balance/reconciliation review** (high, 8 points; depends `RF-018;RF-044`)  
  **Implement:** Flag inconsistent totals, MTBE blended vs produced, scope conflicts.  
  **Accept:** Dec 2025 fuel + fuel oil mismatch flagged, original values preserved.
- [ ] **RF-048 — Chemical variance analytics** (high, 5 points; depends `RF-026;RF-039`)  
  **Implement:** Approved actual/expected by material/unit period.  
  **Accept:** No merging kg vs liters, thresholds versioned.
- [ ] **RF-049 — Engineering analysis annotations** (high, 5 points; depends `RF-044`)  
  **Implement:** Write reasons, hypotheses, evidence and reviewer approval.  
  **Accept:** Audit trail and source links in report.
- [ ] **RF-050 — Three KPI dashboard API** (critical, 5 points; depends `RF-045;RF-046`)  
  **Implement:** Scope/period/version/status, historical trend and details.  
  **Accept:** N/A/stale/reconciliation badges exposed to UI.
- [ ] **RF-051 — Monthly report export** (high, 8 points; depends `RF-050`)  
  **Implement:** Recreate validated sections in pdf/excel with cited source/period.  
  **Accept:** Export user action audited; Arabic numbers readable.
- [ ] **RF-052 — Engineering tests** (critical, 5 points; depends `RF-050`)  
  **Implement:** Unit/period/denominator/rounding/overlap regression suite.  
  **Accept:** KPI tests T-KPI-01..03 pass.
- [ ] **RF-053 — Engineering owner review** (critical, 3 points; depends `RF-052`)  
  **Implement:** Review equations and ranges with process owner.  
  **Accept:** Signed approval on published KPI formulas and thresholds.
- [ ] **RF-054 — Analytic read performance** (medium, 3 points; depends `RF-050`)  
  **Implement:** Indexes, filtering, pagination, prevent unbounded exports.  
  **Accept:** Queries validated under representative size.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________
