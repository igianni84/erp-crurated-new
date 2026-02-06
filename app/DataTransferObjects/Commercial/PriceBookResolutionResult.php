<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;

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
