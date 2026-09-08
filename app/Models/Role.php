<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
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
     */
    public function hasPermission(string $key): bool
    {
        return $this->permissions->contains('key', $key);
    }
}
