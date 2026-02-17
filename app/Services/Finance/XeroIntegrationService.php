<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\XeroSyncStatus;
use App\Enums\Finance\XeroSyncType;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\XeroSyncLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for Xero accounting integration.
 *
 * Centralizes all Xero synchronization logic including:
 * - Invoice sync (when issued)
 * - Credit note sync (when issued)
 * - Payment sync (optional)
 * - Integration health monitoring
 * - Retry management for failed syncs
 *
 * This service creates XeroSyncLog entries for each sync attempt
 * and updates the source entity with the Xero ID upon success.
 */
class XeroIntegrationService
{
    /**
     * Maximum number of retries for failed syncs.
     */
    protected int $maxRetries;

    /**
     * Whether Xero sync is enabled.
     */
    protected bool $syncEnabled;

    public function __construct()
    {
        $this->maxRetries = (int) config('finance.xero.max_retry_count', 3);
        $this->syncEnabled = (bool) config('finance.xero.sync_enabled', true);
    }

    // =========================================================================
    // Invoice Sync
    // =========================================================================

    /**
     * Sync an issued invoice to Xero.
     *
     * Creates an invoice in Xero and stores the Xero invoice ID on the Invoice model.
     * Creates a XeroSyncLog entry to track the sync attempt.
     *
     * @throws RuntimeException If sync fails after all retries
     */
    public function syncInvoice(Invoice $invoice): XeroSyncLog
    {
        // Check if sync is enabled
        if (! $this->syncEnabled) {
            Log::channel('finance')->info('Xero sync disabled, skipping invoice sync', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            // Create a sync log entry marked as synced (disabled bypass)
            return XeroSyncLog::create([
                'sync_type' => XeroSyncType::Invoice,
                'syncable_type' => Invoice::class,
                'syncable_id' => $invoice->id,
                'status' => XeroSyncStatus::Synced,
                'xero_id' => 'SYNC_DISABLED',
                'synced_at' => now(),
                'request_payload' => ['sync_disabled' => true],
            ]);
        }

        // Validate invoice can be synced
        if ($invoice->isDraft()) {
            throw new RuntimeException(
                'Cannot sync draft invoice to Xero. Invoice must be issued first.'
            );
        }

        // Check if already synced
        if ($invoice->xero_invoice_id !== null) {
            Log::channel('finance')->info('Invoice already synced to Xero', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'xero_invoice_id' => $invoice->xero_invoice_id,
            ]);

            $existingLog = XeroSyncLog::getLatestForEntity($invoice);
            if ($existingLog !== null && $existingLog->isSynced()) {
                return $existingLog;
            }
        }

        // Build the request payload
        $requestPayload = $this->buildInvoicePayload($invoice);

        // Create sync log entry
        $syncLog = XeroSyncLog::createForEntity(
            XeroSyncType::Invoice,
            $invoice,
            $requestPayload
        );

        Log::channel('finance')->info('Starting Xero invoice sync', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'sync_log_id' => $syncLog->id,
        ]);

        try {
            // Call Xero API to create invoice
            $xeroResponse = $this->callXeroCreateInvoice($requestPayload);

            // Extract Xero invoice ID from response
            $xeroInvoiceId = $xeroResponse['InvoiceID'] ?? $xeroResponse['invoice_id'] ?? null;

            if ($xeroInvoiceId === null) {
                throw new RuntimeException('Xero API response did not contain invoice ID');
            }

            // Update the Invoice model with Xero ID
            // US-E104: Also clear the sync_pending flag on successful sync
            $invoice->xero_invoice_id = $xeroInvoiceId;
            $invoice->xero_synced_at = now();
            $invoice->xero_sync_pending = false;
            $invoice->save();

            // Mark sync log as successful
            $syncLog->markSynced($xeroInvoiceId, $xeroResponse);

            Log::channel('finance')->info('Xero invoice sync successful', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'xero_invoice_id' => $xeroInvoiceId,
                'sync_log_id' => $syncLog->id,
            ]);

