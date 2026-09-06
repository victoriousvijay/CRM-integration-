# Architecture

## What this is

This repository is a white-label, multi-tenant real estate CRM platform. It
is a fork of [InsulaCRM](https://github.com/InsulaCRM/InsulaCRM) (MIT
licensed, see `CREDITS.md`/`LICENSE`), re-architected so one deployment can
serve many independently-branded real estate companies ("tenants") instead
of one company per install. **ARhomes** is the first tenant onboarded onto
the platform, not a special-cased brand baked into the code — any tenant
(ABC Realty, XYZ Properties, ...) works identically.

## Stack (as inherited from InsulaCRM, largely unchanged)

- **Framework:** Laravel 12, PHP 8.2+
- **Frontend:** Blade + Tailwind CSS 4 + Vite (server-rendered, no SPA framework)
- **Database:** PostgreSQL via Supabase in production (MySQL/MariaDB/SQLite
  also supported by the same migrations — see `DATABASE.md`)
- **Queue/Cache/Session:** database-backed by default (works without Redis;
  see `DEPLOYMENT.md` for why this matters on serverless hosts)
- **Auth:** Laravel's built-in session auth + optional 2FA/SSO drivers
  (`app/Integrations/Sso`, `app/Integrations/TwoFactor`) — not Supabase Auth

## Multi-tenancy model

Every tenant-owned table carries a `tenant_id` foreign key (`leads`,
`properties`, `deals`, `buyers`, `activities`, `tasks`, `webhooks`,
`api_credentials`, `integrations`, `audit_log`, etc.). Isolation is enforced
by `App\Models\Scopes\TenantScope`, a global Eloquent scope applied to every
tenant-owned model, which filters every query to:

- `auth()->user()->tenant_id` in web/session requests, or
- `request()->attributes->get('tenant')->id` in API requests (set by
  `ApiKeyMiddleware`/`EmbedKeyMiddleware` after verifying a credential)

Tenant identity is **never** taken from client-supplied input (no
`tenant_id` field in a request body is trusted). It always comes from either
the authenticated session or a verified API/embed credential. See
`app/Http/Middleware/ApiKeyMiddleware.php` and `EmbedKeyMiddleware.php`.

The `Tenant` model itself, and `User`, are the two places `tenant_id` is
the primary key rather than a foreign key subject to the scope.

## Two admin layers

- **Tenant admin** (`role = admin`, scoped to one tenant): manages their own
  company's users, branding, leads, integrations. This existed in
  InsulaCRM already (`SettingsController`, tenant `Role`/`Permission`
  system in `app/Models/Role.php`, `Permission.php`).
- **Platform admin** (`users.is_platform_admin = true`, added in this fork):
  operates the CRM platform itself — onboarding new clients, suspending
  accounts, viewing every tenant. See
  `app/Http/Controllers/PlatformAdmin/TenantController.php`,
  `App\Http\Middleware\PlatformAdminMiddleware`, routes under
  `/platform-admin`. A platform admin still belongs to a tenant row (schema
  constraint), but the flag — not their tenant's role — gates this area.

## White-label branding

Per-tenant branding (name, logo, locale, currency, timezone, date format,
business mode) lives on the `tenants` table and was already wired through
every Blade view before this fork (`$tenant->name`, `$tenant->logo_path`,
etc. in `resources/views/layouts/app.blade.php`, `forms/lead-capture.blade.php`).
The platform-level `APP_NAME` env var is only a neutral fallback used before
a tenant is known (e.g. the install wizard); it is not a tenant's brand.

## API / integration layer

- `routes/api.php` — the full tenant REST API (`/api/v1/leads`, `/deals`,
  `/buyers`, `/properties`, `/activities`, `/stats`), authenticated via
  `X-API-Key` and a **"full"** `ApiCredential`.
- `routes/api.php` — `/api/v1/public/leads`, a lead-creation-only endpoint
  authenticated via an **"embed"** `ApiCredential`, safe to reference from
  public/browser-side code (`public/embed.js`, the embeddable form).
- `app/Models/ApiCredential.php` / `app/Services/ApiCredentialService.php` —
  named, hashed, revocable per-tenant credentials (Settings > Integrations),
  replacing the old single plaintext `tenants.api_key` column (kept as a
  deprecated fallback for backward compatibility — see `DATABASE.md`).
- `app/Jobs/DispatchWebhook.php` / `app/Services/WebhookService.php` —
  tenant-scoped outbound webhooks with HMAC-SHA256 signing and
  auto-disable after repeated failures.

## What was NOT changed

Business logic for leads, properties, deals, buyers, activities, tasks,
sequences, workflows, AI qualification, plugins, and reporting is inherited
from InsulaCRM as-is (Phase 22: don't break working functionality). Changes
in this fork are additive (platform admin, multi-key credentials, Postgres
portability fixes) or narrowly scoped security fixes (the embeddable-form
key exposure — see `DATABASE.md` and the PR description for details).
