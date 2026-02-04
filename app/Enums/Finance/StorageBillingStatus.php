<?php

namespace App\Enums\Finance;

/**
 * Enum StorageBillingStatus
 *
 * The status of a storage billing period.
 */
enum StorageBillingStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Blocked = 'blocked';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Invoiced => 'Invoiced',
            self::Paid => 'Paid',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Invoiced => 'info',
            self::Paid => 'success',
            self::Blocked => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Invoiced => 'heroicon-o-document-text',
            self::Paid => 'heroicon-o-check-circle',
            self::Blocked => 'heroicon-o-no-symbol',
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
            self::Pending => [self::Invoiced],
            self::Invoiced => [self::Paid, self::Blocked],
            self::Paid => [],
            self::Blocked => [self::Paid], // Unblock after payment
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
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Check if invoice generation is allowed.
     */
    public function allowsInvoicing(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if custody operations are blocked.
     */
    public function custodyBlocked(): bool
    {
        return $this === self::Blocked;
    }

    /**
     * Check if this status requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this === self::Blocked;
    }
}
