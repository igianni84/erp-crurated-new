<?php

namespace App\Enums\Fulfillment;

/**
 * Enum ShipmentStatus
 *
 * States for physical Shipments.
 *
 * Terminal states: delivered, failed
 */
enum ShipmentStatus: string
{
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparing',
            self::Shipped => 'Shipped',
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Preparing => 'warning',
            self::Shipped => 'info',
            self::InTransit => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Preparing => 'heroicon-o-clipboard-document-list',
            self::Shipped => 'heroicon-o-truck',
            self::InTransit => 'heroicon-o-globe-alt',
            self::Delivered => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed => true,
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
     * Get the allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Preparing => [self::Shipped, self::Failed],
            self::Shipped => [self::InTransit, self::Delivered, self::Failed],
            self::InTransit => [self::Delivered, self::Failed],
            self::Delivered => [],
            self::Failed => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
