<?php

namespace App\Enums\Finance;

/**
 * Enum XeroSyncStatus
 *
 * The status of a Xero synchronization attempt.
 */
enum XeroSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Synced => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Synced => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Synced, self::Failed],
            self::Synced => [],
            self::Failed => [self::Pending, self::Synced], // Allow retry
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a terminal success state.
     */
    public function isSuccessful(): bool
    {
        return $this === self::Synced;
    }

    /**
     * Check if retry is allowed.
     */
    public function allowsRetry(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Check if this status requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this === self::Failed;
    }
}
