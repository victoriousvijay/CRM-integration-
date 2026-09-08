<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * What each role we ship is allowed to do, out of the box.
     *
     * This is the definition of a system role, and it is also the fallback when
     * one has no rows in `role_permission` — an install that predates the
     * permissions table, or where the seeder never ran, must keep working
     * rather than go dark. A role a tenant created has no entry here and is
     * governed purely by what its creator switched on.
     *
     * `admin` is deliberately absent: it holds every permission there is, which
     * is a rule rather than a list, and the Roles screen says as much.
     *
     * @var array<string, list<string>>
     */
    public const SYSTEM_PERMISSIONS = [
        'agent' => [
            'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.export', 'leads.bulk_actions',
            'properties.view', 'properties.create', 'properties.edit',
            'deals.view', 'deals.create', 'deals.edit', 'deals.export',
            'calendar.view', 'profile.edit',
        ],
        'acquisition_agent' => [
            'leads.view', 'leads.create', 'leads.edit', 'leads.delete', 'leads.export', 'leads.bulk_actions',
            'properties.view', 'properties.create', 'properties.edit',
            'deals.view', 'deals.create', 'deals.edit', 'deals.export',
            'calendar.view', 'profile.edit',
        ],
        'disposition_agent' => [
            'deals.view', 'deals.create', 'deals.edit', 'deals.export',
            'buyers.view', 'buyers.create', 'buyers.edit', 'buyers.delete', 'buyers.export',
            'calendar.view', 'profile.edit',
        ],
        'field_scout' => [
            'properties.view', 'properties.create',
            'profile.edit',
        ],
        'listing_agent' => [
            'leads.view', 'leads.create', 'leads.edit', 'leads.export',
            'properties.view', 'properties.create', 'properties.edit',
            'deals.view', 'deals.create', 'deals.edit', 'deals.export',
            'calendar.view', 'profile.edit',
        ],
        'buyers_agent' => [
            'leads.view', 'leads.create', 'leads.edit', 'leads.export',
            'deals.view', 'deals.create', 'deals.edit', 'deals.export',
            'buyers.view', 'buyers.create', 'buyers.edit', 'buyers.export',
            'properties.view',
            'calendar.view', 'profile.edit',
        ],
        // The broker's whole product is their own portal, which is gated by the
        // tenant's broker_portal_enabled flag rather than by CRM permissions.
        'broker' => ['profile.edit'],
    ];

    protected $fillable = ['name', 'display_name', 'is_system', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * The roles a tenant may actually put someone in.
     *
     * Two rules, and they used to be applied inconsistently in three different
     * places. A system role counts only when it belongs to the tenant's business
     * mode — a real-estate agency has no Field Scout. A role the tenant created
     * themselves always counts: the business mode is about which of *our*
     * built-in roles make sense, and says nothing about theirs.
     *
     * The bug this replaces: the Add Team Member dropdown applied the mode
     * filter to every role, custom ones included, so a role you had just created
     * never appeared there — while the Roles & Permissions page applied no mode
     * filter at all and listed roles from the other mode. Two screens, two
     * answers, neither right.
     */
    public function scopeAssignableIn($query, Tenant $tenant)
    {
        $modeRoles = \App\Services\BusinessModeService::getRoles($tenant);

        return $query->where(function ($q) use ($tenant, $modeRoles) {
            $q->where(function ($system) use ($modeRoles) {
                $system->where('is_system', true)->whereIn('name', $modeRoles);
            })->orWhere('tenant_id', $tenant->id);
        });
    }

    /**
     * What to show a person for this role.
     *
     * A custom role carries the name its creator typed; a system role carries a
     * slug, and its display_name is the label we ship.
     */
    public function getLabelAttribute(): string
    {
        if (filled($this->display_name)) {
            return $this->is_system ? __($this->display_name) : $this->display_name;
        }

        return __(ucwords(str_replace('_', ' ', $this->name)));
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Check if this role has a specific permission.
     *
     * A system role with no rows of its own falls back to what we ship for it,
     * so an install whose `role_permission` table was never populated behaves
     * exactly as it always did. Once anyone edits a system role, its rows are
     * the answer — including the permissions they took away.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->permissions->isNotEmpty()) {
            return $this->permissions->contains('key', $key);
        }

        if (! $this->is_system) {
            // A custom role with nothing switched on can do nothing, which is
            // what its creator chose.
            return false;
        }

        return in_array($key, static::SYSTEM_PERMISSIONS[$this->name] ?? [], true);
    }
}
