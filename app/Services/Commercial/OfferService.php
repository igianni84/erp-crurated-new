<?php

namespace App\Services\Commercial;

use App\Enums\Commercial\OfferStatus;
use App\Models\AuditLog;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Service for managing Offer lifecycle and operations.
 *
 * Centralizes all Offer business logic including state transitions,
 * eligibility validation, and price resolution operations.
 */
class OfferService
{
    /**
     * Activate an Offer (draft → active).
     *
     * When activated, the Offer becomes available for purchase on its channel.
     * Activation validates:
     * - Offer is in draft status
     * - Referenced Price Book is active
     * - Base price exists in the Price Book
     * - Eligibility does not violate allocation constraints
     *
     * @throws \InvalidArgumentException If activation is not allowed
     */
    public function activate(Offer $offer): Offer
    {
        $validationResult = $offer->canActivateWithValidations();

        if (! $validationResult['valid']) {
            $errors = $validationResult['errors'];
            $errorMessages = implode('; ', $errors);

            throw new \InvalidArgumentException(
                "Cannot activate Offer: {$errorMessages}"
            );
        }

        $oldStatus = $offer->status;
        $offer->status = OfferStatus::Active;
        $offer->save();

        $this->logStatusTransition($offer, $oldStatus, OfferStatus::Active);

        return $offer;
    }

    /**
     * Pause an Offer (active → paused).
     *
     * Paused offers are temporarily unavailable for purchase but can be resumed.
     *
     * @throws \InvalidArgumentException If pausing is not allowed
     */
    public function pause(Offer $offer): Offer
    {
        if (! $offer->canBePaused()) {
            throw new \InvalidArgumentException(
                "Cannot pause Offer: current status '{$offer->status->label()}' is not Active. "
                .'Only Active offers can be paused.'
            );
        }

        $oldStatus = $offer->status;
        $offer->status = OfferStatus::Paused;
        $offer->save();

        $this->logStatusTransition($offer, $oldStatus, OfferStatus::Paused);

        return $offer;
    }

    /**
     * Resume an Offer (paused → active).
     *
     * Resumed offers become available for purchase again.
     *
     * @throws \InvalidArgumentException If resuming is not allowed
     */
    public function resume(Offer $offer): Offer
    {
        if (! $offer->canBeResumed()) {
            throw new \InvalidArgumentException(
                "Cannot resume Offer: current status '{$offer->status->label()}' is not Paused. "
                .'Only Paused offers can be resumed.'
            );
        }

        // Re-validate Price Book is still active before resuming
        $priceBook = $offer->priceBook;
        if ($priceBook !== null && ! $priceBook->isActive()) {
            throw new \InvalidArgumentException(
                'Cannot resume Offer: the referenced Price Book is no longer active. '
                .'Update the Price Book or link a different active Price Book before resuming.'
            );
        }

        $oldStatus = $offer->status;
        $offer->status = OfferStatus::Active;
        $offer->save();

        $this->logStatusTransition($offer, $oldStatus, OfferStatus::Active);

        return $offer;
    }

    /**
     * Cancel an Offer (any → cancelled).
     *
     * Cancelled is a terminal state - the offer cannot be reactivated.
     * Any non-terminal offer can be cancelled.
     *
     * @throws \InvalidArgumentException If cancellation is not allowed
     */
    public function cancel(Offer $offer): Offer
    {
        if (! $offer->canBeCancelled()) {
            throw new \InvalidArgumentException(
                "Cannot cancel Offer: current status '{$offer->status->label()}' does not allow cancellation. "
                .'Only non-terminal offers (not Cancelled or Expired) can be cancelled.'
            );
        }

        $oldStatus = $offer->status;
        $offer->status = OfferStatus::Cancelled;
        $offer->save();

        $this->logStatusTransition($offer, $oldStatus, OfferStatus::Cancelled);

        return $offer;
    }

