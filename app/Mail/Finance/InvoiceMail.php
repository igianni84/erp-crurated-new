<?php

namespace App\Mail\Finance;

use App\Models\Finance\Invoice;
use App\Services\Finance\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for sending invoices to customers.
 *
 * This mailable attaches the invoice PDF and uses a configurable email template.
 * The email is queued by default for better performance.
 */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * The invoice being sent.
     */
    public Invoice $invoice;

    /**
     * Optional custom subject for the email.
     */
    public ?string $customSubject = null;

    /**
     * Optional custom message for the email body.
     */
    public ?string $customMessage = null;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, ?string $customSubject = null, ?string $customMessage = null)
    {
        $this->invoice = $invoice;
        $this->customSubject = $customSubject;
        $this->customMessage = $customMessage;

        // Eager load relationships needed for the email
        $this->invoice->loadMissing(['customer', 'invoiceLines']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->customSubject ?? $this->getDefaultSubject();

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.finance.invoice',
            with: [
                'invoice' => $this->invoice,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdfService = app(InvoicePdfService::class);

        // Only attach PDF if invoice can have PDF generated
        if (! $pdfService->canGeneratePdf($this->invoice)) {
            return [];
        }

        $filename = $pdfService->getFilename($this->invoice);
        $pdfContent = $pdfService->getContent($this->invoice);

        return [
            Attachment::fromData(
                fn () => $pdfContent,
                $filename
            )->withMime('application/pdf'),
        ];
    }

    /**
     * Get the default subject line for the invoice email.
     */
    protected function getDefaultSubject(): string
    {
        $invoiceNumber = $this->invoice->invoice_number ?? 'Draft';
        $companyName = config('app.name', 'ERP4');

        return "Invoice {$invoiceNumber} from {$companyName}";
    }
}
