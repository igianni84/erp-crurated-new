<?php

namespace App\Models\Customer;

use App\Enums\Customer\AccountUserRole;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * AccountUser Model (Pivot)
 *
 * Represents the relationship between a User and an Account.
 * A User can access multiple Accounts, and an Account can have multiple Users.
 * Each User has a specific role within each Account.
 *
 * @property string $id
 * @property string $account_id
 * @property int $user_id
 * @property AccountUserRole $role
 * @property \Carbon\Carbon|null $invited_at
 * @property \Carbon\Carbon|null $accepted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AccountUser extends Model
{
    use Auditable;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'invited_at',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AccountUserRole::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the account that belongs to this relationship.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user that belongs to this relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the audit logs for this account-user relationship.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the user is the owner of the account.
     */
    public function isOwner(): bool
    {
        return $this->role === AccountUserRole::Owner;
    }

    /**
     * Check if the user is an admin of the account.
     */
    public function isAdmin(): bool
    {
        return $this->role === AccountUserRole::Admin;
    }

    /**
     * Check if the user is an operator of the account.
     */
    public function isOperator(): bool
    {
        return $this->role === AccountUserRole::Operator;
    }

    /**
     * Check if the user is a viewer of the account.
     */
    public function isViewer(): bool
    {
        return $this->role === AccountUserRole::Viewer;
    }

    /**
     * Check if the user has been invited but hasn't accepted yet.
     */
    public function isPending(): bool
    {
        return $this->invited_at !== null && $this->accepted_at === null;
    }

    /**
     * Check if the user has accepted the invitation.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if this relationship allows user management.
     */
    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }

    /**
     * Check if this relationship allows operations.
     */
    public function canOperate(): bool
    {
        return $this->role->canOperate();
    }

    /**
     * Get the role label for UI display.
     */
    public function getRoleLabel(): string
    {
        return $this->role->label();
    }

    /**
     * Get the role color for UI display.
     */
    public function getRoleColor(): string
    {
        return $this->role->color();
    }

    /**
     * Get the role icon for UI display.
     */
    public function getRoleIcon(): string
    {
        return $this->role->icon();
    }

    /**
     * Get the status label based on invitation/acceptance state.
     */
    public function getStatusLabel(): string
    {
        if ($this->isAccepted()) {
            return 'Active';
        }

        if ($this->isPending()) {
            return 'Pending';
        }

        return 'Invited';
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        if ($this->isAccepted()) {
            return 'success';
        }

        if ($this->isPending()) {
            return 'warning';
        }

        return 'gray';
    }

    /**
     * Accept the invitation.
     */
    public function accept(): void
    {
        $this->update([
            'accepted_at' => now(),
        ]);
    }

    /**
     * Scope to get only accepted relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AccountUser>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AccountUser>
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope to get only pending (invited but not accepted) relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AccountUser>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AccountUser>
     */
    public function scopePending($query)
    {
        return $query->whereNotNull('invited_at')
            ->whereNull('accepted_at');
    }

    /**
     * Scope to get relationships with a specific role.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AccountUser>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AccountUser>
     */
    public function scopeWithRole($query, AccountUserRole $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get relationships that can manage users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AccountUser>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AccountUser>
     */
    public function scopeCanManageUsers($query)
    {
        return $query->whereIn('role', [AccountUserRole::Owner, AccountUserRole::Admin]);
    }
}
