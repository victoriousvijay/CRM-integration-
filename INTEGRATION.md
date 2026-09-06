# Website → CRM Integration Guide

This is the guide a tenant's website developer needs. It is also rendered
in-app under **Settings > Integrations > API** for the logged-in tenant
(with their own live endpoint/credential filled in).

## Two kinds of credentials — pick the right one

| | `full` | `embed` |
|---|---|---|
| Can read leads/deals/buyers/properties/activities/stats | Yes | No |
| Can create a lead | Yes | Yes |
| Safe to put in browser/public code | **No** | Yes |
| Use for | Zapier, server-side integrations, internal tools | Website contact/lead forms, `embed.js` |

Create credentials under **Settings > Integrations > Website Integration
Credentials**. Each is named (e.g. "Production Website", "Staging
Website") and shown once at creation — store it in your secrets manager;
it cannot be retrieved again (only revoked/rotated by creating a new one).

## Option A — call the API directly (server-side)

```
POST https://your-crm-domain.com/api/v1/leads
Content-Type: application/json
X-API-Key: <your full credential token>

{
  "name": "Rahul Sharma",
  "phone": "9876543210",
  "email": "rahul@example.com",
  "source": "website",
  "property_id": 123,
  "property_name": "3 BHK Apartment",
  "message": "Interested in this property",
  "page_url": "https://arhomes.com/property/123",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "delhi-property",
  "consent": true
}
```

Response:
```json
{ "success": true, "message": "Lead created successfully.", "data": { "id": 12345, "status": "new" } }
```

`first_name`/`last_name` are also accepted (and preferred if you already
split the name). Duplicate leads (same phone or email within the tenant)
return `200` with `"duplicate": true` instead of creating a second record.

### cURL
```bash
curl -X POST https://your-crm-domain.com/api/v1/leads \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_FULL_KEY" \
  -d '{"first_name":"Rahul","last_name":"Sharma","phone":"9876543210","source":"website"}'
```

### PHP
```php
$response = Http::withHeaders(['X-API-Key' => $fullKey])
    ->post('https://your-crm-domain.com/api/v1/leads', [
        'first_name' => 'Rahul', 'last_name' => 'Sharma', 'phone' => '9876543210',
    ]);
```

## Option B — the public, lead-only endpoint (safe for browser JS)

```
POST https://your-crm-domain.com/api/v1/public/leads
Content-Type: application/json
X-Embed-Key: <your embed credential token>

{ "first_name": "Rahul", "last_name": "Sharma", "phone": "9876543210" }
```

Same request/response shape as Option A, minus the ability to read/update
anything. Rate-limited (30 req/min per key) and CORS-enabled for any origin
(safe because the key itself, not cookies/origin, is the auth boundary).

### JavaScript (fetch)
```html
<script>
fetch('https://your-crm-domain.com/api/v1/public/leads', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-Embed-Key': 'YOUR_EMBED_KEY' },
  body: JSON.stringify({ first_name: 'Rahul', last_name: 'Sharma', phone: '9876543210' }),
}).then(r => r.json()).then(console.log);
</script>
```

## Option C — the embeddable widget (`embed.js`)

Drop this into any page, no server-side code required:

```html
<script src="https://your-crm-domain.com/embed.js" data-crm-key="YOUR_EMBED_KEY" async></script>
<div data-crm-form="lead"></div>
```

Includes a honeypot field and posts to Option B under the hood. See
`public/embed.js`.

## Option D — hosted lead-capture page

Every `embed` credential also has a ready-made hosted form and iframe embed
code, shown in Settings > Integrations:

```
GET https://your-crm-domain.com/forms/{embed_token}
```

```html
<iframe src="https://your-crm-domain.com/forms/{embed_token}" width="100%" height="500" frameborder="0"></iframe>
```

## Lead sources & tracking fields

`source` accepts: `website`, `google_ads`/`ppc`, `meta_ads`, `whatsapp`,
`referral`, `walk_in`, `phone`, `email`, `api`, `property_portal`, `manual`,
`other` (falls back to `utm_source` → mapped source → `api` if omitted).
Also accepted, stored as reference metadata on the lead:
`property_id`, `property_name`, `page_url`, `referrer`, `utm_source`,
`utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `consent`,
`external_id`.

## Webhooks (CRM → your website)

Configure under Settings > Webhooks. Events: `lead.created`, `lead.updated`,
`lead.status_changed`, `deal.created`, `deal.stage_changed`,
`activity.created`.

```json
{
  "event": "lead.created",
  "timestamp": "2026-04-01T12:00:00Z",
  "data": { "lead_id": 123, "name": "Rahul Sharma", "phone": "...", "email": "..." }
}
```

Verify the `X-Webhook-Signature` header:
`hash_hmac('sha256', $rawBody, $yourWebhookSecret)`, compared with
`hash_equals`. Failing webhooks retry 3 times (10s/60s/300s backoff, see
`app/Jobs/DispatchWebhook.php`) and auto-disable after 10 consecutive
failures.

## Full internal/admin API reference

See **Settings > Integrations > Full API Documentation** in-app
(`app/Http/Controllers/ApiDocsController.php`) for the complete
leads/deals/buyers/properties/activities/stats reference — that surface
requires a `full` credential and is not meant for public/browser code.
