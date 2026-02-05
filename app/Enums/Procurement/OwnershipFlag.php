<?php

namespace App\Enums\Procurement;

/**
 * Enum OwnershipFlag
 *
 * Defines the ownership status of inbound goods.
 * Note: Inbound does NOT imply ownership - this flag must be explicitly set.
 */
enum OwnershipFlag: string
{
    case Owned = 'owned';
    case InCustody = 'in_custody';
    case Pending = 'pending';

    /**
     * Get the human-readable label for this ownership flag.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owned => 'Owned',
            self::InCustody => 'In Custody',
            self::Pending => 'Pending',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Owned => 'success',
            self::InCustody => 'info',
            self::Pending => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Owned => 'heroicon-o-check-badge',
            self::InCustody => 'heroicon-o-archive-box',
            self::Pending => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Get the description for this ownership flag.
     */
    public function description(): string
    {
        return match ($this) {
            self::Owned => 'We own this product',
            self::InCustody => 'We hold this product but do not own it',
            self::Pending => 'Ownership status to be determined',
        };
    }

    /**
     * Check if ownership is clarified (not pending).
     */
    public function isClarified(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Check if this represents actual ownership.
     */
    public function isOwned(): bool
    {
        return $this === self::Owned;
    }
}
