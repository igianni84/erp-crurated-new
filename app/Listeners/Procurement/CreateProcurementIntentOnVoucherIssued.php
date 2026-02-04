<?php

namespace App\Listeners\Procurement;

use App\Events\VoucherIssued;
use App\Models\Procurement\ProcurementIntent;
use App\Services\Procurement\ProcurementIntentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener that auto-creates a ProcurementIntent when vouchers are issued.
 *
 * Part of Module D (Procurement) integration with Module A (Vouchers).
 * Creates a draft intent for Ops review when a voucher sale triggers sourcing needs.
 */
class CreateProcurementIntentOnVoucherIssued implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected ProcurementIntentService $intentService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(VoucherIssued $event): void
    {
        // Create a single ProcurementIntent for the batch of vouchers
        // The intent represents the need to source wine for all vouchers in this issuance
        $firstVoucher = $event->vouchers->first();

        if ($firstVoucher === null) {
            Log::warning('VoucherIssued event received with no vouchers', [
                'allocation_id' => $event->allocation->id,
                'sale_reference' => $event->saleReference,
            ]);

            return;
        }

        try {
            $intent = $this->intentService->createFromVoucherSale($firstVoucher);

            // Update the intent with batch information
            $totalQuantity = $event->vouchers->count();
            $intent->quantity = $totalQuantity;
            $intent->rationale = sprintf(
                'Auto-created from voucher sale. Sale Reference: %s, %d voucher(s) issued from Allocation ID: %s',
                $event->saleReference,
                $totalQuantity,
                $event->allocation->id
            );

            // Store the source context on the intent
            $intent->source_allocation_id = $event->allocation->id;
            $intent->source_voucher_id = $firstVoucher->id;

            // Flag for Ops review
            $intent->needs_ops_review = true;

            $intent->save();

            Log::info('ProcurementIntent auto-created from voucher issuance', [
                'intent_id' => $intent->id,
                'allocation_id' => $event->allocation->id,
                'voucher_count' => $totalQuantity,
                'sale_reference' => $event->saleReference,
                'needs_ops_review' => true,
            ]);

            // Log the source context for audit trail
            $this->logSourceContext($intent, $event);
        } catch (\Exception $e) {
            Log::error('Failed to auto-create ProcurementIntent from voucher issuance', [
                'allocation_id' => $event->allocation->id,
                'sale_reference' => $event->saleReference,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Log the source context to the intent's audit log.
     */
    protected function logSourceContext(ProcurementIntent $intent, VoucherIssued $event): void
    {
        $intent->auditLogs()->create([
            'event' => 'auto_created_from_voucher_sale',
            'old_values' => [],
            'new_values' => [
                'source_allocation_id' => $event->allocation->id,
                'source_voucher_id' => $event->vouchers->first()?->id,
                'sale_reference' => $event->saleReference,
                'voucher_count' => $event->vouchers->count(),
                'allocation_source_type' => $event->allocation->source_type->value,
            ],
            'user_id' => null, // System-generated, no user context
        ]);
    }
}
