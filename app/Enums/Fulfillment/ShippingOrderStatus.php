<?php

namespace App\Enums\Fulfillment;

/**
 * Enum ShippingOrderStatus
 *
 * Lifecycle states for Shipping Orders.
 *
 * Allowed transitions:
 * - draft → planned
 * - planned → picking
 * - picking → shipped
 * - shipped → completed
 * - any → cancelled
 * - any → on_hold
 * - on_hold → previous_state (handled via state machine)
 */
enum ShippingOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Picking = 'picking';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Planned',
            self::Picking => 'Picking',
            self::Shipped => 'Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On Hold',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Planned => 'info',
            self::Picking => 'warning',
            self::Shipped => 'success',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::OnHold => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Planned => 'heroicon-o-calendar',
            self::Picking => 'heroicon-o-hand-raised',
            self::Shipped => 'heroicon-o-truck',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::OnHold => 'heroicon-o-pause-circle',
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
            self::Draft => [self::Planned, self::Cancelled, self::OnHold],
            self::Planned => [self::Picking, self::Cancelled, self::OnHold],
            self::Picking => [self::Shipped, self::Cancelled, self::OnHold],
            self::Shipped => [self::Completed, self::Cancelled, self::OnHold],
            self::Completed => [],
            self::Cancelled => [],
            self::OnHold => [self::Draft, self::Planned, self::Picking, self::Shipped, self::Cancelled],
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
            self::Completed, self::Cancelled => true,
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
     * Check if the SO is in a state where vouchers should be locked.
     */
    public function requiresVoucherLock(): bool
    {
        return match ($this) {
            self::Planned, self::Picking, self::Shipped, self::OnHold => true,
            self::Draft, self::Completed, self::Cancelled => false,
        };
    }

    /**
     * Check if this status allows editing of SO details.
     */
    public function allowsEditing(): bool
    {
        return match ($this) {
            self::Draft => true,
            default => false,
        };
    }

    /**
     * Check if this status allows cancellation.
     */
    public function allowsCancellation(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => false,
            default => true,
        };
    }
}
