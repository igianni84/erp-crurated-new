<?php

namespace App\Enums\Customer;

/**
 * Enum BlockType
 *
 * Types of operational blocks that can be applied to Customers or Accounts.
 * Each block type prevents specific operations:
 *
 * - Payment: Prevents any payment transaction
 * - Shipment: Prevents shipments
 * - Redemption: Prevents voucher redemption
 * - Trading: Prevents voucher trading
 * - Compliance: General compliance block (blocks all operations)
 */
enum BlockType: string
{
    case Payment = 'payment';
    case Shipment = 'shipment';
    case Redemption = 'redemption';
    case Trading = 'trading';
    case Compliance = 'compliance';

    /**
     * Get the human-readable label for this block type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment Block',
            self::Shipment => 'Shipment Block',
            self::Redemption => 'Redemption Block',
            self::Trading => 'Trading Block',
            self::Compliance => 'Compliance Block',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Payment => 'danger',
            self::Shipment => 'warning',
            self::Redemption => 'warning',
            self::Trading => 'warning',
            self::Compliance => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Payment => 'heroicon-o-credit-card',
            self::Shipment => 'heroicon-o-truck',
            self::Redemption => 'heroicon-o-gift',
            self::Trading => 'heroicon-o-arrows-right-left',
            self::Compliance => 'heroicon-o-shield-exclamation',
        };
    }

    /**
     * Get a description of what this block type prevents.
     */
    public function description(): string
    {
        return match ($this) {
            self::Payment => 'Prevents any payment transactions from being processed.',
            self::Shipment => 'Prevents shipments from being created or dispatched.',
            self::Redemption => 'Prevents voucher redemption operations.',
            self::Trading => 'Prevents voucher trading operations.',
            self::Compliance => 'General compliance block - prevents all operations pending compliance review.',
        };
    }

    /**
     * Check if this block type affects eligibility calculations.
     */
    public function affectsEligibility(): bool
    {
        return match ($this) {
            self::Payment => true,
            self::Compliance => true,
            self::Shipment, self::Redemption, self::Trading => false,
        };
    }

    /**
     * Check if this is a critical block type that requires immediate attention.
     */
    public function isCritical(): bool
    {
        return match ($this) {
            self::Payment => true,
            self::Compliance => true,
            self::Shipment, self::Redemption, self::Trading => false,
        };
    }

    /**
     * Get the operations that this block type prevents.
     *
     * @return list<string>
     */
    public function blockedOperations(): array
    {
        return match ($this) {
            self::Payment => ['payment', 'purchase', 'transaction'],
            self::Shipment => ['shipment', 'delivery', 'dispatch'],
            self::Redemption => ['redemption', 'voucher_use'],
            self::Trading => ['trading', 'voucher_transfer', 'voucher_sale'],
            self::Compliance => ['payment', 'shipment', 'redemption', 'trading', 'all_operations'],
        };
    }
}
