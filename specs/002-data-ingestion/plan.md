# PLAN — 002. Historical Source Ingestion and Data Quality

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **001; signed source mapping** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-011: Source mapping workshop.** Document approved source matrix, names, units, source granularity and product stage. Confirm acceptance: Process owner signs taxonomy and conflict list.
2. **RF-012: 2021 Excel monthly mapper.** Extract target and actual by month using workbook sheet/row/cell lineage. Confirm acceptance: 12 months per mapped metric, no artificial daily expansion.
3. **RF-013: 2025 scanned report reviewed entry.** Design human-verified transcription workflow for key report tables and page references. Confirm acceptance: Two-person review and page provenance; no blind OCR assumption.
4. **RF-014: Upload fingerprint staging.** Source_file sha256, upload safety, stage batch/items before approval. Confirm acceptance: Original immutable and duplicate file idempotent.
5. **RF-015: Mapping dictionary.** Versioned Arabic alias to canonical metric/product/unit, unresolved queue. Confirm acceptance: Unknown alias cannot auto map and approve.
6. **RF-016: Data validation engine.** Dates, negatives, null/zero, incompatible unit, totals and range warnings. Confirm acceptance: Reject reason includes original cell/source and rule id.
7. **RF-017: Preview and approval flow.** Import preview, reviewer acceptance, canonical upsert with revisions. Confirm acceptance: No partial approvals and errors remain visible.
8. **RF-018: Reconciliation cases.** Preserve source disagreements and open human review cases; define tolerance setting. Confirm acceptance: 1 t component mismatch flagged without altering values.
9. **RF-019: Daily input quality profiler.** Daily coverage, missingness, source lag, shutdown class coverage, no daily pseudo records. Confirm acceptance: Readiness report correctly fails monthly-only data.
10. **RF-020: Data import regression suite.** Round trips, duplication, Arabic numerals, formula sanitation, source revisions. Confirm acceptance: All sample fixtures pass/fail expected cases in CI.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Data Engineer + Backend** with realistic approved fixtures.

## Exit gate

Same source twice yields same result without duplicate facts; 2021 becomes 12 monthly periods not daily; source file hash, sheet/row/cell for each accepted value.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
