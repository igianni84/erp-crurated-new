<?php

namespace Tests\Feature\Services\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\User;
use App\Services\Finance\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditNoteService $service;

    private Customer $customer;

    private Invoice $issuedInvoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreditNoteService::class);
        $this->customer = Customer::factory()->create();

        $this->issuedInvoice = Invoice::factory()->issued()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '1000.00',
            'currency' => 'EUR',
        ]);
    }

    public function test_create_draft_happy_path(): void
    {
        $creditNote = $this->service->createDraft(
            $this->issuedInvoice,
            '200.00',
            'Goods returned damaged',
        );

        $this->assertEquals(CreditNoteStatus::Draft, $creditNote->status);
        $this->assertEquals('200.00', $creditNote->amount);
        $this->assertEquals('EUR', $creditNote->currency);
        $this->assertEquals($this->issuedInvoice->id, $creditNote->invoice_id);
        $this->assertEquals($this->customer->id, $creditNote->customer_id);
        $this->assertEquals('Goods returned damaged', $creditNote->reason);
        $this->assertNull($creditNote->credit_note_number);
    }

    public function test_create_draft_rejects_draft_invoice(): void
    {
        $draftInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => '500.00',
            'status' => InvoiceStatus::Draft,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not allow credit notes');

        $this->service->createDraft($draftInvoice, '100.00', 'Test reason');
    }

    public function test_create_draft_rejects_amount_exceeding_outstanding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds invoice outstanding');

        $this->service->createDraft($this->issuedInvoice, '1500.00', 'Too much');
    }

    public function test_create_draft_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $this->service->createDraft($this->issuedInvoice, '0.00', 'Zero reason');
    }

    public function test_create_draft_rejects_empty_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reason is required');

        $this->service->createDraft($this->issuedInvoice, '100.00', '  ');
    }

    public function test_issue_generates_credit_note_number(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $creditNote = $this->service->createDraft(
            $this->issuedInvoice,
            '300.00',
            'Overcharged',
        );

        $issued = $this->service->issue($creditNote);

        $this->assertEquals(CreditNoteStatus::Issued, $issued->status);
        $this->assertNotNull($issued->credit_note_number);
        $this->assertStringStartsWith('CN-', $issued->credit_note_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertEquals($user->id, $issued->issued_by);
    }

    public function test_issue_rejects_already_issued(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $creditNote = $this->service->createDraft(
            $this->issuedInvoice,
            '100.00',
            'Reason',
        );

        $this->service->issue($creditNote);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not in draft status');

        $this->service->issue($creditNote);
    }

    public function test_issue_generates_sequential_numbers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $cn1 = $this->service->createDraft($this->issuedInvoice, '100.00', 'First');
        $cn2 = $this->service->createDraft($this->issuedInvoice, '100.00', 'Second');

        $issued1 = $this->service->issue($cn1);
        $issued2 = $this->service->issue($cn2);

        $this->assertNotNull($issued1->credit_note_number);
        $this->assertNotNull($issued2->credit_note_number);
        $this->assertStringStartsWith('CN-', $issued1->credit_note_number);
        $this->assertStringStartsWith('CN-', $issued2->credit_note_number);
        $this->assertNotEquals($issued1->credit_note_number, $issued2->credit_note_number);
    }

    public function test_apply_updates_invoice_status_when_fully_credited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $creditNote = $this->service->createDraft(
            $this->issuedInvoice,
            '1000.00',
            'Full credit',
        );
        $this->service->issue($creditNote);

        $applied = $this->service->apply($creditNote);

        $this->assertEquals(CreditNoteStatus::Applied, $applied->status);
        $this->assertNotNull($applied->applied_at);

        $this->issuedInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Credited, $this->issuedInvoice->status);
    }
}
