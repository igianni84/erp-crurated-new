<?php

namespace App\Policies;

use App\Models\Customer\Account;
use App\Models\User;

/**
 * Policy for Account model authorization.
 *
 * Defines access control rules for viewing, updating, and deleting accounts,
 * as well as managing users within accounts.
 */
class AccountPolicy
{
    /**
     * Determine if the user can view any accounts.
     *
     * All authenticated users can view the account list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the account.
     *
     * All authenticated users can view account details.
     * In the future, this could be restricted to users with access to the account.
     */
    public function view(User $user, Account $account): bool
    {
        return true;
    }

    /**
     * Determine if the user can create accounts.
     *
     * Requires at least Editor role.
     */
    public function create(User $user): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can update the account.
     *
     * Requires at least Editor role, OR the user has an account-level role that can operate.
     */
    public function update(User $user, Account $account): bool
    {
        // Admin/Editor users can always update
        if ($user->canEdit()) {
            return true;
        }

        // Check if user has account-level permissions to update
        return $account->canUserOperate($user);
    }

    /**
     * Determine if the user can delete the account.
     *
     * Requires at least Manager role for deletion due to data integrity concerns.
     * Account owners can also delete their own accounts.
     */
    public function delete(User $user, Account $account): bool
    {
        // Managers and above can delete any account
        if ($user->role?->hasAtLeast(\App\Enums\UserRole::Manager) ?? false) {
            return true;
        }

        // Account owners can delete their own accounts
        return $account->isOwner($user);
    }

    /**
     * Determine if the user can restore a soft-deleted account.
     *
     * Requires at least Manager role.
     */
    public function restore(User $user, Account $account): bool
    {
        return $user->role?->hasAtLeast(\App\Enums\UserRole::Manager) ?? false;
    }

    /**
     * Determine if the user can permanently delete the account.
     *
     * Requires Admin role for permanent deletion.
     */
    public function forceDelete(User $user, Account $account): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage users on the account.
     *
     * This includes inviting users, changing roles, and removing users.
     * Requires at least Manager role (admin staff), OR account-level Owner/Admin role.
     */
    public function manageUsers(User $user, Account $account): bool
    {
        // Admin staff with Manager+ role can manage any account's users
        if ($user->role?->hasAtLeast(\App\Enums\UserRole::Manager) ?? false) {
            return true;
        }

        // Check if user has account-level permissions to manage users
        return $account->canUserManageUsers($user);
    }

    /**
     * Determine if the user can suspend the account.
     *
     * Requires at least Editor role.
     */
    public function suspend(User $user, Account $account): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can activate the account.
     *
     * Requires at least Editor role.
     */
    public function activate(User $user, Account $account): bool
    {
        return $user->canEdit();
    }

    /**
     * Determine if the user can manage operational blocks on the account.
     *
     * Requires at least Manager role (Compliance/Operations function).
     */
    public function manageBlocks(User $user, Account $account): bool
    {
        return $user->canManageOperationalBlocks();
    }
}
