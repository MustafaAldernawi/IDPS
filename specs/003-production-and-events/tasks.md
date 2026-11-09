# TASKS — 003. Production Records, Unit States, Trips and Restart

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-031 — Daily production record endpoint/form** (critical, 8 points; depends `RF-006;RF-005`)  
  **Implement:** Unit+date+shift+product+stage+quantity+source+status.  
  **Accept:** Only approved values in analytical read; draft/approve lifecycle.
- [ ] **RF-032 — Crude feed records** (high, 5 points; depends `RF-006;RF-005`)  
  **Implement:** Crude grade/facility unit daily/shift data with trace.  
  **Accept:** Valid feed units and no unknown crude labels.
- [ ] **RF-033 — Unit status intervals** (critical, 8 points; depends `RF-006`)  
  **Implement:** Running/trip/startup/maintenance/derated timeline events.  
  **Accept:** No invalid negative windows; timezone tested.
- [ ] **RF-034 — Event intake & idempotency** (critical, 8 points; depends `RF-007;RF-033`)  
  **Implement:** Planned/unplanned event typed intake with audit + outbox.  
  **Accept:** Duplicate event returns same ID; persisted before worker.
- [ ] **RF-035 — ETA revision workflow** (critical, 5 points; depends `RF-034`)  
  **Implement:** Expected restart nullable/versioned, optimistic lock and permissions.  
  **Accept:** Unknown ETA valid, changes create event revision.
- [ ] **RF-036 — Restart/close workflow** (critical, 5 points; depends `RF-034;RF-035`)  
  **Implement:** Actual restart timestamp verification and state transitions.  
  **Accept:** Prior forecasts untouched; actual downtime correct.
- [ ] **RF-037 — Downtime union calculator** (critical, 8 points; depends `RF-033;RF-034`)  
  **Implement:** Compute downtime per unit/period including overlaps and derating fraction.  
  **Accept:** Overlapping intervals counted once, unit/day clipped.
- [ ] **RF-038 — Operational event UI** (high, 8 points; depends `RF-034;RF-035`)  
  **Implement:** Timeline, filter unit/source/class, ETA updates and approvals.  
  **Accept:** Manager sees verified vs pending and event history.
- [ ] **RF-039 — Energy/loss/chem actual capture** (high, 8 points; depends `RF-031`)  
  **Implement:** Validated capture with units, source and approval.  
  **Accept:** No premature SEC GJ/t if conversion factors missing.
- [ ] **RF-040 — Realtime trigger semantics** (critical, 5 points; depends `RF-007;RF-034`)  
  **Implement:** Publish revision-aware request for analytics/reforecast without blocking write.  
  **Accept:** Trip saved if AI down, job retries safely.
- [ ] **RF-041 — Event state & overlap tests** (critical, 8 points; depends `RF-036;RF-037`)  
  **Implement:** Cover trip/startup/ETA/restart/cancel/retro date/overlap.  
  **Accept:** Acceptance cases T-EVT-01..08 all pass.
- [ ] **RF-042 — Production/maintenance UAT** (high, 3 points; depends `RF-031;RF-041`)  
  **Implement:** Verify sample workflows with domain engineers.  
  **Accept:** Owner signs event fields and state transitions.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________
