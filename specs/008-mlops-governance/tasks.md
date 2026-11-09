# TASKS — 008. Retraining, Monitoring and Model Governance

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-080 — Training dataset registry** (critical, 5 points; depends `RF-055`)  
  **Implement:** Store snapshot sha/date/coverage/feature version and lineage.  
  **Accept:** Every model version references exact approved snapshot.
- [ ] **RF-081 — Model registry and signed metadata** (critical, 8 points; depends `RF-064;RF-080`)  
  **Implement:** Artifact integrity hashes/schema/model state transitions.  
  **Accept:** No artifact serving if checksum or feature schema incompatible.
- [ ] **RF-082 — Monthly retraining scheduler** (high, 5 points; depends `RF-081`)  
  **Implement:** Only approved matured labels, skip insufficient dataset.  
  **Accept:** Job not triggered for just one unapproved row.
- [ ] **RF-083 — Champion challenger evaluation** (critical, 8 points; depends `RF-082;RF-062`)  
  **Implement:** Compare candidate and champion on equivalent rolling windows/normal/shutdown regimes.  
  **Accept:** Worse/inconclusive candidate rejected.
- [ ] **RF-084 — Manual promotion & rollback** (critical, 5 points; depends `RF-083`)  
  **Implement:** Approval signature and instant pointer rollback with smoke tests.  
  **Accept:** Past forecasts retain original version ref.
- [ ] **RF-085 — Prediction actual evaluation** (critical, 8 points; depends `RF-064;RF-031`)  
  **Implement:** Join approved actual after horizon, calculate error by product.  
  **Accept:** Forecast records immutable; evaluations separate and versioned.
- [ ] **RF-086 — Data & model drift monitoring** (high, 5 points; depends `RF-085`)  
  **Implement:** Population shift/error alerts with minimum sample count.  
  **Accept:** No claim statistically meaningful on 1–2 records.
- [ ] **RF-087 — Model governance dashboard** (medium, 5 points; depends `RF-084;RF-086`)  
  **Implement:** Versions, metrics, gate status, drift, promotions and rollback history.  
  **Accept:** Only ML operator sees training; manager can approve.
- [ ] **RF-088 — ML pipeline failure drills** (critical, 5 points; depends `RF-084;RF-086`)  
  **Implement:** Bad hash, absent champion, no new data, failed upload, GPU unavailable.  
  **Accept:** Safe fallback and signed event trail.
- [ ] **RF-089 — MLOps owner signoff** (high, 3 points; depends `RF-088`)  
  **Implement:** Review ongoing cadence, alarm thresholds, traceability.  
  **Accept:** Owner accepts gating and rollback.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________

### v1.2 added actual-data tickets

Implement `RF-117..118`: approved new months, versioned retraining, champion/challenger.

- [ ] **RF-115 — Model registry candidate champion and ML_NOT_READY gates** (P8)
  - Dependencies: RF-114
  - Acceptance: No auto promotion, rollback and audit, research only blocked.

- [ ] **RF-118 — Approved-data retraining challenger loop** (P8)
  - Dependencies: RF-115;RF-117
  - Acceptance: Retains champion if no validated gain; records reasons and models.
