<?php

namespace App\Models\Commercial;

use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * OfferEligibility Model
 *
 * Defines the eligibility conditions for an Offer.
 * Determines who can access and purchase the offer.
 *
 * Eligibility rules:
 * - allowed_markets: Specific markets where the offer is available
 * - allowed_customer_types: Types of customers who can access the offer
 * - allowed_membership_tiers: Membership levels required (optional)
 * - allocation_constraint_id: Reference to authoritative allocation constraint
 *
 * Important: Eligibility cannot override allocation constraints from Module A.
 * The allocation_constraint_id references the constraint that takes precedence.
 *
 * @property string $id
 * @property string $offer_id
 * @property array<int, string>|null $allowed_markets
 * @property array<int, string>|null $allowed_customer_types
 * @property array<int, string>|null $allowed_membership_tiers
 * @property string|null $allocation_constraint_id
 */
class OfferEligibility extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'offer_eligibilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'offer_id',
        'allowed_markets',
        'allowed_customer_types',
        'allowed_membership_tiers',
        'allocation_constraint_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_markets' => 'array',
            'allowed_customer_types' => 'array',
            'allowed_membership_tiers' => 'array',
        ];
    }

    /**
     * Get the offer that owns this eligibility.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the audit logs for this offer eligibility.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Market Restriction Helpers
    // =========================================================================

    /**
     * Check if the eligibility has market restrictions.
     */
    public function hasMarketRestrictions(): bool
    {
        return ! empty($this->allowed_markets);
    }

    /**
     * Check if a market is eligible.
     */
    public function isMarketEligible(string $market): bool
    {
        if (! $this->hasMarketRestrictions()) {
            return true;
        }

        return in_array($market, $this->allowed_markets ?? [], true);
    }

    /**
     * Get the allowed markets count.
     */
    public function getMarketsCount(): int
    {
        return count($this->allowed_markets ?? []);
    }

    /**
     * Get the markets display string.
     */
    public function getMarketsDisplayString(): string
    {
        if (! $this->hasMarketRestrictions()) {
            return 'All markets';
        }

        return implode(', ', $this->allowed_markets ?? []);
    }

    // =========================================================================
    // Customer Type Restriction Helpers
    // =========================================================================

    /**
     * Check if the eligibility has customer type restrictions.
     */
    public function hasCustomerTypeRestrictions(): bool
    {
        return ! empty($this->allowed_customer_types);
    }

    /**
     * Check if a customer type is eligible.
     */
    public function isCustomerTypeEligible(string $customerType): bool
    {
        if (! $this->hasCustomerTypeRestrictions()) {
            return true;
        }

        return in_array($customerType, $this->allowed_customer_types ?? [], true);
    }

    /**
     * Get the allowed customer types count.
     */
    public function getCustomerTypesCount(): int
    {
        return count($this->allowed_customer_types ?? []);
    }

    /**
     * Get the customer types display string.
     */
    public function getCustomerTypesDisplayString(): string
    {
        if (! $this->hasCustomerTypeRestrictions()) {
            return 'All customer types';
        }

        return implode(', ', $this->allowed_customer_types ?? []);
    }

    // =========================================================================
    // Membership Tier Restriction Helpers
    // =========================================================================

    /**
     * Check if the eligibility has membership tier restrictions.
     */
    public function hasMembershipTierRestrictions(): bool
    {
        return ! empty($this->allowed_membership_tiers);
    }

    /**
     * Check if a membership tier is eligible.
     */
    public function isMembershipTierEligible(string $membershipTier): bool
    {
        if (! $this->hasMembershipTierRestrictions()) {
            return true;
        }

        return in_array($membershipTier, $this->allowed_membership_tiers ?? [], true);
    }

    /**
     * Get the allowed membership tiers count.
     */
    public function getMembershipTiersCount(): int
    {
        return count($this->allowed_membership_tiers ?? []);
    }

    /**
     * Get the membership tiers display string.
     */
    public function getMembershipTiersDisplayString(): string
    {
        if (! $this->hasMembershipTierRestrictions()) {
            return 'All membership tiers';
        }

        return implode(', ', $this->allowed_membership_tiers ?? []);
    }

    // =========================================================================
    // Allocation Constraint Helpers
    // =========================================================================

    /**
     * Check if the eligibility has an allocation constraint reference.
     */
    public function hasAllocationConstraint(): bool
    {
        return $this->allocation_constraint_id !== null;
    }

    /**
     * Get the allocation constraint ID.
     */
    public function getAllocationConstraintId(): ?string
    {
        return $this->allocation_constraint_id;
    }

    // =========================================================================
    // Combined Eligibility Check
    // =========================================================================

    /**
     * Check if the eligibility has any restrictions at all.
     */
    public function hasAnyRestrictions(): bool
    {
        return $this->hasMarketRestrictions()
            || $this->hasCustomerTypeRestrictions()
            || $this->hasMembershipTierRestrictions();
    }

    /**
     * Check if the given context is fully eligible.
     */
    public function isContextEligible(
        ?string $market = null,
        ?string $customerType = null,
        ?string $membershipTier = null
    ): bool {
        if ($market !== null && ! $this->isMarketEligible($market)) {
            return false;
        }

        if ($customerType !== null && ! $this->isCustomerTypeEligible($customerType)) {
            return false;
        }

        if ($membershipTier !== null && ! $this->isMembershipTierEligible($membershipTier)) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // UI Helper Methods
    // =========================================================================

    /**
     * Get a summary of the eligibility rules for display.
     */
    public function getEligibilitySummary(): string
    {
        $parts = [];

        if ($this->hasMarketRestrictions()) {
            $count = $this->getMarketsCount();
            $parts[] = "{$count} market".($count > 1 ? 's' : '');
        }

        if ($this->hasCustomerTypeRestrictions()) {
            $count = $this->getCustomerTypesCount();
            $parts[] = "{$count} customer type".($count > 1 ? 's' : '');
        }

        if ($this->hasMembershipTierRestrictions()) {
            $count = $this->getMembershipTiersCount();
            $parts[] = "{$count} membership tier".($count > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return 'Open to all';
        }

        return 'Restricted to: '.implode(', ', $parts);
    }

    /**
     * Get a detailed description of the eligibility rules.
     */
    public function getDetailedDescription(): string
    {
        $lines = [];

        $lines[] = 'Markets: '.$this->getMarketsDisplayString();
        $lines[] = 'Customer Types: '.$this->getCustomerTypesDisplayString();
        $lines[] = 'Membership Tiers: '.$this->getMembershipTiersDisplayString();

        if ($this->hasAllocationConstraint()) {
            $lines[] = "Allocation Constraint: {$this->allocation_constraint_id}";
        }

        return implode("\n", $lines);
    }
}
