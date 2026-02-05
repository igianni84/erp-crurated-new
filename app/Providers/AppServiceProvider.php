<?php

namespace App\Providers;

use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Finance\Subscription;
use App\Policies\AllocationPolicy;
use App\Policies\Finance\CreditNotePolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\Finance\RefundPolicy;
use App\Policies\Finance\StorageBillingPeriodPolicy;
use App\Policies\Finance\SubscriptionPolicy;
use App\Policies\VoucherPolicy;
use App\Policies\VoucherTransferPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        // Register policies for models in subdirectories
        Gate::policy(Allocation::class, AllocationPolicy::class);
        Gate::policy(Voucher::class, VoucherPolicy::class);
        Gate::policy(VoucherTransfer::class, VoucherTransferPolicy::class);

        // Register Finance module policies (US-E122 - role-based visibility)
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(StorageBillingPeriod::class, StorageBillingPeriodPolicy::class);
    }
}
