# 26 — Verified deliverables vs not-yet-implemented features

## Available in package, as actual files

- Full markdown specification, 9 epics (spec/plan/tasks), MySQL SQL and OpenAPI draft, sample templates.
- True source documents in `data/raw-sources` with per-file SHA256 and provenance mapping.
- Provisional 36 × 35 monthly dataset, quality flags, baseline testing extracts (not approved operational data).
- Python research code, tests and archived candidate models; status `can_deploy=false`.
- 119-ticket planning backlog and v1.2 tracker (not work completion).

## Not built or not verified here

- Running Laravel backend, Vue frontend, real FastAPI process, database migrations installed, authenticated reviewer UI, staging/import API, model registry deployed, async jobs or training studio in the web app.
- Production MySQL database created or validated by a running MySQL server (SQL is blueprint).
- Live refinery integration or DCS/SCADA integration; no automatic shutdown control.
- Production-approved prediction model or scientifically validated accuracy for any future day/month.

## First milestone acceptance

Local app boot + MySQL migration + import workbook in staging + engineering review/approval + 36 monthly rows exposed through authorization-protected API + no duplicates + traceable reconciliation issues. The first ML integration milestone follows that, with an async research-mode train/eval and safety gate.
