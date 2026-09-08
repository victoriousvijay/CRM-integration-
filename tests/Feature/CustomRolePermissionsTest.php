<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A role a tenant creates must actually govern what its holders can do.
 *
 * It did not. The Roles & Permissions screen wrote to `role_permission` and
 * nothing ever read it: navigation asked "is this user an admin, or an agent,
 * or an acquisition agent…", and every route listed the role names it admitted.
 * A role called "Agency Owner" is in nobody's list, so whoever held it saw a
 * sidebar with the modules missing and got Access Denied on the rest — however
 * many permissions its creator had carefully switched on.
 *
 * The toggles are the contract now. These tests hold them to it.
 */
class CustomRolePermissionsTest extends TestCase
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
        ]);

        $this->admin = $this->userWith(
            Role::where('name', 'admin')->whereNull('tenant_id')->firstOrFail(),
            'admin@test-realty.test'
        );
    }

    /**
     * A role the tenant created, holding exactly the given permission keys.
     *
     * @param  list<string>  $permissionKeys
     */
    protected function roleWith(array $permissionKeys, string $name = 'Agency Owner'): Role
    {
        $role = Role::create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => false,
            'tenant_id' => $this->tenant->id,
        ]);

        $role->permissions()->sync(
            Permission::whereIn('key', $permissionKeys)->pluck('id')->all()
        );

        return $role->fresh();
    }

    protected function userWith(Role $role, string $email): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'name' => 'Sanjay Sharma',
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);
    }

    public function test_a_custom_role_can_open_the_modules_its_permissions_allow(): void
    {
        $role = $this->roleWith([
            'leads.view', 'leads.create', 'leads.edit',
            'properties.view',
            'deals.view',
        ]);

        $user = $this->userWith($role, 'sanjay@test-realty.test');

        $this->actingAs($user)->get('/leads')->assertOk();
        $this->actingAs($user)->get('/properties')->assertOk();
        $this->actingAs($user)->get('/pipeline')->assertOk();
    }

    public function test_a_custom_role_is_refused_the_modules_it_was_not_given(): void
    {
        $role = $this->roleWith(['leads.view']);
        $user = $this->userWith($role, 'sanjay@test-realty.test');

        // Buyers was left switched off, so it stays shut.
        $this->actingAs($user)->get('/buyers')->assertForbidden();
    }

    public function test_the_sidebar_offers_exactly_what_the_role_allows(): void
    {
        $role = $this->roleWith(['leads.view', 'properties.view']);
        $user = $this->userWith($role, 'sanjay@test-realty.test');

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        // A link the role cannot follow is worse than no link: it is an Access
        // Denied page the app itself offered.
        $this->assertStringContainsString(route('leads.index'), $html);
        $this->assertStringContainsString(route('properties.index'), $html);

        // These were shown to everybody, so a role without the permission got a
        // link straight to an Access Denied page — the inverse of the same bug.
        $this->assertStringNotContainsString(route('buyers.index'), $html);
        $this->assertStringNotContainsString(route('pipeline'), $html);
        $this->assertStringNotContainsString(route('calendar.index'), $html);
    }

    public function test_turning_a_permission_on_opens_the_module_without_touching_anything_else(): void
    {
        $role = $this->roleWith(['leads.view']);
        $user = $this->userWith($role, 'sanjay@test-realty.test');

        $this->actingAs($user)->get('/buyers')->assertForbidden();

        // Exactly what the admin does on the Roles & Permissions screen.
        $role->permissions()->syncWithoutDetaching(
            Permission::where('key', 'buyers.view')->pluck('id')->all()
        );

        $this->actingAs($user->fresh())->get('/buyers')->assertOk();
    }

    public function test_a_custom_role_with_nothing_switched_on_reaches_no_module(): void
    {
        $role = $this->roleWith([], 'Observer');
        $user = $this->userWith($role, 'observer@test-realty.test');

        foreach (['/leads', '/buyers', '/properties', '/pipeline'] as $page) {
            $this->actingAs($user)->get($page)->assertForbidden();
        }
    }

    public function test_a_custom_role_never_reaches_the_platform_console(): void
    {
        // Whatever a tenant switches on for their own role, the platform layer
        // is not theirs to reach — those permissions do not exist in their list.
        $role = $this->roleWith(Permission::pluck('key')->all(), 'Everything');
        $user = $this->userWith($role, 'everything@test-realty.test');

        $this->actingAs($user)->get(route('platform-admin.tenants.index'))->assertForbidden();
    }

    public function test_a_system_role_with_no_rows_still_behaves_as_shipped(): void
    {
        // An install whose role_permission table was never populated must keep
        // working rather than go dark, so a system role falls back to what we
        // ship for it.
        $listingAgent = Role::where('name', 'listing_agent')->whereNull('tenant_id')->firstOrFail();
        $listingAgent->permissions()->detach();

        $user = $this->userWith($listingAgent->fresh(), 'listing@test-realty.test');

        $this->actingAs($user)->get('/leads')->assertOk();
        $this->actingAs($user)->get('/buyers')->assertForbidden();
    }

    public function test_editing_a_system_role_takes_effect_including_what_was_removed(): void
    {
        $agent = Role::where('name', 'agent')->whereNull('tenant_id')->firstOrFail();

        $agent->permissions()->sync(
            Permission::whereIn('key', ['properties.view', 'profile.edit'])->pluck('id')->all()
        );

        $user = $this->userWith($agent->fresh(), 'agent@test-realty.test');

        $this->actingAs($user)->get('/properties')->assertOk();
        // Leads was taken away, so it is gone — the rows win once they exist.
        $this->actingAs($user)->get('/leads')->assertForbidden();
    }

    public function test_an_admin_is_never_restricted(): void
    {
        foreach (['/leads', '/buyers', '/properties', '/pipeline'] as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
    }
}
