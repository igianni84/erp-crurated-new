<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer\Party;
use App\Models\User;

class PartyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Party $party): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    public function update(User $user, Party $party): bool
    {
        return $user->canEdit();
    }

    public function delete(User $user, Party $party): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function restore(User $user, Party $party): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function forceDelete(User $user, Party $party): bool
    {
        return $user->isAdmin();
    }
}
