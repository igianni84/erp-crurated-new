<?php

namespace Tests\Feature\Filament\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Filament\Resources\Finance\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\Finance\PaymentResource\Pages\ViewPayment;
use App\Models\Finance\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PaymentResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page (Read-Only) ───────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListPayments::class)
            ->assertSuccessful();
    }

    public function test_list_shows_payments(): void
    {
        $this->actingAsSuperAdmin();

        $payments = Payment::factory()->count(3)->create();

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords($payments);
    }

    public function test_list_can_filter_by_source(): void
    {
        $this->actingAsSuperAdmin();

        $stripe = Payment::factory()->stripe()->create();
        $bank = Payment::factory()->bankTransfer()->create();

        Livewire::test(ListPayments::class)
            ->filterTable('source', [PaymentSource::Stripe->value])
            ->assertCanSeeTableRecords([$stripe])
            ->assertCanNotSeeTableRecords([$bank]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $pending = Payment::factory()->create();
        $confirmed = Payment::factory()->confirmed()->create();

        Livewire::test(ListPayments::class)
            ->filterTable('status', [PaymentStatus::Pending->value])
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$confirmed]);
    }

    public function test_list_can_filter_by_reconciliation_status(): void
    {
        $this->actingAsSuperAdmin();

        $pending = Payment::factory()->create();
        $matched = Payment::factory()->matched()->create();

        Livewire::test(ListPayments::class)
            ->filterTable('reconciliation_status', [ReconciliationStatus::Pending->value])
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$matched]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $payment = Payment::factory()->create();

        Livewire::test(ViewPayment::class, ['record' => $payment->id])
            ->assertSuccessful();
    }

    public function test_view_page_renders_confirmed_payment(): void
    {
        $this->actingAsSuperAdmin();

        $payment = Payment::factory()->confirmed()->create();

        Livewire::test(ViewPayment::class, ['record' => $payment->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListPayments::class)
            ->assertSuccessful();
    }
}
