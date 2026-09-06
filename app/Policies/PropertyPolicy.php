<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent() || $user->isFieldScout();
    }

    public function view(User $user, Property $property): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isAdmin() || $user->isAcquisitionAgent() || $user->isFieldScout()) {
            return true;
        }

        return $property->lead_id === null || $property->lead?->agent_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function createFieldScout(User $user): bool
    {
        return $user->isAdmin() || $user->isFieldScout();
    }

    public function update(User $user, Property $property): bool
    {
        return $this->create($user) && $this->view($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        // Only admins remove records outright; agents edit but don't delete,
        // and a property attached to a lead is part of that lead's history.
        return $user->isAdmin() && $property->lead_id === null;
    }
}
