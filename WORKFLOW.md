# Workflows

## Platform owner: onboarding a new client

```
Login (platform admin)
  → /platform-admin/tenants
  → "Onboard New Client" — company name, slug, business mode, country,
    currency, timezone, admin name/email/password
  → TenantOnboardingService::onboard() runs in one DB transaction:
      - creates the Tenant row (branding defaults, status=active)
      - creates the tenant's first admin User (role: admin)
      - generates a "full" API credential and an "embed" credential
      - seeds default lead sources
  → shown once: the full + embed credential tokens (copy them now)
  → hand the embed token + INTEGRATION.md to the client's web developer
```

## Client admin: configuring their CRM

```
ARhomes admin logs in
  → Settings > General: company name, logo, timezone, currency, date format
  → Settings > Team: invite agents, assign roles
  → Settings > Integrations: create additional credentials if needed
    (e.g. separate keys for a staging site vs. production)
  → Settings > Webhooks: point at their marketing stack / Slack / Zapier
```

## Website → lead → CRM (the core integration loop)

```
ARhomes website contact form
  → POST /api/v1/public/leads  (X-Embed-Key: <embed token>)
  → EmbedKeyMiddleware resolves the credential → tenant (never trusts
    any tenant_id in the request body)
  → LeadIngestController: validate → resolve source → duplicate check
    → create Lead (+ Property if an address was given)
  → LeadDistributionService assigns an agent per the tenant's rules
  → MotivationScoreService scores the lead
  → notify the assigned agent (in-app/email, per their preferences)
  → AuditLog::log('lead.created_via_api', $lead)
  → WebhookService::dispatch('lead.created', ...) → DispatchWebhook job
    → tenant's configured webhook URLs, HMAC-signed, retried on failure
  → response: { success: true, message: "...", data: { id, status } }
```

The embeddable hosted form (`/forms/{embed_token}`) and `embed.js` widget
both funnel into the same endpoint/controller path, so behavior (dedupe,
assignment, notifications, webhooks) is identical regardless of which
integration option a client's developer picks.

## Cross-tenant isolation, end to end

```
Request arrives (web session OR X-API-Key/X-Embed-Key)
  → tenant resolved from auth session or verified credential ONLY
  → request()->attributes->set('tenant', $tenant)  [API]
    or auth()->user()->tenant_id  [web]
  → every Eloquent query on a tenant-owned model passes through
    App\Models\Scopes\TenantScope, which adds
    WHERE tenant_id = <resolved tenant>
  → a user/credential from Tenant A can never see Tenant B's rows,
    because Tenant B's id never enters the query — even if a request
    tried to pass a different tenant_id in its body/query string, it's
    ignored (see LeadIngestController, ApiKeyMiddleware)
```

See the tenant-isolation tests under `tests/Feature` for the automated
version of this check.
