<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends WhatsApp messages to leads through Meta's WhatsApp Cloud API.
 *
 * Two constraints from Meta shape everything here:
 *
 * 1. A business cannot open a WhatsApp conversation with free text. Outside a
 *    24-hour window since the customer last wrote, only a template approved in
 *    Meta Business Manager will be delivered — so the CRM stores a template
 *    name plus its variables, never a message body it composed itself.
 * 2. Credentials belong to the business, not the platform. Each tenant holds
 *    their own phone number id and access token, so a message always goes out
 *    from the client's own number.
 *
 * Every attempt is recorded in whatsapp_messages, including the ones that were
 * skipped or failed, so nobody has to guess whether a client was contacted.
 */
class WhatsAppService
{
    protected const API_VERSION = 'v21.0';

    /** Seconds to wait on the API before giving up — this runs inside a web request. */
    protected const TIMEOUT_SECONDS = 8;

    /**
     * Send the template configured for an event on a lead, if there is one.
     *
     * Never throws: a messaging failure must not take down the request that
     * created or updated the lead.
     */
    public function sendForEvent(string $event, Lead $lead, ?string $status = null): ?WhatsappMessage
    {
        try {
            $tenant = $lead->tenant;

            if (! $tenant) {
                return null;
            }

            $settings = $this->settings($tenant);

            if (! ($settings['enabled'] ?? false)) {
                return null;
            }

            if ($lead->do_not_contact) {
                return $this->record($lead, null, $event, 'skipped', error: 'Lead is marked do not contact.');
            }

            if (blank($lead->phone)) {
                return $this->record($lead, null, $event, 'skipped', error: 'Lead has no phone number.');
            }

            $template = WhatsappTemplate::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('event', $event)
                ->where('status', $status)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return null;
            }

            return $this->sendTemplate($lead, $template, $settings, $event);
        } catch (\Throwable $e) {
            Log::error("WhatsAppService: {$event} for lead #{$lead->id} failed: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Send one template to a lead.
     */
    public function sendTemplate(Lead $lead, WhatsappTemplate $template, array $settings, ?string $event = null): WhatsappMessage
    {
        $to = $this->normalizeNumber($lead->phone, $settings['default_country_code'] ?? null);

        if ($to === null) {
            return $this->record($lead, $template->template_name, $event, 'skipped',
                error: 'Phone number is not in a format WhatsApp accepts.');
        }

        $parameters = collect($template->variables ?? [])
            ->map(fn ($key) => ['type' => 'text', 'text' => $this->resolveVariable($key, $lead)])
            ->values()
            ->all();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template->template_name,
                'language' => ['code' => $template->language_code ?: 'en'],
            ],
        ];

        if ($parameters !== []) {
            $payload['template']['components'] = [
                ['type' => 'body', 'parameters' => $parameters],
            ];
        }

        try {
            $response = Http::withToken($this->accessToken($settings))
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(
                    sprintf('https://graph.facebook.com/%s/%s/messages', self::API_VERSION, $settings['phone_number_id']),
                    $payload
                );

            if ($response->successful()) {
                return $this->record($lead, $template->template_name, $event, 'sent',
                    to: $to,
                    providerMessageId: $response->json('messages.0.id'),
                    payload: $payload);
            }

            return $this->record($lead, $template->template_name, $event, 'failed',
                to: $to,
                error: Str::limit((string) $response->json('error.message', $response->body()), 500),
                payload: $payload);
        } catch (\Throwable $e) {
            return $this->record($lead, $template->template_name, $event, 'failed',
                to: $to,
                error: Str::limit($e->getMessage(), 500),
                payload: $payload);
        }
    }

    /**
     * The tenant's WhatsApp settings, with the token left encrypted.
     *
     * @return array<string, mixed>
     */
    public function settings(Tenant $tenant): array
    {
        $settings = $tenant->whatsapp_settings ?? [];

        if (blank($settings['phone_number_id'] ?? null) || blank($settings['access_token'] ?? null)) {
            $settings['enabled'] = false;
        }

        return $settings;
    }

    /**
     * Values a template's variables can be filled from.
     *
     * Deliberately a fixed list: an admin picks from these when configuring a
     * template, so a typo can't send a client someone else's data.
     *
     * @return array<string, string>
     */
    public static function availableVariables(): array
    {
        return [
            'client_name' => 'Client first name',
            'client_full_name' => 'Client full name',
            'property_address' => 'Property they visited',
            'property_city' => 'City of that property',
            'broker_name' => 'Broker who logged the enquiry',
            'agent_name' => 'Assigned agent',
            'status_label' => 'Current lead status',
            'company_name' => 'Your company name',
        ];
    }

    /**
     * Resolve one variable for a lead. Never returns an empty string — Meta
     * rejects a template whose parameter is blank.
     */
    protected function resolveVariable(string $key, Lead $lead): string
    {
        $value = match ($key) {
            'client_name' => $lead->first_name,
            'client_full_name' => trim($lead->first_name.' '.$lead->last_name),
            'property_address' => $lead->visitedProperty?->address ?? $lead->property?->address,
            'property_city' => $lead->visitedProperty?->city ?? $lead->property?->city,
            'broker_name' => $lead->broker?->name,
            'agent_name' => $lead->agent?->name,
            // Resolved against the lead's own tenant: a status label depends on
            // the tenant's business mode and their custom options, not on
            // whoever happens to be signed in when the message goes out.
            'status_label' => CustomFieldService::getOptions('lead_status', $lead->tenant)[$lead->status] ?? $lead->status,
            'company_name' => $lead->tenant?->name,
            default => null,
        };

        return filled($value) ? (string) $value : '-';
    }

    /**
     * WhatsApp wants a bare international number: digits only, no plus, no
     * spaces. A local number is only usable if the tenant said which country
     * to assume.
     */
    public function normalizeNumber(string $phone, ?string $defaultCountryCode = null): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Local trunk prefix ("0" before the number) isn't part of the
        // international form.
        $digits = ltrim($digits, '0');

        $countryCode = preg_replace('/\D+/', '', (string) $defaultCountryCode);

        if ($countryCode && strlen($digits) <= 10) {
            $digits = $countryCode.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Tokens are stored encrypted; tolerate a plaintext one so a hand-edited
     * settings row still works rather than silently failing to send.
     */
    protected function accessToken(array $settings): string
    {
        try {
            return Crypt::decryptString($settings['access_token']);
        } catch (\Throwable) {
            return (string) $settings['access_token'];
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function record(
        Lead $lead,
        ?string $templateName,
        ?string $event,
        string $status,
        ?string $to = null,
        ?string $providerMessageId = null,
        ?string $error = null,
        ?array $payload = null,
    ): WhatsappMessage {
        return WhatsappMessage::withoutGlobalScopes()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'to_number' => $to ?? (string) $lead->phone,
            'template_name' => $templateName,
            'event' => $event,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'error' => $error,
            'payload' => $payload,
        ]);
    }
}
