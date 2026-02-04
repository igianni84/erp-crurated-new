<?php

namespace App\Enums\Finance;

/**
 * Enum XeroSyncType
 *
 * The type of entity being synchronized with Xero.
 */
enum XeroSyncType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Payment = 'payment';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit Note',
            self::Payment => 'Payment',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Invoice => 'primary',
            self::CreditNote => 'warning',
            self::Payment => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Invoice => 'heroicon-o-document-text',
            self::CreditNote => 'heroicon-o-receipt-refund',
            self::Payment => 'heroicon-o-banknotes',
        };
    }

    /**
     * Get the model class name for this sync type.
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Invoice => 'App\Models\Finance\Invoice',
            self::CreditNote => 'App\Models\Finance\CreditNote',
            self::Payment => 'App\Models\Finance\Payment',
        };
    }
}
