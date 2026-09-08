<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two questions, two people, one answer each.
 *
 *   What did this client buy?           the platform owner decides — modules.
 *   Who inside that client may use it?  their admin decides — role permissions.
 *
 * Effective access is both. A module the plan does not include is invisible to
 * everyone at that client, their own admin included; a module it does include
 * is still only open to the roles they grant it to. These tests hold the two
 * layers apart and prove the AND between them.
 */
class TenantModulePlanTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Sharma Estates',
            'slug' => 'sharma',
            'email' => 'owner@sharma.test',
            'status' => 'active',
            'business_mode' => 'realestate',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Client Admin',
            'email' => 'admin@sharma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);
    }

    protected function withPlan(array $modules): User
    {
        $this->tenant->update(['enabled_modules' => $modules]);

        return $this->admin->fresh();
    }

    public function test_a_client_created_before_plans_existed_keeps_everything(): void
    {
        // Null means every module, so this migration landing takes nothing away
        // from anybody.
        $this->assertNull($this->tenant->enabled_modules);

        foreach (['/leads', '/buyers', '/properties', '/pipeline'] as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
    }

    public function test_a_module_left_out_of_the_plan_is_shut_even_for_their_admin(): void
    {
        $admin = $this->withPlan(['leads', 'properties']);

        $this->actingAs($admin)->get('/leads')->assertOk();
        $this->actingAs($admin)->get('/properties')->assertOk();

        // Their admin holds every permission there is and still cannot reach a
        // module nobody sold them.
        $this->actingAs($admin)->get('/buyers')->assertForbidden();
        $this->actingAs($admin)->get('/pipeline')->assertForbidden();
    }

    public function test_the_sidebar_shows_only_what_the_plan_includes(): void
    {
        $admin = $this->withPlan(['leads']);

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(route('leads.index'), $html);
        $this->assertStringNotContainsString(route('buyers.index'), $html);
        $this->assertStringNotContainsString(route('properties.index'), $html);
    }

    public function test_a_role_cannot_be_granted_past_the_plan(): void
    {
        $this->withPlan(['leads']);

        // The client's admin switches everything on for a role of their own.
        $role = Role::create([
            'name' => 'Agency Owner',
            'display_name' => 'Agency Owner',
            'is_system' => false,
            'tenant_id' => $this->tenant->id,
        ]);
        $role->permissions()->sync(Permission::pluck('id')->all());

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'name' => 'Sanjay Sharma',
            'email' => 'sanjay@sharma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);

        // Leads is in the plan and granted, so it opens.
        $this->actingAs($user)->get('/leads')->assertOk();

        // Buyers is granted but not sold. The plan wins, or "which features
        // does this client get" would be a suggestion rather than a decision.
        $this->actingAs($user)->get('/buyers')->assertForbidden();
    }

    public function test_within_the_plan_the_clients_own_roles_still_decide(): void
    {
        $this->withPlan(['leads', 'buyers']);

        $role = Role::create([
            'name' => 'Leads Only',
            'display_name' => 'Leads Only',
            'is_system' => false,
            'tenant_id' => $this->tenant->id,
        ]);
        $role->permissions()->sync(Permission::where('key', 'leads.view')->pluck('id')->all());

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'name' => 'Riya',
            'email' => 'riya@sharma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)->get('/leads')->assertOk();
        // Sold to the client, not granted to this role.
        $this->actingAs($user)->get('/buyers')->assertForbidden();
        // ...while their admin, who holds everything, still gets in.
        $this->actingAs($this->admin->fresh())->get('/buyers')->assertOk();
    }

    public function test_a_clients_admin_can_always_reach_their_own_settings(): void
    {
        // Settings and profile are not modules: a client admin locked out of
        // their own team and roles could not run the account at all.
        $admin = $this->withPlan([]);

        $this->actingAs($admin)->get(route('settings.index'))->assertOk();
        $this->actingAs($admin)->get(route('settings.roles'))->assertOk();
    }

    public function test_the_console_writes_the_plan_the_owner_ticked(): void
    {
        $owner = User::create([
            'tenant_id' => Tenant::create([
                'name' => 'Platform HQ', 'slug' => 'hq', 'email' => 'o@hq.test',
                'status' => 'active', 'business_mode' => 'realestate',
            ])->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Owner',
            'email' => 'owner@hq.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);
        $owner->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($owner)->put(route('platform-admin.tenants.update', $this->tenant), [
            'name' => 'Sharma Estates',
            'business_mode' => 'realestate',
            'modules' => ['leads', 'calendar'],
        ])->assertRedirect();

        $this->assertSame(['leads', 'calendar'], $this->tenant->fresh()->enabled_modules);
    }

    public function test_unticking_every_module_is_read_as_none_not_as_unchanged(): void
    {
        $this->tenant->update(['enabled_modules' => ['leads']]);

        $owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Owner',
            'email' => 'owner2@hq.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'onboarding_completed' => true,
        ]);
        $owner->forceFill(['is_platform_admin' => true])->save();

        // Unticked checkboxes send nothing at all, and "nothing" here has to
        // mean none — not "leave the plan alone".
        $this->actingAs($owner)->put(route('platform-admin.tenants.update', $this->tenant), [
            'name' => 'Sharma Estates',
            'business_mode' => 'realestate',
        ])->assertRedirect();

        $this->assertSame([], $this->tenant->fresh()->enabled_modules);
    }
}
