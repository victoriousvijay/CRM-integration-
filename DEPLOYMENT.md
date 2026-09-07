# Deployment

## Vercel + Supabase: what actually works

Laravel is not a natural fit for Vercel — Vercel runs stateless, short-lived
serverless functions with a **read-only filesystem** (except `/tmp`) and
**no persistent process**, while Laravel expects a long-running PHP-FPM
process plus separate `queue:work` and `schedule:work` daemons. This repo
makes the web/API layer deployable on Vercel via the community
[`vercel-php`](https://github.com/juicyllama/vercel-php) runtime
(`vercel.json`, `api/index.php`), with the pieces Vercel genuinely can't run
adapted rather than faked:

| Component | On a normal server | On Vercel (this repo) |
|---|---|---|
| Web/API requests | `php-fpm` / `artisan serve` | `vercel-php` serverless function (`api/index.php`) |
| Sessions | file or database | **database** (`SESSION_DRIVER=database`) — a serverless function can't share a local file across invocations |
| Cache | file or redis | **database** (`CACHE_STORE=database`) |
| Queue | `artisan queue:work` daemon | **database** queue, drained by the cron endpoint below (`QUEUE_CONNECTION=database`) |
| Scheduler | `artisan schedule:work` / cron | Vercel Cron hits `GET /internal/cron/schedule` (see `vercel.json` `crons`), which runs due schedule events and `queue:work --stop-when-empty` for one pass |
| File storage (logos, documents, imports) | local disk | **S3** (or Supabase Storage, which is S3-compatible) — `FILESYSTEM_DISK=s3` |
| Logs | `storage/logs/laravel.log` | **stderr** (`LOG_CHANNEL=stderr`) — shows up in Vercel's runtime logs |
| Laravel's own scratch files (compiled views, framework locks) | `storage/framework/*` | redirected to a per-invocation `/tmp` directory automatically — see `public/index.php`'s `insulaPrepareServerlessStoragePath()` and `bootstrap/app.php`'s `useStoragePath()` call; nothing to configure |

### Put the function in the same region as the database

This matters more than anything else on this page. Vercel defaults new
projects to `iad1` (Washington DC); if the Supabase project is in, say,
`ap-south-1` (Mumbai), every single query crosses an ocean. A CRM page runs
dozens of queries, so a ~200 ms round trip turns a page load into 5–25
seconds — the app looks broken rather than slow.

`vercel.json` pins `"regions": ["bom1"]` (Mumbai) to match the Supabase
region this deployment uses. **If you move the database, change this to the
matching Vercel region** — the two must always agree. Vercel's region codes
and their Supabase equivalents: `bom1`/`ap-south-1` (Mumbai),
`iad1`/`us-east-1` (Virginia), `sfo1`/`us-west-1`, `fra1`/`eu-central-1`,
`sin1`/`ap-southeast-1`, `syd1`/`ap-southeast-2`.

Also use Supabase's **Session pooler** connection string (port 5432,
`aws-0-<region>.pooler.supabase.com`, user `postgres.<project-ref>`) rather
than the direct `db.<project-ref>.supabase.co` host: the direct host is
IPv6-only, which serverless functions generally cannot reach.

### Build-time cache warming

Because every invocation is a fresh process, anything Laravel compiles once
and reuses on a normal server is otherwise recompiled on every cold start.
`scripts/build-serverless-cache.php` (wired to Composer's `post-install-cmd`,
and a no-op unless `VERCEL` is set) runs `event:cache`, `route:cache` and
`view:cache` during the build so the results ship inside the bundle;
`public/index.php` then points Laravel at them and seeds `/tmp` with the
pre-compiled Blade views. It deliberately does **not** run `config:cache` —
that would freeze build-time environment values, so changing a variable in
the Vercel dashboard would silently do nothing until the next deploy.

**Trade-off to be explicit about:** Vercel Cron on the free/Hobby plan only
allows daily schedules — `vercel.json` ships with `"0 7 * * *"` (once a
day) for exactly this reason; deploying with a more frequent expression on
Hobby fails at deploy time with `cron_jobs_limits_reached`. The Pro plan
supports per-minute schedules — if your queue needs near-real-time
processing (e.g. instant lead-assignment notifications), either upgrade to
Pro and tighten `vercel.json`'s cron schedule, or run the queue elsewhere —
see the alternative below.

**If you need true background workers or sub-minute reliability**, the
more robust option is a small always-on container (Fly.io, Railway, a $5
VPS) running `docker/` as-is with `php artisan queue:work` and
`schedule:work` as real daemons, pointed at the same Supabase database. The
web layer can still live on Vercel; only the worker needs a persistent host.
Document this clearly to the client if their lead volume grows.

## Step-by-step

1. **Create a Supabase project.** Project Settings > Database > Connection
   string. Copy the URI (or the individual host/port/user/password/db name).
