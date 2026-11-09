# TASKS — 005. Planning, Target Versions and Optional Quality

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-021 — Approve product/stream catalog** (critical, 5 points; depends `RF-011`)  
  **Implement:** Differentiate refinery streams, final gasoline blends and MTBE additives.  
  **Accept:** Owner-signed hierarchy; prevent aggregate double count.
- [ ] **RF-022 — Versioned target plans** (critical, 8 points; depends `RF-006;RF-005`)  
  **Implement:** Schema/forms for year/month/unit/product with draft and controlled activation.  
  **Accept:** Same period cannot have conflicting active target.
- [ ] **RF-023 — Plan approval and supersession** (critical, 5 points; depends `RF-022`)  
  **Implement:** Submit/approve/reject and retain historical plan versions/known_at.  
  **Accept:** Old forecast references historical plan version.
- [ ] **RF-024 — Planned outage schedule** (critical, 8 points; depends `RF-023`)  
  **Implement:** Calendar planned start/end unit/class and approval with known_at.  
  **Accept:** Only pre-known outages enter earlier as_of feature.
- [ ] **RF-025 — Production plan dashboard** (high, 5 points; depends `RF-022`)  
  **Implement:** Target by month/product/unit, upload/mapping, signed approval.  
  **Accept:** Planners can filter, compare and see version state.
- [ ] **RF-026 — Chemical targets and variance** (medium, 5 points; depends `RF-017`)  
  **Implement:** Set chemical normative target by unit/month and calculate validated deviation.  
  **Accept:** Unit mismatch blocked; separate target and actual.
- [ ] **RF-027 — Quality parameter catalog optional** (medium, 3 points; depends `RF-021`)  
  **Implement:** Lab parameters and specification limits proposed with approval.  
  **Accept:** Spec/version/data source displayed, not inferred.
- [ ] **RF-028 — Blending batch model optional** (medium, 8 points; depends `RF-021`)  
  **Implement:** Link reformate/light naphtha/MTBE to batch stage and source.  
  **Accept:** No silently mixing blend output with raw gasoline stream.
- [ ] **RF-029 — Planning temporal leakage tests** (critical, 5 points; depends `RF-024`)  
  **Implement:** Retroactive plan editing and expected ETA future availability tests.  
  **Accept:** Historical snapshot unchanged by later revisions.
- [ ] **RF-030 — Planning UAT and signoff** (high, 3 points; depends `RF-025;RF-029`)  
  **Implement:** Demonstrate approval, changes and export of plan.  
  **Accept:** Planner/manager approve workflow; decisions updated.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________
