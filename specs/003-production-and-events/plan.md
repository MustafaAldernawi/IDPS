# PLAN — 003. Production Records, Unit States, Trips and Restart

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **001;002;005** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-031: Daily production record endpoint/form.** Unit+date+shift+product+stage+quantity+source+status. Confirm acceptance: Only approved values in analytical read; draft/approve lifecycle.
2. **RF-032: Crude feed records.** Crude grade/facility unit daily/shift data with trace. Confirm acceptance: Valid feed units and no unknown crude labels.
3. **RF-033: Unit status intervals.** Running/trip/startup/maintenance/derated timeline events. Confirm acceptance: No invalid negative windows; timezone tested.
4. **RF-034: Event intake & idempotency.** Planned/unplanned event typed intake with audit + outbox. Confirm acceptance: Duplicate event returns same ID; persisted before worker.
5. **RF-035: ETA revision workflow.** Expected restart nullable/versioned, optimistic lock and permissions. Confirm acceptance: Unknown ETA valid, changes create event revision.
6. **RF-036: Restart/close workflow.** Actual restart timestamp verification and state transitions. Confirm acceptance: Prior forecasts untouched; actual downtime correct.
7. **RF-037: Downtime union calculator.** Compute downtime per unit/period including overlaps and derating fraction. Confirm acceptance: Overlapping intervals counted once, unit/day clipped.
8. **RF-038: Operational event UI.** Timeline, filter unit/source/class, ETA updates and approvals. Confirm acceptance: Manager sees verified vs pending and event history.
9. **RF-039: Energy/loss/chem actual capture.** Validated capture with units, source and approval. Confirm acceptance: No premature SEC GJ/t if conversion factors missing.
10. **RF-040: Realtime trigger semantics.** Publish revision-aware request for analytics/reforecast without blocking write. Confirm acceptance: Trip saved if AI down, job retries safely.
11. **RF-041: Event state & overlap tests.** Cover trip/startup/ETA/restart/cancel/retro date/overlap. Confirm acceptance: Acceptance cases T-EVT-01..08 all pass.
12. **RF-042: Production/maintenance UAT.** Verify sample workflows with domain engineers. Confirm acceptance: Owner signs event fields and state transitions.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Backend + Production** with realistic approved fixtures.

## Exit gate

Approved facts are versioned, event timeline immutable in history, calculations do not double count overlap, incidents saved even when AI is unavailable.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
