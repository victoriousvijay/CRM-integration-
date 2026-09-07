<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PropertyShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a broker can do in the portal beyond logging an enquiry: follow their
 * own clients up, find one among many, and send a property to someone.
 */
class BrokerPortalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $broker;

    protected User $otherBroker;

    protected Property $property;

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

        $this->broker = $this->makeBroker('broker@test-realty.test', 'Broker One');
        $this->otherBroker = $this->makeBroker('other@test-realty.test', 'Broker Two');

        $this->property = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => '12 Baker Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'list_price' => 4850000,
            'shared_with_all_brokers' => true,
        ]);
    }

    protected function makeBroker(string $email, string $name): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);
    }

    protected function makeLead(User $broker, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'broker_id' => $broker->id,
            'visited_property_id' => $this->property->id,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
            'lead_source' => 'broker',
            'status' => 'new',
            'temperature' => 'warm',
        ], $overrides));
    }

    public function test_a_broker_can_move_their_own_enquiry_forward(): void
    {
        $lead = $this->makeLead($this->broker);

        $this->actingAs($this->broker)
            ->patch(route('broker.leads.update', $lead), ['status' => 'consultation'])
            ->assertRedirect();

        $this->assertSame('consultation', $lead->fresh()->status);
    }

    public function test_a_note_is_appended_with_a_timestamp_rather_than_replacing_what_is_there(): void
    {
        $lead = $this->makeLead($this->broker, ['notes' => 'Met at the site on Sunday.']);

        $this->actingAs($this->broker)->patch(route('broker.leads.update', $lead), [
            'status' => 'consultation',
            'note' => 'Called again, visiting with family.',
        ]);

        $notes = $lead->fresh()->notes;

        // The notes are the history of the relationship, and the CRM side reads
        // them too — overwriting would silently destroy the team's record.
        $this->assertStringContainsString('Met at the site on Sunday.', $notes);
        $this->assertStringContainsString('Called again, visiting with family.', $notes);
        $this->assertStringContainsString(now()->format('d M Y'), $notes);
    }

    public function test_a_broker_cannot_touch_another_brokers_enquiry(): void
    {
        $lead = $this->makeLead($this->otherBroker);

        $this->actingAs($this->broker)
            ->patch(route('broker.leads.update', $lead), ['status' => 'consultation'])
            ->assertNotFound();

        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $lead = $this->makeLead($this->broker);

        $this->actingAs($this->broker)
            ->patch(route('broker.leads.update', $lead), ['status' => 'not-a-status'])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_searching_and_filtering_narrows_the_enquiry_list_to_the_brokers_own(): void
    {
        $this->makeLead($this->broker, ['first_name' => 'Riya', 'phone' => '9000000001']);
        $this->makeLead($this->broker, ['first_name' => 'Amit', 'phone' => '9000000002', 'status' => 'consultation']);
        $this->makeLead($this->otherBroker, ['first_name' => 'Zara', 'phone' => '9000000003']);

        $byName = $this->actingAs($this->broker)->get(route('broker.leads.index', ['search' => 'Amit']));
        $byName->assertOk();
        $byName->assertSee('Amit');
        $byName->assertDontSee('Riya');

        $byPhone = $this->actingAs($this->broker)->get(route('broker.leads.index', ['search' => '9000000001']));
        $byPhone->assertSee('Riya');
        $byPhone->assertDontSee('Amit');

        $byStatus = $this->actingAs($this->broker)->get(route('broker.leads.index', ['status' => 'consultation']));
        $byStatus->assertSee('Amit');
        $byStatus->assertDontSee('Riya');

        // A search must never widen the list past the broker's own clients.
        // Asserting the empty state rather than the absence of the name: the
        // search box echoes whatever was typed straight back into the page.
        $this->actingAs($this->broker)
            ->get(route('broker.leads.index', ['search' => 'Zara']))
            ->assertSee('No enquiries matched');
    }

    public function test_the_share_message_carries_the_details_and_a_map_link_but_no_portal_url(): void
    {
        $message = PropertyShare::message($this->property, $this->broker);

        $this->assertStringContainsString('12 Baker Street', $message);
        $this->assertStringContainsString('4,850,000', $message);
        $this->assertStringContainsString('3 beds', $message);
        $this->assertStringContainsString($this->property->map_link, $message);
        $this->assertStringContainsString('Broker One', $message);

        // The client has no login, so a link into the portal would be a dead
        // end for them — and would hand out a URL that only staff can open.
        $this->assertStringNotContainsString(route('broker.index'), $message);
    }

    public function test_a_property_with_nothing_but_an_address_still_shares_cleanly(): void
    {
        $bare = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => '4 Nowhere Lane',
            'city' => 'Jaipur',
            'state' => 'RJ',
            'zip_code' => '302012',
            'property_type' => 'single_family',
            'shared_with_all_brokers' => true,
        ]);

        $message = PropertyShare::message($bare, $this->broker);

        $this->assertStringNotContainsString("\n\n\n", $message, 'Empty sections left a run of blank lines.');
        $this->assertStringContainsString('4 Nowhere Lane', $message);
    }
}
