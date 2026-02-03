<?php

namespace App\Enums\Allocation;

/**
 * Enum VoucherLifecycleState
 *
 * Lifecycle states for vouchers (customer entitlements).
 *
 * Valid transitions:
 * - issued -> locked (lock for fulfillment)
 * - issued -> cancelled (cancel issued voucher)
 * - locked -> redeemed (complete redemption)
 * - locked -> issued (unlock/release)
 *
 * Terminal states: redeemed, cancelled (no further transitions allowed)
 */
enum VoucherLifecycleState: string
{
    case Issued = 'issued';
    case Locked = 'locked';
    case Redeemed = 'redeemed';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Locked => 'Locked',
            self::Redeemed => 'Redeemed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Issued => 'success',
            self::Locked => 'warning',
            self::Redeemed => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Issued => 'heroicon-o-ticket',
            self::Locked => 'heroicon-o-lock-closed',
            self::Redeemed => 'heroicon-o-check-badge',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if this is a terminal state (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Redeemed, self::Cancelled], true);
    }

    /**
     * Check if this state is active (not terminal).
     */
    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Get allowed transitions from this state.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Issued => [self::Locked, self::Cancelled],
            self::Locked => [self::Redeemed, self::Issued],
            self::Redeemed, self::Cancelled => [],
        };
    }

    /**
     * Check if transition to given state is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if the voucher can be traded or transferred in this state.
     * Only issued vouchers can be traded/transferred.
     */
    public function allowsTrading(): bool
    {
        return $this === self::Issued;
    }

    /**
     * Check if behavioral flags can be modified in this state.
     * Flags can be modified on non-terminal vouchers.
     */
    public function allowsFlagModification(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Get a description of this state for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::Issued => 'Voucher is active and available for the customer.',
            self::Locked => 'Voucher is locked for fulfillment processing.',
            self::Redeemed => 'Voucher has been redeemed and fulfilled.',
            self::Cancelled => 'Voucher has been cancelled and is no longer valid.',
        };
    }
}
