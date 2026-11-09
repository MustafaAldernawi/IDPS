# Local MySQL 8.4 Dev Service — IDPS

**Development only.** Production hosting, network ACL, backups and secrets need separate IT approval.

Create a local untracked shell environment (example):

```bash
export MYSQL_ROOT_PASSWORD='YOUR_LOCAL_ROOT_PASSWORD'
export MYSQL_USER='idps_app'
export MYSQL_PASSWORD='YOUR_LOCAL_APP_PASSWORD'
export MYSQL_DATABASE='idps_db'
docker compose -f infra/docker-compose.mysql.yml up -d
```

Then set `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=idps_db`, `DB_USERNAME=idps_app`, `DB_PASSWORD=<local secret>` in a local untracked Laravel `.env`.

Run `php artisan migrate:fresh` **only on a disposable testing DB** after migrations are implemented, never on production. This handoff has no runtime application code yet and the reference SQL has not been executed on a live MySQL server.
