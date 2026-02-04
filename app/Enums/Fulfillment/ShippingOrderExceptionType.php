<?php

namespace App\Enums\Fulfillment;

/**
 * Enum ShippingOrderExceptionType
 *
 * Types of fulfillment exceptions that can occur during shipping order processing.
 *
 * - supply_insufficient: Planning fails due to no eligible inventory
 * - voucher_ineligible: Voucher fails eligibility check
 * - wms_discrepancy: WMS picks bottle that fails validation
 * - binding_failed: Late binding validation failed
 * - case_integrity_violated: Case integrity constraint violated
 * - ownership_constraint: Ownership/custody validation failed
 * - early_binding_failed: Early binding (Module D) validation failed
 */
enum ShippingOrderExceptionType: string
{
    case SupplyInsufficient = 'supply_insufficient';
    case VoucherIneligible = 'voucher_ineligible';
    case WmsDiscrepancy = 'wms_discrepancy';
    case BindingFailed = 'binding_failed';
    case CaseIntegrityViolated = 'case_integrity_violated';
    case OwnershipConstraint = 'ownership_constraint';
    case EarlyBindingFailed = 'early_binding_failed';

    /**
     * Get the human-readable label for this exception type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SupplyInsufficient => 'Supply Insufficient',
            self::VoucherIneligible => 'Voucher Ineligible',
            self::WmsDiscrepancy => 'WMS Discrepancy',
            self::BindingFailed => 'Binding Failed',
            self::CaseIntegrityViolated => 'Case Integrity Violated',
            self::OwnershipConstraint => 'Ownership Constraint',
            self::EarlyBindingFailed => 'Early Binding Failed',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::SupplyInsufficient => 'danger',
            self::VoucherIneligible => 'danger',
            self::WmsDiscrepancy => 'warning',
            self::BindingFailed => 'danger',
            self::CaseIntegrityViolated => 'warning',
            self::OwnershipConstraint => 'danger',
            self::EarlyBindingFailed => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::SupplyInsufficient => 'heroicon-o-exclamation-triangle',
            self::VoucherIneligible => 'heroicon-o-no-symbol',
            self::WmsDiscrepancy => 'heroicon-o-arrow-path-rounded-square',
            self::BindingFailed => 'heroicon-o-link-slash',
            self::CaseIntegrityViolated => 'heroicon-o-shield-exclamation',
            self::OwnershipConstraint => 'heroicon-o-lock-closed',
            self::EarlyBindingFailed => 'heroicon-o-bolt-slash',
        };
    }

    /**
     * Get a description of this exception type.
     */
    public function description(): string
    {
        return match ($this) {
            self::SupplyInsufficient => 'Planning failed due to no eligible inventory for allocation',
            self::VoucherIneligible => 'Voucher failed eligibility check for fulfillment',
            self::WmsDiscrepancy => 'WMS picked a bottle that fails validation',
            self::BindingFailed => 'Late binding voucher to bottle failed',
            self::CaseIntegrityViolated => 'Case integrity constraint was violated',
            self::OwnershipConstraint => 'Ownership or custody validation failed',
            self::EarlyBindingFailed => 'Early binding from Module D personalization failed',
        };
    }

    /**
     * Check if this exception type blocks SO progression.
     */
    public function isBlocking(): bool
    {
        return match ($this) {
            self::SupplyInsufficient,
            self::VoucherIneligible,
            self::BindingFailed,
            self::OwnershipConstraint,
            self::EarlyBindingFailed => true,
            self::WmsDiscrepancy,
            self::CaseIntegrityViolated => false,
        };
    }

    /**
     * Check if this exception can be auto-resolved.
     */
    public function canAutoResolve(): bool
    {
        return false; // All exceptions require manual review
    }
}
