<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BrokerEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $broker;

    protected Property $shared;

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
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Admin',
            'email' => 'admin@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $this->broker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker One',
            'email' => 'broker@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $this->shared = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => '12 Baker Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
            'shared_with_all_brokers' => true,
        ]);
    }

    public function test_broker_enquiry_becomes_a_lead_attributed_to_them(): void
    {
        $this->actingAs($this->broker)
            ->post(route('broker.leads.store'), [
                'first_name' => 'Riya',
                'last_name' => 'Sharma',
                'phone' => '9876543210',
                'visited_property_id' => $this->shared->id,
                'status' => 'inquiry',
                'notes' => 'Liked the balcony, wants a second visit.',
                'photo' => UploadedFile::fake()->image('client.jpg', 40, 40),
            ])
            ->assertRedirect(route('broker.leads.index'))
            ->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();

        $this->assertSame($this->broker->id, $lead->broker_id);
        $this->assertSame($this->shared->id, $lead->visited_property_id);
        $this->assertSame('broker', $lead->lead_source);
        $this->assertNotNull($lead->created_at, 'The submission time is recorded automatically.');
        $this->assertNotNull($lead->clientPhoto);
    }

    public function test_the_lead_shows_in_the_crm_with_its_broker(): void
    {
        $this->actingAs($this->broker)->post(route('broker.leads.store'), [
            'first_name' => 'Riya',
            'phone' => '9876543210',
            'status' => 'inquiry',
        ]);

        $lead = Lead::firstOrFail();

        $this->actingAs($this->admin)->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Riya');

        $this->actingAs($this->admin)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Broker One');
    }

    public function test_broker_sees_only_their_own_enquiries(): void
    {
        $otherBroker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker Two',
            'email' => 'broker2@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->broker)->post(route('broker.leads.store'), [
            'first_name' => 'Mine', 'phone' => '1', 'status' => 'inquiry',
        ]);
        $this->actingAs($otherBroker)->post(route('broker.leads.store'), [
            'first_name' => 'Theirs', 'phone' => '2', 'status' => 'inquiry',
        ]);

        $this->actingAs($this->broker)->get(route('broker.leads.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }

    public function test_an_enquiry_cannot_reference_a_property_not_shared_with_the_broker(): void
    {
        $hidden = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => 'Hidden House',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400002',
            'property_type' => 'single_family',
        ]);

        $this->actingAs($this->broker)->post(route('broker.leads.store'), [
            'first_name' => 'Riya',
            'phone' => '9876543210',
            'visited_property_id' => $hidden->id,
            'status' => 'inquiry',
        ]);

        $this->assertNull(Lead::firstOrFail()->visited_property_id);
    }

    public function test_a_property_always_has_a_map_link(): void
    {
        $this->assertStringContainsString('google.com/maps', $this->shared->map_link);
        $this->assertStringContainsString('Baker', urldecode($this->shared->map_link));

        $this->shared->update(['map_url' => 'https://maps.app.goo.gl/abc123']);

        $this->assertSame('https://maps.app.goo.gl/abc123', $this->shared->fresh()->map_link);
    }

    public function test_a_broker_cannot_see_another_brokers_client_photo(): void
    {
        $this->actingAs($this->broker)->post(route('broker.leads.store'), [
            'first_name' => 'Riya', 'phone' => '1', 'status' => 'inquiry',
            'photo' => UploadedFile::fake()->image('client.jpg', 20, 20),
        ]);

        $otherBroker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker Two',
            'email' => 'broker2@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $lead = Lead::firstOrFail();

        $this->actingAs($this->broker)->get(route('leads.photo', $lead))->assertOk();
        $this->actingAs($otherBroker)->get(route('leads.photo', $lead))->assertForbidden();
    }
}
