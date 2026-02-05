<?php

namespace App\Enums\Commercial;

/**
 * Enum EmpConfidenceLevel
 *
 * Confidence levels for Estimated Market Prices.
 */
enum EmpConfidenceLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Get the human-readable label for this confidence level.
     */
    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::High => 'success',
            self::Medium => 'warning',
            self::Low => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::High => 'heroicon-o-check-badge',
            self::Medium => 'heroicon-o-exclamation-triangle',
            self::Low => 'heroicon-o-question-mark-circle',
        };
    }
}
