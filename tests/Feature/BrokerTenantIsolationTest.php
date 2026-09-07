<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadClientPhoto;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant isolation across everything the broker portal added.
 *
 * TenantIsolationTest covers the original CRM. This covers the surfaces added
 * since — broker sharing, property photos, client photos, WhatsApp settings and
 * logs — because each one takes an id straight from the URL, and a scoping
 * mistake in any of them would hand one client another client's records.
 *
 * Every case here asks the same question from a different door: signed in as
 * tenant B, can I reach tenant A's row by knowing its id?
 */
class BrokerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $brokerA;

    protected User $brokerB;

    protected User $adminB;

    protected Property $propertyA;

    protected Lead $leadA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $this->tenantA = $this->makeTenant('Alpha Realty', 'alpha');
        $this->tenantB = $this->makeTenant('Beta Realty', 'beta');

        $this->brokerA = $this->makeUser($this->tenantA, 'broker', 'broker@alpha.test');
        $this->brokerB = $this->makeUser($this->tenantB, 'broker', 'broker@beta.test');
        $this->adminB = $this->makeUser($this->tenantB, 'admin', 'admin@beta.test');

        // Shared with every broker — but only every broker *of tenant A*.
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'address' => 'Alpha House',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
            'shared_with_all_brokers' => true,
        ]);

        $this->leadA = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'broker_id' => $this->brokerA->id,
            'visited_property_id' => $this->propertyA->id,
            'first_name' => 'Alpha',
            'last_name' => 'Client',
            'phone' => '9000000001',
            'lead_source' => 'broker',
            'status' => 'inquiry',
            'temperature' => 'warm',
        ]);
    }

    protected function makeTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'email' => "owner@{$slug}.test",
            'status' => 'active',
            'business_mode' => 'realestate',
        ]);
    }

    protected function makeUser(Tenant $tenant, string $role, string $email): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('name', $role)->whereNull('tenant_id')->value('id'),
            'name' => ucfirst($role).' '.$tenant->slug,
            'email' => $email,
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);
    }

    public function test_a_broker_never_sees_another_tenants_property_however_widely_it_is_shared(): void
    {
        $response = $this->actingAs($this->brokerB)->get(route('broker.index'));

        $response->assertOk();
        $response->assertDontSee('Alpha House');

        $this->actingAs($this->brokerB)
            ->get(route('broker.show', $this->propertyA))
            ->assertNotFound();
    }

    public function test_a_broker_cannot_fetch_another_tenants_property_photo(): void
    {
        $image = PropertyImage::create([
            'tenant_id' => $this->tenantA->id,
            'property_id' => $this->propertyA->id,
            'filename' => 'alpha.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3,
            'content' => base64_encode('abc'),
        ]);

        $this->actingAs($this->brokerB)
            ->get(route('properties.images.show', $image))
            ->assertNotFound();
    }

    public function test_an_admin_cannot_fetch_another_tenants_client_photo(): void
    {
        LeadClientPhoto::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $this->leadA->id,
            'filename' => 'client.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3,
            'content' => base64_encode('abc'),
        ]);

        $this->actingAs($this->adminB)
            ->get(route('leads.photo', $this->leadA))
            ->assertNotFound();
    }

    public function test_a_broker_cannot_attach_another_tenants_property_to_their_enquiry(): void
    {
        $this->actingAs($this->brokerB)->post(route('broker.leads.store'), [
            'first_name' => 'Beta',
            'phone' => '9000000002',
            'visited_property_id' => $this->propertyA->id,
            'status' => 'inquiry',
        ])->assertSessionHasNoErrors();

        $lead = Lead::where('tenant_id', $this->tenantB->id)->firstOrFail();

        $this->assertNull(
            $lead->visited_property_id,
            'A property from another tenant must not be attached even when its id is posted.'
        );
    }

    public function test_a_broker_only_sees_their_own_enquiries_not_another_tenants(): void
    {
        $response = $this->actingAs($this->brokerB)->get(route('broker.leads.index'));

        $response->assertOk();
        $response->assertDontSee('Alpha Client');
    }

    public function test_whatsapp_templates_and_logs_are_scoped_to_the_signed_in_tenant(): void
    {
        WhatsappTemplate::create([
            'tenant_id' => $this->tenantA->id,
            'event' => 'lead.created',
            'status' => null,
            'template_name' => 'alpha_only_template',
            'language_code' => 'en',
            'is_active' => true,
        ]);

        WhatsappMessage::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $this->leadA->id,
            'to_number' => '919000000001',
            'template_name' => 'alpha_only_template',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($this->adminB)->get(route('settings.whatsapp'));

        $response->assertOk();
        $response->assertDontSee('alpha_only_template');
        $response->assertDontSee('919000000001');
    }

    public function test_an_admin_cannot_open_another_tenants_lead_or_property(): void
    {
        $this->actingAs($this->adminB)->get(route('leads.show', $this->leadA))->assertNotFound();
        $this->actingAs($this->adminB)->get(route('properties.show', $this->propertyA))->assertNotFound();
        $this->actingAs($this->adminB)->get(route('properties.edit', $this->propertyA))->assertNotFound();
    }
}
