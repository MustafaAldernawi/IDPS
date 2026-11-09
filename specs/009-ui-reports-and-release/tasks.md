# TASKS — 009. User Interface, Reporting, UAT and Release

> Mark complete only with tests and evidence; unchecked = not implemented.

- [ ] **RF-090 — Frontend shell and RTL design system** (high, 8 points; depends `RF-001;RF-002`)  
  **Implement:** Typed API client, navigation, tokens, language and local date/number formatting.  
  **Accept:** Accessible RTL desktop/mobile no decoration overload.
- [ ] **RF-091 — Role-specific dashboard** (critical, 8 points; depends `RF-050;RF-090`)  
  **Implement:** Three KPIs, plan risk, source freshness and linked alerts.  
  **Accept:** No fake live numbers or unmarked demo chart.
- [ ] **RF-092 — Production and planning forms** (high, 13 points; depends `RF-017;RF-031;RF-025;RF-090`)  
  **Implement:** Actual entry/approval, target/version and import preview UX.  
  **Accept:** Server-side authorization enforced beyond UI.
- [ ] **RF-093 — Engineering analytics pages** (high, 13 points; depends `RF-050;RF-048;RF-090`)  
  **Implement:** Actual-target, energy, loss, chemicals and trend drilldown.  
  **Accept:** Unit/source/period filters and N/A warnings.
- [ ] **RF-094 — Events and impact interface** (critical, 8 points; depends `RF-076;RF-090`)  
  **Implement:** Unplanned Trip timeline, ETA controls, scenario comparison.  
  **Accept:** See original forecast and new versions, unknown ETA clearly.
- [ ] **RF-095 — AI forecast and model governance UI** (high, 8 points; depends `RF-064;RF-087;RF-090`)  
  **Implement:** Per-product forecast/quality/horizon/version/metrics with no model fallback.  
  **Accept:** V1 Training Studio is operational; ML readiness and baseline status are distinguished truthfully.
- [ ] **RF-096 — Report templates and authorized exports** (high, 8 points; depends `RF-051;RF-090`)  
  **Implement:** Monthly production, loss, energy, chemical, AI, exceptions; source lineage.  
  **Accept:** Export access controlled and audit recorded.
- [ ] **RF-097 — Frontend accessibility and responsive QA** (high, 5 points; depends `RF-091;RF-094;RF-095`)  
  **Implement:** RTL, mobile, charts textual alternatives, focus and empty/error states.  
  **Accept:** Screen-level accessibility checks and screenshot evidence.
- [ ] **RF-098 — API and DB performance hardening** (high, 8 points; depends `RF-092;RF-095`)  
  **Implement:** Slow queries, pagination, export limits, reforecast job retries.  
  **Accept:** Measured timings, no blocking event write.
- [ ] **RF-099 — Security penetration and permission tests** (critical, 8 points; depends `RF-098`)  
  **Implement:** Auth/IDOR/upload/formula injection/export/secret leak risk.  
  **Accept:** Critical findings fixed, policies audited.
- [ ] **RF-100 — Backup and restore drills** (critical, 5 points; depends `RF-099`)  
  **Implement:** Database/files/model artifacts consistency, RPO/RTO signoff.  
  **Accept:** Documented restore verified and rollback checked.
- [ ] **RF-101 — Cross-department UAT** (critical, 8 points; depends `RF-096;RF-097;RF-100`)  
  **Implement:** Scenario: plan→actual→KPI→Trip→ETA→reforecast→restart→report.  
  **Accept:** Role owners sign tracked UAT outcomes.
- [ ] **RF-102 — Deploy staging and controlled pilot** (critical, 8 points; depends `RF-101`)  
  **Implement:** Deployment checklist, monitoring, incident support, no DCS writes.  
  **Accept:** Management and IT approve pilot release.
- [ ] **RF-103 — Operational runbook and team handover** (high, 5 points; depends `RF-102`)  
  **Implement:** Training material, troubleshooting, logs, model rollback, data import corrections.  
  **Accept:** Trained nominated owners and documented contact/escalation.
- [ ] **RF-104 — End-to-end traceability and docs** (high, 5 points; depends `RF-101`)  
  **Implement:** Update feature docs, ADRs, diagrams, test-to-FR matrix.  
  **Accept:** Each FR has relevant test or explicit blocked status.
- [ ] **RF-105 — Scope-2 backlog grooming** (low, 3 points; depends `RF-103`)  
  **Implement:** Separate future OT read-only/Predictive Maintenance/optimization proposals.  
  **Accept:** No future feature silently treated as MVP done.

## Signoff evidence

- CI/test run reference: __________
- Migration/rollback: __________
- Source/fixture provenance: __________
- Reviewer decision: __________
- Remaining blockers: __________
