<?php

namespace App\Events\Finance;

use App\Models\Finance\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: SubscriptionBillingDue
 *
 * Dispatched when a subscription is due for billing.
 * Triggers auto-generation of INV0 (Membership Service) invoice.
 *
 * This event is typically dispatched by:
 * - The daily subscription billing job (US-E027)
 * - Manual billing trigger
 */
class SubscriptionBillingDue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  bool  $autoIssue  Whether to automatically issue the invoice after creation
     */
    public function __construct(
        public Subscription $subscription,
        public bool $autoIssue = false
    ) {}
}
