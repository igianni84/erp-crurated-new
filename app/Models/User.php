<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Customer\AccountUserRole;
use App\Enums\UserRole;
use App\Models\Customer\Account;
use App\Models\Customer\AccountUser;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole|null $role
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // All authenticated users can access the admin panel
        return true;
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /**
     * Check if the user is an admin (super_admin or admin).
     */
    public function isAdmin(): bool
    {
        return $this->role?->hasAtLeast(UserRole::Admin) ?? false;
    }

    /**
     * Check if the user can manage users.
     */
    public function canManageUsers(): bool
    {
        return $this->role?->canManageUsers() ?? false;
    }

    /**
     * Check if the user can edit content.
     */
    public function canEdit(): bool
    {
        return $this->role?->canEdit() ?? false;
    }

    /**
     * Check if the user is read-only (viewer).
     */
    public function isViewer(): bool
    {
        return $this->role?->isReadOnly() ?? true;
    }

    /**
     * Check if the user can consume committed inventory (exceptional operation).
     *
     * This permission allows consuming inventory that is committed to voucher
     * fulfillment. This is an exceptional flow that requires explicit justification
     * and creates InventoryException records for finance & ops review.
     */
    public function canConsumeCommittedInventory(): bool
    {
        return $this->role?->canConsumeCommittedInventory() ?? false;
    }

    /**
     * Check if the user can approve price books.
     * Requires at least Manager role.
     */
    public function canApprovePriceBooks(): bool
    {
        return $this->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    /**
     * Check if the user can approve or reject memberships.
     * Requires at least Manager role.
     */
    public function canApproveMemberships(): bool
    {
        return $this->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    /**
     * Check if the user can manage payment permissions.
     * This includes modifying credit limits and authorizing bank transfers.
     * Requires at least Manager role (Finance function).
     */
    public function canManagePaymentPermissions(): bool
    {
        return $this->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    /**
     * Check if the user can manage operational blocks.
     * This includes adding and removing blocks on Customers and Accounts.
     * Requires at least Manager role (Compliance/Operations function).
     */
    public function canManageOperationalBlocks(): bool
    {
        return $this->role?->hasAtLeast(UserRole::Manager) ?? false;
    }

    /**
     * Get all accounts this user has access to.
     *
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_users')
            ->withPivot(['role', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    /**
     * Get all account-user relationships for this user.
     *
     * @return HasMany<AccountUser, $this>
     */
    public function accountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }

    /**
     * Get only accepted account-user relationships for this user.
     *
     * @return HasMany<AccountUser, $this>
     */
    public function acceptedAccountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class)
            ->whereNotNull('accepted_at');
    }

    /**
     * Get only pending (invited but not accepted) account-user relationships for this user.
     *
     * @return HasMany<AccountUser, $this>
     */
    public function pendingAccountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class)
            ->whereNotNull('invited_at')
            ->whereNull('accepted_at');
    }

    /**
     * Check if this user has access to a specific account.
     */
    public function hasAccessToAccount(Account $account): bool
    {
        return $this->accountUsers()
            ->where('account_id', $account->id)
            ->exists();
    }

    /**
     * Check if this user has accepted access to a specific account.
     */
    public function hasAcceptedAccessToAccount(Account $account): bool
    {
        return $this->acceptedAccountUsers()
            ->where('account_id', $account->id)
            ->exists();
    }

    /**
     * Get the user's role within a specific account.
     */
    public function getRoleForAccount(Account $account): ?AccountUserRole
    {
        $accountUser = $this->accountUsers()
            ->where('account_id', $account->id)
            ->first();

        return $accountUser?->role;
    }

    /**
     * Check if this user is the owner of a specific account.
     */
    public function isOwnerOfAccount(Account $account): bool
    {
        return $this->getRoleForAccount($account) === AccountUserRole::Owner;
    }

    /**
     * Check if this user can manage users on a specific account.
     */
    public function canManageUsersOnAccount(Account $account): bool
    {
        $role = $this->getRoleForAccount($account);

        return $role !== null && $role->canManageUsers();
    }

    /**
     * Check if this user can perform operations on a specific account.
     */
    public function canOperateOnAccount(Account $account): bool
    {
        $role = $this->getRoleForAccount($account);

        return $role !== null && $role->canOperate();
    }

    /**
     * Get the count of accounts this user has accepted access to.
     */
    public function getAcceptedAccountsCount(): int
    {
        return $this->acceptedAccountUsers()->count();
    }

    /**
     * Get the count of pending invitations for this user.
     */
    public function getPendingInvitationsCount(): int
    {
        return $this->pendingAccountUsers()->count();
    }
}
