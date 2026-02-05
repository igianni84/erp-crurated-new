<?php

namespace App\Enums\Procurement;

/**
 * Enum PurchaseOrderStatus
 *
 * Lifecycle statuses for purchase orders.
 * Transitions: draft -> sent -> confirmed -> closed
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Closed = 'closed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Confirmed => 'Confirmed',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Confirmed => 'success',
            self::Closed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::Sent => 'heroicon-o-paper-airplane',
            self::Confirmed => 'heroicon-o-check-circle',
            self::Closed => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Confirmed],
            self::Confirmed => [self::Closed],
            self::Closed => [],
        };
    }

    /**
     * Check if transition to given status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }
}
