<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Models\Allocation\AllocationConstraint;
use App\Models\AuditLog;
use App\Models\Pim\SellableSku;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Offer Model
 *
 * Represents the activation of sellability for a Sellable SKU on a specific channel.
 * An Offer links a Sellable SKU to a Channel and Price Book, defining when and how
 * the product can be sold.
 *
 * Key invariants:
 * - 1 Offer = 1 Sellable SKU (bundles are composite SKUs, not multiple offers)
 * - Offer does not carry price directly; it resolves via the referenced PriceBook
 * - Eligibility and benefits are managed via related OfferEligibility and OfferBenefit
 *
 * Status transitions:
 * - draft → active
 * - active → paused (bidirectional with paused → active)
 * - active → expired (automatic when now > valid_to)
 * - any → cancelled (terminal)
 *
 * @property string $id
 * @property string $name
 * @property string $sellable_sku_id
 * @property string $channel_id
 * @property string $price_book_id
 * @property OfferType $offer_type
 * @property OfferVisibility $visibility
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property OfferStatus $status
 * @property string|null $campaign_tag
 */
class Offer extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'offers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sellable_sku_id',
        'channel_id',
        'price_book_id',
        'offer_type',
        'visibility',
        'valid_from',
        'valid_to',
        'status',
        'campaign_tag',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offer_type' => OfferType::class,
            'visibility' => OfferVisibility::class,
            'status' => OfferStatus::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    /**
     * Get the Sellable SKU that this offer is for.
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    /**
     * Get the channel that this offer is for.
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the price book that provides base pricing for this offer.
     *
     * @return BelongsTo<PriceBook, $this>
     */
    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    /**
     * Get the eligibility rules for this offer.
     *
     * @return HasOne<OfferEligibility, $this>
     */
    public function eligibility(): HasOne
    {
        return $this->hasOne(OfferEligibility::class);
    }

    /**
     * Get the benefit configuration for this offer.
     *
     * @return HasOne<OfferBenefit, $this>
     */
    public function benefit(): HasOne
    {
        return $this->hasOne(OfferBenefit::class);
    }

    /**
     * Get the audit logs for this offer.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Status Helper Methods
    // =========================================================================

    /**
     * Check if the offer is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    /**
     * Check if the offer is active.
     */
    public function isActive(): bool
    {
        return $this->status === OfferStatus::Active;
    }

    /**
     * Check if the offer is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === OfferStatus::Paused;
    }

    /**
     * Check if the offer is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === OfferStatus::Expired;
    }

    /**
     * Check if the offer is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === OfferStatus::Cancelled;
    }

    // =========================================================================
    // State Transition Checks
    // =========================================================================

    /**
     * Check if the offer can be activated.
     * Must be in draft status.
     */
    public function canBeActivated(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if the offer can be paused.
     * Must be in active status.
     */
    public function canBePaused(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if the offer can be resumed (unpaused).
     * Must be in paused status.
     */
    public function canBeResumed(): bool
    {
        return $this->isPaused();
    }

    /**
     * Check if the offer can be cancelled.
     * Cannot cancel if already cancelled or expired.
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isCancelled() && ! $this->isExpired();
    }

    /**
     * Check if the offer is editable.
     * Only draft offers can be edited.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if the offer is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->isCancelled() || $this->isExpired();
    }

    // =========================================================================
    // Validity Period Helpers
    // =========================================================================

    /**
     * Check if the offer is within its validity period.
     */
    public function isWithinValidityPeriod(): bool
    {
        $now = now();

        if ($now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to !== null && $now->gt($this->valid_to)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the offer should be auto-expired.
     */
    public function shouldAutoExpire(): bool
    {
        if ($this->valid_to === null) {
            return false;
        }

        return now()->gt($this->valid_to) && $this->isActive();
    }

    /**
     * Check if the offer is about to expire (within 7 days).
     */
    public function isExpiringSoon(): bool
    {
        if ($this->valid_to === null) {
            return false;
        }

        $now = now();
        $daysUntilExpiry = $now->diffInDays($this->valid_to, false);

        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= 7;
    }

    /**
     * Get days until expiration, or null if no expiration.
     */
    public function getDaysUntilExpiry(): ?int
    {
        if ($this->valid_to === null) {
            return null;
        }

        $days = now()->diffInDays($this->valid_to, false);

        return (int) $days;
    }

    // =========================================================================
    // Type and Visibility Helpers
    // =========================================================================

    /**
     * Check if this is a standard offer.
     */
    public function isStandard(): bool
    {
        return $this->offer_type === OfferType::Standard;
    }

    /**
     * Check if this is a promotion offer.
     */
    public function isPromotion(): bool
    {
        return $this->offer_type === OfferType::Promotion;
    }

    /**
     * Check if this is a bundle offer.
     */
    public function isBundle(): bool
    {
        return $this->offer_type === OfferType::Bundle;
    }

    /**
     * Check if the offer is publicly visible.
     */
    public function isPublic(): bool
    {
        return $this->visibility === OfferVisibility::Public;
    }

    /**
     * Check if the offer is restricted visibility.
     */
    public function isRestricted(): bool
    {
        return $this->visibility === OfferVisibility::Restricted;
    }

    // =========================================================================
    // UI Helper Methods
    // =========================================================================

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
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get the offer type color for UI display.
     */
    public function getOfferTypeColor(): string
    {
        return $this->offer_type->color();
    }

    /**
     * Get the offer type label for UI display.
     */
    public function getOfferTypeLabel(): string
    {
        return $this->offer_type->label();
    }

    /**
     * Get the visibility color for UI display.
     */
    public function getVisibilityColor(): string
    {
        return $this->visibility->color();
    }

    /**
     * Get the visibility label for UI display.
     */
    public function getVisibilityLabel(): string
    {
        return $this->visibility->label();
    }

    // =========================================================================
    // Price Resolution
    // =========================================================================

    /**
     * Get the base price from the referenced Price Book.
     * Returns null if no Price Book entry exists for the SKU.
     */
    public function getBasePrice(): ?string
    {
        $priceBook = $this->priceBook;
        if ($priceBook === null) {
            return null;
        }

        $entry = $priceBook->entries()
            ->where('sellable_sku_id', $this->sellable_sku_id)
            ->first();

        return $entry?->base_price;
    }

    /**
     * Check if the offer has a base price defined.
     */
    public function hasBasePrice(): bool
    {
        return $this->getBasePrice() !== null;
    }

    // =========================================================================
    // Eligibility Validation (for status transitions)
    // =========================================================================

    /**
     * Validate that the offer's eligibility does not violate allocation constraints.
     * Returns an array of validation errors (empty if valid).
     *
     * @return array<string, string>
     */
    public function validateEligibilityAgainstAllocation(): array
    {
        $errors = [];

        $eligibility = $this->eligibility;
        if ($eligibility === null) {
            // No eligibility restrictions means no violation possible
            return [];
        }

        // Get the allocation constraint referenced by eligibility
        $constraintId = $eligibility->getAllocationConstraintId();
        if ($constraintId === null) {
            // No allocation constraint linked, cannot validate
            return [];
        }

        $allocationConstraint = AllocationConstraint::find($constraintId);
        if ($allocationConstraint === null) {
            $errors['allocation_constraint'] = 'Referenced allocation constraint not found';

            return $errors;
        }

        // Validate market restrictions
        if ($eligibility->hasMarketRestrictions()) {
            $allowedMarkets = $eligibility->allowed_markets ?? [];
            $constraintGeographies = $allocationConstraint->getEffectiveGeographies();

            if (! empty($constraintGeographies)) {
                foreach ($allowedMarkets as $market) {
                    if (! $allocationConstraint->isGeographyAllowed($market)) {
                        $errors['markets'] = "Market '{$market}' is not allowed by the allocation constraint";
                        break;
                    }
                }
            }
        }

        // Validate customer type restrictions
        if ($eligibility->hasCustomerTypeRestrictions()) {
            $allowedCustomerTypes = $eligibility->allowed_customer_types ?? [];
            $constraintCustomerTypes = $allocationConstraint->getEffectiveCustomerTypes();

            foreach ($allowedCustomerTypes as $customerType) {
                if (! $allocationConstraint->isCustomerTypeAllowed($customerType)) {
                    $errors['customer_types'] = "Customer type '{$customerType}' is not allowed by the allocation constraint";
                    break;
                }
            }
        }

        // Validate channel against allocation constraint
        $channel = $this->channel;
        if ($channel !== null) {
            $channelType = $channel->channel_type->value;
            if (! $allocationConstraint->isChannelAllowed($channelType)) {
                $errors['channel'] = "Channel type '{$channelType}' is not allowed by the allocation constraint";
            }
        }

        return $errors;
    }

    /**
     * Check if the offer can be activated with all validations.
     * This includes eligibility validation and Price Book checks.
     *
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function canActivateWithValidations(): array
    {
        $errors = [];

        // Must be in draft status
        if (! $this->isDraft()) {
            $errors['status'] = 'Offer must be in Draft status to activate';
        }

        // Price Book must be active
        $priceBook = $this->priceBook;
        if ($priceBook === null) {
            $errors['price_book'] = 'Offer must have a Price Book assigned';
        } elseif (! $priceBook->isActive()) {
            $errors['price_book'] = 'Price Book must be active before activating the offer';
        }

        // Should have a base price
        if (! $this->hasBasePrice()) {
            $errors['base_price'] = 'No price entry found in the linked Price Book for this SKU';
        }

        // Validate eligibility against allocation constraints
        $eligibilityErrors = $this->validateEligibilityAgainstAllocation();
        $errors = array_merge($errors, $eligibilityErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // =========================================================================
    // Scopes
    // =========================================================================
    /**
     * Scope a query to only include active offers for a specific SKU and channel.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    public function scopeActiveForContext($query, string $sellableSkuId, string $channelId)
    {
        return $query
            ->where('status', OfferStatus::Active)
            ->where('sellable_sku_id', $sellableSkuId)
            ->where('channel_id', $channelId)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });
    }

    /**
     * Scope a query to only include offers expiring soon (within N days).
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query
            ->whereNotNull('valid_to')
            ->where('valid_to', '>=', now())
            ->where('valid_to', '<=', now()->addDays($days));
    }

    /**
     * Scope a query to only include offers by campaign tag.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    public function scopeByCampaign($query, string $campaignTag)
    {
        return $query->where('campaign_tag', $campaignTag);
    }
}
