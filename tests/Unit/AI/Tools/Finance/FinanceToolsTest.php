<?php

namespace Tests\Unit\AI\Tools\Finance;

use App\AI\Tools\Finance\CreditNoteSummaryTool;
use App\AI\Tools\Finance\OutstandingInvoicesTool;
use App\AI\Tools\Finance\OverdueInvoicesTool;
use App\AI\Tools\Finance\PaymentReconciliationStatusTool;
use App\AI\Tools\Finance\RevenueSummaryTool;
use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Enums\UserRole;
use App\Models\Customer\Customer;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Unit tests for the 5 Finance AI tools.
 *
 * Each tool is tested for:
 * 1. Happy path: create data, call handle(), verify JSON output structure
 * 2. Filtering: verify filters/parameters work
 * 3. Authorization: verify authorizeForUser() returns false for insufficient role
 */
class FinanceToolsTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test-finance-tools@example.com',
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create an invoice with the given attributes, merged with sensible defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createInvoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'customer_id' => $this->customer->id,
            'invoice_type' => InvoiceType::VoucherSale,
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'issued_at' => now(),
        ], $attributes));
    }

    /**
     * Create a credit note with the given attributes, merged with sensible defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createCreditNote(array $attributes = []): CreditNote
    {
        // Ensure we have an invoice for the credit note
        $invoiceId = $attributes['invoice_id'] ?? $this->createInvoice()->id;

        return CreditNote::create(array_merge([
            'invoice_id' => $invoiceId,
            'customer_id' => $this->customer->id,
            'amount' => '50.00',
            'currency' => 'EUR',
            'reason' => 'Test credit note reason',
            'status' => CreditNoteStatus::Issued,
            'issued_at' => now(),
        ], $attributes));
    }

    /**
     * Create a payment with the given attributes, merged with sensible defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createPayment(array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('######'),
            'source' => PaymentSource::Stripe,
            'amount' => '120.00',
            'currency' => 'EUR',
            'status' => PaymentStatus::Confirmed,
            'reconciliation_status' => ReconciliationStatus::Matched,
            'received_at' => now(),
            'customer_id' => $this->customer->id,
        ], $attributes));
    }

    // =========================================================================
    // CreditNoteSummaryTool Tests
    // =========================================================================

    public function test_credit_note_summary_happy_path(): void
    {
        $invoice = $this->createInvoice();

        // Create credit notes with different statuses in the current month
        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '50.00',
            'status' => CreditNoteStatus::Draft,
        ]);
        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '30.00',
            'status' => CreditNoteStatus::Issued,
        ]);
        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '20.00',
            'status' => CreditNoteStatus::Applied,
        ]);

        $tool = new CreditNoteSummaryTool;
        $request = new Request(['period' => 'this_month']);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_credit_notes', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertArrayHasKey('by_status', $data);

        $this->assertEquals(3, $data['total_credit_notes']);

        // Verify by_status has all CreditNoteStatus labels as keys
        foreach (CreditNoteStatus::cases() as $status) {
            $this->assertArrayHasKey($status->label(), $data['by_status']);
            $this->assertArrayHasKey('count', $data['by_status'][$status->label()]);
            $this->assertArrayHasKey('amount', $data['by_status'][$status->label()]);
        }

        // Verify counts per status
        $this->assertEquals(1, $data['by_status']['Draft']['count']);
        $this->assertEquals(1, $data['by_status']['Issued']['count']);
        $this->assertEquals(1, $data['by_status']['Applied']['count']);
    }

    public function test_credit_note_summary_filters_by_status(): void
    {
        $invoice = $this->createInvoice();

        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '50.00',
            'status' => CreditNoteStatus::Draft,
        ]);
        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '30.00',
            'status' => CreditNoteStatus::Issued,
        ]);
        $this->createCreditNote([
            'invoice_id' => $invoice->id,
            'amount' => '20.00',
            'status' => CreditNoteStatus::Issued,
        ]);

        $tool = new CreditNoteSummaryTool;
        $request = new Request([
            'period' => 'this_month',
            'status' => CreditNoteStatus::Issued->value,
        ]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // When filtering by status, only matching credit notes are counted
        $this->assertEquals(2, $data['total_credit_notes']);
    }

    public function test_credit_note_summary_authorization_denied_for_viewer(): void
    {
        // CreditNoteSummaryTool requires Full access (Admin or SuperAdmin)
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $tool = new CreditNoteSummaryTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
        $this->assertFalse($tool->authorizeForUser($editor));
        $this->assertFalse($tool->authorizeForUser($manager));
    }

    public function test_credit_note_summary_authorization_granted_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $tool = new CreditNoteSummaryTool;

        $this->assertTrue($tool->authorizeForUser($admin));
        $this->assertTrue($tool->authorizeForUser($superAdmin));
    }

    // =========================================================================
    // OutstandingInvoicesTool Tests
    // =========================================================================

    public function test_outstanding_invoices_happy_path(): void
    {
        // Create invoices with outstanding balances (Issued or PartiallyPaid)
        $this->createInvoice([
            'invoice_number' => 'INV-OUT-001',
            'total_amount' => '200.00',
            'amount_paid' => '50.00',
            'status' => InvoiceStatus::PartiallyPaid,
        ]);
        $this->createInvoice([
            'invoice_number' => 'INV-OUT-002',
            'total_amount' => '300.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
        ]);
        // This one should NOT appear (Paid)
        $this->createInvoice([
            'invoice_number' => 'INV-OUT-003',
            'total_amount' => '100.00',
            'amount_paid' => '100.00',
            'status' => InvoiceStatus::Paid,
        ]);

        $tool = new OutstandingInvoicesTool;
        $request = new Request([]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_outstanding_amount', $data);
        $this->assertArrayHasKey('invoice_count', $data);
        $this->assertArrayHasKey('invoices', $data);

        // Only issued/partially_paid invoices should appear
        $this->assertEquals(2, $data['invoice_count']);

        // Check invoice structure
        $firstInvoice = $data['invoices'][0];
        $this->assertArrayHasKey('invoice_number', $firstInvoice);
        $this->assertArrayHasKey('customer_name', $firstInvoice);
        $this->assertArrayHasKey('invoice_type', $firstInvoice);
        $this->assertArrayHasKey('total_amount', $firstInvoice);
        $this->assertArrayHasKey('amount_paid', $firstInvoice);
        $this->assertArrayHasKey('outstanding', $firstInvoice);
        $this->assertArrayHasKey('issued_at', $firstInvoice);
        $this->assertArrayHasKey('due_date', $firstInvoice);
        $this->assertArrayHasKey('is_overdue', $firstInvoice);

        // Invoices should be ordered by outstanding DESC (300 first, 150 second)
        $this->assertStringContainsString('INV-OUT-002', $data['invoices'][0]['invoice_number']);
    }

    public function test_outstanding_invoices_filters_by_invoice_type(): void
    {
        $this->createInvoice([
            'invoice_number' => 'INV-FILT-001',
            'invoice_type' => InvoiceType::VoucherSale,
            'total_amount' => '500.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
        ]);
        $this->createInvoice([
            'invoice_number' => 'INV-FILT-002',
            'invoice_type' => InvoiceType::StorageFee,
            'total_amount' => '200.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
        ]);
        $this->createInvoice([
            'invoice_number' => 'INV-FILT-003',
            'invoice_type' => InvoiceType::VoucherSale,
            'total_amount' => '50.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
        ]);

        $tool = new OutstandingInvoicesTool;

        // Filter by invoice_type only
        $request = new Request([
            'invoice_type' => InvoiceType::VoucherSale->value,
        ]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Only voucher_sale invoices should appear (INV-FILT-001 and INV-FILT-003)
        $this->assertEquals(2, $data['invoice_count']);

        // Should be ordered by outstanding DESC (500 first, 50 second)
        $invoiceNumbers = array_column($data['invoices'], 'invoice_number');
        $this->assertContains('INV-FILT-001', $invoiceNumbers);
        $this->assertContains('INV-FILT-003', $invoiceNumbers);
        $this->assertNotContains('INV-FILT-002', $invoiceNumbers);
    }

    public function test_outstanding_invoices_authorization_denied_for_viewer_and_editor(): void
    {
        // OutstandingInvoicesTool requires Standard access (Manager+)
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new OutstandingInvoicesTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_outstanding_invoices_authorization_granted_for_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $tool = new OutstandingInvoicesTool;

        $this->assertTrue($tool->authorizeForUser($manager));
        $this->assertTrue($tool->authorizeForUser($admin));
    }

    // =========================================================================
    // OverdueInvoicesTool Tests
    // =========================================================================

    public function test_overdue_invoices_happy_path(): void
    {
        // Create overdue invoice (due_date in the past, status = issued)
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-001',
            'total_amount' => '200.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
            'due_date' => Carbon::today()->subDays(10),
        ]);
        // Another overdue invoice
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-002',
            'total_amount' => '300.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
            'due_date' => Carbon::today()->subDays(5),
        ]);
        // NOT overdue (due_date in the future)
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-003',
            'total_amount' => '100.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Issued,
            'due_date' => Carbon::today()->addDays(10),
        ]);
        // NOT overdue (paid)
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-004',
            'total_amount' => '100.00',
            'amount_paid' => '100.00',
            'status' => InvoiceStatus::Paid,
            'due_date' => Carbon::today()->subDays(20),
        ]);

        $tool = new OverdueInvoicesTool;
        $request = new Request([]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_overdue_count', $data);
        $this->assertArrayHasKey('total_overdue_amount', $data);
        $this->assertArrayHasKey('invoices', $data);

        $this->assertEquals(2, $data['total_overdue_count']);

        // Check invoice structure
        $firstInvoice = $data['invoices'][0];
        $this->assertArrayHasKey('invoice_number', $firstInvoice);
        $this->assertArrayHasKey('customer_name', $firstInvoice);
        $this->assertArrayHasKey('invoice_type', $firstInvoice);
        $this->assertArrayHasKey('total_amount', $firstInvoice);
        $this->assertArrayHasKey('due_date', $firstInvoice);
        $this->assertArrayHasKey('days_overdue', $firstInvoice);

        // Should be ordered by due_date ASC (oldest overdue first)
        $this->assertStringContainsString('INV-OVD-001', $data['invoices'][0]['invoice_number']);
    }

    public function test_overdue_invoices_filters_by_days_overdue_min(): void
    {
        // 10 days overdue
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-F1',
            'status' => InvoiceStatus::Issued,
            'due_date' => Carbon::today()->subDays(10),
        ]);
        // 3 days overdue
        $this->createInvoice([
            'invoice_number' => 'INV-OVD-F2',
            'status' => InvoiceStatus::Issued,
            'due_date' => Carbon::today()->subDays(3),
        ]);

        $tool = new OverdueInvoicesTool;

        // Only show invoices at least 7 days overdue
        $request = new Request(['days_overdue_min' => 7]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Only the 10-day overdue invoice should match
        $this->assertEquals(1, $data['total_overdue_count']);
        $this->assertStringContainsString('INV-OVD-F1', $data['invoices'][0]['invoice_number']);
    }

    public function test_overdue_invoices_authorization_denied_for_viewer_and_editor(): void
    {
        // OverdueInvoicesTool requires Standard access (Manager+)
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new OverdueInvoicesTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_overdue_invoices_authorization_granted_for_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $tool = new OverdueInvoicesTool;

        $this->assertTrue($tool->authorizeForUser($manager));
    }

    // =========================================================================
    // PaymentReconciliationStatusTool Tests
    // =========================================================================

    public function test_payment_reconciliation_status_happy_path(): void
    {
        // Create payments with different reconciliation statuses
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Pending,
        ]);
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Mismatched,
        ]);

        $tool = new PaymentReconciliationStatusTool;
        $request = new Request([]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_payments', $data);
        $this->assertArrayHasKey('by_reconciliation_status', $data);

        $this->assertEquals(4, $data['total_payments']);

        // Verify all ReconciliationStatus labels are present
        foreach (ReconciliationStatus::cases() as $status) {
            $this->assertArrayHasKey($status->label(), $data['by_reconciliation_status']);
        }

        $this->assertEquals(1, $data['by_reconciliation_status']['Pending']);
        $this->assertEquals(2, $data['by_reconciliation_status']['Matched']);
        $this->assertEquals(1, $data['by_reconciliation_status']['Mismatched']);

        // When no status filter, mismatched_details should not be present
        $this->assertArrayNotHasKey('mismatched_details', $data);
    }

    public function test_payment_reconciliation_status_filters_by_mismatched_shows_details(): void
    {
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Mismatched,
            'amount' => '500.00',
        ]);
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        $tool = new PaymentReconciliationStatusTool;

        // Filter to show mismatched details
        $request = new Request(['status' => ReconciliationStatus::Mismatched->value]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // When filtering by 'mismatched', mismatched_details should be included
        $this->assertArrayHasKey('mismatched_details', $data);
        $this->assertIsArray($data['mismatched_details']);
        $this->assertCount(1, $data['mismatched_details']);

        $detail = $data['mismatched_details'][0];
        $this->assertArrayHasKey('payment_reference', $detail);
        $this->assertArrayHasKey('customer_name', $detail);
        $this->assertArrayHasKey('amount', $detail);
        $this->assertArrayHasKey('source', $detail);
        $this->assertArrayHasKey('received_at', $detail);
    }

    public function test_payment_reconciliation_status_non_mismatched_filter_omits_details(): void
    {
        $this->createPayment([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        $tool = new PaymentReconciliationStatusTool;

        // Filter by 'matched' - mismatched_details should NOT be present
        $request = new Request(['status' => ReconciliationStatus::Matched->value]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertArrayNotHasKey('mismatched_details', $data);
    }

    public function test_payment_reconciliation_status_authorization_denied_for_viewer(): void
    {
        // PaymentReconciliationStatusTool requires Full access (Admin or SuperAdmin)
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $tool = new PaymentReconciliationStatusTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
        $this->assertFalse($tool->authorizeForUser($editor));
        $this->assertFalse($tool->authorizeForUser($manager));
    }

    public function test_payment_reconciliation_status_authorization_granted_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $tool = new PaymentReconciliationStatusTool;

        $this->assertTrue($tool->authorizeForUser($admin));
        $this->assertTrue($tool->authorizeForUser($superAdmin));
    }

    // =========================================================================
    // RevenueSummaryTool Tests
    // =========================================================================

    public function test_revenue_summary_happy_path(): void
    {
        // Create invoices issued this month with different types
        $this->createInvoice([
            'invoice_type' => InvoiceType::VoucherSale,
            'subtotal' => '100.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'amount_paid' => '120.00',
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
        ]);
        $this->createInvoice([
            'invoice_type' => InvoiceType::MembershipService,
            'subtotal' => '50.00',
            'tax_amount' => '10.00',
            'total_amount' => '60.00',
            'amount_paid' => '30.00',
            'status' => InvoiceStatus::PartiallyPaid,
            'issued_at' => now(),
        ]);
        // Draft invoice should NOT be included in revenue
        $this->createInvoice([
            'invoice_type' => InvoiceType::VoucherSale,
            'subtotal' => '200.00',
            'tax_amount' => '40.00',
            'total_amount' => '240.00',
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Draft,
            'issued_at' => now(),
        ]);

        $tool = new RevenueSummaryTool;
        $request = new Request(['period' => 'this_month', 'group_by' => 'invoice_type']);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('gross_revenue', $data);
        $this->assertArrayHasKey('tax_total', $data);
        $this->assertArrayHasKey('net_revenue', $data);
        $this->assertArrayHasKey('amount_collected', $data);
        $this->assertArrayHasKey('outstanding', $data);
        $this->assertArrayHasKey('breakdown', $data);

        $this->assertEquals('this_month', $data['period']);

        // Verify breakdown has all InvoiceType labels
        foreach (InvoiceType::cases() as $type) {
            $this->assertArrayHasKey($type->label(), $data['breakdown']);
            $this->assertArrayHasKey('amount', $data['breakdown'][$type->label()]);
            $this->assertArrayHasKey('count', $data['breakdown'][$type->label()]);
        }

        // VoucherSale: 1 invoice, MembershipService: 1 invoice (Draft excluded)
        $this->assertEquals(1, $data['breakdown']['Voucher Sale']['count']);
        $this->assertEquals(1, $data['breakdown']['Membership Service']['count']);
    }

    public function test_revenue_summary_filters_by_period(): void
    {
        // Invoice issued this month
        $this->createInvoice([
            'invoice_type' => InvoiceType::VoucherSale,
            'total_amount' => '120.00',
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
        ]);
        // Invoice issued last month
        $this->createInvoice([
            'invoice_type' => InvoiceType::VoucherSale,
            'total_amount' => '200.00',
            'status' => InvoiceStatus::Issued,
            'issued_at' => now()->subMonth()->startOfMonth()->addDay(),
        ]);

        $tool = new RevenueSummaryTool;

        // Query for this_month only
        $request = new Request(['period' => 'this_month', 'group_by' => 'none']);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        // Only the invoice issued this month should be included
        // Breakdown should not be present when group_by = none
        $this->assertArrayNotHasKey('breakdown', $data);

        // gross_revenue should only reflect the one from this month
        $this->assertStringContainsString('120', $data['gross_revenue']);
    }

    public function test_revenue_summary_authorization_denied_for_viewer_and_editor(): void
    {
        // RevenueSummaryTool requires Standard access (Manager+)
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new RevenueSummaryTool;

        $this->assertFalse($tool->authorizeForUser($viewer));
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_revenue_summary_authorization_granted_for_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $tool = new RevenueSummaryTool;

        $this->assertTrue($tool->authorizeForUser($manager));
        $this->assertTrue($tool->authorizeForUser($admin));
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function test_credit_note_summary_empty_result(): void
    {
        $tool = new CreditNoteSummaryTool;
        $request = new Request(['period' => 'this_month']);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(0, $data['total_credit_notes']);

        // All status counts should be 0
        foreach (CreditNoteStatus::cases() as $status) {
            $this->assertEquals(0, $data['by_status'][$status->label()]['count']);
        }
    }

    public function test_outstanding_invoices_respects_limit_parameter(): void
    {
        // Create 5 outstanding invoices
        for ($i = 0; $i < 5; $i++) {
            $this->createInvoice([
                'total_amount' => (string) (($i + 1) * 100).'.00',
                'amount_paid' => '0.00',
                'status' => InvoiceStatus::Issued,
            ]);
        }

        $tool = new OutstandingInvoicesTool;
        $request = new Request(['limit' => 2]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(2, $data['invoice_count']);
        $this->assertCount(2, $data['invoices']);
    }

    public function test_overdue_invoices_empty_result(): void
    {
        // No invoices at all
        $tool = new OverdueInvoicesTool;
        $request = new Request([]);

        $result = $tool->handle($request);
        $data = json_decode((string) $result, true);

        $this->assertEquals(0, $data['total_overdue_count']);
        $this->assertEmpty($data['invoices']);
    }

    public function test_viewer_role_denied_for_all_finance_tools(): void
    {
        // Viewer has Overview access (level 10), which is insufficient for
        // Standard (level 40) and Full (level 60) tools
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tools = [
            new CreditNoteSummaryTool,      // Full access required
            new OutstandingInvoicesTool,     // Standard access required
            new OverdueInvoicesTool,         // Standard access required
            new PaymentReconciliationStatusTool, // Full access required
            new RevenueSummaryTool,          // Standard access required
        ];

        foreach ($tools as $tool) {
            $this->assertFalse(
                $tool->authorizeForUser($viewer),
                get_class($tool).' should deny access for Viewer role'
            );
        }
    }
}
