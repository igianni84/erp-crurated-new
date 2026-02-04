<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Mail\Finance\InvoiceMail;
use App\Models\Finance\Invoice;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/** @phpstan-consistent-constructor */

/**
 * Service for sending invoice emails to customers.
 *
 * This service handles email sending with PDF attachment,
 * configurable templates, and audit logging.
 */
class InvoiceMailService
{
    /**
     * Statuses that allow sending emails.
     *
     * @var array<InvoiceStatus>
     */
    private const ALLOWED_STATUSES = [
        InvoiceStatus::Issued,
        InvoiceStatus::Paid,
        InvoiceStatus::PartiallyPaid,
        InvoiceStatus::Credited,
    ];

    public function __construct() {}

    /**
     * Send an invoice email to the customer.
     *
     * @param  int|null  $sentBy  The user ID who initiated the send (null for system)
     *
     * @throws InvalidArgumentException If invoice cannot be sent
     */
    public function sendToCustomer(
        Invoice $invoice,
        ?string $customSubject = null,
        ?string $customMessage = null,
        ?int $sentBy = null
    ): void {
        $this->validateInvoiceForSending($invoice);

        // Get customer email
        $invoice->loadMissing('customer');
        $customerEmail = $invoice->customer?->email;

        if (empty($customerEmail)) {
            throw new InvalidArgumentException(
                'Cannot send invoice email: Customer does not have an email address.'
            );
        }

        // Send the email
        Mail::to($customerEmail)
            ->send(new InvoiceMail($invoice, $customSubject, $customMessage));

        // Log the email sending in audit trail
        $this->logEmailSent($invoice, $customerEmail, $sentBy);
    }

    /**
     * Queue an invoice email to the customer (for async sending).
     *
     * @param  int|null  $sentBy  The user ID who initiated the send (null for system)
     *
     * @throws InvalidArgumentException If invoice cannot be sent
     */
    public function queueToCustomer(
        Invoice $invoice,
        ?string $customSubject = null,
        ?string $customMessage = null,
        ?int $sentBy = null
    ): void {
        $this->validateInvoiceForSending($invoice);

        // Get customer email
        $invoice->loadMissing('customer');
        $customerEmail = $invoice->customer?->email;

        if (empty($customerEmail)) {
            throw new InvalidArgumentException(
                'Cannot send invoice email: Customer does not have an email address.'
            );
        }

        // Queue the email (InvoiceMail implements ShouldQueue)
        Mail::to($customerEmail)
            ->queue(new InvoiceMail($invoice, $customSubject, $customMessage));

        // Log the email queued in audit trail
        $this->logEmailSent($invoice, $customerEmail, $sentBy, queued: true);
    }

    /**
     * Check if an invoice can have an email sent.
     */
    public function canSendEmail(Invoice $invoice): bool
    {
        if (! in_array($invoice->status, self::ALLOWED_STATUSES, true)) {
            return false;
        }

        // Must have a valid invoice number (i.e., be issued)
        if ($invoice->invoice_number === null) {
            return false;
        }

        // Must have a customer with an email
        $invoice->loadMissing('customer');
        if ($invoice->customer === null || empty($invoice->customer->email)) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why an invoice cannot have an email sent.
     */
    public function getCannotSendReason(Invoice $invoice): ?string
    {
        if (! in_array($invoice->status, self::ALLOWED_STATUSES, true)) {
            $allowedStatusLabels = array_map(
                fn (InvoiceStatus $status): string => $status->label(),
                self::ALLOWED_STATUSES
            );

            return 'Invoice status must be one of: '.implode(', ', $allowedStatusLabels).
                '. Current status: '.$invoice->status->label();
        }

        if ($invoice->invoice_number === null) {
            return 'Invoice must be issued before sending email.';
        }

        $invoice->loadMissing('customer');
        if ($invoice->customer === null) {
            return 'Invoice has no associated customer.';
        }

        if (empty($invoice->customer->email)) {
            return 'Customer does not have an email address.';
        }

        return null;
    }

    /**
     * Validate that the invoice is in a state that allows sending emails.
     *
     * @throws InvalidArgumentException If invoice cannot have email sent
     */
    private function validateInvoiceForSending(Invoice $invoice): void
    {
        $reason = $this->getCannotSendReason($invoice);

        if ($reason !== null) {
            throw new InvalidArgumentException("Cannot send invoice email: {$reason}");
        }
    }

    /**
     * Log the email sending event in the audit trail.
     */
    private function logEmailSent(
        Invoice $invoice,
        string $recipientEmail,
        ?int $sentBy = null,
        bool $queued = false
    ): void {
        $event = $queued ? 'email_queued' : 'email_sent';

        $invoice->auditLogs()->create([
            'event' => $event,
            'user_id' => $sentBy,
            'old_values' => null,
            'new_values' => [
                'recipient_email' => $recipientEmail,
                'invoice_number' => $invoice->invoice_number,
                'sent_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
