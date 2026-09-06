# Plan / Status

This tracks what the original 23-phase brief asked for, what shipped in
this pass, and what's left. InsulaCRM was already a substantially-built
multi-tenant real estate CRM (tenants, roles/permissions, leads, deals,
buyers, properties, webhooks, an API-key-gated REST API, an install
wizard) — this pass focused on the gaps that mattered most: a real
platform-admin layer, a secret-exposure fix in the public lead form, a
proper multi-key credential system, Postgres/Supabase compatibility, and
documentation. It does not re-implement what already worked.

## Done in this pass

- **Phase 0 (audit)** — read the InsulaCRM codebase end to end; see
  `ARCHITECTURE.md`/`DATABASE.md` for findings (Laravel 12, PHP 8.2,
  MySQL by default, tenant_id + `TenantScope` isolation already in place,
  single API key per tenant, webhooks with HMAC signing already built).
- **Phase 1 (Supabase/Postgres)** — `config/database.php` default switched
  to `pgsql` with `sslmode=require`; audited raw SQL for MySQL-only
  functions and fixed the 4 call sites using `DATEDIFF()`/`NOW()`
  (dashboard/report/PDF "avg days" widgets) via a new
  `App\Services\SqlPortability` helper. `.env.example` rewritten for a
  Supabase-first production config.
- **Phase 3 (multi-tenancy)** — audited; `TenantScope` + credential-based
  tenant resolution already correct. Added regression tests proving a
  tenant's API/embed credential cannot read or write another tenant's data
  even when the request body tries to smuggle a different `tenant_id`
  (`tests/Feature/TenantIsolationTest.php`).
- **Phase 5 (platform admin)** — new `users.is_platform_admin` flag,
  `PlatformAdminMiddleware`, `PlatformAdmin\TenantController` (list/create/
  view/edit/suspend/activate tenants), minimal views, routes under
  `/platform-admin`.
- **Phase 6 (onboarding)** — `TenantOnboardingService`: one transaction
  creates the tenant, its admin user, a full + embed API credential, and
  default lead sources.
- **Phase 7/9/10/11 (website → CRM API, embeddable form, embed.js)** —
  extended `LeadIngestController` to accept the documented field set
  (`name`, `message`, `property_id`/`property_name`, `page_url`,
  `referrer`, `utm_term`/`utm_content`, `consent`) and to return
  `{success, message, data:{id,status}}`; added `public/embed.js` and
  `/api/v1/public/leads`; `INTEGRATION.md` documents all four integration
  options with cURL/JS/PHP examples.
- **Phase 8/13 (API credentials, security)** — **fixed a real secret
  exposure**: the embeddable lead form used the tenant's single, full,
  plaintext `api_key` directly as a public URL segment
  (`/forms/{api_key}`), so anyone who viewed a client's public contact
  form could extract a credential that read/wrote their entire CRM. New
  `api_credentials` table + `ApiCredentialService` (named, hashed,
  revocable, typed `full`/`embed` keys); the public form and `embed.js`
  now require a non-privileged `embed` credential; `ApiKeyMiddleware`
  rejects `embed` credentials on the full API. The legacy `tenants.api_key`
  column still works (backward compatible) but is no longer usable for the
  public form.
- **Phase 12 (webhooks)** — audited; HMAC signing, retry/backoff, and
  auto-disable-after-10-failures already existed
  (`app/Jobs/DispatchWebhook.php`). Added a `lead.created` dispatch to the
  hosted web-form path, which was missing it (API path already had it).
- **Phase 15 (tests)** — `tests/Feature/Api/ApiCredentialTest.php` (full
  vs. embed scoping, revocation, honeypot) plus two new cases in
  `TenantIsolationTest.php` for credential-based cross-tenant isolation.
  All 407 tests, including these, were run against a real local
  **PostgreSQL 16** instance (not just SQLite) — see the Postgres
  validation note below.

### Postgres validation (beyond static audit)

