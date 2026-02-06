<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Allocation\Allocation;

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
