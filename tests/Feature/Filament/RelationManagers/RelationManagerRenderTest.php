<?php

namespace Tests\Feature\Filament\RelationManagers;

use App\Filament\Resources\Allocation\AllocationResource\Pages\ViewAllocation;
use App\Filament\Resources\Allocation\AllocationResource\RelationManagers\VouchersRelationManager as AllocationVouchersRM;
use App\Filament\Resources\Customer\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\Customer\CustomerResource\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Customer\CustomerResource\RelationManagers\VouchersRelationManager as CustomerVouchersRM;
use App\Filament\Resources\Finance\InvoiceResource\Pages\ViewInvoice;
use App\Filament\Resources\Finance\InvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages\ViewShippingOrder;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\RelationManagers\LinesRelationManager;
use App\Filament\Resources\Pim\WineMasterResource\Pages\EditWineMaster;
use App\Filament\Resources\Pim\WineMasterResource\RelationManagers\VariantsRelationManager;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoicePayment;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RelationManagerRenderTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_customer_vouchers_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $customer = Customer::factory()->create();
        $vouchers = Voucher::factory()->count(2)->create(['customer_id' => $customer->id]);

        Livewire::test(CustomerVouchersRM::class, [
            'ownerRecord' => $customer,
            'pageClass' => ViewCustomer::class,
        ])->assertSuccessful();
    }

    public function test_customer_invoices_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $customer = Customer::factory()->create();
        $invoices = Invoice::factory()->count(2)->create(['customer_id' => $customer->id]);

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => ViewCustomer::class,
        ])->assertSuccessful();
    }

    public function test_allocation_vouchers_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $allocation = Allocation::factory()->create();
        $vouchers = Voucher::factory()->count(2)->create(['allocation_id' => $allocation->id]);

        Livewire::test(AllocationVouchersRM::class, [
            'ownerRecord' => $allocation,
            'pageClass' => ViewAllocation::class,
        ])->assertSuccessful();
    }

    public function test_wine_master_variants_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $wineMaster = WineMaster::factory()->create();
        $variants = WineVariant::factory()->count(2)->create(['wine_master_id' => $wineMaster->id]);

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $wineMaster,
            'pageClass' => EditWineMaster::class,
        ])->assertSuccessful();
    }

    public function test_shipping_order_lines_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $order = ShippingOrder::factory()->create();
        $lines = ShippingOrderLine::factory()->count(2)->create(['shipping_order_id' => $order->id]);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewShippingOrder::class,
        ])->assertSuccessful();
    }

    public function test_invoice_payments_relation_manager_renders(): void
    {
        $this->actingAs($this->superAdmin);

        $invoice = Invoice::factory()->create(['total_amount' => '10000.00']);
        $payment = \App\Models\Finance\Payment::factory()->create(['amount' => '10000.00']);
        InvoicePayment::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'amount_applied' => '100.00',
        ]);

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $invoice,
            'pageClass' => ViewInvoice::class,
        ])->assertSuccessful();
    }
}
