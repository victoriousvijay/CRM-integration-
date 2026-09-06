<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\CustomFieldService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class WhatsappSettingsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $tenant = $request->user()->tenant;
        $settings = $tenant->whatsapp_settings ?? [];

        return view('settings.whatsapp', [
            'settings' => $settings,
            'hasToken' => filled($settings['access_token'] ?? null),
            'statuses' => CustomFieldService::getOptions('lead_status'),
            'variables' => WhatsAppService::availableVariables(),
            'templates' => WhatsappTemplate::orderBy('event')->orderBy('status')->get(),
            'recent' => WhatsappMessage::with('lead')->latest()->limit(25)->get(),
        ]);
    }

    /**
     * Save the tenant's WhatsApp Cloud API connection.
     */
    public function updateConnection(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'phone_number_id' => 'nullable|string|max:100',
            'business_account_id' => 'nullable|string|max:100',
            'access_token' => 'nullable|string|max:1000',
            'default_country_code' => 'nullable|string|max:5',
            'default_language' => 'nullable|string|max:10',
        ]);

        $tenant = $request->user()->tenant;
        $settings = $tenant->whatsapp_settings ?? [];

        $settings['enabled'] = (bool) ($validated['enabled'] ?? false);
        $settings['provider'] = 'meta_cloud';
        $settings['phone_number_id'] = $validated['phone_number_id'] ?? null;
        $settings['business_account_id'] = $validated['business_account_id'] ?? null;
        $settings['default_country_code'] = $validated['default_country_code'] ?? null;
        $settings['default_language'] = $validated['default_language'] ?? 'en';

        // The form leaves the token blank to keep the stored one; it is only
        // ever written encrypted and never rendered back to the browser.
        if (filled($validated['access_token'] ?? null)) {
            $settings['access_token'] = Crypt::encryptString($validated['access_token']);
        }

        $tenant->update(['whatsapp_settings' => $settings]);

        AuditLog::log('whatsapp.settings_updated', $tenant);

        return redirect()->route('settings.whatsapp')->with('success', __('WhatsApp settings saved.'));
    }

    /**
     * Map an approved template to a moment in a lead's life.
     */
    public function saveTemplate(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'event' => 'required|in:lead.created,lead.status_changed',
            'status' => 'nullable|string|in:'.implode(',', CustomFieldService::getValidSlugs('lead_status')),
            'template_name' => 'required|string|max:255',
            'language_code' => 'required|string|max:10',
            'variables' => 'nullable|array',
            'variables.*' => 'string|in:'.implode(',', array_keys(WhatsAppService::availableVariables())),
            'preview' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        // A status only means something for a status change; storing one
        // against lead.created would make the unique key ambiguous.
        $status = $validated['event'] === 'lead.status_changed' ? ($validated['status'] ?? null) : null;

        if ($validated['event'] === 'lead.status_changed' && $status === null) {
            return back()->withInput()->withErrors([
                'status' => __('Choose which status should send this message.'),
            ]);
        }

        WhatsappTemplate::updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'event' => $validated['event'],
                'status' => $status,
            ],
            [
                'template_name' => $validated['template_name'],
                'language_code' => $validated['language_code'],
                'variables' => array_values($validated['variables'] ?? []),
                'preview' => $validated['preview'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]
        );

        return redirect()->route('settings.whatsapp')->with('success', __('Template saved.'));
    }

    public function deleteTemplate(WhatsappTemplate $template)
    {
        $this->authorizeAdmin();

        $template->delete();

        return redirect()->route('settings.whatsapp')->with('success', __('Template removed.'));
    }

    /**
     * Send one template to a real lead, so the admin can confirm the setup
     * works before it starts going out on its own.
     */
    public function test(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'lead_id' => 'required|integer',
            'template_id' => 'required|integer',
        ]);

        $lead = Lead::find($validated['lead_id']);
        $template = WhatsappTemplate::find($validated['template_id']);

        if (! $lead || ! $template) {
            return back()->with('error', __('Pick a lead and a template that still exist.'));
        }

        $service = app(WhatsAppService::class);
        $settings = $service->settings($request->user()->tenant);

        if (! ($settings['enabled'] ?? false)) {
            return back()->with('error', __('Turn WhatsApp on and save your connection details first.'));
        }

        $message = $service->sendTemplate($lead, $template, $settings, 'test');

        return redirect()->route('settings.whatsapp')->with(
            $message->status === 'sent' ? 'success' : 'error',
            $message->status === 'sent'
                ? __('Test message sent to :number.', ['number' => $message->to_number])
                : __('Test failed: :error', ['error' => $message->error ?? __('unknown error')])
        );
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
