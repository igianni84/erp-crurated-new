<?php

namespace App\Enums\Finance;

/**
 * Enum RefundMethod
 *
 * The method used to process a refund.
 */
enum RefundMethod: string
{
    case Stripe = 'stripe';
    case BankTransfer = 'bank_transfer';

    /**
     * Get the human-readable label for this method.
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
     * Check if this method supports automatic processing.
     */
    public function supportsAutoProcess(): bool
    {
        return $this === self::Stripe;
    }

    /**
     * Check if this method requires manual tracking.
     */
    public function requiresManualTracking(): bool
    {
        return $this === self::BankTransfer;
    }
}
