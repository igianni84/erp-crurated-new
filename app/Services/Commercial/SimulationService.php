<?php

namespace App\Services\Commercial;

use App\DataTransferObjects\Commercial\AllocationCheckResult;
use App\DataTransferObjects\Commercial\EmpReferenceResult;
use App\DataTransferObjects\Commercial\FinalPriceResult;
use App\DataTransferObjects\Commercial\OfferResolutionResult;
use App\DataTransferObjects\Commercial\PriceBookResolutionResult;
use App\DataTransferObjects\Commercial\SimulationContext;
use App\DataTransferObjects\Commercial\SimulationResult;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Commercial\PriceBookStatus;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use App\Services\Allocation\AllocationService;
use Carbon\Carbon;

/**
 * Service for performing pricing simulations.
 *
 * Simulates the complete end-to-end price resolution process:
 * 1. Allocation Check - verifies SKU availability
 * 2. EMP Reference - retrieves estimated market price
 * 3. Price Book Resolution - finds applicable price book and base price
 * 4. Offer Resolution - finds active offer and applies benefits
 * 5. Final Price Calculation - computes final price
 */
class SimulationService
{
    public function __construct(
        protected AllocationService $allocationService,
        protected OfferService $offerService,
        protected PriceBookService $priceBookService,
    ) {}

