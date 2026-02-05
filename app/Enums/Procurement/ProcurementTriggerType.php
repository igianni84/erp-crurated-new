<?php

namespace App\Enums\Procurement;

/**
 * Enum ProcurementTriggerType
 *
 * Defines what triggered the procurement intent.
 */
enum ProcurementTriggerType: string
{
    case VoucherDriven = 'voucher_driven';
    case AllocationDriven = 'allocation_driven';
    case Strategic = 'strategic';
    case Contractual = 'contractual';

    /**
     * Get the human-readable label for this trigger type.
     */
    public function label(): string
    {
        return match ($this) {
            self::VoucherDriven => 'Voucher Driven',
            self::AllocationDriven => 'Allocation Driven',
            self::Strategic => 'Strategic',
            self::Contractual => 'Contractual',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::VoucherDriven => 'success',
            self::AllocationDriven => 'info',
            self::Strategic => 'warning',
            self::Contractual => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::VoucherDriven => 'heroicon-o-ticket',
            self::AllocationDriven => 'heroicon-o-cube',
            self::Strategic => 'heroicon-o-light-bulb',
            self::Contractual => 'heroicon-o-document-text',
        };
    }

    /**
     * Get the description for this trigger type.
     */
    public function description(): string
    {
        return match ($this) {
            self::VoucherDriven => 'Linked to a voucher sale',
            self::AllocationDriven => 'Pre-emptive sourcing based on allocation',
            self::Strategic => 'Speculative procurement decision',
            self::Contractual => 'Committed contractual obligation',
        };
    }
}
