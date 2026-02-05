<?php

namespace App\Services\Finance;

use App\Enums\Finance\CreditNoteStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Service for managing CreditNote lifecycle and operations.
 *
 * Centralizes all credit note business logic including creation,
 * issuance, and application to invoices.
 *
 * Credit notes preserve the invoice_type of the original invoice
 * for proper reporting categorization.
 */
class CreditNoteService
{
    /**
     * Create a draft credit note for an invoice.
     *
     * @param  Invoice  $invoice  The invoice to create credit note against
     * @param  string  $amount  The credit note amount
     * @param  string  $reason  The reason for the credit note (required)
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function createDraft(Invoice $invoice, string $amount, string $reason): CreditNote
    {
        // Validate invoice allows credit notes
        if (! $invoice->status->allowsCreditNote()) {
            throw new InvalidArgumentException(
                "Cannot create credit note: invoice status '{$invoice->status->label()}' does not allow credit notes."
            );
        }

        // Validate amount is positive
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(
                'Cannot create credit note: amount must be greater than zero.'
            );
        }

        // Validate amount doesn't exceed invoice outstanding
        $outstanding = $this->getInvoiceOutstanding($invoice);
        if (bccomp($amount, $outstanding, 2) > 0) {
            throw new InvalidArgumentException(
                "Cannot create credit note: amount ({$amount}) exceeds invoice outstanding balance ({$outstanding})."
            );
        }

        // Validate reason is provided
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(
                'Cannot create credit note: reason is required.'
            );
        }

        return DB::transaction(function () use ($invoice, $amount, $reason): CreditNote {
            $creditNote = CreditNote::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'reason' => $reason,
                'status' => CreditNoteStatus::Draft,
            ]);

            // Log creation
            $this->logCreditNoteEvent(
                $creditNote,
                AuditLog::EVENT_CREATED,
                [],
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $amount,
                    'currency' => $invoice->currency,
                    'reason' => $reason,
                ]
            );

            Log::channel('finance')->info('Credit note draft created', [
                'credit_note_id' => $creditNote->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $amount,
                'currency' => $invoice->currency,
            ]);

            return $creditNote;
        });
    }

    /**
     * Issue a draft credit note.
     *
     * Generates sequential credit_note_number (format: CN-YYYY-NNNNNN),
     * sets issued_at, triggers Xero sync, and updates invoice status if fully credited.
     *
     * @throws InvalidArgumentException If credit note is not in draft status
     */
    public function issue(CreditNote $creditNote): CreditNote
    {
        if (! $creditNote->isDraft()) {
            throw new InvalidArgumentException(
                "Cannot issue credit note: credit note is not in draft status. Current status: {$creditNote->status->label()}"
            );
        }

        if (! $creditNote->canBeIssued()) {
            throw new InvalidArgumentException(
                'Cannot issue credit note: transition from draft to issued is not allowed.'
            );
        }

        return DB::transaction(function () use ($creditNote): CreditNote {
            $oldStatus = $creditNote->status;

            // Generate credit note number
            $creditNoteNumber = $this->generateCreditNoteNumber();

            // Update credit note
            $creditNote->credit_note_number = $creditNoteNumber;
            $creditNote->status = CreditNoteStatus::Issued;
            $creditNote->issued_at = now();
            $creditNote->issued_by = Auth::id();
            $creditNote->save();

            // Log the issuance
            $this->logCreditNoteEvent(
                $creditNote,
                AuditLog::EVENT_STATUS_CHANGE,
                [
                    'status' => $oldStatus->value,
                    'credit_note_number' => null,
                    'issued_at' => null,
                ],
                [
                    'status' => CreditNoteStatus::Issued->value,
                    'credit_note_number' => $creditNoteNumber,
                    'issued_at' => $creditNote->issued_at->toIso8601String(),
                ]
            );

            Log::channel('finance')->info('Credit note issued', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNoteNumber,
                'invoice_id' => $creditNote->invoice_id,
                'amount' => $creditNote->amount,
                'currency' => $creditNote->currency,
                'issued_by' => Auth::id(),
            ]);

            // Check if invoice should be marked as credited
            $this->updateInvoiceStatusIfFullyCredited($creditNote);

            // TODO: Trigger Xero sync event (US-E099)
            // event(new CreditNoteIssued($creditNote));

            return $creditNote;
        });
    }

    /**
     * Apply an issued credit note to the invoice.
     *
     * Marks the credit note as applied and updates invoice status if needed.
     *
     * @throws InvalidArgumentException If credit note cannot be applied
     */
    public function apply(CreditNote $creditNote): CreditNote
    {
        if (! $creditNote->isIssued()) {
            throw new InvalidArgumentException(
                "Cannot apply credit note: credit note is not in issued status. Current status: {$creditNote->status->label()}"
            );
        }

        if (! $creditNote->canBeApplied()) {
            throw new InvalidArgumentException(
                'Cannot apply credit note: transition from issued to applied is not allowed.'
            );
        }

        return DB::transaction(function () use ($creditNote): CreditNote {
            $oldStatus = $creditNote->status;

            // Update credit note
            $creditNote->status = CreditNoteStatus::Applied;
            $creditNote->applied_at = now();
            $creditNote->save();

            // Log the application
            $this->logCreditNoteEvent(
                $creditNote,
                AuditLog::EVENT_STATUS_CHANGE,
                [
                    'status' => $oldStatus->value,
                    'applied_at' => null,
                ],
                [
                    'status' => CreditNoteStatus::Applied->value,
                    'applied_at' => $creditNote->applied_at->toIso8601String(),
                ]
            );

            Log::channel('finance')->info('Credit note applied', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'invoice_id' => $creditNote->invoice_id,
                'amount' => $creditNote->amount,
            ]);

            return $creditNote;
        });
    }

    /**
     * Generate a sequential credit note number.
     *
     * Format: CN-YYYY-NNNNNN (e.g., CN-2026-000001)
     */
    protected function generateCreditNoteNumber(): string
    {
        $year = now()->year;
        $prefix = "CN-{$year}-";

        // Get the last credit note number for this year
        $lastCreditNote = CreditNote::where('credit_note_number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING(credit_note_number, -6) AS UNSIGNED) DESC')
            ->first();

        if ($lastCreditNote !== null && $lastCreditNote->credit_note_number !== null) {
            $lastNumber = (int) substr($lastCreditNote->credit_note_number, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update invoice status if it is fully credited.
     *
     * When the total credit notes for an invoice equal or exceed the invoice total,
     * the invoice status transitions to 'credited'.
     */
    protected function updateInvoiceStatusIfFullyCredited(CreditNote $creditNote): void
    {
        $invoice = $creditNote->invoice;

        if ($invoice === null) {
            return;
        }

        // Cannot transition to credited if invoice is already in a terminal state
        if ($invoice->status->isTerminal()) {
            return;
        }

        // Check if invoice can transition to credited
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Credited)) {
            return;
        }

        // Calculate total credited amount for this invoice
        $totalCredited = $this->getTotalCreditedAmount($invoice);

        // Check if fully credited
        if (bccomp($totalCredited, $invoice->total_amount, 2) >= 0) {
            $oldStatus = $invoice->status;
            $invoice->status = InvoiceStatus::Credited;
            $invoice->save();

            // Log the status change on the invoice
            $invoice->auditLogs()->create([
                'event' => AuditLog::EVENT_STATUS_CHANGE,
                'old_values' => ['status' => $oldStatus->value],
                'new_values' => [
                    'status' => InvoiceStatus::Credited->value,
                    'total_credited' => $totalCredited,
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                ],
                'user_id' => Auth::id(),
            ]);

            Log::channel('finance')->info('Invoice marked as credited', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
                'total_credited' => $totalCredited,
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
            ]);
        }
    }

    /**
     * Get the total credited amount for an invoice.
     *
     * Sums all issued and applied credit notes for the invoice.
     */
    public function getTotalCreditedAmount(Invoice $invoice): string
    {
        return CreditNote::where('invoice_id', $invoice->id)
            ->whereIn('status', [CreditNoteStatus::Issued, CreditNoteStatus::Applied])
            ->sum('amount');
    }

    /**
     * Get the outstanding amount on an invoice after credits.
     */
    public function getInvoiceOutstanding(Invoice $invoice): string
    {
        $totalCredited = $this->getTotalCreditedAmount($invoice);
        $outstanding = bcsub($invoice->total_amount, $invoice->amount_paid, 2);
        $outstanding = bcsub($outstanding, $totalCredited, 2);

        // Don't return negative outstanding
        if (bccomp($outstanding, '0', 2) < 0) {
            return '0.00';
        }

        return $outstanding;
    }

    /**
     * Log a credit note event to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function logCreditNoteEvent(
        CreditNote $creditNote,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $creditNote->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