    /**
     * Perform a complete pricing simulation.
     *
     * @param  SellableSku  $sellableSku  The SKU to simulate pricing for
     * @param  Customer|null  $customer  Optional customer context for eligibility
     * @param  Channel  $channel  The sales channel
     * @param  Carbon  $date  The simulation date for validity checks
     * @param  int  $quantity  Number of units (for volume-based pricing)
     */
    public function simulate(
        SellableSku $sellableSku,
        ?Customer $customer,
        Channel $channel,
        Carbon $date,
        int $quantity = 1
    ): SimulationResult {
        // Create simulation context
        $context = new SimulationContext(
            $sellableSku,
            $channel,
            $customer,
            $date,
            $quantity
        );

        // Build each step result
        $allocationCheck = $this->buildAllocationCheckResult($sellableSku, $channel, $quantity);
        $empReference = $this->buildEmpReferenceResult($sellableSku);
        $priceBookResolution = $this->buildPriceBookResolutionResult($sellableSku, $channel, $date);
        $offerResolution = $this->buildOfferResolutionResult(
            $sellableSku,
            $channel,
            $customer,
            $priceBookResolution,
            $date
        );
        $finalPrice = $this->buildFinalPriceResult(
            $priceBookResolution,
            $offerResolution,
            $quantity,
            $channel
        );

        // Collect errors and warnings
        $errors = [];
        $warnings = [];

        if ($allocationCheck->status === AllocationCheckResult::STATUS_ERROR) {
            $errors[] = 'Allocation: '.$allocationCheck->message;
        }
        if ($priceBookResolution->status === PriceBookResolutionResult::STATUS_ERROR) {
            $errors[] = 'Price Book: '.$priceBookResolution->message;
        }
        if ($offerResolution->status === OfferResolutionResult::STATUS_ERROR) {
            $errors[] = 'Offer: '.$offerResolution->message;
        }
        if ($empReference->status === EmpReferenceResult::STATUS_WARNING) {
            $warnings[] = 'EMP: '.$empReference->message;
        }
        if ($allocationCheck->status === AllocationCheckResult::STATUS_WARNING) {
            $warnings[] = 'Allocation: '.$allocationCheck->message;
        }

        return new SimulationResult(
            context: $context,
            allocationCheck: $allocationCheck,
            empReference: $empReference,
            priceBookResolution: $priceBookResolution,
            offerResolution: $offerResolution,
            finalPrice: $finalPrice,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * Build Allocation Check result (Step 1).
     *
     * Verifies that the SKU has an active allocation with sufficient quantity.
     */
    protected function buildAllocationCheckResult(
        SellableSku $sku,
        Channel $channel,
        int $quantity
    ): AllocationCheckResult {
        // Try to find an active allocation for this SKU's wine variant and format
        $wineVariantId = $sku->wine_variant_id;
        $formatId = $sku->format_id;

        $allocation = Allocation::query()
            ->where('wine_variant_id', $wineVariantId)
            ->where('format_id', $formatId)
            ->where('status', AllocationStatus::Active)
            ->with('constraint')
            ->first();

        if ($allocation === null) {
            return AllocationCheckResult::error(
                'No active allocation found for this SKU',
                [
                    'wine_variant_id' => $wineVariantId,
                    'format_id' => $formatId,
                    'rationale' => 'SKU requires an active allocation to be sellable',
                ]
            );
        }

        // Check availability using AllocationService
        $remaining = $this->allocationService->getRemainingAvailable($allocation);

        if ($remaining < $quantity) {
            return AllocationCheckResult::warning(
                "Insufficient allocation: only {$remaining} units available, requested {$quantity}",
                $allocation,
                [
                    'total_quantity' => (string) $allocation->total_quantity,
                    'sold_quantity' => (string) $allocation->sold_quantity,
                    'remaining' => (string) $remaining,
                    'requested' => (string) $quantity,
                    'source' => 'Allocation ID: '.$allocation->id,
                ]
            );
        }

        // Check channel constraint if applicable
        $constraint = $allocation->constraint;
        $constraintDetails = [];
        if ($constraint !== null) {
            $allowedChannels = $constraint->allowed_channels ?? [];
            $allowedMarkets = $constraint->allowed_markets ?? [];
            $constraintDetails = [
                'allowed_channels' => $allowedChannels !== [] ? implode(', ', $allowedChannels) : 'All',
                'allowed_markets' => $allowedMarkets !== [] ? implode(', ', $allowedMarkets) : 'All',
            ];

            if ($allowedChannels !== [] && ! in_array($channel->id, $allowedChannels, true)) {
                return AllocationCheckResult::warning(
                    'Channel not in allowed channels for this allocation',
                    $allocation,
                    array_merge($constraintDetails, [
                        'rationale' => 'Allocation constraint restricts channels',
                    ])
                );
            }
        }

        return AllocationCheckResult::success(
            $allocation,
            'Allocation available with sufficient quantity',
            [
                'total_quantity' => (string) $allocation->total_quantity,
                'sold_quantity' => (string) $allocation->sold_quantity,
                'remaining' => (string) $remaining,
                'supply_form' => $allocation->supply_form->label(),
                'source' => 'Allocation ID: '.substr((string) $allocation->id, 0, 8).'...',
                'rationale' => 'Active allocation with sufficient remaining quantity',
                ...$constraintDetails,
            ]
        );
    }

    /**
     * Build EMP Reference result (Step 2).
     *
     * Retrieves the Estimated Market Price for the SKU.
     */
    protected function buildEmpReferenceResult(SellableSku $sku): EmpReferenceResult
    {
        $empRecord = $sku->estimatedMarketPrices()->first();

        if ($empRecord === null) {
            return EmpReferenceResult::warning(
                'No EMP data available for this SKU',
                null,
                [
                    'sku_code' => $sku->sku_code,
                    'note' => 'Pricing will proceed without EMP reference',
                    'rationale' => 'EMP data may be pending import or not applicable for this SKU',
                ]
            );
        }

        $details = [
            'market' => $empRecord->market,
            'emp_value' => '€ '.number_format((float) $empRecord->emp_value, 2),
            'confidence' => $empRecord->confidence_level->label(),
            'source' => $empRecord->source->label(),
            'freshness' => $empRecord->getFreshnessIndicator(),
            'fetched_at' => $empRecord->fetched_at?->format('Y-m-d H:i') ?? 'N/A',
            'rationale' => 'EMP provides market reference for pricing decisions',
        ];

        // Check if EMP data is stale
        if ($empRecord->isStale()) {
            return EmpReferenceResult::warning(
                'EMP data is stale (older than 7 days)',
                $empRecord,
                $details
            );
        }

        return EmpReferenceResult::success(
            $empRecord,
            'EMP data available and fresh',
            $details
        );
    }

    /**
     * Build Price Book Resolution result (Step 3).
     *
     * Finds the applicable Price Book and retrieves the base price.
     */
    protected function buildPriceBookResolutionResult(
        SellableSku $sku,
        Channel $channel,
        Carbon $date
    ): PriceBookResolutionResult {
        // Find active Price Book for this channel
        $priceBook = PriceBook::query()
            ->where('status', PriceBookStatus::Active)
            ->where(function ($query) use ($channel): void {
                $query->where('channel_id', $channel->id)
                    ->orWhereNull('channel_id');
            })
            ->where('valid_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            })
            ->orderByRaw('CASE WHEN channel_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($priceBook === null) {
            return PriceBookResolutionResult::error(
                'No active Price Book found for this context',
                [
                    'channel' => $channel->name,
                    'rationale' => 'An active Price Book is required for pricing',
                ]
            );
        }

        // Look for price entry using PriceBookService
        $entry = $this->priceBookService->getPriceForSku($priceBook, $sku);

        if ($entry === null) {
            return PriceBookResolutionResult::warning(
                'No price entry found for this SKU in the active Price Book',
                $priceBook,
                null,
                null,
                [
                    'price_book_name' => $priceBook->name,
                    'price_book_market' => $priceBook->market,
                    'price_book_currency' => $priceBook->currency,
                    'rationale' => 'SKU must have a price entry in the Price Book',
                ]
            );
        }

        $basePrice = (float) $entry->base_price;

        return PriceBookResolutionResult::success(
            $priceBook,
            $entry,
            $basePrice,
            'Price Book resolved with base price',
            [
                'price_book_name' => $priceBook->name,
                'market' => $priceBook->market,
                'currency' => $priceBook->currency,
                'base_price' => '€ '.number_format($basePrice, 2),
                'price_source' => $entry->source->label(),
                'valid_from' => $priceBook->valid_from->format('Y-m-d'),
                'valid_to' => $priceBook->valid_to !== null ? $priceBook->valid_to->format('Y-m-d') : 'Indefinite',
                'rationale' => 'Using channel-specific or default Price Book',
            ]
        );
    }

    /**
     * Build Offer Resolution result (Step 4).
     *
     * Finds the active Offer and calculates any benefits to apply.
     */
    protected function buildOfferResolutionResult(
        SellableSku $sku,
        Channel $channel,
        ?Customer $customer,
        PriceBookResolutionResult $priceBookResult,
        Carbon $date
    ): OfferResolutionResult {
        if ($priceBookResult->basePrice === null) {
            return OfferResolutionResult::error(
                'No base price available from Price Book',
                ['rationale' => 'Offer resolution requires a base price']
            );
        }

        // Build customer context if customer provided
        $customerContext = null;
        if ($customer !== null) {
            $customerContext = new CustomerContext(
                market: $customer->market ?? null,
                customerType: $customer->type ?? null,
                membershipTier: $customer->membership_tier ?? null,
                customerId: $customer->id,
            );
        }

        // Use OfferService to find active offer
        $offer = $this->offerService->getActiveForContext($sku, $channel, $customerContext);

        if ($offer === null) {
            return OfferResolutionResult::warning(
                'No active Offer found for this SKU on this Channel',
                null,
                [
                    'sku_code' => $sku->sku_code,
                    'channel' => $channel->name,
                    'rationale' => 'SKU is not currently offered on this channel',
                ]
            );
        }

        // Get benefit details
        $benefit = $offer->benefit;
        if ($benefit === null || $benefit->isNone()) {
            return OfferResolutionResult::successNoDiscount(
                $offer,
                'Offer found - using Price Book price (no benefit)',
                [
                    'offer_name' => $offer->name,
                    'offer_type' => $offer->offer_type->label(),
                    'visibility' => $offer->visibility->label(),
                    'valid_from' => $offer->valid_from->format('Y-m-d H:i'),
                    'valid_to' => $offer->valid_to !== null ? $offer->valid_to->format('Y-m-d H:i') : 'Indefinite',
                    'benefit_type' => 'None',
                    'rationale' => 'Offer does not apply additional benefit to base price',
                ]
            );
        }

        // Calculate discount using OfferService
        $priceResolution = $this->offerService->resolvePrice($offer);
        $basePrice = $priceBookResult->basePrice;
        $discountAmount = $benefit->getDiscountAmount($basePrice);
        $discountPercent = $benefit->getDiscountPercentage($basePrice);
        $benefitDescription = $benefit->getBenefitSummary();

        return OfferResolutionResult::successWithDiscount(
            $offer,
            $discountAmount,
            $discountPercent,
            $benefitDescription,
            'Offer found with benefit applied',
            [
                'offer_name' => $offer->name,
                'offer_type' => $offer->offer_type->label(),
                'visibility' => $offer->visibility->label(),
                'valid_from' => $offer->valid_from->format('Y-m-d H:i'),
                'valid_to' => $offer->valid_to !== null ? $offer->valid_to->format('Y-m-d H:i') : 'Indefinite',
                'benefit_type' => $benefit->benefit_type->label(),
                'benefit_value' => $benefit->benefit_value !== null
                    ? ($benefit->isPercentageDiscount() ? number_format((float) $benefit->benefit_value, 1).'%' : '€ '.number_format((float) $benefit->benefit_value, 2))
                    : 'N/A',
                'discount_amount' => '€ '.number_format($discountAmount, 2),
                'rationale' => 'Benefit applied to base price from Price Book',
            ]
        );
    }

    /**
     * Build Final Price result (Step 5).
     *
     * Computes the final price based on Price Book and Offer resolution.
     */
    protected function buildFinalPriceResult(
        PriceBookResolutionResult $priceBookResult,
        OfferResolutionResult $offerResult,
        int $quantity,
        Channel $channel
    ): FinalPriceResult {
        // Check if we have the required data
        if ($priceBookResult->basePrice === null) {
            return FinalPriceResult::error(
                'Cannot calculate final price: no base price from Price Book',
                ['rationale' => 'Final price calculation requires a base price']
            );
        }

        if ($offerResult->status === OfferResolutionResult::STATUS_ERROR) {
            return FinalPriceResult::error(
                'Cannot calculate final price: '.$offerResult->message,
                ['rationale' => 'Offer resolution failed']
            );
        }

        // No active offer means we can't sell (offer is the activation of sellability)
        if ($offerResult->offer === null) {
            return FinalPriceResult::error(
                'Cannot calculate final price: no active Offer found',
                [
                    'base_price' => '€ '.number_format($priceBookResult->basePrice, 2),
                    'rationale' => 'An active Offer is required for the SKU to be sellable on this channel',
                ]
            );
        }

        // Calculate final price
        $basePrice = $priceBookResult->basePrice;
        $finalPrice = $basePrice;
        $explanation = 'Base price from Price Book';

        if ($offerResult->discountAmount !== null && $offerResult->discountAmount > 0) {
            $finalPrice = $basePrice - $offerResult->discountAmount;
            $explanation = sprintf(
                'Base price (€%.2f) - %s discount (€%.2f) = Final price',
                $basePrice,
                $offerResult->benefitDescription ?? 'offer',
                $offerResult->discountAmount
            );
        }

        $currency = $channel->default_currency ?? ($priceBookResult->priceBook !== null ? $priceBookResult->priceBook->currency : 'EUR');

        $totalPrice = $finalPrice * $quantity;

        return FinalPriceResult::success(
            $finalPrice,
            $quantity,
            $currency,
            $explanation,
            'Final price calculated successfully',
            [
                'base_price' => '€ '.number_format($basePrice, 2),
                'discount' => $offerResult->discountAmount !== null && $offerResult->discountAmount > 0
                    ? '€ '.number_format($offerResult->discountAmount, 2).' ('.number_format($offerResult->discountPercent ?? 0, 1).'%)'
                    : 'None',
                'final_price' => '€ '.number_format($finalPrice, 2),
                'quantity' => (string) $quantity,
                'total_price' => '€ '.number_format($totalPrice, 2),
                'currency' => $currency,
                'rationale' => $explanation,
            ]
        );
    }
}