    /**
     * Expire an Offer (active → expired).
     *
     * Used when the validity period ends. This is typically called
     * by the ExpireOffersJob scheduled task.
     *
     * @throws \InvalidArgumentException If expiration is not allowed
     */
    public function expire(Offer $offer): Offer
    {
        if (! $offer->isActive()) {
            throw new \InvalidArgumentException(
                "Cannot expire Offer: current status '{$offer->status->label()}' is not Active. "
                .'Only Active offers can be expired.'
            );
        }

        $oldStatus = $offer->status;
        $offer->status = OfferStatus::Expired;
        $offer->save();

        $this->logStatusTransition($offer, $oldStatus, OfferStatus::Expired);

        return $offer;
    }

    /**
     * Find the active Offer for a specific context.
     *
     * Returns the Offer that is currently active and valid for the
     * specified Sellable SKU, Channel, and optionally Customer context.
     *
     * @param  CustomerContext|null  $customer  Optional customer context for eligibility filtering
     */
    public function getActiveForContext(
        SellableSku $sellableSku,
        Channel $channel,
        ?CustomerContext $customer = null
    ): ?Offer {
        // First, find all active offers for this SKU and channel
        $query = Offer::query()
            ->where('status', OfferStatus::Active)
            ->where('sellable_sku_id', $sellableSku->id)
            ->where('channel_id', $channel->id)
            ->where('valid_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->with(['eligibility', 'benefit', 'priceBook']);

        $offers = $query->get();

        if ($offers->isEmpty()) {
            return null;
        }

        // If no customer context, return the first offer
        if ($customer === null) {
            return $offers->first();
        }

        // Filter by customer eligibility
        foreach ($offers as $offer) {
            if ($this->validateEligibility($offer, $customer)) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * Find all active Offers for a specific SKU and Channel.
     *
     * @return Collection<int, Offer>
     */
    public function getActiveOffersForContext(
        SellableSku $sellableSku,
        Channel $channel
    ): Collection {
        return Offer::query()
            ->where('status', OfferStatus::Active)
            ->where('sellable_sku_id', $sellableSku->id)
            ->where('channel_id', $channel->id)
            ->where('valid_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->with(['eligibility', 'benefit', 'priceBook'])
            ->get();
    }

    /**
     * Resolve the final price for an Offer.
     *
     * Calculates the final price by:
     * 1. Getting the base price from the referenced Price Book
     * 2. Applying the benefit (discount or fixed price) if any
     *
     * @return PriceResolution The resolved price information
     */
    public function resolvePrice(Offer $offer): PriceResolution
    {
        // Get base price from Price Book
        $basePrice = $offer->getBasePrice();

        if ($basePrice === null) {
            return new PriceResolution(
                offer: $offer,
                basePrice: null,
                finalPrice: null,
                discount: null,
                discountPercent: null,
                error: 'No base price found in the linked Price Book for this SKU'
            );
        }

        $basePriceFloat = (float) $basePrice;
        $benefit = $offer->benefit;

        // If no benefit or benefit type is "none", final price equals base price
        if ($benefit === null || $benefit->isNone()) {
            return new PriceResolution(
                offer: $offer,
                basePrice: $basePriceFloat,
                finalPrice: $basePriceFloat,
                discount: 0.0,
                discountPercent: 0.0,
                error: null
            );
        }

        // Calculate final price using benefit
        $finalPrice = $benefit->calculateFinalPrice($basePriceFloat);
        $discountAmount = $benefit->getDiscountAmount($basePriceFloat);
        $discountPercent = $benefit->getDiscountPercentage($basePriceFloat);

        return new PriceResolution(
            offer: $offer,
            basePrice: $basePriceFloat,
            finalPrice: $finalPrice,
            discount: $discountAmount,
            discountPercent: $discountPercent,
            error: null
        );
    }

    /**
     * Resolve the final price as a decimal string.
     *
     * Convenience method that returns just the final price value.
     *
     * @return string|null The final price as a decimal string, or null if cannot resolve
     */
    public function resolvePriceValue(Offer $offer): ?string
    {
        $resolution = $this->resolvePrice($offer);

        if ($resolution->finalPrice === null) {
            return null;
        }

        return number_format($resolution->finalPrice, 2, '.', '');
    }

    /**
     * Validate customer eligibility for an Offer.
     *
     * Checks if the customer context is eligible for the offer based on:
     * - Market restrictions
     * - Customer type restrictions
     * - Membership tier restrictions
     *
     * @param  CustomerContext  $customer  The customer context to validate
     * @return bool True if the customer is eligible, false otherwise
     */
    public function validateEligibility(Offer $offer, CustomerContext $customer): bool
    {
        $eligibility = $offer->eligibility;

        // No eligibility restrictions means everyone is eligible
        if ($eligibility === null) {
            return true;
        }

        return $eligibility->isContextEligible(
            $customer->market,
            $customer->customerType,
            $customer->membershipTier
        );
    }

    /**
     * Get detailed eligibility validation results.
     *
     * Returns information about which eligibility checks passed or failed.
     *
     * @return EligibilityValidation The validation result with details
     */
    public function validateEligibilityDetailed(Offer $offer, CustomerContext $customer): EligibilityValidation
    {
        $eligibility = $offer->eligibility;

        // No eligibility restrictions means everyone is eligible
        if ($eligibility === null) {
            return new EligibilityValidation(
                isEligible: true,
                marketCheck: true,
                customerTypeCheck: true,
                membershipTierCheck: true,
                failureReasons: []
            );
        }

        $failureReasons = [];

        // Check market
        $marketCheck = true;
        if ($customer->market !== null && $eligibility->hasMarketRestrictions()) {
            $marketCheck = $eligibility->isMarketEligible($customer->market);
            if (! $marketCheck) {
                $failureReasons[] = "Market '{$customer->market}' is not in the allowed markets";
            }
        }

        // Check customer type
        $customerTypeCheck = true;
        if ($customer->customerType !== null && $eligibility->hasCustomerTypeRestrictions()) {
            $customerTypeCheck = $eligibility->isCustomerTypeEligible($customer->customerType);
            if (! $customerTypeCheck) {
                $failureReasons[] = "Customer type '{$customer->customerType}' is not in the allowed types";
            }
        }

        // Check membership tier
        $membershipTierCheck = true;
        if ($customer->membershipTier !== null && $eligibility->hasMembershipTierRestrictions()) {
            $membershipTierCheck = $eligibility->isMembershipTierEligible($customer->membershipTier);
            if (! $membershipTierCheck) {
                $failureReasons[] = "Membership tier '{$customer->membershipTier}' is not in the allowed tiers";
            }
        }

        return new EligibilityValidation(
            isEligible: $marketCheck && $customerTypeCheck && $membershipTierCheck,
            marketCheck: $marketCheck,
            customerTypeCheck: $customerTypeCheck,
            membershipTierCheck: $membershipTierCheck,
            failureReasons: $failureReasons
        );
    }

    /**
     * Check if an Offer can be activated.
     */
    public function canActivate(Offer $offer): bool
    {
        $validationResult = $offer->canActivateWithValidations();

        return $validationResult['valid'];
    }

    /**
     * Check if an Offer can be paused.
     */
    public function canPause(Offer $offer): bool
    {
        return $offer->canBePaused();
    }

    /**
     * Check if an Offer can be resumed.
     */
    public function canResume(Offer $offer): bool
    {
        if (! $offer->canBeResumed()) {
            return false;
        }

        // Also check that Price Book is still active
        $priceBook = $offer->priceBook;

        return $priceBook !== null && $priceBook->isActive();
    }

    /**
     * Check if an Offer can be cancelled.
     */
    public function canCancel(Offer $offer): bool
    {
        return $offer->canBeCancelled();
    }

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        Offer $offer,
        OfferStatus $oldStatus,
        OfferStatus $newStatus
    ): void {
        $offer->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => [
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
            ],
            'user_id' => Auth::id(),
        ]);
    }
}

/**
 * Value object representing customer context for eligibility checks.
 */
class CustomerContext
{
    public function __construct(
        public readonly ?string $market = null,
        public readonly ?string $customerType = null,
        public readonly ?string $membershipTier = null,
        public readonly ?string $customerId = null,
    ) {}

