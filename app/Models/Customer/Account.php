<?php

namespace App\Models\Customer;

use App\Enums\Customer\AccountStatus;
use App\Enums\Customer\ChannelScope;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
