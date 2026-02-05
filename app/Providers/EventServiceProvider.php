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
    }
}
