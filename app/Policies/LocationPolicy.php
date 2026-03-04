<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Inventory\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Location $location): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function update(User $user, Location $location): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Location $location): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return $user->isAdmin();
    }
}
