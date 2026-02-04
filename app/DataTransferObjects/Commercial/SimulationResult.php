<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Carbon\Carbon;

/**
 * Data Transfer Object representing the result of a price simulation.
 *
 * Encapsulates the complete breakdown of price resolution steps:
 * 1. Allocation Check
 * 2. EMP Reference
 * 3. Price Book Resolution
 * 4. Offer Resolution
 * 5. Final Price Calculation
 */
class SimulationResult
{
    /**
     * @param  SimulationContext  $context  The simulation input context
     * @param  AllocationCheckResult  $allocationCheck  Step 1: Allocation verification
     * @param  EmpReferenceResult  $empReference  Step 2: EMP reference data
     * @param  PriceBookResolutionResult  $priceBookResolution  Step 3: Price Book lookup
     * @param  OfferResolutionResult  $offerResolution  Step 4: Offer application
     * @param  FinalPriceResult  $finalPrice  Step 5: Final price computation
     * @param  array<int, string>  $errors  Blocking errors that prevented simulation
     * @param  array<int, string>  $warnings  Non-blocking warnings
     */
    public function __construct(
        public readonly SimulationContext $context,
        public readonly AllocationCheckResult $allocationCheck,
        public readonly EmpReferenceResult $empReference,
        public readonly PriceBookResolutionResult $priceBookResolution,
        public readonly OfferResolutionResult $offerResolution,
        public readonly FinalPriceResult $finalPrice,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    /**
     * Check if the simulation completed successfully.
     */
    public function isSuccess(): bool
    {
        return empty($this->errors) && $this->finalPrice->hasPrice();
    }

    /**
     * Check if there were blocking errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Check if there were warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get a summary status of the simulation.
     */
    public function getStatus(): string
    {
        if ($this->hasErrors()) {
            return 'error';
        }

        if ($this->hasWarnings()) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context' => $this->context->toArray(),
            'steps' => [
                'allocation' => $this->allocationCheck->toArray(),
                'emp' => $this->empReference->toArray(),
                'price_book' => $this->priceBookResolution->toArray(),
                'offer' => $this->offerResolution->toArray(),
                'final' => $this->finalPrice->toArray(),
            ],
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'status' => $this->getStatus(),
            'final_price' => $this->finalPrice->finalPrice,
        ];
    }
}

/**
 * Simulation input context.
 */
class SimulationContext
{
    public function __construct(
        public readonly ?SellableSku $sellableSku,
        public readonly ?Channel $channel,
        public readonly ?Customer $customer,
        public readonly Carbon $date,
        public readonly int $quantity,
    ) {}

    /**
     * Get SKU display label.
     */
    public function getSkuLabel(): string
    {
        if ($this->sellableSku === null) {
            return 'Unknown SKU';
        }

        $wineVariant = $this->sellableSku->wineVariant;
        if ($wineVariant === null) {
            return $this->sellableSku->sku_code;
        }

        $wineMaster = $wineVariant->wineMaster;
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant->vintage_year ?? 'NV';
        $format = $this->sellableSku->format !== null ? $this->sellableSku->format->volume_ml.'ml' : '';
        $caseConfig = $this->sellableSku->caseConfiguration;
        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.'x' : '';

        return "{$this->sellableSku->sku_code} - {$wineName} {$vintage} ({$format} {$packaging})";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->getSkuLabel(),
            'sku_code' => $this->sellableSku !== null ? $this->sellableSku->sku_code : 'N/A',
            'sku_id' => $this->sellableSku?->id,
            'channel' => $this->channel !== null ? $this->channel->name : 'Unknown Channel',
            'channel_id' => $this->channel?->id,
            'customer' => $this->customer !== null ? $this->customer->name : 'Anonymous',
            'customer_id' => $this->customer?->id,
            'date' => $this->date->format('Y-m-d'),
            'quantity' => $this->quantity,
        ];
    }
}

/**
 * Step 1: Allocation Check Result.
 */
class AllocationCheckResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  string  $status  One of: success, warning, error, pending
     * @param  string  $message  Human-readable status message
     * @param  Allocation|null  $allocation  The resolved allocation (if found)
     * @param  array<string, mixed>  $details  Additional details for display
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?Allocation $allocation = null,
        public readonly array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => 'Allocation Check',
            'status' => $this->status,
            'icon' => 'heroicon-o-clipboard-document-check',
            'message' => $this->message,
            'details' => $this->details,
            'allocation_id' => $this->allocation?->id,
        ];
    }

    /**
     * Create a success result.
     */
    public static function success(Allocation $allocation, string $message, array $details = []): self
    {
        return new self(self::STATUS_SUCCESS, $message, $allocation, $details);
    }

    /**
     * Create a warning result.
     */
    public static function warning(string $message, ?Allocation $allocation = null, array $details = []): self
    {
        return new self(self::STATUS_WARNING, $message, $allocation, $details);
    }

    /**
     * Create an error result.
     */
    public static function error(string $message, array $details = []): self
    {
        return new self(self::STATUS_ERROR, $message, null, $details);
    }

    /**
     * Create a pending result (for placeholder when SimulationService is not yet implemented).
     */
    public static function pending(string $message = 'Allocation check pending', array $details = []): self
    {
        return new self(self::STATUS_PENDING, $message, null, $details);
    }
}

/**
 * Step 2: EMP Reference Result.
 */
class EmpReferenceResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  string  $status  One of: success, warning, error, pending
     * @param  string  $message  Human-readable status message
     * @param  EstimatedMarketPrice|null  $emp  The EMP record (if found)
     * @param  array<string, mixed>  $details  Additional details for display
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?EstimatedMarketPrice $emp = null,
        public readonly array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => 'EMP Reference',
            'status' => $this->status,
            'icon' => 'heroicon-o-chart-bar-square',
            'message' => $this->message,
            'details' => $this->details,
            'emp_id' => $this->emp?->id,
            'emp_value' => $this->emp !== null ? number_format((float) $this->emp->emp_value, 2) : null,
        ];
    }

    /**
     * Create a success result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function success(EstimatedMarketPrice $emp, string $message, array $details = []): self
    {
        return new self(self::STATUS_SUCCESS, $message, $emp, $details);
    }

    /**
     * Create a warning result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function warning(string $message, ?EstimatedMarketPrice $emp = null, array $details = []): self
    {
        return new self(self::STATUS_WARNING, $message, $emp, $details);
    }

    /**
     * Create a pending result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function pending(string $message = 'EMP lookup pending', array $details = []): self
    {
        return new self(self::STATUS_PENDING, $message, null, $details);
    }
}

/**
 * Step 3: Price Book Resolution Result.
 */
class PriceBookResolutionResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  string  $status  One of: success, warning, error, pending
     * @param  string  $message  Human-readable status message
     * @param  PriceBook|null  $priceBook  The resolved Price Book (if found)
     * @param  PriceBookEntry|null  $entry  The SKU's price entry (if found)
     * @param  float|null  $basePrice  The base price from Price Book
     * @param  array<string, mixed>  $details  Additional details for display
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?PriceBook $priceBook = null,
        public readonly ?PriceBookEntry $entry = null,
        public readonly ?float $basePrice = null,
        public readonly array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => 'Price Book Resolution',
            'status' => $this->status,
            'icon' => 'heroicon-o-book-open',
            'message' => $this->message,
            'details' => $this->details,
            'price_book_id' => $this->priceBook?->id,
            'price_book_name' => $this->priceBook?->name,
            'base_price' => $this->basePrice !== null ? number_format($this->basePrice, 2) : null,
        ];
    }

    /**
     * Create a success result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function success(
        PriceBook $priceBook,
        PriceBookEntry $entry,
        float $basePrice,
        string $message,
        array $details = []
    ): self {
        return new self(self::STATUS_SUCCESS, $message, $priceBook, $entry, $basePrice, $details);
    }

    /**
     * Create a warning result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function warning(
        string $message,
        ?PriceBook $priceBook = null,
        ?PriceBookEntry $entry = null,
        ?float $basePrice = null,
        array $details = []
    ): self {
        return new self(self::STATUS_WARNING, $message, $priceBook, $entry, $basePrice, $details);
    }

    /**
     * Create an error result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(string $message, array $details = []): self
    {
        return new self(self::STATUS_ERROR, $message, null, null, null, $details);
    }

    /**
     * Create a pending result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function pending(string $message = 'Price Book lookup pending', array $details = []): self
    {
        return new self(self::STATUS_PENDING, $message, null, null, null, $details);
    }
}

/**
 * Step 4: Offer Resolution Result.
 */
class OfferResolutionResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  string  $status  One of: success, warning, error, pending
     * @param  string  $message  Human-readable status message
     * @param  Offer|null  $offer  The resolved Offer (if found)
     * @param  float|null  $discountAmount  Amount of discount applied
     * @param  float|null  $discountPercent  Percentage of discount applied
     * @param  string|null  $benefitDescription  Description of benefit applied
     * @param  array<string, mixed>  $details  Additional details for display
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?Offer $offer = null,
        public readonly ?float $discountAmount = null,
        public readonly ?float $discountPercent = null,
        public readonly ?string $benefitDescription = null,
        public readonly array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => 'Offer Resolution',
            'status' => $this->status,
            'icon' => 'heroicon-o-tag',
            'message' => $this->message,
            'details' => $this->details,
            'offer_id' => $this->offer?->id,
            'offer_name' => $this->offer?->name,
            'discount_amount' => $this->discountAmount !== null ? number_format($this->discountAmount, 2) : null,
            'discount_percent' => $this->discountPercent !== null ? number_format($this->discountPercent, 1).'%' : null,
            'benefit_description' => $this->benefitDescription,
        ];
    }

    /**
     * Create a success result with a discount.
     *
     * @param  array<string, mixed>  $details
     */
    public static function successWithDiscount(
        Offer $offer,
        float $discountAmount,
        float $discountPercent,
        string $benefitDescription,
        string $message,
        array $details = []
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $message,
            $offer,
            $discountAmount,
            $discountPercent,
            $benefitDescription,
            $details
        );
    }

    /**
     * Create a success result without discount (Price Book price used).
     *
     * @param  array<string, mixed>  $details
     */
    public static function successNoDiscount(
        Offer $offer,
        string $message,
        array $details = []
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $message,
            $offer,
            0.0,
            0.0,
            'Using Price Book price (no benefit)',
            $details
        );
    }

    /**
     * Create a warning result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function warning(string $message, ?Offer $offer = null, array $details = []): self
    {
        return new self(self::STATUS_WARNING, $message, $offer, null, null, null, $details);
    }

    /**
     * Create an error result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(string $message, array $details = []): self
    {
        return new self(self::STATUS_ERROR, $message, null, null, null, null, $details);
    }

    /**
     * Create a pending result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function pending(string $message = 'Offer lookup pending', array $details = []): self
    {
        return new self(self::STATUS_PENDING, $message, null, null, null, null, $details);
    }
}

/**
 * Step 5: Final Price Result.
 */
class FinalPriceResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  string  $status  One of: success, warning, error, pending
     * @param  string  $message  Human-readable status message
     * @param  float|null  $finalPrice  The final computed price
     * @param  float|null  $unitPrice  Price per unit
     * @param  float|null  $totalPrice  Total price for quantity
     * @param  string|null  $currency  Currency code
     * @param  string|null  $explanation  Plain-language explanation of computation
     * @param  array<string, mixed>  $details  Additional details for display
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?float $finalPrice = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $totalPrice = null,
        public readonly ?string $currency = null,
        public readonly ?string $explanation = null,
        public readonly array $details = [],
    ) {}

    /**
     * Check if a price was computed.
     */
    public function hasPrice(): bool
    {
        return $this->finalPrice !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => 'Final Price Calculation',
            'status' => $this->status,
            'icon' => 'heroicon-o-currency-euro',
            'message' => $this->message,
            'details' => $this->details,
            'final_price' => $this->finalPrice !== null ? number_format($this->finalPrice, 2) : null,
            'unit_price' => $this->unitPrice !== null ? number_format($this->unitPrice, 2) : null,
            'total_price' => $this->totalPrice !== null ? number_format($this->totalPrice, 2) : null,
            'currency' => $this->currency,
            'explanation' => $this->explanation,
        ];
    }

    /**
     * Create a success result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function success(
        float $finalPrice,
        int $quantity,
        string $currency,
        string $explanation,
        string $message,
        array $details = []
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $message,
            $finalPrice,
            $finalPrice,
            $finalPrice * $quantity,
            $currency,
            $explanation,
            $details
        );
    }

    /**
     * Create an error result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(string $message, array $details = []): self
    {
        return new self(self::STATUS_ERROR, $message, null, null, null, null, null, $details);
    }

    /**
     * Create a pending result.
     *
     * @param  array<string, mixed>  $details
     */
    public static function pending(string $message = 'Price calculation pending', array $details = []): self
    {
        return new self(self::STATUS_PENDING, $message, null, null, null, null, null, $details);
    }
}
