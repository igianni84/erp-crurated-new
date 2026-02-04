<?php

namespace App\Enums\Finance;

/**
 * Enum InvoiceType
 *
 * The five immutable invoice types in the ERP system.
 * Invoice type is IMMUTABLE once set - it can never be changed.
 *
 * INV0: Membership Service - subscription billing
 * INV1: Voucher Sale - wine voucher purchases
 * INV2: Shipping Redemption - shipping and handling fees
 * INV3: Storage Fee - custody/storage billing
 * INV4: Service Events - events, tastings, consultations
 */
enum InvoiceType: string
{
    case MembershipService = 'membership_service';
    case VoucherSale = 'voucher_sale';
    case ShippingRedemption = 'shipping_redemption';
    case StorageFee = 'storage_fee';
    case ServiceEvents = 'service_events';

    /**
     * Get the short code for this invoice type (INV0-INV4).
     */
    public function code(): string
    {
        return match ($this) {
            self::MembershipService => 'INV0',
            self::VoucherSale => 'INV1',
            self::ShippingRedemption => 'INV2',
            self::StorageFee => 'INV3',
            self::ServiceEvents => 'INV4',
        };
    }

    /**
     * Get the human-readable label for this invoice type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MembershipService => 'Membership Service',
            self::VoucherSale => 'Voucher Sale',
            self::ShippingRedemption => 'Shipping Redemption',
            self::StorageFee => 'Storage Fee',
            self::ServiceEvents => 'Service Events',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::MembershipService => 'primary',
            self::VoucherSale => 'success',
            self::ShippingRedemption => 'info',
            self::StorageFee => 'warning',
            self::ServiceEvents => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::MembershipService => 'heroicon-o-user-group',
            self::VoucherSale => 'heroicon-o-ticket',
            self::ShippingRedemption => 'heroicon-o-truck',
            self::StorageFee => 'heroicon-o-archive-box',
            self::ServiceEvents => 'heroicon-o-calendar-days',
        };
    }

    /**
     * Check if this invoice type requires a source reference.
     * INV4 (Service Events) can be created without source for ad-hoc services.
     */
    public function requiresSourceReference(): bool
    {
        return match ($this) {
            self::MembershipService => true,
            self::VoucherSale => true,
            self::ShippingRedemption => true,
            self::StorageFee => true,
            self::ServiceEvents => false, // Can be ad-hoc
        };
    }

    /**
     * Check if this invoice type requires a due date.
     * INV0 and INV3 require due dates (deferred payment).
     * INV1, INV2, INV4 expect immediate payment.
     */
    public function requiresDueDate(): bool
    {
        return match ($this) {
            self::MembershipService => true,
            self::VoucherSale => false,
            self::ShippingRedemption => false,
            self::StorageFee => true,
            self::ServiceEvents => false,
        };
    }

    /**
     * Get the default due date offset in days from issuance.
     */
    public function defaultDueDateDays(): ?int
    {
        return match ($this) {
            self::MembershipService => 30,
            self::VoucherSale => null, // Immediate
            self::ShippingRedemption => null, // Immediate
            self::StorageFee => 30,
            self::ServiceEvents => null, // Immediate
        };
    }

    /**
     * Get the expected source type for this invoice type.
     */
    public function expectedSourceType(): string
    {
        return match ($this) {
            self::MembershipService => 'subscription',
            self::VoucherSale => 'voucher_sale',
            self::ShippingRedemption => 'shipping_order',
            self::StorageFee => 'storage_billing_period',
            self::ServiceEvents => 'event_booking', // Optional
        };
    }

    /**
     * Get descriptive text for this invoice type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MembershipService => 'Membership and subscription fees',
            self::VoucherSale => 'Wine voucher purchases',
            self::ShippingRedemption => 'Shipping and handling fees for redemptions',
            self::StorageFee => 'Wine storage and custody fees',
            self::ServiceEvents => 'Events, tastings, and consultations',
        };
    }
}