    /**
     * Create a CustomerContext from an array.
     *
     * @param  array<string, string|null>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            market: $data['market'] ?? null,
            customerType: $data['customer_type'] ?? $data['customerType'] ?? null,
            membershipTier: $data['membership_tier'] ?? $data['membershipTier'] ?? null,
            customerId: $data['customer_id'] ?? $data['customerId'] ?? null,
        );
    }
}

/**
 * Value object representing the result of price resolution.
 */
class PriceResolution
{
    public function __construct(
        public readonly Offer $offer,
        public readonly ?float $basePrice,
        public readonly ?float $finalPrice,
        public readonly ?float $discount,
        public readonly ?float $discountPercent,
        public readonly ?string $error = null,
    ) {}

    /**
     * Check if the price resolution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->error === null && $this->finalPrice !== null;
    }

    /**
     * Check if there was an error resolving the price.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Check if a discount was applied.
     */
    public function hasDiscount(): bool
    {
        return $this->discount !== null && $this->discount > 0;
    }

    /**
     * Get the formatted final price.
     */
    public function getFormattedFinalPrice(?string $currency = 'EUR'): string
    {
        if ($this->finalPrice === null) {
            return '-';
        }

        return $currency.' '.number_format($this->finalPrice, 2);
    }

    /**
     * Get the formatted base price.
     */
    public function getFormattedBasePrice(?string $currency = 'EUR'): string
    {
        if ($this->basePrice === null) {
            return '-';
        }

        return $currency.' '.number_format($this->basePrice, 2);
    }

