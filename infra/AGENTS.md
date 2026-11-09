# Infrastructure Agent

- Read root AGENTS and `docs/14_SECURITY_DEPLOYMENT.md`.
- Prefer private/internal hosting approved by refinery; never expose operational data to unapproved third-party APIs.
- Use environment-separated credentials; database/backups encrypted, tested restore, least privilege service accounts.
- AI inference failure must not prevent data capture or disable dashboards: degraded mode uses last approved forecast flagged stale or no forecast.
- No OPC-UA/Modbus/DCS writes. Future connectors restricted to allowlisted read-only collection endpoints after security assessment.
- Document backup/restore, log retention, key rotation, audit retention, runbooks, rollback, disaster recovery tests.
