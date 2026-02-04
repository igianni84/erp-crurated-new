<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Service for generating Invoice PDFs.
 *
 * This service handles the generation of PDF documents for invoices.
 * PDFs can only be generated for issued, paid, partially_paid, or credited invoices.
 */
class InvoicePdfService
{
    /**
     * Statuses that allow PDF generation.
     *
     * @var array<InvoiceStatus>
     */
    private const ALLOWED_STATUSES = [
        InvoiceStatus::Issued,
        InvoiceStatus::Paid,
        InvoiceStatus::PartiallyPaid,
        InvoiceStatus::Credited,
    ];

    /**
     * Generate a PDF for the given invoice.
     *
     * @throws InvalidArgumentException If invoice is not in an allowed status
     */
    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $this->validateInvoiceForPdf($invoice);

        // Eager load relationships needed for PDF
        $invoice->load(['customer', 'invoiceLines']);

        return Pdf::loadView('pdf.invoices.invoice', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Generate a PDF and return it as a download response.
     *
     * @throws InvalidArgumentException If invoice is not in an allowed status
     */
    public function download(Invoice $invoice): Response
    {
        $pdf = $this->generate($invoice);
        $filename = $this->getFilename($invoice);

        return $pdf->download($filename);
    }

    /**
     * Generate a PDF and return it as a stream response (for viewing in browser).
     *
     * @throws InvalidArgumentException If invoice is not in an allowed status
     */
    public function stream(Invoice $invoice): Response
    {
        $pdf = $this->generate($invoice);
        $filename = $this->getFilename($invoice);

        return $pdf->stream($filename);
    }

    /**
     * Generate a PDF and return the raw content.
     *
     * @throws InvalidArgumentException If invoice is not in an allowed status
     */
    public function getContent(Invoice $invoice): string
    {
        $pdf = $this->generate($invoice);

        return $pdf->output();
    }

    /**
     * Get the filename for the invoice PDF.
     */
    public function getFilename(Invoice $invoice): string
    {
        $invoiceNumber = $invoice->invoice_number ?? 'DRAFT-'.$invoice->id;

        // Sanitize the invoice number for use in filename
        $sanitized = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNumber);

        return $sanitized.'.pdf';
    }

    /**
     * Check if an invoice can have a PDF generated.
     */
    public function canGeneratePdf(Invoice $invoice): bool
    {
        return in_array($invoice->status, self::ALLOWED_STATUSES, true);
    }

    /**
     * Validate that the invoice is in a state that allows PDF generation.
     *
     * @throws InvalidArgumentException If invoice is not in an allowed status
     */
    private function validateInvoiceForPdf(Invoice $invoice): void
    {
        if (! $this->canGeneratePdf($invoice)) {
            $allowedStatusLabels = array_map(
                fn (InvoiceStatus $status): string => $status->label(),
                self::ALLOWED_STATUSES
            );

            throw new InvalidArgumentException(
                'PDF can only be generated for invoices with status: '.implode(', ', $allowedStatusLabels).
                '. Current status: '.$invoice->status->label()
            );
        }

        if ($invoice->invoice_number === null) {
            throw new InvalidArgumentException(
                'PDF cannot be generated for an invoice without an invoice number. Please issue the invoice first.'
            );
        }
    }
}