    /**
     * Get the formatted discount amount.
     */
    public function getFormattedDiscount(?string $currency = 'EUR'): string
    {
        if ($this->discount === null || $this->discount <= 0) {
            return '-';
        }

        return $currency.' '.number_format($this->discount, 2);
    }

    /**
     * Get the formatted discount percentage.
     */
    public function getFormattedDiscountPercent(): string
    {
        if ($this->discountPercent === null || $this->discountPercent <= 0) {
            return '-';
        }

        return number_format($this->discountPercent, 1).'%';
    }

    /**
     * Get a summary of the price resolution.
     */
    public function getSummary(?string $currency = 'EUR'): string
    {
        if (! $this->isSuccess()) {
            return $this->error ?? 'Price resolution failed';
        }

        if ($this->hasDiscount()) {
            return sprintf(
                '%s (was %s, save %s)',
                $this->getFormattedFinalPrice($currency),
                $this->getFormattedBasePrice($currency),
                $this->getFormattedDiscountPercent()
            );
        }

        return $this->getFormattedFinalPrice($currency);
    }
}

/**
 * Value object representing the result of eligibility validation.
 */
class EligibilityValidation
{
    /**
     * @param  array<int, string>  $failureReasons
     */
    public function __construct(
        public readonly bool $isEligible,
        public readonly bool $marketCheck,
        public readonly bool $customerTypeCheck,
        public readonly bool $membershipTierCheck,
        public readonly array $failureReasons = [],
    ) {}

    /**
     * Get a summary of the validation result.
     */
    public function getSummary(): string
    {
        if ($this->isEligible) {
            return 'Customer is eligible for this offer';
        }

        if (empty($this->failureReasons)) {
            return 'Customer is not eligible for this offer';
        }

        return 'Not eligible: '.implode('; ', $this->failureReasons);
    }

    /**
     * Get the failure reasons as a list.
     *
     * @return array<int, string>
     */
    public function getFailureReasons(): array
    {
        return $this->failureReasons;
    }
}
