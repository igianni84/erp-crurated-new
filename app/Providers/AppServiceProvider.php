<?php

namespace App\Providers;

use App\Events\VoucherIssued;
use App\Listeners\Procurement\CreateProcurementIntentOnVoucherIssued;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\Customer\Account;
use App\Models\Customer\Customer;
use App\Models\Customer\PartyRole;
use App\Observers\Customer\CustomerObserver;
use App\Observers\Customer\PartyRoleObserver;
use App\Policies\AccountPolicy;
use App\Policies\AllocationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\VoucherPolicy;
use App\Policies\VoucherTransferPolicy;
use Illuminate\Support\Facades\Event;
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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);

        // Register observers for Module K
        PartyRole::observe(PartyRoleObserver::class);
        Customer::observe(CustomerObserver::class);

        // Register event listeners for Module D (Procurement)
        // VoucherIssued event triggers auto-creation of ProcurementIntent
        Event::listen(VoucherIssued::class, CreateProcurementIntentOnVoucherIssued::class);
    }
}
