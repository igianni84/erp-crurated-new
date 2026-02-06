<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Commercial\EstimatedMarketPrice;

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
