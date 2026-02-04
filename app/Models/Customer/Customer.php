<?php

namespace App\Models\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer Model
 *
 * Represents a customer as a specialization of Party.
 * A Customer is created automatically when a Party receives the customer role.
 *
 * @property string $id
 * @property string|null $party_id
 * @property string|null $name (legacy field, deprecated - use party.legal_name)
 * @property string|null $email (legacy field, deprecated - use contact system)
 * @property CustomerType $customer_type
 * @property CustomerStatus $status
 * @property string|null $default_billing_address_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Customer extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * Legacy status constants for backward compatibility.
     *
     * @deprecated Use CustomerStatus enum instead
     */
    public const STATUS_PROSPECT = 'prospect';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'name',
        'email',
        'customer_type',
        'status',
        'default_billing_address_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_type' => CustomerType::class,
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * Get the party that this customer belongs to.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Get the vouchers owned by this customer.
     *
     * @return HasMany<\App\Models\Allocation\Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(\App\Models\Allocation\Voucher::class);
    }

    /**
     * Get the case entitlements owned by this customer.
     *
     * @return HasMany<\App\Models\Allocation\CaseEntitlement, $this>
     */
    public function caseEntitlements(): HasMany
    {
        return $this->hasMany(\App\Models\Allocation\CaseEntitlement::class);
    }

    /**
     * Get the accounts for this customer.
     *
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get the addresses for this customer.
     *
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the billing addresses for this customer.
     *
     * @return MorphMany<Address, $this>
     */
    public function billingAddresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')
            ->where('type', \App\Enums\Customer\AddressType::Billing);
    }

    /**
     * Get the shipping addresses for this customer.
     *
     * @return MorphMany<Address, $this>
     */
    public function shippingAddresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')
            ->where('type', \App\Enums\Customer\AddressType::Shipping);
    }

    /**
     * Get the default billing address for this customer.
     */
    public function getDefaultBillingAddress(): ?Address
    {
        return $this->billingAddresses()->where('is_default', true)->first();
    }

    /**
     * Get the default shipping address for this customer.
     */
    public function getDefaultShippingAddress(): ?Address
    {
        return $this->shippingAddresses()->where('is_default', true)->first();
    }

    /**
     * Check if the customer has at least one billing address.
     */
    public function hasBillingAddress(): bool
    {
        return $this->billingAddresses()->exists();
    }

    /**
     * Check if the customer has at least one shipping address.
     */
    public function hasShippingAddress(): bool
    {
        return $this->shippingAddresses()->exists();
    }

    /**
     * Get all memberships for this customer (historical and current).
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get the current/active membership for this customer.
     * Returns the most recent membership record.
     *
     * @return HasOne<Membership, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class)->latestOfMany();
    }

    /**
     * Get the active membership for this customer (approved and within effective dates).
     *
     * @return HasOne<Membership, $this>
     */
    public function activeMembership(): HasOne
    {
        return $this->hasOne(Membership::class)
            ->where('status', \App\Enums\Customer\MembershipStatus::Approved)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->latestOfMany();
    }

    /**
     * Check if the customer has an active membership.
     */
    public function hasActiveMembership(): bool
    {
        return $this->activeMembership()->exists();
    }

    /**
     * Get the current membership tier, or null if no membership exists.
     */
    public function getMembershipTier(): ?\App\Enums\Customer\MembershipTier
    {
        return $this->membership?->tier;
    }

    /**
     * Get the current membership status, or null if no membership exists.
     */
    public function getMembershipStatus(): ?\App\Enums\Customer\MembershipStatus
    {
        return $this->membership?->status;
    }

    /**
     * Get the audit logs for this customer.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the customer is a prospect.
     */
    public function isProspect(): bool
    {
        return $this->status === CustomerStatus::Prospect;
    }

    /**
     * Check if the customer is active.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Check if the customer is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::Suspended;
    }

    /**
     * Check if the customer is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === CustomerStatus::Closed;
    }

    /**
     * Check if the customer is B2C.
     */
    public function isB2C(): bool
    {
        return $this->customer_type === CustomerType::B2C;
    }

    /**
     * Check if the customer is B2B.
     */
    public function isB2B(): bool
    {
        return $this->customer_type === CustomerType::B2B;
    }

    /**
     * Check if the customer is a partner type.
     */
    public function isPartnerType(): bool
    {
        return $this->customer_type === CustomerType::Partner;
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
     * Get the customer type color for UI display.
     */
    public function getCustomerTypeColor(): string
    {
        return $this->customer_type->color();
    }

    /**
     * Get the customer type label for UI display.
     */
    public function getCustomerTypeLabel(): string
    {
        return $this->customer_type->label();
    }

    /**
     * Get the customer's display name.
     * Prefers party.legal_name, falls back to legacy name field.
     */
    public function getName(): string
    {
        if ($this->party !== null) {
            return $this->party->legal_name;
        }

        return $this->name ?? 'Unknown';
    }

    /**
     * Check if the customer's membership tier allows access to a channel.
     * This is the base tier eligibility check - other factors may restrict access.
     *
     * @param  \App\Enums\Customer\ChannelScope  $channel
     */
    public function isMembershipEligibleForChannel($channel): bool
    {
        $membership = $this->activeMembership;

        if ($membership === null) {
            return false;
        }

        return $membership->isEligibleForChannel($channel);
    }

    /**
     * Check if the customer has automatic club access via their membership tier.
     */
    public function hasAutomaticClubAccess(): bool
    {
        $membership = $this->activeMembership;

        if ($membership === null) {
            return false;
        }

        return $membership->hasAutomaticClubAccess();
    }

    /**
     * Check if the customer has access to exclusive/invitation-only products.
     */
    public function hasExclusiveProductAccess(): bool
    {
        $membership = $this->activeMembership;

        if ($membership === null) {
            return false;
        }

        return $membership->hasExclusiveProductAccess();
    }

    /**
     * Get channel eligibility reasons based on membership tier and status.
     * Returns human-readable explanations for each channel.
     *
     * @return array<string, array{eligible: bool, reason: string}>|null
     */
    public function getMembershipChannelEligibilityReasons(): ?array
    {
        $membership = $this->membership;

        if ($membership === null) {
            return null;
        }

        return $membership->getChannelEligibilityReasons();
    }

    /**
     * Get the payment permission for this customer.
     *
     * @return HasOne<PaymentPermission, $this>
     */
    public function paymentPermission(): HasOne
    {
        return $this->hasOne(PaymentPermission::class);
    }

    /**
     * Check if the customer has a payment permission record.
     */
    public function hasPaymentPermission(): bool
    {
        return $this->paymentPermission()->exists();
    }

    /**
     * Check if the customer has any payment restrictions.
     * Returns true if card payments are blocked.
     */
    public function hasPaymentBlock(): bool
    {
        $permission = $this->paymentPermission;

        if ($permission === null) {
            // No permission record means default permissions (no block)
            return false;
        }

        return $permission->hasPaymentBlock();
    }

    /**
     * Check if the customer has approved credit (credit_limit is set).
     */
    public function hasCreditApproved(): bool
    {
        $permission = $this->paymentPermission;

        if ($permission === null) {
            // No permission record means no credit approved
            return false;
        }

        return $permission->hasCreditApproved();
    }

    /**
     * Get the customer's credit limit, or null if not set.
     */
    public function getCreditLimit(): ?float
    {
        return $this->paymentPermission?->getCreditLimitAmount();
    }

    /**
     * Check if card payments are allowed for this customer.
     */
    public function isCardAllowed(): bool
    {
        $permission = $this->paymentPermission;

        if ($permission === null) {
            // Default: card payments allowed
            return true;
        }

        return $permission->isCardAllowed();
    }

    /**
     * Check if bank transfer payments are allowed for this customer.
     */
    public function isBankTransferAllowed(): bool
    {
        $permission = $this->paymentPermission;

        if ($permission === null) {
            // Default: bank transfer not allowed
            return false;
        }

        return $permission->isBankTransferAllowed();
    }
}
