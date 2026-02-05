<?php

namespace App\Events\Finance;

use App\Models\Finance\Invoice;
use App\Models\Finance\StorageBillingPeriod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: StoragePaymentBlocked
 *
 * Dispatched when a storage billing period is blocked due to overdue INV3 payment.
 * Other modules (e.g., Module B for custody, Module C for shipping) should listen to this event
 * to block custody operations for the affected customer.
 *
 * This event is dispatched by:
 * - BlockOverdueStorageBillingJob (scheduled daily)
 *
 * Finance is consequence, not cause - this event notifies other modules
 * of the financial status change, but does not directly control operations.
 *
 * Downstream modules should:
 * - Block redemption requests (Module C)
 * - Block custody transfers (Module B)
 * - Block shipping order creation (Module C)
 * - Display warnings in customer view (Module K)
 */
class StoragePaymentBlocked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  StorageBillingPeriod  $storageBillingPeriod  The billing period that was blocked
     * @param  Invoice  $overdueInvoice  The overdue INV3 that triggered the block
     * @param  int  $daysOverdue  Number of days the invoice was overdue
     * @param  string  $reason  Reason for block (for audit trail)
     */
    public function __construct(
        public StorageBillingPeriod $storageBillingPeriod,
        public Invoice $overdueInvoice,
        public int $daysOverdue,
        public string $reason
    ) {}

    /**
     * Get the customer ID affected by this block.
     */
    public function getCustomerId(): string
    {
        return $this->storageBillingPeriod->customer_id;
    }

    /**
     * Get the location ID if the block is location-specific.
     */
    public function getLocationId(): ?string
    {
        return $this->storageBillingPeriod->location_id;
    }

    /**
     * Check if this block is for a specific location or all locations.
     */
    public function isLocationSpecific(): bool
    {
        return $this->storageBillingPeriod->location_id !== null;
    }
}
