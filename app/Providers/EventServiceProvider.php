<?php

namespace App\Providers;

use App\Events\Finance\SubscriptionBillingDue;
use App\Listeners\Finance\GenerateSubscriptionInvoice;
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
        Event::listen(
            SubscriptionBillingDue::class,
            GenerateSubscriptionInvoice::class
        );
    }
}
