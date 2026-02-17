<?php

namespace App\Jobs\Finance;

use App\Enums\Finance\SubscriptionStatus;
use App\Events\Finance\SubscriptionBillingDue;
use App\Models\Finance\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to process subscription billing for the day.
 *
 * This job runs daily and:
 * 1. Finds all active subscriptions with next_billing_date = today
 * 2. Dispatches SubscriptionBillingDue event for each (which triggers INV0 generation)
 * 3. Updates next_billing_date according to billing_cycle
 * 4. Logs billing events
 *
 * The actual invoice creation is handled by GenerateSubscriptionInvoice listener.
 */
class ProcessSubscriptionBillingJob implements ShouldQueue
{
    use Queueable;

    /**
     * Whether to auto-issue invoices after creation.
     */
    protected bool $autoIssue;

    /**
     * Create a new job instance.
     *
     * @param  bool  $autoIssue  Whether to automatically issue invoices after creation
     */
    public function __construct(bool $autoIssue = true)
    {
        $this->autoIssue = $autoIssue;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = now()->startOfDay();

        // Find all subscriptions due for billing today
        $dueSubscriptions = $this->getDueSubscriptionsQuery()->get();

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        Log::channel('finance')->info('Starting subscription billing processing', [
            'date' => $today->toDateString(),
            'subscriptions_due' => $dueSubscriptions->count(),
        ]);

        foreach ($dueSubscriptions as $subscription) {
            try {
                // Double-check subscription is still billable (may have changed since query)
                if (! $subscription->allowsBilling()) {
                    Log::channel('finance')->info('Skipping non-billable subscription', [
                        'subscription_id' => $subscription->id,
                        'status' => $subscription->status->value,
                    ]);
                    $skippedCount++;

                    continue;
                }

                // Dispatch the billing event (GenerateSubscriptionInvoice listener handles invoice creation)
                SubscriptionBillingDue::dispatch($subscription, $this->autoIssue);

                // Update next_billing_date for the next cycle
                $this->advanceNextBillingDate($subscription);

                Log::channel('finance')->info('Processed subscription billing', [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'plan_name' => $subscription->plan_name,
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency,
                    'next_billing_date' => $subscription->next_billing_date->toDateString(),
                ]);

                $processedCount++;
            } catch (Throwable $e) {
                Log::channel('finance')->error('Failed to process subscription billing', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        Log::channel('finance')->info('Completed subscription billing processing', [
            'date' => $today->toDateString(),
            'processed' => $processedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'total_due' => $dueSubscriptions->count(),
        ]);
    }

    /**
     * Advance the subscription's next_billing_date to the next cycle.
     */
    protected function advanceNextBillingDate(Subscription $subscription): void
    {
        $currentBillingDate = $subscription->next_billing_date;
        $newBillingDate = $subscription->calculateNextBillingDate($currentBillingDate);

        $subscription->next_billing_date = $newBillingDate;
        $subscription->save();

        Log::channel('finance')->info('Advanced subscription billing date', [
            'subscription_id' => $subscription->id,
            'previous_billing_date' => $currentBillingDate->toDateString(),
            'new_billing_date' => $newBillingDate->toDateString(),
            'billing_cycle' => $subscription->billing_cycle->value,
        ]);
    }

    /**
     * Get query builder for subscriptions due for billing today.
     *
     * @return Builder<Subscription>
     */
    public static function getDueSubscriptionsQuery(): Builder
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('next_billing_date', '<=', now()->startOfDay());
    }

    /**
     * Get query builder for subscriptions due for billing on a specific date.
     *
     * @return Builder<Subscription>
     */
    public static function getDueSubscriptionsForDateQuery(Carbon $date): Builder
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('next_billing_date', '<=', $date->startOfDay());
    }

    /**
     * Get the count of subscriptions due for billing today.
     */
    public static function getDueSubscriptionsCount(): int
    {
        return self::getDueSubscriptionsQuery()->count();
    }
}
