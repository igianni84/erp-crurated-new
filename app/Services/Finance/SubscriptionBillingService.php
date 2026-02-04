<?php

namespace App\Services\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\SubscriptionBillingDue;
use App\Models\Finance\Invoice;
use App\Models\Finance\Subscription;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Service for subscription billing operations.
 *
 * Handles subscription billing cycles, pro-rata calculations,
 * and invoice generation for membership (INV0) invoices.
 */
class SubscriptionBillingService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Calculate pro-rata amount for a subscription period.
     *
     * Calculates the proportional amount for a partial billing period.
     * Used for new signups, cancellations, and upgrades.
     *
     * @param  Subscription  $subscription  The subscription to calculate for
     * @param  Carbon  $startDate  The start of the pro-rata period
     * @param  Carbon  $endDate  The end of the pro-rata period
     * @return array{amount: string, days_charged: int, total_days: int, daily_rate: string, description: string, metadata: array<string, mixed>}
     *
     * @throws InvalidArgumentException If dates are invalid
     */
    public function calculateProRata(Subscription $subscription, Carbon $startDate, Carbon $endDate): array
    {
        // Validate dates
        if ($endDate->lte($startDate)) {
            throw new InvalidArgumentException(
                'Pro-rata end date must be after start date.'
            );
        }

        // Calculate the full billing period for reference
        $fullPeriodStart = $this->getBillingPeriodStart($subscription, $startDate);
        $fullPeriodEnd = $this->getBillingPeriodEnd($subscription, $fullPeriodStart);

        // Total days in the full billing period
        $totalDays = (int) $fullPeriodStart->diffInDays($fullPeriodEnd);

        // Days being charged in the pro-rata period
        $daysCharged = (int) $startDate->diffInDays($endDate);

        // Ensure we don't exceed total days
        if ($daysCharged > $totalDays) {
            $daysCharged = $totalDays;
        }

        // Calculate daily rate and pro-rata amount
        // daily_rate = subscription_amount / total_days
        $dailyRate = bcdiv($subscription->amount, (string) $totalDays, 6);

        // pro_rata_amount = daily_rate * days_charged
        $proRataAmount = bcmul($dailyRate, (string) $daysCharged, 2);

        // Build description indicating pro-rata period
        $description = $this->buildProRataDescription(
            $subscription,
            $startDate,
            $endDate,
            $daysCharged,
            $totalDays
        );

        // Build metadata for audit trail
        $metadata = [
            'pro_rata' => true,
            'subscription_id' => $subscription->id,
            'subscription_amount' => $subscription->amount,
            'billing_cycle' => $subscription->billing_cycle->value,
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'full_period_start' => $fullPeriodStart->toDateString(),
            'full_period_end' => $fullPeriodEnd->toDateString(),
            'days_charged' => $daysCharged,
            'total_days' => $totalDays,
            'daily_rate' => $dailyRate,
            'plan_name' => $subscription->plan_name,
            'plan_type' => $subscription->plan_type->value,
        ];

        return [
            'amount' => $proRataAmount,
            'days_charged' => $daysCharged,
            'total_days' => $totalDays,
            'daily_rate' => $dailyRate,
            'description' => $description,
            'metadata' => $metadata,
        ];
    }

    /**
     * Calculate pro-rata for a new signup.
     *
     * For new signups, the pro-rata period runs from the signup date
     * to the end of the current billing period.
     *
     * @param  Subscription  $subscription  The new subscription
     * @param  Carbon|null  $signupDate  The signup date (defaults to subscription.started_at)
     * @return array{amount: string, days_charged: int, total_days: int, daily_rate: string, description: string, metadata: array<string, mixed>}
     */
    public function calculateProRataForNewSignup(Subscription $subscription, ?Carbon $signupDate = null): array
    {
        $startDate = $signupDate ?? $subscription->started_at;
        $endDate = $this->getBillingPeriodEnd($subscription, $startDate);

        $proRata = $this->calculateProRata($subscription, $startDate, $endDate);
        $proRata['metadata']['pro_rata_type'] = 'new_signup';

        return $proRata;
    }

    /**
     * Calculate pro-rata for a cancellation.
     *
     * For cancellations, the pro-rata period runs from the start of the
     * current billing period to the cancellation date.
     *
     * @param  Subscription  $subscription  The subscription being cancelled
     * @param  Carbon|null  $cancellationDate  The cancellation date (defaults to subscription.cancelled_at or now)
     * @return array{amount: string, days_charged: int, total_days: int, daily_rate: string, description: string, metadata: array<string, mixed>}
     */
    public function calculateProRataForCancellation(Subscription $subscription, ?Carbon $cancellationDate = null): array
    {
        $endDate = $cancellationDate ?? $subscription->cancelled_at ?? Carbon::now();
        $startDate = $this->getBillingPeriodStart($subscription, $endDate);

        $proRata = $this->calculateProRata($subscription, $startDate, $endDate);
        $proRata['metadata']['pro_rata_type'] = 'cancellation';

        return $proRata;
    }

    /**
     * Calculate pro-rata for an upgrade/downgrade.
     *
     * Returns both a credit for the old plan (remaining period) and
     * a charge for the new plan (remaining period).
     *
     * @param  Subscription  $oldSubscription  The old subscription (before upgrade)
     * @param  Subscription  $newSubscription  The new subscription (after upgrade)
     * @param  Carbon|null  $changeDate  The date of the change (defaults to now)
     * @return array{credit: array<string, mixed>, charge: array<string, mixed>, net_amount: string}
     */
    public function calculateProRataForUpgrade(
        Subscription $oldSubscription,
        Subscription $newSubscription,
        ?Carbon $changeDate = null
    ): array {
        $changeDate = $changeDate ?? Carbon::now();

        // Calculate credit for old subscription (from change date to end of period)
        $oldPeriodEnd = $this->getBillingPeriodEnd($oldSubscription, $changeDate);
        $credit = $this->calculateProRata($oldSubscription, $changeDate, $oldPeriodEnd);
        $credit['metadata']['pro_rata_type'] = 'upgrade_credit';

        // Calculate charge for new subscription (from change date to end of period)
        $newPeriodEnd = $this->getBillingPeriodEnd($newSubscription, $changeDate);
        $charge = $this->calculateProRata($newSubscription, $changeDate, $newPeriodEnd);
        $charge['metadata']['pro_rata_type'] = 'upgrade_charge';

        // Calculate net amount (charge - credit)
        $netAmount = bcsub($charge['amount'], $credit['amount'], 2);

        return [
            'credit' => $credit,
            'charge' => $charge,
            'net_amount' => $netAmount,
        ];
    }

    /**
     * Create a pro-rata invoice for a new signup.
     *
     * @param  Subscription  $subscription  The new subscription
     * @param  Carbon|null  $signupDate  The signup date
     * @param  bool  $autoIssue  Whether to auto-issue the invoice
     */
    public function createProRataInvoiceForNewSignup(
        Subscription $subscription,
        ?Carbon $signupDate = null,
        bool $autoIssue = false
    ): Invoice {
        $proRata = $this->calculateProRataForNewSignup($subscription, $signupDate);

        return $this->createProRataInvoice($subscription, $proRata, $autoIssue);
    }

    /**
     * Create a pro-rata invoice for a cancellation refund calculation.
     *
     * Note: This returns the calculation for what would need to be refunded.
     * The actual credit note/refund should be handled separately.
     *
     * @param  Subscription  $subscription  The subscription being cancelled
     * @param  Carbon|null  $cancellationDate  The cancellation date
     * @return array{amount: string, days_charged: int, total_days: int, daily_rate: string, description: string, metadata: array<string, mixed>}
     */
    public function calculateCancellationRefund(Subscription $subscription, ?Carbon $cancellationDate = null): array
    {
        $cancellationDate = $cancellationDate ?? $subscription->cancelled_at ?? Carbon::now();

        // The "refund" is the unused portion from cancellation to end of period
        $periodEnd = $this->getBillingPeriodEnd($subscription, $cancellationDate);
        $unusedPeriod = $this->calculateProRata($subscription, $cancellationDate, $periodEnd);
        $unusedPeriod['metadata']['pro_rata_type'] = 'cancellation_refund';

        return $unusedPeriod;
    }

    /**
     * Create a pro-rata invoice.
     *
     * @param  array{amount: string, description: string, metadata: array<string, mixed>}  $proRata  Pro-rata calculation result
     * @param  bool  $autoIssue  Whether to auto-issue the invoice
     */
    protected function createProRataInvoice(
        Subscription $subscription,
        array $proRata,
        bool $autoIssue = false
    ): Invoice {
        $subscription->loadMissing('customer');

        // Build invoice line
        $lines = [
            [
                'description' => $proRata['description'],
                'quantity' => '1',
                'unit_price' => $proRata['amount'],
                'tax_rate' => '0', // Tax rate should come from customer/product configuration
                'metadata' => $proRata['metadata'],
            ],
        ];

        // Create the invoice
        $invoice = $this->invoiceService->createDraft(
            InvoiceType::MembershipService,
            $subscription->customer,
            $lines,
            'subscription',
            $subscription->id,
            $subscription->currency,
            Carbon::now()->addDays(InvoiceType::MembershipService->defaultDueDateDays() ?? 30),
            'Pro-rata invoice for subscription: '.$subscription->plan_name
        );

        // Auto-issue if requested
        if ($autoIssue) {
            $invoice = $this->invoiceService->issue($invoice);
        }

        return $invoice;
    }

    /**
     * Build a descriptive string for the pro-rata period.
     */
    protected function buildProRataDescription(
        Subscription $subscription,
        Carbon $startDate,
        Carbon $endDate,
        int $daysCharged,
        int $totalDays
    ): string {
        $planName = $subscription->plan_name;
        $billingCycle = $subscription->billing_cycle->label();
        $periodStart = $startDate->format('d M Y');
        $periodEnd = $endDate->format('d M Y');

        return sprintf(
            '%s (%s) - Pro-rata: %s to %s (%d of %d days)',
            $planName,
            $billingCycle,
            $periodStart,
            $periodEnd,
            $daysCharged,
            $totalDays
        );
    }

    /**
     * Get the start of the billing period containing the given date.
     *
     * @param  Subscription  $subscription  The subscription
     * @param  Carbon  $date  The date to find the period for
     */
    protected function getBillingPeriodStart(Subscription $subscription, Carbon $date): Carbon
    {
        $billingCycleMonths = $subscription->billing_cycle->months();
        $startedAt = $subscription->started_at->copy();

        // Calculate billing periods forward from start date
        $periodStart = $startedAt->copy();

        while ($periodStart->copy()->addMonths($billingCycleMonths)->lte($date)) {
            $periodStart->addMonths($billingCycleMonths);
        }

        // If the date is before the subscription started, use the start date
        if ($date->lt($startedAt)) {
            return $startedAt;
        }

        return $periodStart;
    }

    /**
     * Get the end of the billing period starting at the given date.
     *
     * @param  Subscription  $subscription  The subscription
     * @param  Carbon  $periodStart  The start of the period
     */
    protected function getBillingPeriodEnd(Subscription $subscription, Carbon $periodStart): Carbon
    {
        return $periodStart->copy()->addMonths($subscription->billing_cycle->months());
    }

    // =========================================================================
    // Methods from US-E086 (placeholder for future implementation)
    // =========================================================================

    /**
     * Get subscriptions due for billing today.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Subscription>
     */
    public function getSubscriptionsDue(): \Illuminate\Database\Eloquent\Builder
    {
        return Subscription::where('status', 'active')
            ->where('next_billing_date', '<=', Carbon::today());
    }

    /**
     * Generate INV0 invoice for a subscription.
     */
    public function generateInvoice(Subscription $subscription, bool $autoIssue = true): Invoice
    {
        // Dispatch the event which triggers GenerateSubscriptionInvoice listener
        event(new SubscriptionBillingDue($subscription, $autoIssue));

        // Wait briefly for async processing and find the invoice
        // In practice, this would be handled via jobs/queues
        return Invoice::where('source_type', 'subscription')
            ->where('source_id', $subscription->id)
            ->latest()
            ->firstOrFail();
    }

    /**
     * Advance the next billing date for a subscription.
     */
    public function advanceNextBillingDate(Subscription $subscription): Subscription
    {
        $subscription->next_billing_date = $subscription->calculateNextBillingDate();
        $subscription->save();

        return $subscription;
    }
}
