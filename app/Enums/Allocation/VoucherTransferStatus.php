<?php

namespace App\Enums\Allocation;

/**
 * Enum VoucherTransferStatus
 *
 * Lifecycle statuses for voucher transfers between customers.
 *
 * Transitions:
 * - pending -> accepted (recipient accepts the transfer)
 * - pending -> cancelled (sender or admin cancels the transfer)
 * - pending -> expired (automatic when expires_at is reached)
 */
enum VoucherTransferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Accepted => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Expired => 'heroicon-o-x-mark',
        };
    }

    /**
     * Check if this is a terminal status (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Cancelled, self::Expired], true);
    }

    /**
     * Check if the transfer is still pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the transfer was accepted.
     */
    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Check if the transfer can still be cancelled.
     * Only pending transfers can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the transfer can still be accepted.
     * Only pending transfers can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Cancelled, self::Expired],
            self::Accepted, self::Cancelled, self::Expired => [],
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
     * Get a description of this status for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Transfer is awaiting acceptance from the recipient.',
            self::Accepted => 'Transfer has been accepted and completed.',
            self::Cancelled => 'Transfer was cancelled by the sender or an administrator.',
            self::Expired => 'Transfer expired before being accepted.',
        };
    }
}
