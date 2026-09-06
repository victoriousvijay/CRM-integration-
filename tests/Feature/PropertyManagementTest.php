<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
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
        ]);
    }

    /** @return array<string, mixed> */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'address' => '12 Baker Street',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip_code' => '400001',
            'property_type' => 'single_family',
            'bedrooms' => 3,
            'bathrooms' => 2,
        ], $overrides);
    }

    public function test_admin_can_create_a_standalone_property(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('properties.store'), $this->validPayload());

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('properties', [
            'tenant_id' => $this->tenant->id,
            'city' => 'Mumbai',
            'lead_id' => null,
        ]);
    }

    public function test_creating_a_property_stores_uploaded_photos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('properties.store'), $this->validPayload([
                'images' => [UploadedFile::fake()->image('front.jpg', 40, 30)],
            ]))
            ->assertSessionHasNoErrors();

        $property = Property::firstOrFail();

        $this->assertSame(1, $property->images()->count());
        $this->assertTrue($property->images()->first()->is_primary);
    }

    public function test_property_can_be_shared_with_all_brokers(): void
    {
        $this->actingAs($this->admin)
            ->post(route('properties.store'), $this->validPayload(['broker_access' => 'all']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Property::firstOrFail()->shared_with_all_brokers);
    }

    public function test_property_can_be_shared_with_named_brokers_only(): void
    {
        $broker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker One',
            'email' => 'broker@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('properties.store'), $this->validPayload([
                'broker_access' => 'selected',
                'broker_ids' => [$broker->id],
            ]))
            ->assertSessionHasNoErrors();

        $property = Property::firstOrFail();

        $this->assertFalse($property->shared_with_all_brokers);
        $this->assertTrue($property->brokers()->where('users.id', $broker->id)->exists());
    }

    public function test_broker_only_sees_properties_shared_with_them(): void
    {
        $broker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker One',
            'email' => 'broker@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $shared = Property::create($this->validPayload([
            'tenant_id' => $this->tenant->id,
            'address' => 'Shared House',
            'shared_with_all_brokers' => true,
        ]));

        $hidden = Property::create($this->validPayload([
            'tenant_id' => $this->tenant->id,
            'address' => 'Hidden House',
        ]));

        $response = $this->actingAs($broker)->get(route('broker.index'));

        $response->assertOk();
        $response->assertSee('Shared House');
        $response->assertDontSee('Hidden House');

        $this->actingAs($broker)->get(route('broker.show', $hidden))->assertNotFound();
    }

    public function test_broker_cannot_fetch_an_image_of_an_unshared_property(): void
    {
        $broker = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'broker')->whereNull('tenant_id')->value('id'),
            'name' => 'Broker One',
            'email' => 'broker@test-realty.test',
            'password' => bcrypt('password'),
        ]);

        $hidden = Property::create($this->validPayload([
            'tenant_id' => $this->tenant->id,
            'address' => 'Hidden House',
        ]));

        $image = PropertyImage::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $hidden->id,
            'filename' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3,
            'content' => base64_encode('abc'),
        ]);

        $this->actingAs($broker)->get(route('properties.images.show', $image))->assertForbidden();
    }
}
