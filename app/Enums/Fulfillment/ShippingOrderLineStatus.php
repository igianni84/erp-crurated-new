<?php

namespace App\Enums\Fulfillment;

/**
 * Enum ShippingOrderLineStatus
 *
 * Lifecycle states for Shipping Order Lines.
 *
 * Allowed transitions:
 * - pending → validated
 * - validated → picked
 * - picked → shipped
 * - any → cancelled
 */
enum ShippingOrderLineStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Picked = 'picked';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Validated => 'Validated',
            self::Picked => 'Picked',
            self::Shipped => 'Shipped',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Validated => 'info',
            self::Picked => 'warning',
            self::Shipped => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Validated => 'heroicon-o-check',
            self::Picked => 'heroicon-o-hand-raised',
            self::Shipped => 'heroicon-o-truck',
            self::Cancelled => 'heroicon-o-x-circle',
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
            self::Pending => [self::Validated, self::Cancelled],
            self::Validated => [self::Picked, self::Cancelled],
            self::Picked => [self::Shipped, self::Cancelled],
            self::Shipped => [],
            self::Cancelled => [],
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
        return match ($this) {
            self::Shipped, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Check if this status is an active (non-terminal) state.
     */
    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Check if binding can be performed in this status.
     */
    public function allowsBinding(): bool
    {
        return match ($this) {
            self::Validated => true,
            default => false,
        };
    }

    /**
     * Check if this line can be cancelled.
     */
    public function allowsCancellation(): bool
    {
        return match ($this) {
            self::Shipped, self::Cancelled => false,
            default => true,
        };
    }
}
