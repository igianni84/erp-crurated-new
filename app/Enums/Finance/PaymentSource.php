<?php

namespace App\Enums\Finance;

/**
 * Enum PaymentSource
 *
 * The source/method of a payment.
 */
enum PaymentSource: string
{
    case Stripe = 'stripe';
    case BankTransfer = 'bank_transfer';

    /**
     * Get the human-readable label for this source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::BankTransfer => 'Bank Transfer',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Stripe => 'primary',
            self::BankTransfer => 'info',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Stripe => 'heroicon-o-credit-card',
            self::BankTransfer => 'heroicon-o-building-library',
        };
    }

    /**
     * Check if this source supports automatic reconciliation.
     */
    public function supportsAutoReconciliation(): bool
    {
        return $this === self::Stripe;
    }

    /**
     * Check if this source requires manual entry.
     */
    public function requiresManualEntry(): bool
    {
        return $this === self::BankTransfer;
    }

    /**
     * Check if this source supports automatic refunds.
     */
    public function supportsAutoRefund(): bool
    {
        return $this === self::Stripe;
    }
}
