<?php

namespace App\Enums\Finance;

/**
 * Enum ReconciliationStatus
 *
 * The reconciliation status of a payment against invoices.
 */
enum ReconciliationStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Mismatched = 'mismatched';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Matched => 'Matched',
            self::Mismatched => 'Mismatched',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Matched => 'success',
            self::Mismatched => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Matched => 'heroicon-o-check-circle',
            self::Mismatched => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if this status requires attention.
     */
    public function requiresAttention(): bool
    {
        return match ($this) {
            self::Pending, self::Mismatched => true,
            self::Matched => false,
        };
    }

    /**
     * Check if business events can be triggered.
     * Only matched payments should trigger downstream events.
     */
    public function allowsBusinessEvents(): bool
    {
        return $this === self::Matched;
    }
}
