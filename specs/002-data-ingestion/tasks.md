# TASKS — 002. Historical Source Ingestion and Data Quality

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-011 — Source mapping workshop** (critical, 5 points; depends `RF-006`)  
  **Implement:** Document approved source matrix, names, units, source granularity and product stage.  
  **Accept:** Process owner signs taxonomy and conflict list.
- [ ] **RF-012 — 2021 Excel monthly mapper** (critical, 8 points; depends `RF-011`)  
  **Implement:** Extract target and actual by month using workbook sheet/row/cell lineage.  
  **Accept:** 12 months per mapped metric, no artificial daily expansion.
- [ ] **RF-013 — 2025 scanned report reviewed entry** (high, 8 points; depends `RF-011`)  
  **Implement:** Design human-verified transcription workflow for key report tables and page references.  
  **Accept:** Two-person review and page provenance; no blind OCR assumption.
- [ ] **RF-014 — Upload fingerprint staging** (critical, 8 points; depends `RF-004`)  
  **Implement:** Source_file sha256, upload safety, stage batch/items before approval.  
  **Accept:** Original immutable and duplicate file idempotent.
- [ ] **RF-015 — Mapping dictionary** (critical, 5 points; depends `RF-011;RF-014`)  
  **Implement:** Versioned Arabic alias to canonical metric/product/unit, unresolved queue.  
  **Accept:** Unknown alias cannot auto map and approve.
- [ ] **RF-016 — Data validation engine** (critical, 8 points; depends `RF-015`)  
  **Implement:** Dates, negatives, null/zero, incompatible unit, totals and range warnings.  
  **Accept:** Reject reason includes original cell/source and rule id.
- [ ] **RF-017 — Preview and approval flow** (critical, 8 points; depends `RF-014;RF-016`)  
  **Implement:** Import preview, reviewer acceptance, canonical upsert with revisions.  
  **Accept:** No partial approvals and errors remain visible.
- [ ] **RF-018 — Reconciliation cases** (high, 5 points; depends `RF-016`)  
  **Implement:** Preserve source disagreements and open human review cases; define tolerance setting.  
  **Accept:** 1 t component mismatch flagged without altering values.
- [ ] **RF-019 — Daily input quality profiler** (high, 8 points; depends `RF-017`)  
  **Implement:** Daily coverage, missingness, source lag, shutdown class coverage, no daily pseudo records.  
  **Accept:** Readiness report correctly fails monthly-only data.
- [ ] **RF-020 — Data import regression suite** (high, 5 points; depends `RF-012;RF-017`)  
  **Implement:** Round trips, duplication, Arabic numerals, formula sanitation, source revisions.  
  **Accept:** All sample fixtures pass/fail expected cases in CI.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________

### v1.2 added actual-data tickets

Implement new tickets `RF-106..109` for existing 36-month real data, QA and canonical MySQL importer.

- [ ] **RF-106 — Inventory raw files SHA and source register** (P0)
  - Dependencies: RF-001
  - Acceptance: All 12 source hashes matched and immutable metadata stored.

- [ ] **RF-107 — Map 2021–2023 monthly Excel to 35-column canonical dataset** (P2)
  - Dependencies: RF-106;RF-011
  - Acceptance: 36 unique months and documented cells, unit, period, stage.

- [ ] **RF-108 — Reconcile 2023 semi-actual and monthly sum anomalies** (P2)
  - Dependencies: RF-107
  - Acceptance: All discrepancies flagged; no silent correction; reviewer decisions recorded.

- [ ] **RF-109 — MySQL monthly staging importer and review workflow** (P2)
  - Dependencies: RF-004;RF-107
  - Acceptance: Idempotent import/revisions, approval audit, real MySQL tests.

- [ ] **RF-117 — Add future monthly actuals while preserving gaps and revisions** (P2)
  - Dependencies: RF-109
  - Acceptance: Can stage 2026 without fabricating 2024/2025 and no duplicate rows.
