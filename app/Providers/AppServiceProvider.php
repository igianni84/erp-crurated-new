<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Events\VoucherIssued;
use App\Listeners\Procurement\CreateProcurementIntentOnVoucherIssued;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\CaseEntitlement;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\Commercial\Bundle;
use App\Models\Commercial\Channel;
use App\Models\Commercial\DiscountRule;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PricingPolicy;
use App\Models\Customer\Account;
use App\Models\Customer\Club;
use App\Models\Customer\Customer;
use App\Models\Customer\OperationalBlock;
use App\Models\Customer\Party;
use App\Models\Customer\PartyRole;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Finance\Subscription;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\Pim\Appellation;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Country;
use App\Models\Pim\Format;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\Procurement\BottlingInstruction;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use App\Observers\Customer\CustomerObserver;
use App\Observers\Customer\PartyRoleObserver;
use App\Observers\Pim\PimCacheObserver;
use App\Policies\AccountPolicy;
use App\Policies\AllocationPolicy;
use App\Policies\ChannelPolicy;
use App\Policies\ClubPolicy;
use App\Policies\Commercial\BundlePolicy;
use App\Policies\Commercial\DiscountRulePolicy;
use App\Policies\Commercial\OfferPolicy;
use App\Policies\Commercial\PriceBookPolicy;
use App\Policies\Commercial\PricingPolicyPolicy;
use App\Policies\Customer\CaseEntitlementPolicy;
use App\Policies\Customer\OperationalBlockPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\Finance\CreditNotePolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\Finance\RefundPolicy;
use App\Policies\Finance\StorageBillingPeriodPolicy;
use App\Policies\Finance\SubscriptionPolicy;
use App\Policies\Fulfillment\ShipmentPolicy;
use App\Policies\Fulfillment\ShippingOrderExceptionPolicy;
use App\Policies\Fulfillment\ShippingOrderPolicy;
use App\Policies\Inventory\InventoryCasePolicy;
use App\Policies\Inventory\InventoryMovementPolicy;
use App\Policies\Inventory\SerializedBottlePolicy;
use App\Policies\LocationPolicy;
use App\Policies\PartyPolicy;
use App\Policies\Pim\AppellationPolicy;
use App\Policies\Pim\CaseConfigurationPolicy;
use App\Policies\Pim\CountryPolicy;
use App\Policies\Pim\FormatPolicy;
use App\Policies\Pim\LiquidProductPolicy;
use App\Policies\Pim\ProducerPolicy;
use App\Policies\Pim\RegionPolicy;
use App\Policies\Pim\SellableSkuPolicy;
use App\Policies\Pim\WineMasterPolicy;
use App\Policies\Pim\WineVariantPolicy;
use App\Policies\Procurement\BottlingInstructionPolicy;
use App\Policies\Procurement\InboundPolicy;
use App\Policies\Procurement\ProcurementIntentPolicy;
use App\Policies\Procurement\PurchaseOrderPolicy;
use App\Policies\VoucherPolicy;
use App\Policies\VoucherTransferPolicy;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

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
        // Enforce model strictness in non-production environments
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Log lazy loading violations instead of throwing exceptions
        // This allows detecting N+1 issues without breaking the app
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            logger()->warning("Lazy loading [{$relation}] on model [".$model::class.'].');
        });

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
        Gate::policy(Party::class, PartyPolicy::class);
        Gate::policy(Club::class, ClubPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);

        // Register Finance module policies (US-E122 - role-based visibility)
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(StorageBillingPeriod::class, StorageBillingPeriodPolicy::class);

        // Register PIM module policies
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Region::class, RegionPolicy::class);
        Gate::policy(Appellation::class, AppellationPolicy::class);
        Gate::policy(Format::class, FormatPolicy::class);
        Gate::policy(CaseConfiguration::class, CaseConfigurationPolicy::class);
        Gate::policy(Producer::class, ProducerPolicy::class);
        Gate::policy(LiquidProduct::class, LiquidProductPolicy::class);
        Gate::policy(WineMaster::class, WineMasterPolicy::class);
        Gate::policy(WineVariant::class, WineVariantPolicy::class);
        Gate::policy(SellableSku::class, SellableSkuPolicy::class);

        // Register Commercial module policies
        Gate::policy(Bundle::class, BundlePolicy::class);
        Gate::policy(DiscountRule::class, DiscountRulePolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(PriceBook::class, PriceBookPolicy::class);
        Gate::policy(PricingPolicy::class, PricingPolicyPolicy::class);

        // Register Procurement module policies
        Gate::policy(ProcurementIntent::class, ProcurementIntentPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Inbound::class, InboundPolicy::class);
        Gate::policy(BottlingInstruction::class, BottlingInstructionPolicy::class);

        // Register Customer module policies
        Gate::policy(CaseEntitlement::class, CaseEntitlementPolicy::class);
        Gate::policy(OperationalBlock::class, OperationalBlockPolicy::class);

        // Register Inventory module policies (read-only)
        Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
        Gate::policy(SerializedBottle::class, SerializedBottlePolicy::class);
        Gate::policy(InventoryCase::class, InventoryCasePolicy::class);

        // Register Fulfillment module policies
        Gate::policy(ShippingOrder::class, ShippingOrderPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(ShippingOrderException::class, ShippingOrderExceptionPolicy::class);

        // Register observers for Module K
        PartyRole::observe(PartyRoleObserver::class);
        Customer::observe(CustomerObserver::class);

        // Register PIM cache invalidation observer
        Country::observe(PimCacheObserver::class);
        Region::observe(PimCacheObserver::class);
        Producer::observe(PimCacheObserver::class);
        Appellation::observe(PimCacheObserver::class);

        // Register event listeners for Module D (Procurement)
        // VoucherIssued event triggers auto-creation of ProcurementIntent
        Event::listen(VoucherIssued::class, CreateProcurementIntentOnVoucherIssued::class);

        // Register rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
        RateLimiter::for('trading-api', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        RateLimiter::for('login', function (Request $request) {
            $key = str($request->input('email', ''))->lower().'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Filament v5 global configuration
        FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('private'));
        ImageColumn::configureUsing(fn (ImageColumn $ic) => $ic->visibility('private'));
        Table::configureUsing(fn (Table $table) => $table->deferFilters(false));

        // API documentation access gate (Scramble)
        Gate::define('viewApiDocs', function (?User $user): bool {
            return app()->isLocal() || ($user !== null && $user->role === UserRole::SuperAdmin);
        });

        // Register Pennant feature flag discovery
        Feature::discover();
    }
}
