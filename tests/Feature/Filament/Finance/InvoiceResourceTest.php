<?php

namespace Tests\Feature\Filament\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Filament\Resources\Finance\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\Finance\InvoiceResource\Pages\ViewInvoice;
use App\Models\Finance\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page ───────────────────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }

    public function test_list_shows_invoices(): void
    {
        $this->actingAsSuperAdmin();

        $invoices = Invoice::factory()->count(3)->create();

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords($invoices);
    }

    public function test_list_can_filter_by_invoice_type(): void
    {
        $this->actingAsSuperAdmin();

        $voucher = Invoice::factory()->create(['invoice_type' => InvoiceType::VoucherSale]);
        $membership = Invoice::factory()->membership()->create();

        Livewire::test(ListInvoices::class)
            ->filterTable('invoice_type', [InvoiceType::VoucherSale->value])
            ->assertCanSeeTableRecords([$voucher])
            ->assertCanNotSeeTableRecords([$membership]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $draft = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
        $issued = Invoice::factory()->issued()->create();

        Livewire::test(ListInvoices::class)
            ->filterTable('status', [InvoiceStatus::Draft->value])
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$issued]);
    }

    // ── Create Page (Disabled — invoices are system-generated) ──

    public function test_create_is_disabled(): void
    {
        $this->assertFalse(InvoiceResource::canCreate());
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $invoice = Invoice::factory()->create();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_issued_invoice(): void
    {
        $this->actingAsSuperAdmin();

        $invoice = Invoice::factory()->issued()->create();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertSuccessful();
    }
}
