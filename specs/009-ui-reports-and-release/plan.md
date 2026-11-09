# PLAN — 009. User Interface, Reporting, UAT and Release

## Before coding

1. Read root `AGENTS.md`, `SPEC.md`, linked docs and directory AGENTS.md.
2. Check existing code and migrations; report a plan before modifying anything.
3. Confirm dependency: **001–008 with conditional AI readiness** and unresolved domain questions.
4. Agree test fixtures, expected outputs and exact data scopes with owner.

## Execution sequence

1. **RF-090: Frontend shell and RTL design system.** Typed API client, navigation, tokens, language and local date/number formatting. Confirm acceptance: Accessible RTL desktop/mobile no decoration overload.
2. **RF-091: Role-specific dashboard.** Three KPIs, plan risk, source freshness and linked alerts. Confirm acceptance: No fake live numbers or unmarked demo chart.
3. **RF-092: Production and planning forms.** Actual entry/approval, target/version and import preview UX. Confirm acceptance: Server-side authorization enforced beyond UI.
4. **RF-093: Engineering analytics pages.** Actual-target, energy, loss, chemicals and trend drilldown. Confirm acceptance: Unit/source/period filters and N/A warnings.
5. **RF-094: Events and impact interface.** Unplanned Trip timeline, ETA controls, scenario comparison. Confirm acceptance: See original forecast and new versions, unknown ETA clearly.
6. **RF-095: AI forecast and model governance UI.** Per-product forecast/quality/horizon/version/metrics with no model fallback. Confirm acceptance: V1 Training Studio is operational; ML readiness and baseline status are distinguished truthfully.
7. **RF-096: Report templates and authorized exports.** Monthly production, loss, energy, chemical, AI, exceptions; source lineage. Confirm acceptance: Export access controlled and audit recorded.
8. **RF-097: Frontend accessibility and responsive QA.** RTL, mobile, charts textual alternatives, focus and empty/error states. Confirm acceptance: Screen-level accessibility checks and screenshot evidence.
9. **RF-098: API and DB performance hardening.** Slow queries, pagination, export limits, reforecast job retries. Confirm acceptance: Measured timings, no blocking event write.
10. **RF-099: Security penetration and permission tests.** Auth/IDOR/upload/formula injection/export/secret leak risk. Confirm acceptance: Critical findings fixed, policies audited.
11. **RF-100: Backup and restore drills.** Database/files/model artifacts consistency, RPO/RTO signoff. Confirm acceptance: Documented restore verified and rollback checked.
12. **RF-101: Cross-department UAT.** Scenario: plan→actual→KPI→Trip→ETA→reforecast→restart→report. Confirm acceptance: Role owners sign tracked UAT outcomes.
13. **RF-102: Deploy staging and controlled pilot.** Deployment checklist, monitoring, incident support, no DCS writes. Confirm acceptance: Management and IT approve pilot release.
14. **RF-103: Operational runbook and team handover.** Training material, troubleshooting, logs, model rollback, data import corrections. Confirm acceptance: Trained nominated owners and documented contact/escalation.
15. **RF-104: End-to-end traceability and docs.** Update feature docs, ADRs, diagrams, test-to-FR matrix. Confirm acceptance: Each FR has relevant test or explicit blocked status.
16. **RF-105: Scope-2 backlog grooming.** Separate future OT read-only/Predictive Maintenance/optimization proposals. Confirm acceptance: No future feature silently treated as MVP done.


## Integration and deployment requirements

- Keep OpenAPI/DB schema/feature docs consistent; note changes in ADR log.
- Run unit + integration + permissions + negative-path tests.
- Implement idempotent retry where the queue or external services are involved.
- Demonstrate the supported journey to **Frontend + QA + IT** with realistic approved fixtures.

## Exit gate

Core UAT passes on source-fixtured scenarios, mobile RTL validated, backup restore tested, production deployment only with signed management authorization.

Archive test command outputs, migration plan, known limitations, screenshots when UI present, and reviewer sign-off. If blockers remain, mark phase partial rather than complete.
