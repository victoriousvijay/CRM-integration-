<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The platform console and the CRM are two products, not two tabs.
 *
 * They had grown together: every console page extended the CRM layout, so the
 * platform owner managing clients was looking at a sidebar of Leads, Properties
 * and Pipeline belonging to their own empty tenant, with a "search leads, deals,
 * buyers" box above it. Nothing leaked between clients, but the two levels were
 * impossible to tell apart, and that is its own kind of bug.
 *
 * These tests pin the boundary in both directions.
 */
class PortalSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformOwner;

    protected Tenant $client;

    protected User $clientAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $platformTenant = $this->makeTenant('Platform HQ', 'platform-hq');
        $this->platformOwner = $this->makeAdmin($platformTenant, 'owner@platform-hq.test');
        $this->platformOwner->forceFill(['is_platform_admin' => true])->save();

        $this->client = $this->makeTenant('Sharma Estates', 'sharma');
        $this->clientAdmin = $this->makeAdmin($this->client, 'admin@sharma.test');
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

    protected function makeAdmin(Tenant $tenant, string $email): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Admin '.$tenant->slug,
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);
    }

    public function test_the_console_carries_none_of_the_crm_navigation(): void
    {
        $html = $this->actingAs($this->platformOwner)
            ->get(route('platform-admin.tenants.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Platform Console', $html);

        // The CRM's own sidebar and search have no business on a console screen.
        foreach (['Pipeline', 'Activity Feed', 'Search leads', 'Open Houses'] as $crmChrome) {
            $this->assertStringNotContainsString(
                $crmChrome,
                $html,
                "The console is still rendering the CRM's \"{$crmChrome}\" chrome."
            );
        }
    }

    public function test_signing_in_lands_the_platform_owner_in_the_console_not_the_crm(): void
    {
        $this->post(route('login'), [
            'email' => 'owner@platform-hq.test',
            'password' => 'password',
        ])->assertRedirect(route('platform-admin.tenants.index'));
    }

    public function test_signing_in_lands_a_client_admin_in_their_crm(): void
    {
        $this->post(route('login'), [
            'email' => 'admin@sharma.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }

    public function test_a_client_admin_sees_no_sign_of_the_platform_layer(): void
    {
        $html = $this->actingAs($this->clientAdmin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('Platform Console', $html);
        $this->assertStringNotContainsString(route('platform-admin.tenants.index'), $html);
        // The product name in the "Powered by" footer is deliberate; the
        // platform *tenant* appearing on a client's screen would not be.
        $this->assertStringNotContainsString('Platform HQ', $html);
    }

    public function test_the_owner_keeps_a_way_back_to_the_console_from_their_own_crm(): void
    {
        $this->actingAs($this->platformOwner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('platform-admin.tenants.index'), false);
    }

    public function test_the_switcher_lists_every_client_but_never_the_owners_own_tenant(): void
    {
        $second = $this->makeTenant('Verma Realty', 'verma');

        $response = $this->actingAs($this->platformOwner)
            ->get(route('platform-admin.tenants.index'))
            ->assertOk();

        // Read off the rendered switcher: a view composer's data belongs to the
        // layout, not to the response's own view data.
        $html = $response->getContent();

        $this->assertStringContainsString('value="'.$this->client->id.'">', $html);
        $this->assertStringContainsString('value="'.$second->id.'">', $html);
        $this->assertStringNotContainsString(
            'value="'.$this->platformOwner->tenant_id.'">',
            $html,
            'The switcher offered the owner their own tenant, which signs them in as themselves.'
        );
    }

    public function test_the_console_never_shows_a_clients_actual_records(): void
    {
        Lead::create([
            'tenant_id' => $this->client->id,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
            'lead_source' => 'website',
            'status' => 'new',
            'temperature' => 'warm',
        ]);

        $html = $this->actingAs($this->platformOwner)
            ->get(route('platform-admin.tenants.show', $this->client))
            ->assertOk()
            ->getContent();

        // Counts are the platform owner's business; the client's actual people
        // are not, and the console must never become a back door to them.
        $this->assertStringContainsString('Sharma Estates', $html);
        $this->assertStringNotContainsString('Riya', $html);
        $this->assertStringNotContainsString('9876543210', $html);
    }

    public function test_entering_a_client_through_the_switcher_shows_their_crm_and_the_way_back(): void
    {
        // Through the switcher's own route, the one the console's control posts to.
        $this->actingAs($this->platformOwner)
            ->post(route('platform-admin.switch'), [
                'tenant' => $this->client->id,
                'password' => 'password',
            ])
            ->assertRedirect('/dashboard');

        $html = $this->get('/dashboard')->assertOk()->getContent();

        // Their brand, their CRM — plus the banner out, which is the one thing
        // that marks this as a platform session rather than a normal login.
        $this->assertStringContainsString('Sharma Estates', $html);
        $this->assertStringContainsString(route('platform-admin.return'), $html);
    }

    public function test_the_switcher_refuses_the_owners_own_tenant_even_when_posted_directly(): void
    {
        $this->actingAs($this->platformOwner)
            ->post(route('platform-admin.switch'), [
                'tenant' => $this->platformOwner->tenant_id,
                'password' => 'password',
            ])
            ->assertRedirect();

        $this->assertSame($this->platformOwner->id, auth()->id());
    }

    public function test_the_switcher_still_needs_the_owners_password(): void
    {
        $this->actingAs($this->platformOwner)
            ->post(route('platform-admin.switch'), [
                'tenant' => $this->client->id,
                'password' => 'not-the-password',
            ])
            ->assertRedirect();

        $this->assertSame($this->platformOwner->id, auth()->id());
    }

    public function test_a_client_admin_cannot_use_the_switcher_to_reach_another_client(): void
    {
        $this->actingAs($this->clientAdmin)
            ->post(route('platform-admin.switch'), [
                'tenant' => $this->platformOwner->tenant_id,
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertSame($this->clientAdmin->id, auth()->id());
    }
}