2. **Configure environment variables** (Vercel Project Settings >
   Environment Variables — never commit these):
   - `APP_KEY` — generate locally with `php artisan key:generate --show`
   - `APP_URL` — your production domain (Phase 18: custom domain, e.g.
     `https://crm.arhomes.in`)
   - `DB_CONNECTION=pgsql`, `DB_URL` (or `DB_HOST`/`DB_PORT`/`DB_DATABASE`/
     `DB_USERNAME`/`DB_PASSWORD`), `DB_SSLMODE=require`
   - `SESSION_DRIVER=database`, `CACHE_STORE=database`,
     `QUEUE_CONNECTION=database`, `LOG_CHANNEL=stderr`
   - `FILESYSTEM_DISK=s3` + `AWS_*` (or Supabase Storage's S3-compatible
     credentials)
   - `MAIL_*` for your transactional email provider
   - `CRON_SECRET` — a random string; Vercel automatically sends it as
     `Authorization: Bearer $CRON_SECRET` to your cron endpoint
   - See `.env.example` for the full list with comments.
3. **Run migrations** against Supabase before or right after the first
   deploy: `php artisan migrate --force` (run this from your machine or CI
   with `DB_*` pointed at Supabase — Vercel's build step does not run
   artisan commands automatically).
4. **Deploy to Vercel** (`vercel --prod`, or connect the GitHub repo in the
   Vercel dashboard — it will detect `vercel.json`).
5. **Configure your custom domain** in Vercel (Phase 18) and set `APP_URL`
   to match.
6. **Create the first platform admin** (see below).
7. **Create the ARhomes tenant** — either via the platform admin UI
   (`/platform-admin/tenants/create`) or `php artisan tinker` using
   `App\Services\TenantOnboardingService`.
8. **Configure ARhomes branding** — tenant admin logs in, Settings >
   General (logo, name, currency, timezone).
9. **Generate ARhomes API credentials** — Settings > Integrations > Website
   Integration Credentials → create an `embed` credential for the website
   form and a `full` credential for any server-side integration.
10. **Connect the ARhomes website** — follow `INTEGRATION.md` (Option A, B,
    C, or D).
11. **Send a test lead** — POST to `/api/v1/public/leads` with the embed
    key, or submit the hosted form.
12. **Verify the lead appears** in the ARhomes CRM (Leads list).
13. **Verify a webhook** — configure one under Settings > Webhooks pointing
    at a tool like webhook.site, then confirm `lead.created` fires with a
    valid `X-Webhook-Signature`.
14. **Verify notifications** — confirm the assigned agent receives the
    in-app/email notification for the new lead.

### Creating the first platform admin

There is no UI for this (a platform admin must exist before anyone can use
the platform admin UI). Run once, against production:

```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', 'you@yourcompany.com')->first();
>>> $user->update(['is_platform_admin' => true]);
```

## Backups & migration safety (Phase 19)

- Supabase takes automatic daily backups on paid plans (Point-in-Time
  Recovery on Team+); on the Free plan, schedule your own periodic
  `pg_dump` (Supabase exposes a direct Postgres connection you can dump).
- Never hand-edit the production schema — every change is a migration
  (`php artisan make:migration ...`), reviewed and run through the normal
  deploy path.
- Test every migration against a disposable Supabase branch/staging project
  first (`php artisan migrate --pretend` to preview the SQL, then a real run
  against staging) before applying to the production project.
- Rollback: `php artisan migrate:rollback --step=1` for the last batch, or
  restore from a Supabase backup for anything destructive a migration
  `down()` can't cleanly undo.

## Supabase: close the public REST API before going live

**Do this on every new Supabase project.** Supabase publishes every table in
the `public` schema through PostgREST, and grants the `anon` and
`authenticated` roles full rights on anything created there — including tables
created by Laravel migrations. The anon key that authenticates as `anon` is
public by design: it is meant to ship in browser code. So on a fresh project,
with row level security off, anyone holding that key can read every lead, user
row and stored credential over plain HTTP, and can `DELETE` or `TRUNCATE` them.

This CRM never uses PostgREST or the Supabase client libraries — Laravel
connects straight to Postgres as the tables' owner — so the fix is to take the
access away rather than write a policy for every table. The migration
`2026_04_14_000002_close_supabase_public_api_access` does it, and is a no-op
on a database where those roles don't exist:

- revokes both roles from the tables, sequences, functions and the schema,
- revokes the *default* privileges too, so a later migration can't reopen it,
- enables RLS on every table as a second line — with no policies a non-owner
  reads nothing, and Postgres exempts owners from RLS, so the app is unaffected.

Check it took effect. This should return no rows:

```sql
select grantee, table_name from information_schema.role_table_grants
where table_schema = 'public' and grantee in ('anon', 'authenticated');
```

Supabase's own database linter (Dashboard → Advisors) should then report no
`rls_disabled_in_public` errors. The remaining `rls_enabled_no_policy` notices
are expected and correct here: no policy is needed for roles that have no
access at all.

## Secrets

Never commit `.env`, database passwords, Supabase service-role keys, AI
provider keys, or OAuth secrets. `.gitignore` already excludes `.env*`.
`.env.example` documents every variable with a safe placeholder. Set real
values only in Vercel's Environment Variables (or your host's equivalent
secret store).
