<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Club $club): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, Club $club): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function restore(User $user, Club $club): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function forceDelete(User $user, Club $club): bool
    {
        return $user->isAdmin();
    }
}
