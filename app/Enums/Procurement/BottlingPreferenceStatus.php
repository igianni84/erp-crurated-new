<?php

namespace App\Enums\Procurement;

/**
 * Enum BottlingPreferenceStatus
 *
 * Tracks the status of customer preference collection for bottling instructions.
 */
enum BottlingPreferenceStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Complete = 'complete';
    case Defaulted = 'defaulted';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partial',
            self::Complete => 'Complete',
            self::Defaulted => 'Defaulted',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Partial => 'warning',
            self::Complete => 'success',
            self::Defaulted => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Partial => 'heroicon-o-ellipsis-horizontal-circle',
            self::Complete => 'heroicon-o-check-circle',
            self::Defaulted => 'heroicon-o-exclamation-circle',
        };
    }

    /**
     * Check if preferences are still being collected.
     */
    public function isCollecting(): bool
    {
        return in_array($this, [self::Pending, self::Partial], true);
    }

    /**
     * Check if defaults were applied due to deadline expiry.
     */
    public function wasDefaulted(): bool
    {
        return $this === self::Defaulted;
    }
}
