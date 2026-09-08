<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BaseSeeder extends Seeder
{
    /**
     * Seed essential data required for the app to function.
     */
    public function run(): void
    {
        $roles = [
            'admin' => 'Admin',
            'acquisition_agent' => 'Acquisition Agent',
            'disposition_agent' => 'Disposition Agent',
            'field_scout' => 'Field Scout',
            'agent' => 'Agent',
            'listing_agent' => 'Listing Agent',
            'buyers_agent' => 'Buyers Agent',
        ];

        foreach ($roles as $name => $displayName) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => $displayName, 'is_system' => true]
            );
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $allPermissions = Permission::query()->pluck('id', 'key');
        if ($allPermissions->isEmpty()) {
            return;
        }

        // One definition, shared with the runtime fallback in Role.
        $rolePermissions = ['admin' => $allPermissions->keys()->all()]
            + Role::SYSTEM_PERMISSIONS;

        foreach ($rolePermissions as $roleName => $permissionKeys) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $permissionIds = collect($permissionKeys)
                ->map(fn (string $key) => $allPermissions[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
