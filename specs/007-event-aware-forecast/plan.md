# PLAN — 007. Dynamic Reforecast and Event Impact

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **003;006** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-070: Event-driven forecast orchestrator.** Listen to event outbox with stable idempotency and queue. Confirm acceptance: One run per unique version/trigger; retries safe.
2. **RF-071: Planned shutdown known-as-of features.** Apply plan version and hours only when approved before cut-off. Confirm acceptance: Historical run not changed by later schedule edit.
3. **RF-072: Trip reforecast trigger.** New trip event requests inference with updated unit status. Confirm acceptance: Old run unchanged; new parent_run_id and event_id.
4. **RF-073: ETA-null scenario engine.** Bounded best/central/worst with approved assumptions and explicit synthetic flag. Confirm acceptance: No fake precise restart probability; scenario choice audited.
5. **RF-074: ETA update and restart rerun.** Create revision-aware new forecast on ETA correction/restart. Confirm acceptance: Before/after captures new as_of without overwrites.
6. **RF-075: Event impact comparison rules.** Diff matched scope/horizon/plan, mark incomparable if changed. Confirm acceptance: Delta is estimate difference, not observed lost ton.
7. **RF-076: Event impact detail UI.** Timeline, model versions, assumptions, before-after charts. Confirm acceptance: Uncertain/missing state clearly signaled.
8. **RF-077: Alerts & acknowledgment.** Raise when risk threshold crossed, owner/ack with audit. Confirm acceptance: Alerts scoped/acknowledgment versioned.
9. **RF-078: Failure/fallback and latency tests.** Queue/AI down, no model, backdated event, overlap, unit derating. Confirm acceptance: No invented output and no event loss.
10. **RF-079: Operations incident demo UAT.** Walkthrough Trip→unknown ETA→update→restart→reforecast. Confirm acceptance: Owner approves text and semantics.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Backend + ML + Operations** with realistic approved fixtures.

## Exit gate

Events stored even when AI unavailable; all new predictions timestamped; ETA unknown yields labeled scenario outputs; no causal lost production claim.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
