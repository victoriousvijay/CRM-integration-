<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Test Realty',
            'slug' => 'test-realty',
            'email' => 'owner@test-realty.test',
            'status' => 'active',
            'business_mode' => 'realestate',
            'whatsapp_settings' => [
                'enabled' => true,
                'provider' => 'meta_cloud',
                'phone_number_id' => '1234567890',
                'access_token' => Crypt::encryptString('test-token'),
                'default_country_code' => '91',
                'default_language' => 'en',
            ],
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Admin',
            'email' => 'admin@test-realty.test',
            'password' => bcrypt('password'),
        ]);
    }

    protected function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
            'lead_source' => 'broker',
            'status' => 'inquiry',
            'temperature' => 'warm',
        ], $overrides));
    }

    protected function mapTemplate(string $event, ?string $status, array $variables = []): WhatsappTemplate
    {
        return WhatsappTemplate::create([
            'tenant_id' => $this->tenant->id,
            'event' => $event,
            'status' => $status,
            'template_name' => 'visit_thank_you',
            'language_code' => 'en',
            'variables' => $variables,
            'is_active' => true,
        ]);
    }

    public function test_a_status_change_sends_the_template_mapped_to_that_status(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => '12 Baker Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
        ]);

        $lead = $this->makeLead(['visited_property_id' => $property->id]);

        $this->mapTemplate('lead.status_changed', 'consultation', [
            'client_name', 'property_address', 'status_label',
        ]);

        $lead->update(['status' => 'consultation']);
        app(WhatsAppService::class)->sendForEvent('lead.status_changed', $lead->fresh(), 'consultation');

        $message = WhatsappMessage::firstOrFail();

        $this->assertSame('sent', $message->status);
        $this->assertSame('919876543210', $message->to_number, 'A local number is sent in international form.');
        $this->assertSame('wamid.test', $message->provider_message_id);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $params = array_column($body['template']['components'][0]['parameters'], 'text');

            return $body['template']['name'] === 'visit_thank_you'
                && $params === ['Riya', '12 Baker Street', 'Consultation'];
        });
    }

    public function test_nothing_is_sent_when_no_template_is_mapped_to_that_status(): void
    {
        Http::fake();

        $lead = $this->makeLead();
        $this->mapTemplate('lead.status_changed', 'closed_won');

        app(WhatsAppService::class)->sendForEvent('lead.status_changed', $lead, 'nurture');

        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_a_lead_marked_do_not_contact_is_never_messaged(): void
    {
        Http::fake();

        $lead = $this->makeLead(['do_not_contact' => true]);
        $this->mapTemplate('lead.created', null);

        app(WhatsAppService::class)->sendForEvent('lead.created', $lead);

        Http::assertNothingSent();
        $this->assertSame('skipped', WhatsappMessage::firstOrFail()->status);
    }

    public function test_a_failed_send_is_recorded_rather_than_thrown(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not found']], 400),
        ]);

        $lead = $this->makeLead();
        $this->mapTemplate('lead.created', null);

        app(WhatsAppService::class)->sendForEvent('lead.created', $lead);

        $message = WhatsappMessage::firstOrFail();

        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('Template not found', $message->error);
    }

    public function test_sending_is_off_until_the_tenant_configures_it(): void
    {
        Http::fake();

        $this->tenant->update(['whatsapp_settings' => ['enabled' => false]]);
        $lead = $this->makeLead();
        $this->mapTemplate('lead.created', null);

        app(WhatsAppService::class)->sendForEvent('lead.created', $lead->fresh());

        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_the_access_token_is_stored_encrypted(): void
    {
        $this->actingAs($this->admin)->put(route('settings.whatsapp.connection'), [
            'enabled' => '1',
            'phone_number_id' => '999',
            'access_token' => 'super-secret-token',
        ])->assertRedirect();

        $stored = $this->tenant->fresh()->whatsapp_settings['access_token'];

        $this->assertNotSame('super-secret-token', $stored);
        $this->assertSame('super-secret-token', Crypt::decryptString($stored));
    }

    public function test_only_an_admin_can_reach_the_whatsapp_settings(): void
    {
        $broker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker',
            'email' => 'broker@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($broker)->get(route('settings.whatsapp'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('settings.whatsapp'))->assertOk();
    }
}
