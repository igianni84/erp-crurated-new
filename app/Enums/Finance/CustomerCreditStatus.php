<?php

namespace App\Enums\Finance;

/**
 * CustomerCreditStatus Enum
 *
 * Represents the status of customer credits from overpayments.
 */
enum CustomerCreditStatus: string
{
    case Available = 'available';
    case PartiallyUsed = 'partially_used';
    case FullyUsed = 'fully_used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::PartiallyUsed => 'Partially Used',
            self::FullyUsed => 'Fully Used',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for Filament badge display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::PartiallyUsed => 'warning',
            self::FullyUsed => 'gray',
            self::Expired => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Get the icon for Filament badge display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::PartiallyUsed => 'heroicon-o-minus-circle',
            self::FullyUsed => 'heroicon-o-check',
            self::Expired => 'heroicon-o-clock',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if credit can be used (applied to invoices).
     */
    public function canBeUsed(): bool
    {
        return match ($this) {
            self::Available, self::PartiallyUsed => true,
            default => false,
        };
    }

    /**
     * Check if credit is terminal (no more changes allowed).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::FullyUsed, self::Expired, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return array<CustomerCreditStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::PartiallyUsed, self::FullyUsed, self::Expired, self::Cancelled],
            self::PartiallyUsed => [self::FullyUsed, self::Expired, self::Cancelled],
            self::FullyUsed => [], // Terminal
            self::Expired => [], // Terminal
            self::Cancelled => [], // Terminal
        };
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(CustomerCreditStatus $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
