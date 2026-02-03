<?php

namespace App\Enums\Commercial;

/**
 * Enum ExecutionStatus
 *
 * Defines the result status of a PricingPolicy execution.
 */
enum ExecutionStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    /**
     * Get the human-readable label for this execution status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Partial => 'warning',
            self::Failed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Success => 'heroicon-o-check-circle',
            self::Partial => 'heroicon-o-exclamation-triangle',
            self::Failed => 'heroicon-o-x-circle',
        };
    }
}