Running the migrations against a real Postgres instance (installed
locally for this) surfaced bugs a code-only audit missed: five migrations
used raw MySQL-only `MODIFY COLUMN` statements guarded by `!== 'sqlite'`,
so they silently ran the MySQL branch on Postgres and failed outright.
Beyond that, even after fixing the syntax, the tenant-configurable
lead-status/deal-stage columns still rejected new values — Laravel's
`$table->enum()` compiles to a CHECK constraint on Postgres (not a native
enum), and widening a column's type doesn't drop that constraint the way
MySQL's `MODIFY COLUMN` does, so a leftover `leads_status_check`/
`deals_stage_check` kept enforcing the original hard-coded value list.
Fixed in a follow-up commit; validated by running all 528 migration
statements end-to-end and the full 407-test suite against that Postgres
instance (all passing). This is a good example of why "audited for X" and
"ran against a real X" are different claims — the former missed both
issues above.
- **Phase 16/17 (Vercel deploy)** — `vercel.json` + `api/index.php` using
  the community `vercel-php` runtime; session/cache/queue moved to
  database-backed drivers (no local filesystem persistence on Vercel);
  `CronController` + Vercel Cron entry to run the scheduler and drain the
  database queue, since Vercel has no persistent worker process. See
  `DEPLOYMENT.md` for the honest trade-offs (Cron frequency limits on the
  Hobby plan; a small always-on host is the more robust option for heavy
  queue/scheduler needs).
- **Docs** — `ARCHITECTURE.md`, `WORKFLOW.md`, `PLAN.md`, `INTEGRATION.md`,
  `DEPLOYMENT.md`, `DATABASE.md` (this set).

## Deliberately not done / left as follow-up

- **Per-tenant custom domains** (Phase 18 mentions this as a stretch goal
  only) — not implemented; `APP_URL` is a single platform-wide domain
  today. Worth a dedicated migration (`tenants.custom_domain` + a
  domain-to-tenant resolution middleware) if a client needs it.
- **Rate limiting tuning per credential** — the public embed endpoint uses
  a flat `throttle:30,1`; a high-volume client may need per-credential
  limits (would live in `EmbedKeyMiddleware`).
- **OpenAPI spec** (Phase 14 mentions "prefer OpenAPI where practical") —
  `INTEGRATION.md` + the in-app API docs controller cover the same ground
  in Markdown/HTML; a formal `openapi.yaml` was not generated in this pass.
- **CAPTCHA/reCAPTCHA on the embeddable form** — the honeypot field
  (`website_url_confirm`) and rate limiting cover basic bot traffic;
  reCAPTCHA integration is a config-only addition if spam volume warrants
  it (add a `g-recaptcha-response` field, verify server-side in
  `PublicLeadController`/`WebFormController` before creating the lead).
- **Full security audit sign-off (Phase 13)** — the specific, concrete
  issue found (API key exposure in the public form URL) is fixed; a
  broader pass (dependency CVE scan, rate-limit tuning across every
  endpoint, file-upload content-type validation) was not performed as a
  separate exercise in this pass.
- **Vercel deployment has not been live-tested** against a real Supabase
  project in this pass (no Vercel deploy credentials available in this
  environment) — `vercel.json`/`api/index.php` follow the documented
  `vercel-php` pattern but should be verified against a staging deploy
  before pointing a real client's domain at it. The database schema and
  application logic side is now validated against real Postgres (see
  above); what's untested is specifically the Vercel serverless runtime
  itself (`vercel-php`, the Cron-triggered scheduler/queue endpoint) and
  the actual Supabase project's network path (SSL, connection pooling).
- **Migration not yet run against the actual Supabase project** — the
  schema was validated against a local PostgreSQL 16 instance (see above),
  not the Supabase Postgres 17 project itself, since that requires the
  project's database password, which — deliberately — was never requested
  in this chat. Run `php artisan migrate --force` with `DB_URL` set as a
  real environment variable (your machine/CI/Vercel) to apply it there;
  see `DEPLOYMENT.md` step 3.
