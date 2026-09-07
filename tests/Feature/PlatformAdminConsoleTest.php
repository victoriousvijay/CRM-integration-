<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantLogo;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The platform owner's controls over a client: branding, which features their
 * plan includes, how many seats they get, and signing in as them for support.
 *
 * The gate matters as much as the controls. A tenant admin who reached any of
 * this could re-brand themselves, grant themselves features they are not paying
 * for, or sign in as another client.
 */
class PlatformAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformAdmin;

    protected Tenant $client;

    protected User $clientAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $platformTenant = $this->makeTenant('Platform', 'platform');
        $this->platformAdmin = $this->makeAdmin($platformTenant, 'owner@platform.test');
        $this->platformAdmin->forceFill(['is_platform_admin' => true])->save();

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

    /** @return array<string, mixed> */
    protected function editPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sharma Estates',
            'business_mode' => 'realestate',
        ], $overrides);
    }

    public function test_a_tenant_admin_cannot_reach_the_platform_console_at_all(): void
    {
        $this->actingAs($this->clientAdmin)
            ->get(route('platform-admin.tenants.index'))
            ->assertForbidden();

        $this->actingAs($this->clientAdmin)
            ->put(route('platform-admin.tenants.update', $this->client), $this->editPayload(['max_users' => 500]))
            ->assertForbidden();

        $this->assertNull($this->client->fresh()->max_users);
    }

    public function test_the_platform_owner_sets_which_features_a_client_gets(): void
    {
        // Unticked checkboxes send nothing at all, so the absent ones have to
        // read as "off" rather than "unchanged".
        $this->actingAs($this->platformAdmin)
            ->put(route('platform-admin.tenants.update', $this->client), $this->editPayload([
                'api_enabled' => '1',
                'broker_portal_enabled' => '1',
            ]))
            ->assertRedirect();

        $client = $this->client->fresh();

        $this->assertTrue($client->api_enabled);
        $this->assertTrue($client->broker_portal_enabled);
        $this->assertFalse($client->buyer_portal_enabled);
        $this->assertFalse($client->ai_enabled);
    }

    public function test_turning_off_the_broker_portal_closes_it_for_that_clients_brokers(): void
    {
        $broker = User::create([
            'tenant_id' => $this->client->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker',
            'email' => 'broker@sharma.test',
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);

        $this->actingAs($broker)->get(route('broker.index'))->assertOk();

        $this->client->update(['broker_portal_enabled' => false]);

        // Re-read the user: a real request loads it fresh, while the test keeps
        // handing back the same instance with its tenant relation still cached.
        $broker = $broker->fresh();

        // The whole feature goes dark together, not just the front page.
        $this->actingAs($broker)->get(route('broker.index'))->assertNotFound();
        $this->actingAs($broker)->get(route('broker.leads.index'))->assertNotFound();
        $this->actingAs($broker)->get(route('broker.leads.create'))->assertNotFound();
    }

    public function test_a_client_cannot_invite_past_the_seat_limit_the_platform_set(): void
    {
        // One admin already exists, so a limit of one is already reached.
        $this->client->update(['max_users' => 1]);

        $this->actingAs($this->clientAdmin)->post(route('settings.inviteAgent'), [
            'name' => 'Second User',
            'email' => 'second@sharma.test',
            'password' => 'password123',
            'role_id' => Role::where('name', 'agent')->whereNull('tenant_id')->value('id'),
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'second@sharma.test']);

        // Raising the limit lets the same invite through — the refusal is the
        // limit, not something wrong with the request.
        $this->client->update(['max_users' => 2]);

        $this->actingAs($this->clientAdmin->fresh())->post(route('settings.inviteAgent'), [
            'name' => 'Second User',
            'email' => 'second@sharma.test',
            'password' => 'password123',
            'role_id' => Role::where('name', 'agent')->whereNull('tenant_id')->value('id'),
        ]);

        $this->assertDatabaseHas('users', ['email' => 'second@sharma.test']);
    }

    public function test_a_logo_uploaded_by_the_platform_owner_is_stored_and_served(): void
    {
        $this->actingAs($this->platformAdmin)
            ->put(route('platform-admin.tenants.update', $this->client), $this->editPayload([
                'logo' => UploadedFile::fake()->image('sharma.png', 200, 80),
            ]))
            ->assertSessionHasNoErrors();

        $client = $this->client->fresh();

        // In the database, not on a disk: a serverless host mounts the
        // filesystem read-only, so a disk upload is lost the moment the request
        // that received it ends.
        $this->assertDatabaseHas('tenant_logos', ['tenant_id' => $client->id]);

        $url = Brand::logoFor($client);
        $this->assertNotNull($url);

        $response = $this->get($url);
        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    public function test_uploading_a_second_logo_replaces_the_first_rather_than_piling_up(): void
    {
        foreach (['one.png', 'two.png'] as $name) {
            $this->actingAs($this->platformAdmin)
                ->put(route('platform-admin.tenants.update', $this->client), $this->editPayload([
                    'logo' => UploadedFile::fake()->image($name, 120, 60),
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, TenantLogo::where('tenant_id', $this->client->id)->count());
        $this->assertSame('two.png', TenantLogo::where('tenant_id', $this->client->id)->value('filename'));
    }

    public function test_a_clients_logo_is_never_another_clients(): void
    {
        $other = $this->makeTenant('Verma Realty', 'verma');

        $this->actingAs($this->platformAdmin)
            ->put(route('platform-admin.tenants.update', $this->client), $this->editPayload([
                'logo' => UploadedFile::fake()->image('sharma.png', 120, 60),
            ]));

        $this->assertNotNull(Brand::logoFor($this->client->fresh()));
        $this->assertNull(Brand::logoFor($other->fresh()));
    }

    public function test_signing_in_as_a_client_needs_the_platform_owners_own_password(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform-admin.tenants.signInAs', $this->client), ['password' => 'wrong'])
            ->assertRedirect();

        $this->assertSame($this->platformAdmin->id, auth()->id());
    }

    public function test_the_platform_owner_can_sign_in_as_a_client_and_get_back(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform-admin.tenants.signInAs', $this->client), ['password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertSame($this->clientAdmin->id, auth()->id());
        $this->assertSame($this->platformAdmin->id, session('platform_impersonating'));

        // The return trip: the account signed in at this point is the client's
        // admin, who is not a platform admin, so the route cannot be gated on
        // that — it is gated on the session instead.
        $this->post(route('platform-admin.return'))
            ->assertRedirect(route('platform-admin.tenants.index'));

        $this->assertSame($this->platformAdmin->id, auth()->id());
        $this->assertNull(session('platform_impersonating'));
    }

    public function test_a_tenant_admin_cannot_forge_the_way_into_a_platform_account(): void
    {
        // No session key means nobody handed them this trip.
        $this->actingAs($this->clientAdmin)
            ->post(route('platform-admin.return'))
            ->assertForbidden();

        // Nor does naming a user who is not a platform admin get them anywhere.
        $this->actingAs($this->clientAdmin)
            ->withSession(['platform_impersonating' => $this->clientAdmin->id])
            ->post(route('platform-admin.return'))
            ->assertForbidden();

        $this->assertSame($this->clientAdmin->id, auth()->id());
    }
}
