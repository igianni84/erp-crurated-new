<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Commercial\Offer;

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
