# 14 — Security, Deployment, Operations and Disaster Recovery

## Deployment principle

Private/intranet or approved infrastructure. Operational data may be confidential; no automatic third-party upload, online AI inference, analytics beacon or external hosted model unless policy explicitly permits. The reference architecture is conceptual until hosting/network/OT-security decisions are made.

## Environments

- `dev`: synthetic only, quick iteration.
- `staging`: anonymized/scrubbed or approved test samples, same migrations as production.
- `production`: approved network, least privilege, controlled migrations and monitoring.

## Authentication and authorization

- Role and unit-specific permission checks on every endpoint; MFA/SSO policy decision from facility IT.
- HTTPS-only; secure cookies/session or access tokens with rotation and expiry.
- Prevent privilege escalation, IDOR, bulk export beyond role, cross-unit access.
- Model registry and training endpoints require separated privileges.

## Hardening and data protection

- Encryption at rest where available, TLS in transit, secret management; no secrets in code, logs or document artifacts.
- CSV injection and Excel formula safety: treat imports as data, sanitize exported cell prefixes.
- Restrict upload MIME/size/type; scan where approved; preserve immutable original hash.
- SQL parameterization, ORM queries/scopes, validation and rate limiting.
- Input quality gates prevent poisoning via unreviewed operational values.
- Audited exports: actor/filters/count/time/reason, controlled retention.

## Backup & DR

- Define RPO/RTO through risk workshop; implement database backup + encrypted source/artifact backup, retention schedule, periodic test restores.
- Model registry and artifacts versioned/snapshotted to allow Champion rollback without re-training.
- Restore must preserve `forecast_run`/source provenance and audit chain.

## CI/CD pipeline

1. Lint/static analysis → unit tests → integration tests → SQL migration check → security SAST/dependency check.
2. Build frontend and API; AI contract validation; artifact integrity test.
3. Staging deployment → smoke tests/permission tests → UAT gate.
4. Backup and migration plan; controlled production release with rollback.
5. Monitor DB connection, queue lag, import backlog, AI health, job retry rate, data freshness.

## Network boundaries

Web → API only; API → DB/Queue/AI private network; AI training isolated with curated readonly approved data access. Any future DCS historian should use a separate, security-reviewed, **read-only** connector zone and must never have command channels from public web users.

## Recovery modes

- AI down: data capture/analytics continue, forecast status unavailable or flagged stale.
- DB down: API proper 503, no offline writes claimed successful.
- Queue lag: events saved, jobs pending and eventually retry without duplication.
- Source file unavailable: existing approved facts remain available with broken lineage alert.

## Operational runbooks required before launch

Restores, forced model rollback, failed import replay, stuck forecast job, event correction, leaked credential rotation, partial deployment rollback, capacity/latency monitoring, audit extraction, and incident escalation.
