# Database

## Engine

Production target: **PostgreSQL via Supabase**. The same migrations also run
on MySQL/MariaDB (self-hosted installs) and SQLite (local dev/tests) — see
`config/database.php`. Default connection is now `pgsql`
(`DB_CONNECTION=pgsql`).

Postgres-specific notes:
- `sslmode` defaults to `require` (`config/database.php` /
  `DB_SSLMODE`) — Supabase requires TLS for external connections.
- A handful of analytics queries used MySQL's `DATEDIFF()`/`NOW()`
  functions directly (dashboard/report "avg days in stage" widgets). These
  now go through `App\Services\SqlPortability::dateDiffDaysExpr()`, which
  picks the right SQL per driver (`DATEDIFF` on MySQL, `EXTRACT(EPOCH ...)`
  on Postgres, `julianday()` on SQLite) instead of assuming MySQL
  everywhere.
- Older migrations use `$table->enum(...)`; a later migration
  (`2026_03_16_100001_convert_stage_status_enums_to_varchar.php`) already
  converts the columns that needed tenant-customizable values (lead status,
  deal stage) to `varchar`, which is what actually matters for Postgres
  portability — Laravel's `enum()` itself works on Postgres too (compiled to
  a `varchar` + `CHECK` constraint), so nothing further was required there.
- `whereJsonContains` (used for `buyers.preferred_property_types`) works
  against Postgres `json` columns via Laravel's query grammar; no schema
  change was needed.

## Core tables (inherited from InsulaCRM, unchanged)

`tenants`, `users`, `roles`, `permissions` + `role_permission`, `leads`,
`properties`, `buyers`, `deals`, `activities`, `tasks`, `notifications`,
`integrations`, `webhooks`, `audit_log`, `api_logs`, `error_logs`,
`custom_field_definitions`, plus feature-specific tables (`campaigns`,
`workflows`, `showings`, `open_houses`, `deal_offers`,
`transaction_checklists`, etc.) — see `database/migrations/` for the full,
already-substantial schema. Every one of these carries `tenant_id` and is
protected by `TenantScope` (see `ARCHITECTURE.md`).

## New in this fork

### `api_credentials`
Replaces the single `tenants.api_key` column as the primary way to
authenticate API/embed traffic (that column is kept, deprecated, for
backward compatibility — see `ApiKeyMiddleware`).

| Column | Notes |
|---|---|
| `tenant_id` | owner |
| `name` | e.g. "Production Website" |
| `type` | `full` (whole REST API) or `embed` (lead creation only) |
| `key_prefix` | public, unique, used to look the credential up |
| `hashed_secret` | SHA-256 of the secret half; the plaintext is never stored |
| `last_used_at`, `revoked_at` | lifecycle |

A credential's plaintext token (`{prefix}.{secret}`) is shown exactly once,
at creation time (Settings > Integrations, or the platform admin "onboard
client" flow) — see `App\Services\ApiCredentialService::generate()`.

### `users.is_platform_admin`
Boolean flag gating `/platform-admin` (see `ARCHITECTURE.md`). Does not
change `tenant_id` (still required/non-null) — a platform admin is simply a
user, in some tenant, additionally flagged as operating the platform.

## Why the old `tenants.api_key` was a problem

`tenants.api_key` was a single, plaintext, permanent secret used for **both**
the full read/write REST API *and* as the URL segment of the public,
unauthenticated embeddable lead form (`/forms/{api_key}`). Anyone who viewed
that public form's URL or page source obtained a credential that could read
every lead/deal/buyer/property for that tenant. `api_credentials` fixes this
by separating "can create a lead from a public web page" (`embed` type) from
"can read/write the full API" (`full` type), and by making credentials
named, hashed, and individually revocable.

## Migrations / rollback

Standard Laravel migrations (`php artisan migrate`). No manual schema edits
in production — every change must be a migration, so it's reproducible
across environments and re-runnable during onboarding of new
self-hosted/staging copies. To roll back a specific batch:
`php artisan migrate:rollback --step=1`. Always run
`php artisan migrate --pretend` against a fresh Supabase branch/staging
project before applying a new migration to production (see `DEPLOYMENT.md`
for the Supabase backup/branching workflow).
