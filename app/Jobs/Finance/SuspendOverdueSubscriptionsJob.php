<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\SubscriptionStatus;
use App\Events\Finance\SubscriptionSuspended;
use App\Models\Finance\Invoice;
use App\Models\Finance\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to suspend subscriptions with overdue INV0 invoices.
 *
 * This job runs daily and:
 * 1. Finds all active subscriptions with INV0 invoices overdue > X days (configurable)
 * 2. Suspends the subscription (sets status = suspended)
 * 3. Emits SubscriptionSuspended event for Module K eligibility updates
 * 4. Logs suspension events
 *
 * The overdue threshold is configurable via the finance.subscription_overdue_suspension_days config.
 * Default is 14 days.
 */
class SuspendOverdueSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of days overdue before suspension.
     * If null, uses config value.
     */
    protected ?int $overdueDaysThreshold;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $overdueDaysThreshold  Override the config value for overdue days threshold
     */
    public function __construct(?int $overdueDaysThreshold = null)
    {
        $this->overdueDaysThreshold = $overdueDaysThreshold;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $thresholdDays = $this->getOverdueDaysThreshold();

        Log::channel('finance')->info('Starting subscription suspension check', [
            'overdue_threshold_days' => $thresholdDays,
        ]);

        // Find subscriptions with overdue INV0 invoices that exceed the threshold
        $subscriptionsToSuspend = $this->getSubscriptionsToSuspend($thresholdDays);

        $suspendedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($subscriptionsToSuspend as $data) {
            try {
                /** @var Subscription $subscription */
                $subscription = $data['subscription'];
                /** @var Invoice $overdueInvoice */
                $overdueInvoice = $data['overdue_invoice'];
                $daysOverdue = $data['days_overdue'];

                // Skip if subscription was already suspended or cancelled
                if (! $subscription->isActive()) {
                    Log::channel('finance')->info('Skipping non-active subscription', [
                        'subscription_id' => $subscription->id,
                        'status' => $subscription->status->value,
                    ]);
                    $skippedCount++;

                    continue;
                }

                // Suspend the subscription
                $this->suspendSubscription($subscription, $overdueInvoice, $daysOverdue);

                $suspendedCount++;
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to suspend subscription', [
                    'subscription_id' => $data['subscription']->id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        Log::channel('finance')->info('Completed subscription suspension check', [
            'overdue_threshold_days' => $thresholdDays,
            'suspended' => $suspendedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'total_checked' => count($subscriptionsToSuspend),
        ]);
    }

    /**
     * Get the overdue days threshold from config or constructor override.
     */
    protected function getOverdueDaysThreshold(): int
    {
        if ($this->overdueDaysThreshold !== null) {
            return $this->overdueDaysThreshold;
        }

        return (int) config('finance.subscription_overdue_suspension_days', 14);
    }

    /**
     * Get subscriptions with overdue INV0 invoices that exceed the threshold.
     *
     * @return array<int, array{subscription: Subscription, overdue_invoice: Invoice, days_overdue: int}>
     */
    protected function getSubscriptionsToSuspend(int $thresholdDays): array
    {
        $thresholdDate = now()->subDays($thresholdDays)->startOfDay();

        // Find overdue INV0 invoices that are past the threshold
        $overdueInvoices = Invoice::query()
            ->where('invoice_type', InvoiceType::MembershipService)
            ->where('status', InvoiceStatus::Issued)
            ->where('source_type', 'subscription')
            ->whereNotNull('source_id')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $thresholdDate)
            ->get();

        $result = [];

        foreach ($overdueInvoices as $invoice) {
            $subscription = $invoice->getSourceSubscription();

            if ($subscription === null) {
                Log::channel('finance')->warning('Could not find subscription for overdue invoice', [
                    'invoice_id' => $invoice->id,
                    'source_id' => $invoice->source_id,
                ]);

                continue;
            }

            // Only consider active subscriptions
            if (! $subscription->isActive()) {
                continue;
            }

            $daysOverdue = $invoice->getDaysOverdue();

            if ($daysOverdue === null || $daysOverdue < $thresholdDays) {
                continue;
            }

            $result[] = [
                'subscription' => $subscription,
                'overdue_invoice' => $invoice,
                'days_overdue' => $daysOverdue,
            ];
        }

        return $result;
    }

    /**
     * Suspend a subscription due to overdue payment.
     */
    protected function suspendSubscription(Subscription $subscription, Invoice $overdueInvoice, int $daysOverdue): void
    {
        $reason = "Suspended due to overdue INV0 payment. Invoice #{$overdueInvoice->invoice_number} is {$daysOverdue} days overdue.";

        DB::transaction(function () use ($subscription, $overdueInvoice, $daysOverdue): void {
            // Update subscription status to suspended
            $subscription->status = SubscriptionStatus::Suspended;
            $subscription->save();

            // Log the suspension in audit trail
            $subscription->auditLogs()->create([
                'event' => 'subscription_suspended',
                'old_values' => ['status' => SubscriptionStatus::Active->value],
                'new_values' => [
                    'status' => SubscriptionStatus::Suspended->value,
                    'overdue_invoice_id' => $overdueInvoice->id,
                    'overdue_invoice_number' => $overdueInvoice->invoice_number,
                    'days_overdue' => $daysOverdue,
                ],
                'user_id' => null, // Automated system action
            ]);

            // Log the event in the invoice audit trail as well
            $overdueInvoice->auditLogs()->create([
                'event' => 'subscription_suspended_due_to_overdue',
                'old_values' => null,
                'new_values' => [
                    'subscription_id' => $subscription->id,
                    'days_overdue' => $daysOverdue,
                ],
                'user_id' => null, // Automated system action
            ]);
        });

        // Dispatch event for other modules (e.g., Module K eligibility)
        SubscriptionSuspended::dispatch($subscription, $overdueInvoice, $daysOverdue, $reason);

        Log::channel('finance')->info('Suspended subscription due to overdue payment', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'plan_name' => $subscription->plan_name,
            'overdue_invoice_id' => $overdueInvoice->id,
            'overdue_invoice_number' => $overdueInvoice->invoice_number,
            'days_overdue' => $daysOverdue,
        ]);
    }

    /**
     * Get query builder for subscriptions with overdue INV0 invoices.
     * Useful for reporting and dashboard widgets.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    public static function getOverdueInvoicesQuery(int $thresholdDays): \Illuminate\Database\Eloquent\Builder
    {
        $thresholdDate = now()->subDays($thresholdDays)->startOfDay();

        return Invoice::query()
            ->where('invoice_type', InvoiceType::MembershipService)
            ->where('status', InvoiceStatus::Issued)
            ->where('source_type', 'subscription')
            ->whereNotNull('source_id')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $thresholdDate);
    }

    /**
     * Get count of subscriptions at risk of suspension.
     * Useful for dashboard warnings.
     */
    public static function getAtRiskCount(?int $thresholdDays = null): int
    {
        $threshold = $thresholdDays ?? (int) config('finance.subscription_overdue_suspension_days', 14);

        return self::getOverdueInvoicesQuery($threshold)->count();
    }
}
