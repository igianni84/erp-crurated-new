<?php

namespace App\DataTransferObjects\Commercial;

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
