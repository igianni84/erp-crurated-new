<?php

namespace App\Enums\Allocation;

/**
 * Enum CaseEntitlementStatus
 *
 * Statuses for case entitlements.
 * - intact: All vouchers in the case are still held by the same customer and none have been redeemed
 * - broken: One or more vouchers have been transferred, traded, or redeemed individually
 *
 * Note: The transition from intact to broken is irreversible.
 */
enum CaseEntitlementStatus: string
{
    case Intact = 'intact';
    case Broken = 'broken';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Intact => 'Intact',
            self::Broken => 'Broken',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Intact => 'success',
            self::Broken => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Intact => 'heroicon-o-cube',
            self::Broken => 'heroicon-o-puzzle-piece',
        };
    }

    /**
     * Check if the case can be broken.
     * Only intact cases can be broken.
     */
    public function canBeBroken(): bool
    {
        return $this === self::Intact;
    }

    /**
     * Check if the case is already broken.
     */
    public function isBroken(): bool
    {
        return $this === self::Broken;
    }

    /**
     * Check if the case is still intact.
     */
    public function isIntact(): bool
    {
        return $this === self::Intact;
    }
}