            return $syncLog;
        } catch (Exception $e) {
            // Mark sync log as failed
            $syncLog->markFailed($e->getMessage());

            Log::channel('finance')->error('Xero invoice sync failed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'retry_count' => $syncLog->retry_count,
            ]);

            throw $e;
        }
    }

    /**
     * Build the payload for creating an invoice in Xero.
     *
     * @return array<string, mixed>
     */
    protected function buildInvoicePayload(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $lines = $invoice->invoiceLines()->get();

        $lineItems = [];
        foreach ($lines as $line) {
            $lineItems[] = [
                'Description' => $line->description,
                'Quantity' => $line->quantity,
                'UnitAmount' => $line->unit_price,
                'TaxAmount' => $line->tax_amount,
                'LineAmount' => $line->line_total,
                'AccountCode' => $this->getAccountCodeForInvoiceType($invoice),
            ];
        }

        return [
            'Type' => 'ACCREC', // Accounts Receivable Invoice
            'Contact' => [
                'Name' => $customer->name ?? $customer->company_name ?? 'Unknown Customer',
                'EmailAddress' => $customer->email ?? null,
            ],
            'Date' => $invoice->issued_at?->format('Y-m-d'),
            'DueDate' => $invoice->due_date?->format('Y-m-d'),
            'InvoiceNumber' => $invoice->invoice_number,
            'Reference' => $invoice->invoice_type->code().' - '.$invoice->source_type,
            'CurrencyCode' => $invoice->currency,
            'Status' => 'AUTHORISED',
            'LineAmountTypes' => 'Inclusive',
            'SubTotal' => $invoice->subtotal,
            'TotalTax' => $invoice->tax_amount,
            'Total' => $invoice->total_amount,
            'LineItems' => $lineItems,
            // ERP reference for traceability
            'Metadata' => [
                'erp_invoice_id' => $invoice->id,
                'erp_invoice_type' => $invoice->invoice_type->value,
                'erp_source_type' => $invoice->source_type,
                'erp_source_id' => $invoice->source_id,
            ],
        ];
    }

    /**
     * Get the Xero account code for an invoice type.
     *
     * Maps ERP invoice types to Xero account codes.
     * These should be configured based on the organization's chart of accounts.
     */
    protected function getAccountCodeForInvoiceType(Invoice $invoice): string
    {
        // TODO: Make this configurable via config/finance.php
        // These are placeholder account codes
        return match ($invoice->invoice_type) {
            InvoiceType::MembershipService => '200',  // Membership Revenue
            InvoiceType::VoucherSale => '210',        // Wine Sales Revenue
            InvoiceType::ShippingRedemption => '220', // Shipping Revenue
            InvoiceType::StorageFee => '230',         // Storage Revenue
            InvoiceType::ServiceEvents => '240',      // Service Events Revenue
        };
    }

    /**
     * Call Xero API to create an invoice.
     *
     * This is a stub implementation. In production, this should integrate
     * with the Xero API SDK (e.g., xeroapi/xero-php-oauth2).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RuntimeException If API call fails
     */
    protected function callXeroCreateInvoice(array $payload): array
    {
        // TODO: Implement actual Xero API integration
        // In production, use Xero PHP SDK:
        //
        // $xeroTenantId = config('services.xero.tenant_id');
        // $apiInstance = new \XeroAPI\XeroPHP\Api\AccountingApi(...);
        // $invoice = new \XeroAPI\XeroPHP\Models\Accounting\Invoice();
        // $invoice->setType('ACCREC');
        // ...
        // $result = $apiInstance->createInvoices($xeroTenantId, $invoices);
        // return $result->getInvoices()[0]->getInvoiceID();

        // Stub implementation for development/testing
        // Returns a simulated Xero response with a generated invoice ID
        Log::channel('finance')->info('Xero API call (STUB): Creating invoice', [
            'invoice_number' => $payload['InvoiceNumber'] ?? null,
        ]);

        // Simulate successful response
        $xeroInvoiceId = 'XERO-'.strtoupper(substr(md5((string) ($payload['InvoiceNumber'] ?? uniqid())), 0, 12));

        return [
            'InvoiceID' => $xeroInvoiceId,
            'InvoiceNumber' => $payload['InvoiceNumber'],
            'Status' => 'AUTHORISED',
            'Type' => 'ACCREC',
            'Total' => $payload['Total'],
            'UpdatedDateUTC' => now()->toIso8601String(),
        ];
    }

    // =========================================================================
    // Credit Note Sync
    // =========================================================================

    /**
     * Sync an issued credit note to Xero.
     *
     * Creates a credit note in Xero and stores the Xero credit note ID.
     *
     * @throws RuntimeException If sync fails
     */
    public function syncCreditNote(CreditNote $creditNote): XeroSyncLog
    {
        // Check if sync is enabled
        if (! $this->syncEnabled) {
            Log::channel('finance')->info('Xero sync disabled, skipping credit note sync', [
                'credit_note_id' => $creditNote->id,
            ]);

            return XeroSyncLog::create([
                'sync_type' => XeroSyncType::CreditNote,
                'syncable_type' => CreditNote::class,
                'syncable_id' => $creditNote->id,
                'status' => XeroSyncStatus::Synced,
                'xero_id' => 'SYNC_DISABLED',
                'synced_at' => now(),
                'request_payload' => ['sync_disabled' => true],
            ]);
        }

        // Check if already synced
        if ($creditNote->xero_credit_note_id !== null) {
            $existingLog = XeroSyncLog::getLatestForEntity($creditNote);
            if ($existingLog !== null && $existingLog->isSynced()) {
                return $existingLog;
            }
        }

        // Build request payload
        $requestPayload = $this->buildCreditNotePayload($creditNote);

        // Create sync log entry
        $syncLog = XeroSyncLog::createForEntity(
            XeroSyncType::CreditNote,
            $creditNote,
            $requestPayload
        );

        Log::channel('finance')->info('Starting Xero credit note sync', [
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'sync_log_id' => $syncLog->id,
        ]);

        try {
            // Call Xero API
            $xeroResponse = $this->callXeroCreateCreditNote($requestPayload);

            $xeroCreditNoteId = $xeroResponse['CreditNoteID'] ?? $xeroResponse['credit_note_id'] ?? null;

            if ($xeroCreditNoteId === null) {
                throw new RuntimeException('Xero API response did not contain credit note ID');
            }

            // Update the CreditNote model
            $creditNote->xero_credit_note_id = $xeroCreditNoteId;
            $creditNote->xero_synced_at = now();
            $creditNote->save();

            // Mark sync log as successful
            $syncLog->markSynced($xeroCreditNoteId, $xeroResponse);

            Log::channel('finance')->info('Xero credit note sync successful', [
                'credit_note_id' => $creditNote->id,
                'xero_credit_note_id' => $xeroCreditNoteId,
            ]);

            return $syncLog;
        } catch (Exception $e) {
            $syncLog->markFailed($e->getMessage());

            Log::channel('finance')->error('Xero credit note sync failed', [
                'credit_note_id' => $creditNote->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the payload for creating a credit note in Xero.
     *
     * @return array<string, mixed>
     */
    protected function buildCreditNotePayload(CreditNote $creditNote): array
    {
        $customer = $creditNote->customer;
        $invoice = $creditNote->invoice;

        return [
            'Type' => 'ACCRECCREDIT', // Accounts Receivable Credit Note
            'Contact' => [
                'Name' => $customer->name ?? $customer->company_name ?? 'Unknown Customer',
                'EmailAddress' => $customer->email ?? null,
            ],
            'Date' => $creditNote->issued_at?->format('Y-m-d'),
            'CreditNoteNumber' => $creditNote->credit_note_number,
            'Reference' => $invoice->invoice_number ?? 'Credit Note',
            'CurrencyCode' => $creditNote->currency,
            'Status' => 'AUTHORISED',
            'Total' => $creditNote->amount,
            'LineItems' => [
                [
                    'Description' => $creditNote->reason,
                    'Quantity' => '1',
                    'UnitAmount' => $creditNote->amount,
                    'AccountCode' => $this->getAccountCodeForInvoiceType($invoice),
                ],
            ],
            'Metadata' => [
                'erp_credit_note_id' => $creditNote->id,
                'erp_invoice_id' => $invoice->id ?? null,
                'erp_invoice_number' => $invoice->invoice_number ?? null,
            ],
        ];
    }

    /**
     * Call Xero API to create a credit note.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function callXeroCreateCreditNote(array $payload): array
    {
        // Stub implementation
        Log::channel('finance')->info('Xero API call (STUB): Creating credit note', [
            'credit_note_number' => $payload['CreditNoteNumber'] ?? null,
        ]);

        $xeroCreditNoteId = 'XERO-CN-'.strtoupper(substr(md5((string) ($payload['CreditNoteNumber'] ?? uniqid())), 0, 12));

        return [
            'CreditNoteID' => $xeroCreditNoteId,
            'CreditNoteNumber' => $payload['CreditNoteNumber'],
            'Status' => 'AUTHORISED',
            'Type' => 'ACCRECCREDIT',
            'Total' => $payload['Total'],
            'UpdatedDateUTC' => now()->toIso8601String(),
        ];
    }

    // =========================================================================
    // Payment Sync (Optional)
    // =========================================================================

    /**
     * Sync a payment to Xero.
     *
     * Optional - syncs payment reconciliation to Xero.
     *
     * @throws RuntimeException If sync fails
     */
    public function syncPayment(Payment $payment): XeroSyncLog
    {
        // Check if sync is enabled
        if (! $this->syncEnabled) {
            return XeroSyncLog::create([
                'sync_type' => XeroSyncType::Payment,
                'syncable_type' => Payment::class,
                'syncable_id' => $payment->id,
                'status' => XeroSyncStatus::Synced,
                'xero_id' => 'SYNC_DISABLED',
                'synced_at' => now(),
                'request_payload' => ['sync_disabled' => true],
            ]);
        }

        // Build request payload
        $requestPayload = $this->buildPaymentPayload($payment);

        // Create sync log entry
        $syncLog = XeroSyncLog::createForEntity(
            XeroSyncType::Payment,
            $payment,
            $requestPayload
        );

        try {
            // Call Xero API
            $xeroResponse = $this->callXeroCreatePayment($requestPayload);

            $xeroPaymentId = $xeroResponse['PaymentID'] ?? $xeroResponse['payment_id'] ?? null;

            if ($xeroPaymentId === null) {
                throw new RuntimeException('Xero API response did not contain payment ID');
            }

            // Mark sync log as successful
            $syncLog->markSynced($xeroPaymentId, $xeroResponse);

            Log::channel('finance')->info('Xero payment sync successful', [
                'payment_id' => $payment->id,
                'xero_payment_id' => $xeroPaymentId,
            ]);

            return $syncLog;
        } catch (Exception $e) {
            $syncLog->markFailed($e->getMessage());

            Log::channel('finance')->error('Xero payment sync failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the payload for creating a payment in Xero.
     *
     * @return array<string, mixed>
     */
    protected function buildPaymentPayload(Payment $payment): array
    {
        return [
            'Date' => $payment->received_at->format('Y-m-d'),
            'Amount' => $payment->amount,
            'Reference' => $payment->payment_reference,
            'CurrencyCode' => $payment->currency,
            'Metadata' => [
                'erp_payment_id' => $payment->id,
                'erp_payment_reference' => $payment->payment_reference,
                'payment_source' => $payment->source->value ?? null,
            ],
        ];
    }

    /**
     * Call Xero API to create a payment.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function callXeroCreatePayment(array $payload): array
    {
        // Stub implementation
        $xeroPaymentId = 'XERO-PMT-'.strtoupper(substr(md5((string) ($payload['Reference'] ?? uniqid())), 0, 12));

        return [
            'PaymentID' => $xeroPaymentId,
            'Reference' => $payload['Reference'],
            'Amount' => $payload['Amount'],
            'Date' => $payload['Date'],
            'UpdatedDateUTC' => now()->toIso8601String(),
        ];
    }

    // =========================================================================
    // Retry Management
    // =========================================================================

    /**
     * Retry a failed sync.
     *
     * @return bool True if retry was initiated and successful
     */
    public function retryFailed(XeroSyncLog $syncLog): bool
    {
        if (! $syncLog->canRetry()) {
            Log::channel('finance')->warning('Xero sync log cannot be retried', [
                'sync_log_id' => $syncLog->id,
                'status' => $syncLog->status->value,
            ]);

            return false;
        }

        // Check max retries
        if ($syncLog->retry_count >= $this->maxRetries) {
            Log::channel('finance')->warning('Xero sync log exceeded max retries', [
                'sync_log_id' => $syncLog->id,
                'retry_count' => $syncLog->retry_count,
                'max_retries' => $this->maxRetries,
            ]);

            return false;
        }

        // Reset for retry
        $syncLog->resetForRetry();

        Log::channel('finance')->info('Xero sync retry initiated', [
            'sync_log_id' => $syncLog->id,
            'sync_type' => $syncLog->sync_type->value,
            'retry_count' => $syncLog->retry_count,
        ]);

        // Dispatch the appropriate sync based on type
        try {
            $syncable = $syncLog->syncable;

            return match ($syncLog->sync_type) {
                XeroSyncType::Invoice => $syncable instanceof Invoice
                    ? $this->syncInvoice($syncable)->isSynced()
                    : throw new RuntimeException('Syncable entity is not an Invoice'),
                XeroSyncType::CreditNote => $syncable instanceof CreditNote
                    ? $this->syncCreditNote($syncable)->isSynced()
                    : throw new RuntimeException('Syncable entity is not a CreditNote'),
                XeroSyncType::Payment => $syncable instanceof Payment
                    ? $this->syncPayment($syncable)->isSynced()
                    : throw new RuntimeException('Syncable entity is not a Payment'),
            };
        } catch (Exception $e) {
            Log::channel('finance')->error('Xero sync retry failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retry all failed syncs.
     *
     * @return int Number of successfully retried syncs
     */
    public function retryAllFailed(): int
    {
        $failedLogs = XeroSyncLog::retryable($this->maxRetries)->get();
        $successCount = 0;

        foreach ($failedLogs as $syncLog) {
            if ($this->retryFailed($syncLog)) {
                $successCount++;
            }
        }

        Log::channel('finance')->info('Bulk Xero retry completed', [
            'total_failed' => $failedLogs->count(),
            'successful_retries' => $successCount,
        ]);

        return $successCount;
    }

    // =========================================================================
    // Integration Health
    // =========================================================================
    /**
     * Get Xero integration health metrics.
     *
     * Returns a comprehensive health summary including:
     * - Overall status
     * - Pending/failed/synced counts
     * - Recent sync activity
     * - Active alerts
     * - US-E104: Invoices pending sync count
     *
     * @return array{status: string, status_color: string, sync_enabled: bool, pending_count: int, failed_count: int, synced_today: int, last_sync: Carbon|null, last_sync_type: string|null, alerts: array<string>, is_healthy: bool, invoices_pending_sync: int, invoices_not_synced: int}
     */
    public function getIntegrationHealth(): array
    {
        $pendingCount = XeroSyncLog::pending()->count();
        $failedCount = XeroSyncLog::failed()->count();
        $syncedToday = XeroSyncLog::synced()
            ->whereDate('synced_at', today())
            ->count();

        $lastSync = XeroSyncLog::synced()
            ->orderBy('synced_at', 'desc')
            ->first();

        // US-E104: Count invoices with pending Xero sync
        $invoicesPendingSync = Invoice::xeroSyncPending()->count();

        // US-E104: Count issued invoices without Xero ID (invariant violations)
        $invoicesNotSynced = Invoice::xeroNotSynced()->count();

        // Determine status and alerts
        $alerts = [];
        $status = 'healthy';
        $statusColor = 'success';

        if (! $this->syncEnabled) {
            $status = 'disabled';
            $statusColor = 'gray';
            $alerts[] = 'Xero sync is disabled. Enable it in configuration.';
        } elseif ($failedCount > 10) {
            $status = 'critical';
            $statusColor = 'danger';
            $alerts[] = "{$failedCount} sync(s) have failed. Immediate attention required.";
        } elseif ($failedCount > 0) {
            $status = 'warning';
            $statusColor = 'warning';
            $alerts[] = "{$failedCount} sync(s) have failed. Review and retry.";
        }

        if ($pendingCount > 10) {
            $alerts[] = "{$pendingCount} sync(s) are pending. Check queue processing.";
        }

        // US-E104: Alert for invoices issued without xero_invoice_id
        if ($invoicesNotSynced > 0) {
            $status = $status === 'healthy' ? 'warning' : $status;
            $statusColor = $statusColor === 'success' ? 'warning' : $statusColor;
            $alerts[] = "{$invoicesNotSynced} issued invoice(s) are not synced to Xero. This violates the mandatory sync invariant.";
        }

        // US-E104: Warning for invoices with pending sync
        if ($invoicesPendingSync > 0 && $invoicesNotSynced === 0) {
            $alerts[] = "{$invoicesPendingSync} invoice(s) have pending Xero sync. Retry the failed syncs.";
        }

        return [
            'status' => $status,
            'status_color' => $statusColor,
            'sync_enabled' => $this->syncEnabled,
            'pending_count' => $pendingCount,
            'failed_count' => $failedCount,
            'synced_today' => $syncedToday,
            'last_sync' => $lastSync?->synced_at,
            'last_sync_type' => $lastSync?->sync_type->label(),
            'alerts' => $alerts,
            'is_healthy' => $status === 'healthy',
            'invoices_pending_sync' => $invoicesPendingSync,
            'invoices_not_synced' => $invoicesNotSynced,
        ];
    }

    /**
     * Get failed sync logs for review.
     *
     * @param  int  $limit  Maximum number of logs to return
     * @return Collection<int, XeroSyncLog>
     */
    public function getFailedSyncs(int $limit = 20): Collection
    {
        return XeroSyncLog::failed()
            ->with('syncable')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending sync logs.
     *
     * @return Collection<int, XeroSyncLog>
     */
    public function getPendingSyncs(): Collection
    {
        return XeroSyncLog::pending()
            ->with('syncable')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    // =========================================================================
    // US-E104: Invoice Sync Pending Management
    // =========================================================================
    /**
     * Get invoices with pending Xero sync.
     *
     * US-E104: Returns invoices that have been issued but have the
     * xero_sync_pending flag set (sync failed and needs retry).
     *
     * @param  int  $limit  Maximum number of invoices to return
     * @return Collection<int, Invoice>
     */
    public function getInvoicesWithPendingSync(int $limit = 20): Collection
    {
        return Invoice::xeroSyncPending()
            ->with('customer')
            ->orderBy('issued_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get issued invoices without Xero ID.
     *
     * US-E104: Returns invoices that violate the invariant - they are issued
     * but don't have a xero_invoice_id.
     *
     * @param  int  $limit  Maximum number of invoices to return
     * @return Collection<int, Invoice>
     */
    public function getInvoicesNotSyncedToXero(int $limit = 20): Collection
    {
        return Invoice::xeroNotSynced()
            ->with('customer')
            ->orderBy('issued_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Retry sync for all invoices with pending sync.
     *
     * US-E104: Attempts to sync all invoices that have the xero_sync_pending flag.
     *
     * @return int Number of successfully synced invoices
     */
    public function retryAllPendingInvoiceSyncs(): int
    {
        $invoices = Invoice::xeroSyncPending()->get();
        $successCount = 0;

        foreach ($invoices as $invoice) {
            try {
                $syncLog = $this->syncInvoice($invoice);
                if ($syncLog->isSynced()) {
                    $successCount++;
                }
            } catch (Exception $e) {
                Log::channel('finance')->warning('Failed to retry invoice sync', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('finance')->info('Bulk invoice sync retry completed (US-E104)', [
            'total_pending' => $invoices->count(),
            'successful_syncs' => $successCount,
        ]);

        return $successCount;
    }
}
