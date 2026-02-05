<?php

namespace App\Providers;

use App\Events\Finance\SubscriptionBillingDue;
use App\Events\Finance\VoucherSaleConfirmed;
use App\Listeners\Finance\GenerateSubscriptionInvoice;
use App\Listeners\Finance\GenerateVoucherSaleInvoice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Finance module events

        // INV0 - Subscription billing
        Event::listen(
            SubscriptionBillingDue::class,
            GenerateSubscriptionInvoice::class
        );

        // INV1 - Voucher sale
        Event::listen(
            VoucherSaleConfirmed::class,
            GenerateVoucherSaleInvoice::class
        );

        // InvoicePaid event (emitted by InvoiceService)
        // This event is dispatched when an invoice becomes fully paid.
        // No Finance listeners - downstream modules should listen to this event:
        // - Module A: Listens for INV1 (VoucherSale) to create/activate vouchers
        // - Module K: Listens for INV0 (MembershipService) to update membership
        // - Module C: Listens for INV2 (ShippingRedemption) to confirm shipment
        // - Module B: Listens for INV3 (StorageFee) to unlock custody operations
        // Example listener registration in other modules:
        // Event::listen(InvoicePaid::class, HandleVoucherIssuance::class);
    }
}
