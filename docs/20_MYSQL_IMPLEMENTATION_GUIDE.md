# 20 — MySQL 8.4 LTS Implementation Guide (IDPS V1)

## Decision
MySQL 8.4 LTS is the **approved** relational database for IDPS; PostgreSQL-specific DDL must not be used. `contracts/schema.sql` is a MySQL **reference design**, not applied Laravel migrations.

## Runtime configuration
- Engine: MySQL **8.4 LTS** with InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
- Laravel `.env`: `DB_CONNECTION=mysql`, `DB_PORT=3306`, separate DB credentials for app/migrations.
- `sql_mode`: strict (`STRICT_TRANS_TABLES`, no zero-date shortcuts).
- Generate ID using Laravel `HasUuids`; schema uses `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin` consistently. Do not rely on PostgreSQL `gen_random_uuid()`.
- Store operational timestamps `DATETIME(6)` **UTC**, display using `Africa/Tripoli`; maintain `business_date` as refinery local business date.
- Use `DECIMAL` for tonnes and other engineered quantities, not float.
- Store flexible metadata as MySQL `JSON`; defaults in MySQL use `DEFAULT (JSON_OBJECT())` / `DEFAULT (JSON_ARRAY())` where appropriate.
- Foreign keys must be explicit `FOREIGN KEY ... REFERENCES ...`; ensure matching charset/collation/length between FK and PK.

## Differences from PostgreSQL that require deliberate implementation
1. MySQL composite unique indexes treat `NULL` values as distinct. For `user_roles`, `production_targets`, `forecast_points` and monthly source lineage, use generated non-null scope keys or application guards **with concurrent-request tests**.
2. MySQL does not have the same partial-index syntax (`WHERE status='active'`). To enforce only one `active` model per `(product,granularity,horizon)`, index a nullable generated `active_scope_key`.
3. MySQL has no PostgreSQL native row-level security; use Laravel policies, scoped queries, authorization middleware and integration tests.
4. No `timestamptz`, `JSONB`, `CREATE EXTENSION`, `ON CONFLICT`, `NULLS NOT DISTINCT` syntax.
5. CHECK constraints are supported in MySQL 8.4. Keep matching Laravel and FastAPI validation, never rely solely on DB CHECK.
6. Triggers are not a substitute for approved record revisions: preserve original rows and record supersession in audit.
7. Queue/outbox at-least-once processing requires idempotency keys, unique constraints and retry-safe handlers.

## Migration implementation order
1. Schema + users/roles/master tables; 2. sources/imports/approvals; 3. targets + units; 4. feed/production/monthly observations; 5. events/downtime; 6. energy/losses/chemicals/KPIs; 7. dataset snapshots/model_versions/training_runs; 8. forecast/scenarios/points/evaluations/alerts/outbox.

## Essential automated integration tests
- `migrate:fresh` succeeds against **MySQL 8.4** in CI; no SQLite-only verification claims.
- Every defined foreign key prevents dangling references; rollback works on isolated test database.
- Two parallel requests creating the same nullable-scope record → exactly one wins.
- Two parallel transactions promote active models of the same `(product,granularity,horizon)` → exactly one active remains.
- UTC persistence and Africa/Tripoli rendering around midnight; dates compared by business date correctly.
- JSON metadata round-trip, large decimal precision, strict SQL mode, import replay idempotency.
- `lockForUpdate()` for scoped approvals and active model switches; test race outcomes.

## Status / limitations
Reference DDL converted and statically checked. **It has not been executed on a live MySQL server in this deliverable.** The implementation agent must translate it into tested Laravel migrations and validate SQL syntax/constraints on a MySQL 8.4 test instance before use.
