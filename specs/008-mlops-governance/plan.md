# PLAN — 008. Retraining, Monitoring and Model Governance

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **006;007** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-080: Training dataset registry.** Store snapshot sha/date/coverage/feature version and lineage. Confirm acceptance: Every model version references exact approved snapshot.
2. **RF-081: Model registry and signed metadata.** Artifact integrity hashes/schema/model state transitions. Confirm acceptance: No artifact serving if checksum or feature schema incompatible.
3. **RF-082: Monthly retraining scheduler.** Only approved matured labels, skip insufficient dataset. Confirm acceptance: Job not triggered for just one unapproved row.
4. **RF-083: Champion challenger evaluation.** Compare candidate and champion on equivalent rolling windows/normal/shutdown regimes. Confirm acceptance: Worse/inconclusive candidate rejected.
5. **RF-084: Manual promotion & rollback.** Approval signature and instant pointer rollback with smoke tests. Confirm acceptance: Past forecasts retain original version ref.
6. **RF-085: Prediction actual evaluation.** Join approved actual after horizon, calculate error by product. Confirm acceptance: Forecast records immutable; evaluations separate and versioned.
7. **RF-086: Data & model drift monitoring.** Population shift/error alerts with minimum sample count. Confirm acceptance: No claim statistically meaningful on 1–2 records.
8. **RF-087: Model governance dashboard.** Versions, metrics, gate status, drift, promotions and rollback history. Confirm acceptance: Only ML operator sees training; manager can approve.
9. **RF-088: ML pipeline failure drills.** Bad hash, absent champion, no new data, failed upload, GPU unavailable. Confirm acceptance: Safe fallback and signed event trail.
10. **RF-089: MLOps owner signoff.** Review ongoing cadence, alarm thresholds, traceability. Confirm acceptance: Owner accepts gating and rollback.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **ML Engineer + Operations** with realistic approved fixtures.

## Exit gate

Version/metrics/snapshot complete; rejected candidate stays archived and champion unchanged; real test evidence before promotion.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
