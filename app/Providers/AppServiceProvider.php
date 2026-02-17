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
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Finance\Subscription;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\SellableSku;
use App\Observers\Customer\CustomerObserver;
use App\Observers\Customer\PartyRoleObserver;
use App\Policies\AccountPolicy;
use App\Policies\AllocationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\Finance\CreditNotePolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\Finance\RefundPolicy;
use App\Policies\Finance\StorageBillingPeriodPolicy;
use App\Policies\Finance\SubscriptionPolicy;
use App\Policies\VoucherPolicy;
use App\Policies\VoucherTransferPolicy;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Register morph map for polymorphic relationships
        // This maps the short alias stored in DB to the full class names
        Relation::morphMap([
            'sellable_skus' => SellableSku::class,
            'liquid_products' => LiquidProduct::class,
        ]);

        // Register policies for models in subdirectories
        Gate::policy(Allocation::class, AllocationPolicy::class);
        Gate::policy(Voucher::class, VoucherPolicy::class);
        Gate::policy(VoucherTransfer::class, VoucherTransferPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);

        // Register Finance module policies (US-E122 - role-based visibility)
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(StorageBillingPeriod::class, StorageBillingPeriodPolicy::class);

        // Register observers for Module K
        PartyRole::observe(PartyRoleObserver::class);
        Customer::observe(CustomerObserver::class);

        // Register event listeners for Module D (Procurement)
        // VoucherIssued event triggers auto-creation of ProcurementIntent
        Event::listen(VoucherIssued::class, CreateProcurementIntentOnVoucherIssued::class);

        // Filament v4 global configuration
        FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('public'));
        ImageColumn::configureUsing(fn (ImageColumn $ic) => $ic->visibility('public'));
        Table::configureUsing(fn (Table $table) => $table->deferFilters(false));
    }
}
