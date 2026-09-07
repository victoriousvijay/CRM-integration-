<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every page in the product as each kind of user and fails on any that
 * errors.
 *
 * Individual features have their own tests; this exists to catch the pages
 * nobody thought to test — a view referencing a variable the controller stopped
 * passing, a route pointing at a method that was renamed, a relation that is
 * null for a record shaped slightly differently. Those only ever showed up when
 * someone clicked the page in production.
 *
 * A page is allowed to redirect or refuse (302/403/404). It is not allowed to
 * throw.
 */
class PortalSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $broker;

    /**
     * Routes that do something rather than show something, or that need
     * outside state this test has no way to build.
     */
    protected const SKIP = [
        'logout',
        'install.index', 'install.requirements', 'install.database',
        'install.setup', 'install.complete',
        'two-factor.challenge', 'two-factor.setup',
        'sso.redirect', 'sso.callback',
        'calendar.feed',
        'update.check', 'update.download',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BaseSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Smoke Realty',
            'slug' => 'smoke-realty',
            'email' => 'owner@smoke.test',
            'status' => 'active',
            'business_mode' => 'realestate',
        ]);

        $this->admin = $this->makeUser('admin', 'admin@smoke.test');
        $this->broker = $this->makeUser('broker', 'broker@smoke.test');

        // One of each record the pages list, so views render their populated
        // branch rather than only their empty state.
        $property = Property::create([
            'tenant_id' => $this->tenant->id,
            'address' => '12 Baker Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
            'shared_with_all_brokers' => true,
        ]);

        Lead::create([
            'tenant_id' => $this->tenant->id,
            'broker_id' => $this->broker->id,
            'visited_property_id' => $property->id,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'phone' => '9876543210',
            'lead_source' => 'broker',
            'status' => 'inquiry',
            'temperature' => 'warm',
        ]);
    }

    protected function makeUser(string $role, string $email): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', $role)->whereNull('tenant_id')->value('id'),
            'name' => ucfirst($role),
            'email' => $email,
            'password' => bcrypt('password'),
            'onboarding_completed' => true,
        ]);
    }

    /**
     * Every parameterless GET route the app exposes.
     *
     * @return list<string>
     */
    protected function pages(): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $name = $route->getName();

            if ($name !== null && in_array($name, self::SKIP, true)) {
                continue;
            }

            $uri = $route->uri();

            if (str_contains($uri, '{') || str_starts_with($uri, '_') || str_starts_with($uri, 'internal/')) {
                continue;
            }

            $pages[] = '/'.ltrim($uri, '/');
        }

        return array_values(array_unique($pages));
    }

    /**
     * @param  list<string>  $pages
     */
    protected function assertNoPageErrors(User $user, array $pages): void
    {
        $broken = [];

        foreach ($pages as $page) {
            try {
                $status = $this->actingAs($user)->get($page)->getStatusCode();
            } catch (\Throwable $e) {
                $broken[] = "{$page} — ".get_class($e).': '.$e->getMessage();

                continue;
            }

            if ($status >= 500) {
                $broken[] = "{$page} — HTTP {$status}";
            }
        }

        $this->assertSame([], $broken, "Pages errored for {$user->role->name}:\n".implode("\n", $broken));
    }

    public function test_no_page_errors_for_an_admin(): void
    {
        $pages = $this->pages();

        $this->assertGreaterThan(50, count($pages), 'The route sweep found almost nothing — the filter is wrong.');

        $this->assertNoPageErrors($this->admin, $pages);
    }

    public function test_no_errors_on_the_pages_that_open_a_single_record(): void
    {
        $property = Property::firstOrFail();
        $lead = Lead::firstOrFail();

        $this->assertNoPageErrors($this->admin, [
            route('properties.show', $property, false),
            route('properties.edit', $property, false),
            route('leads.show', $lead, false),
            route('leads.edit', $lead, false),
        ]);

        $this->assertNoPageErrors($this->broker, [
            route('broker.show', $property, false),
            route('broker.leads.create', ['property' => $property->id], false),
        ]);
    }

    public function test_no_page_errors_for_a_broker(): void
    {
        $this->assertNoPageErrors($this->broker, $this->pages());
    }

    public function test_no_page_errors_for_a_guest(): void
    {
        $broken = [];

        foreach (['/login', '/login?as=broker', '/register', '/forgot-password', '/offline', '/manifest.json'] as $page) {
            $status = $this->get($page)->getStatusCode();

            if ($status >= 500) {
                $broken[] = "{$page} — HTTP {$status}";
            }
        }

        $this->assertSame([], $broken, "Public pages errored:\n".implode("\n", $broken));
    }
}
