# PLAN — 004. Process Engineering Analytics and Reports

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **002;003;005** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-043: KPI definition registry.** Version formulas, scope, threshold and approval. Confirm acceptance: KPI cannot silently change historical definition.
2. **RF-044: Actual vs Target engine.** Compute per product/unit/period actual target variance. Confirm acceptance: December diesel 105798/132339 ≈79.94%, with source.
3. **RF-045: SEC/fuel ratio implementation.** Convert fuel/electricity via approved energy factors or fall back to fuel ratio. Confirm acceptance: Missing factor = SEC N/A, no unit mismatch.
4. **RF-046: Unaccounted loss KPI.** Compute loss fraction by approved scope/period; separate fuel. Confirm acceptance: 12051/432116≈2.79%, source not fabricated.
5. **RF-047: Mass balance/reconciliation review.** Flag inconsistent totals, MTBE blended vs produced, scope conflicts. Confirm acceptance: Dec 2025 fuel + fuel oil mismatch flagged, original values preserved.
6. **RF-048: Chemical variance analytics.** Approved actual/expected by material/unit period. Confirm acceptance: No merging kg vs liters, thresholds versioned.
7. **RF-049: Engineering analysis annotations.** Write reasons, hypotheses, evidence and reviewer approval. Confirm acceptance: Audit trail and source links in report.
8. **RF-050: Three KPI dashboard API.** Scope/period/version/status, historical trend and details. Confirm acceptance: N/A/stale/reconciliation badges exposed to UI.
9. **RF-051: Monthly report export.** Recreate validated sections in pdf/excel with cited source/period. Confirm acceptance: Export user action audited; Arabic numbers readable.
10. **RF-052: Engineering tests.** Unit/period/denominator/rounding/overlap regression suite. Confirm acceptance: KPI tests T-KPI-01..03 pass.
11. **RF-053: Engineering owner review.** Review equations and ranges with process owner. Confirm acceptance: Signed approval on published KPI formulas and thresholds.
12. **RF-054: Analytic read performance.** Indexes, filtering, pagination, prevent unbounded exports. Confirm acceptance: Queries validated under representative size.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Backend + Process Engineering** with realistic approved fixtures.

## Exit gate

December 2025 anchored sample matches source reporting within approved rounding; non-comparable aggregation shows warnings; source differences never silently edited.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
