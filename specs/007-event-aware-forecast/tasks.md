# TASKS — 007. Dynamic Reforecast and Event Impact

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-070 — Event-driven forecast orchestrator** (critical, 8 points; depends `RF-040;RF-064`)  
  **Implement:** Listen to event outbox with stable idempotency and queue.  
  **Accept:** One run per unique version/trigger; retries safe.
- [ ] **RF-071 — Planned shutdown known-as-of features** (critical, 8 points; depends `RF-024;RF-056`)  
  **Implement:** Apply plan version and hours only when approved before cut-off.  
  **Accept:** Historical run not changed by later schedule edit.
- [ ] **RF-072 — Trip reforecast trigger** (critical, 5 points; depends `RF-070`)  
  **Implement:** New trip event requests inference with updated unit status.  
  **Accept:** Old run unchanged; new parent_run_id and event_id.
- [ ] **RF-073 — ETA-null scenario engine** (critical, 8 points; depends `RF-070`)  
  **Implement:** Bounded best/central/worst with approved assumptions and explicit synthetic flag.  
  **Accept:** No fake precise restart probability; scenario choice audited.
- [ ] **RF-074 — ETA update and restart rerun** (critical, 8 points; depends `RF-035;RF-036;RF-072`)  
  **Implement:** Create revision-aware new forecast on ETA correction/restart.  
  **Accept:** Before/after captures new as_of without overwrites.
- [ ] **RF-075 — Event impact comparison rules** (critical, 5 points; depends `RF-074`)  
  **Implement:** Diff matched scope/horizon/plan, mark incomparable if changed.  
  **Accept:** Delta is estimate difference, not observed lost ton.
- [ ] **RF-076 — Event impact detail UI** (high, 8 points; depends `RF-075`)  
  **Implement:** Timeline, model versions, assumptions, before-after charts.  
  **Accept:** Uncertain/missing state clearly signaled.
- [ ] **RF-077 — Alerts & acknowledgment** (high, 5 points; depends `RF-066;RF-075`)  
  **Implement:** Raise when risk threshold crossed, owner/ack with audit.  
  **Accept:** Alerts scoped/acknowledgment versioned.
- [ ] **RF-078 — Failure/fallback and latency tests** (critical, 8 points; depends `RF-073;RF-074`)  
  **Implement:** Queue/AI down, no model, backdated event, overlap, unit derating.  
  **Accept:** No invented output and no event loss.
- [ ] **RF-079 — Operations incident demo UAT** (critical, 3 points; depends `RF-076;RF-078`)  
  **Implement:** Walkthrough Trip→unknown ETA→update→restart→reforecast.  
  **Accept:** Owner approves text and semantics.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________

### v1.2 added actual-data tickets

Implement `RF-119` with event-aware monthly scenario without invented downtime hours.

- [ ] **RF-116 — Monthly forecast API with actual-target comparison** (P7)
  - Dependencies: RF-113;RF-115
  - Acceptance: Scope/as_of/source version correct, fallback clearly labeled.

- [ ] **RF-119 — Event-aware monthly reforecast unknown ETA scenarios** (P7)
  - Dependencies: RF-116;RF-033
  - Acceptance: Trip/restart immutable run and no invented shutdown hours.
