# PLAN — 005. Planning, Target Versions and Optional Quality

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **001; 002** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-021: Approve product/stream catalog.** Differentiate refinery streams, final gasoline blends and MTBE additives. Confirm acceptance: Owner-signed hierarchy; prevent aggregate double count.
2. **RF-022: Versioned target plans.** Schema/forms for year/month/unit/product with draft and controlled activation. Confirm acceptance: Same period cannot have conflicting active target.
3. **RF-023: Plan approval and supersession.** Submit/approve/reject and retain historical plan versions/known_at. Confirm acceptance: Old forecast references historical plan version.
4. **RF-024: Planned outage schedule.** Calendar planned start/end unit/class and approval with known_at. Confirm acceptance: Only pre-known outages enter earlier as_of feature.
5. **RF-025: Production plan dashboard.** Target by month/product/unit, upload/mapping, signed approval. Confirm acceptance: Planners can filter, compare and see version state.
6. **RF-026: Chemical targets and variance.** Set chemical normative target by unit/month and calculate validated deviation. Confirm acceptance: Unit mismatch blocked; separate target and actual.
7. **RF-027: Quality parameter catalog optional.** Lab parameters and specification limits proposed with approval. Confirm acceptance: Spec/version/data source displayed, not inferred.
8. **RF-028: Blending batch model optional.** Link reformate/light naphtha/MTBE to batch stage and source. Confirm acceptance: No silently mixing blend output with raw gasoline stream.
9. **RF-029: Planning temporal leakage tests.** Retroactive plan editing and expected ETA future availability tests. Confirm acceptance: Historical snapshot unchanged by later revisions.
10. **RF-030: Planning UAT and signoff.** Demonstrate approval, changes and export of plan. Confirm acceptance: Planner/manager approve workflow; decisions updated.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Backend + Planning + Engineering** with realistic approved fixtures.

## Exit gate

Unique approved plan per scope/period, plan version/time recorded and target-linked forecasts remain reproducible.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
