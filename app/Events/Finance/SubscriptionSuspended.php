<?php

namespace App\Events\Finance;

use App\Models\Finance\Invoice;
use App\Models\Finance\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SubscriptionSuspended
 *
 * Dispatched when a subscription is suspended due to overdue INV0 payment.
 * Other modules (e.g., Module K for eligibility) should listen to this event
 * to update customer access and block operations.
 *
 * This event is dispatched by:
 * - SuspendOverdueSubscriptionsJob (scheduled daily)
 *
 * Finance is consequence, not cause - this event notifies other modules
 * of the financial status change, but does not directly control operations.
 */
class SubscriptionSuspended
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Subscription  $subscription  The subscription that was suspended
     * @param  Invoice  $overdueInvoice  The overdue INV0 that triggered the suspension
     * @param  int  $daysOverdue  Number of days the invoice was overdue
     * @param  string  $reason  Reason for suspension (for audit trail)
     */
    public function __construct(
        public Subscription $subscription,
        public Invoice $overdueInvoice,
        public int $daysOverdue,
        public string $reason
    ) {}
}
