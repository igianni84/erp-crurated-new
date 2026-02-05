<?php

namespace App\Models\Customer;

use App\Enums\Customer\AccountStatus;
use App\Enums\Customer\AccountUserRole;
use App\Enums\Customer\ChannelScope;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Account Model
 *
 * Represents an operational context for a Customer.
 * A Customer can have multiple Accounts (e.g., different channel scopes).
 * Account inherits restrictions from Customer but can add its own.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $name
 * @property ChannelScope $channel_scope
 * @property AccountStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Account extends Model
{
    use Auditable;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'channel_scope',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_scope' => ChannelScope::class,
            'status' => AccountStatus::class,
        ];
    }

    /**
     * Get the customer that this account belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the audit logs for this account.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the account is active.
     */
    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * Check if the account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === AccountStatus::Suspended;
    }

    /**
     * Check if the account is B2C scope.
     */
    public function isB2CScope(): bool
    {
        return $this->channel_scope === ChannelScope::B2C;
    }

    /**
     * Check if the account is B2B scope.
     */
    public function isB2BScope(): bool
    {
        return $this->channel_scope === ChannelScope::B2B;
    }

    /**
     * Check if the account is Club scope.
     */
    public function isClubScope(): bool
    {
        return $this->channel_scope === ChannelScope::Club;
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the channel scope color for UI display.
     */
    public function getChannelScopeColor(): string
    {
        return $this->channel_scope->color();
    }

    /**
     * Get the channel scope label for UI display.
     */
    public function getChannelScopeLabel(): string
    {
        return $this->channel_scope->label();
    }

    /**
     * Get all operational blocks for this account.
     *
     * @return MorphMany<OperationalBlock, $this>
     */
    public function operationalBlocks(): MorphMany
    {
        return $this->morphMany(OperationalBlock::class, 'blockable');
    }

    /**
     * Get only active operational blocks for this account.
     *
     * @return MorphMany<OperationalBlock, $this>
     */
    public function activeOperationalBlocks(): MorphMany
    {
        return $this->morphMany(OperationalBlock::class, 'blockable')
            ->where('status', \App\Enums\Customer\BlockStatus::Active);
    }

    /**
     * Check if the account has any active operational blocks.
     */
    public function hasActiveBlocks(): bool
    {
        return $this->activeOperationalBlocks()->exists();
    }

    /**
     * Check if the account has an active block of a specific type.
     */
    public function hasActiveBlockOfType(\App\Enums\Customer\BlockType $type): bool
    {
        return $this->activeOperationalBlocks()
            ->where('block_type', $type)
            ->exists();
    }

    /**
     * Check if the account has any critical active blocks (payment or compliance).
     */
    public function hasCriticalBlocks(): bool
    {
        return $this->activeOperationalBlocks()
            ->whereIn('block_type', [
                \App\Enums\Customer\BlockType::Payment,
                \App\Enums\Customer\BlockType::Compliance,
            ])
            ->exists();
    }

    /**
     * Get the count of active operational blocks.
     */
    public function getActiveBlocksCount(): int
    {
        return $this->activeOperationalBlocks()->count();
    }

    /**
     * Check if a specific operation is blocked for this account.
     * Checks against active operational blocks and their blocked operations.
     * Also checks parent Customer's blocks.
     *
     * @param  string  $operation  The operation to check (e.g., 'payment', 'shipment', 'redemption', 'trading')
     */
    public function isOperationBlocked(string $operation): bool
    {
        // Check account-level blocks
        $activeBlocks = $this->activeOperationalBlocks()->get();

        foreach ($activeBlocks as $block) {
            if (in_array($operation, $block->block_type->blockedOperations(), true)) {
                return true;
            }
        }

        // Also check customer-level blocks (inherited)
        return $this->customer->isOperationBlocked($operation);
    }

    /**
     * Check if payments are blocked for this account.
     * Returns true if there's an active Payment or Compliance block on this account or its customer.
     */
    public function hasPaymentOperationBlocked(): bool
    {
        return $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Payment)
            || $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Compliance)
            || $this->customer->hasPaymentOperationBlocked();
    }

    /**
     * Check if shipments are blocked for this account.
     * Returns true if there's an active Shipment or Compliance block on this account or its customer.
     */
    public function hasShipmentOperationBlocked(): bool
    {
        return $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Shipment)
            || $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Compliance)
            || $this->customer->hasShipmentOperationBlocked();
    }

    /**
     * Check if redemptions are blocked for this account.
     * Returns true if there's an active Redemption or Compliance block on this account or its customer.
     */
    public function hasRedemptionOperationBlocked(): bool
    {
        return $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Redemption)
            || $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Compliance)
            || $this->customer->hasRedemptionOperationBlocked();
    }

    /**
     * Check if trading is blocked for this account.
     * Returns true if there's an active Trading or Compliance block on this account or its customer.
     */
    public function hasTradingOperationBlocked(): bool
    {
        return $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Trading)
            || $this->hasActiveBlockOfType(\App\Enums\Customer\BlockType::Compliance)
            || $this->customer->hasTradingOperationBlocked();
    }

    /**
     * Get all users with access to this account.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot(['role', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    /**
     * Get all account-user relationships for this account.
     *
     * @return HasMany<AccountUser, $this>
     */
    public function accountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }

    /**
     * Get only accepted account-user relationships.
     *
     * @return HasMany<AccountUser, $this>
     */
    public function acceptedAccountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class)
            ->whereNotNull('accepted_at');
    }

    /**
     * Get only pending (invited but not accepted) account-user relationships.
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
     * Check if a user has access to this account.
     */
    public function hasUser(User $user): bool
    {
        return $this->accountUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Check if a user has accepted access to this account.
     */
    public function hasAcceptedUser(User $user): bool
    {
        return $this->acceptedAccountUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Get the account-user relationship for a specific user.
     */
    public function getAccountUser(User $user): ?AccountUser
    {
        return $this->accountUsers()
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Get the role of a user within this account.
     */
    public function getUserRole(User $user): ?AccountUserRole
    {
        $accountUser = $this->getAccountUser($user);

        return $accountUser?->role;
    }

    /**
     * Check if a user is the owner of this account.
     */
    public function isOwner(User $user): bool
    {
        return $this->getUserRole($user) === AccountUserRole::Owner;
    }

    /**
     * Check if a user can manage users on this account.
     */
    public function canUserManageUsers(User $user): bool
    {
        $role = $this->getUserRole($user);

        return $role !== null && $role->canManageUsers();
    }

    /**
     * Check if a user can perform operations on this account.
     */
    public function canUserOperate(User $user): bool
    {
        $role = $this->getUserRole($user);

        return $role !== null && $role->canOperate();
    }

    /**
     * Get the owner of this account.
     */
    public function getOwner(): ?User
    {
        $ownerRelation = $this->accountUsers()
            ->where('role', AccountUserRole::Owner)
            ->first();

        return $ownerRelation?->user;
    }

    /**
     * Get the count of accepted users on this account.
     */
    public function getAcceptedUsersCount(): int
    {
        return $this->acceptedAccountUsers()->count();
    }

    /**
     * Get the count of pending invitations on this account.
     */
    public function getPendingInvitationsCount(): int
    {
        return $this->pendingAccountUsers()->count();
    }
}
