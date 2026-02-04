<?php

namespace App\Enums\Inventory;

/**
 * Enum InboundBatchStatus
 *
 * Serialization statuses for inbound batches.
 */
enum InboundBatchStatus: string
{
    case PendingSerialization = 'pending_serialization';
    case PartiallySerialized = 'partially_serialized';
    case FullySerialized = 'fully_serialized';
    case Discrepancy = 'discrepancy';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingSerialization => 'Pending Serialization',
            self::PartiallySerialized => 'Partially Serialized',
            self::FullySerialized => 'Fully Serialized',
            self::Discrepancy => 'Discrepancy',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::PendingSerialization => 'warning',
            self::PartiallySerialized => 'info',
            self::FullySerialized => 'success',
            self::Discrepancy => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::PendingSerialization => 'heroicon-o-clock',
            self::PartiallySerialized => 'heroicon-o-arrow-path',
            self::FullySerialized => 'heroicon-o-check-circle',
            self::Discrepancy => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if serialization can be started on this batch.
     */
    public function canStartSerialization(): bool
    {
        return match ($this) {
            self::PendingSerialization, self::PartiallySerialized => true,
            self::FullySerialized, self::Discrepancy => false,
        };
    }

    /**
     * Check if the batch requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this === self::Discrepancy;
    }
}
