<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A route's role list and the policy behind it must agree.
 *
 * Where they disagree the role gets through the door and is then refused by the
 * controller's policy — a bare Access Denied page on a link the app itself put
 * in front of them. PortalSmokeTest only fails on 500s and only signs in as an
 * admin, so a mismatch like that survives it untouched.
 *
 * The role list is read off the router rather than written down here, so a route
 * that gains a role later is covered without anyone remembering to add it.
 */
class RouteRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    /** @var array<string, User> */
    protected array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Consistency Realty',
            'slug' => 'consistency',
            'email' => 'owner@consistency.test',
            'status' => 'active',
            'business_mode' => 'realestate',
        ]);
    }

    protected function userWithRole(string $role): User
    {
        return $this->users[$role] ??= User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', $role)->whereNull('tenant_id')->value('id'),
            'name' => ucfirst($role),
            'email' => $role.'@consistency.test',
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);
    }

    /**
     * Every parameterless GET page, with the roles its `role:` middleware admits.
     *
     * @return array<string, list<string>>
     */
    protected function pagesByAdmittedRole(): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (str_contains($uri, '{') || str_starts_with($uri, '_') || str_starts_with($uri, 'internal/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                    $pages['/'.ltrim($uri, '/')] = explode(',', substr($middleware, strlen('role:')));
                }
            }
        }

        return $pages;
    }

    public function test_no_page_refuses_a_role_its_own_middleware_admits(): void
    {
        $pages = $this->pagesByAdmittedRole();

        $this->assertGreaterThan(20, count($pages), 'The route sweep found almost nothing — the filter is wrong.');

        $mismatched = [];

        foreach ($pages as $page => $roles) {
            foreach ($roles as $role) {
                // A broker is deliberately bounced to their own portal rather
                // than let into the CRM; that redirect is the subject of its own
                // test below.
                if ($role === 'broker') {
                    continue;
                }

                if ($this->actingAs($this->userWithRole($role))->get($page)->getStatusCode() === 403) {
                    $mismatched[] = "{$page} — admits {$role}, then refuses them";
                }
            }
        }

        $this->assertSame([], $mismatched, "Route middleware and policy disagree:\n".implode("\n", $mismatched));
    }

    public function test_a_broker_lands_in_their_own_portal_instead_of_a_dead_end(): void
    {
        $broker = $this->userWithRole('broker');

        // A broker has no business on a CRM screen, but the honest answer is
        // their own portal — an Access Denied page is one they cannot act on,
        // and it is what they hit today by opening a bookmarked CRM URL.
        foreach (['/leads', '/buyers', '/pipeline', '/properties'] as $page) {
            $this->actingAs($broker)->get($page)->assertRedirect(route('broker.index'));
        }
    }

    public function test_a_role_that_simply_lacks_the_privilege_still_gets_a_refusal(): void
    {
        // The broker redirect is about being in the wrong product, not about
        // softening refusals: an agent reaching for an admin-only screen must
        // still be refused rather than quietly redirected somewhere.
        $this->actingAs($this->userWithRole('agent'))->get('/tags')->assertForbidden();
    }
}
