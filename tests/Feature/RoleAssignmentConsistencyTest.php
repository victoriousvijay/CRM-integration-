<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Roles page and the Add Team Member dropdown must agree.
 *
 * They did not: the dropdown ran the business-mode filter over every role,
 * custom ones included, so a role an admin had just created never appeared
 * there — while the Roles page ran no mode filter at all and listed roles from
 * the other business mode. Same question, two screens, two wrong answers.
 */
class RoleAssignmentConsistencyTest extends TestCase
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

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->whereNull('tenant_id')->value('id'),
            'name' => 'Admin',
            'email' => 'admin@test-realty.test',
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);
    }

    protected function customRole(string $name = 'Site Supervisor'): Role
    {
        return Role::create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => false,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_a_role_the_tenant_created_is_offered_when_adding_a_team_member(): void
    {
        $role = $this->customRole();

        $response = $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'team']));

        $response->assertOk();
        $response->assertSee('Site Supervisor');
        $response->assertSee('value="'.$role->id.'"', false);
    }

    public function test_someone_can_actually_be_given_that_role(): void
    {
        $role = $this->customRole();

        $this->actingAs($this->admin)->post(route('settings.inviteAgent'), [
            'name' => 'Sanjay Sharma',
            'email' => 'sanjay@test-realty.test',
            'password' => 'password123',
            'role_id' => $role->id,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sanjay@test-realty.test',
            'role_id' => $role->id,
        ]);
    }

    public function test_both_screens_offer_exactly_the_same_roles(): void
    {
        $this->customRole();

        $onRolesPage = $this->rolesShownOn(route('settings.roles'));
        $onTeamTab = $this->rolesShownOn(route('settings.index', ['tab' => 'team']));

        $this->assertNotEmpty($onRolesPage);
        $this->assertSame(
            $onRolesPage,
            $onTeamTab,
            'The Roles page and the Add Team Member dropdown disagree about which roles exist.'
        );
    }

    /**
     * The role ids a page was given to render.
     *
     * Taken from the view data of each page rather than scraped out of its
     * HTML: the bug was that the two controllers computed different lists, and
     * an id like "2" appears in plenty of unrelated markup on a settings page.
     * Test one above covers the dropdown actually rendering what it is handed.
     *
     * @return list<int>
     */
    protected function rolesShownOn(string $url): array
    {
        return $this->actingAs($this->admin)->get($url)->assertOk()
            ->viewData('roles')
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    public function test_a_system_role_from_the_other_business_mode_is_offered_on_neither(): void
    {
        // Field Scout is a wholesale role; this tenant is a real-estate agency.
        $fieldScout = Role::where('name', 'field_scout')->whereNull('tenant_id')->firstOrFail();

        $this->assertFalse(
            Role::assignableIn($this->tenant)->where('roles.id', $fieldScout->id)->exists(),
            'A role from the other business mode should not be assignable.'
        );

        $this->actingAs($this->admin)->post(route('settings.inviteAgent'), [
            'name' => 'Wrong Mode',
            'email' => 'wrong@test-realty.test',
            'password' => 'password123',
            'role_id' => $fieldScout->id,
        ])->assertSessionHasErrors('role_id');
    }

    public function test_another_tenants_custom_role_is_never_assignable(): void
    {
        $other = Tenant::create([
            'name' => 'Other Realty',
            'slug' => 'other-realty',
            'email' => 'owner@other.test',
            'status' => 'active',
            'business_mode' => 'realestate',
        ]);

        $theirs = Role::create([
            'name' => 'Their Role',
            'display_name' => 'Their Role',
            'is_system' => false,
            'tenant_id' => $other->id,
        ]);

        $this->actingAs($this->admin)->post(route('settings.inviteAgent'), [
            'name' => 'Nope',
            'email' => 'nope@test-realty.test',
            'password' => 'password123',
            'role_id' => $theirs->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'nope@test-realty.test']);
    }

    public function test_a_custom_role_keeps_the_name_its_creator_typed(): void
    {
        $role = $this->customRole('Site Supervisor');

        // Not slugified and re-title-cased like a system role's name would be.
        $this->assertSame('Site Supervisor', $role->label);

        $systemAdmin = Role::where('name', 'admin')->whereNull('tenant_id')->firstOrFail();
        $this->assertSame('Admin', $systemAdmin->label);
    }
}
