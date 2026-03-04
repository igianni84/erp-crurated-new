<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Commercial\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Channel $channel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Channel $channel): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Channel $channel): bool
    {
        return $user->isAdmin();
    }
}
