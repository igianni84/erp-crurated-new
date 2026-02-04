<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\SubscriptionBillingDue;
use App\Models\Finance\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: GenerateSubscriptionInvoice
 *
 * Generates an INV0 (Membership Service) invoice when a subscription
 * billing is due.
 *
 * This listener:
 * - Creates a draft invoice with type INV0
 * - Links the invoice to the subscription (source_type='subscription')
 * - Creates invoice lines from subscription plan details
 * - Optionally auto-issues the invoice if configured
 */
class GenerateSubscriptionInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(SubscriptionBillingDue $event): void
    {
        $subscription = $event->subscription;

        // Only generate invoice for active subscriptions that allow billing
        if (! $subscription->allowsBilling()) {
            Log::channel('finance')->info('Skipping invoice generation for non-billable subscription', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status->value,
            ]);

            return;
        }

        // Check for existing invoice (idempotency)
        $existingInvoice = $this->invoiceService->findBySource('subscription', $subscription->id);
        if ($existingInvoice !== null && $this->isRecentBillingPeriod($existingInvoice, $subscription)) {
            Log::channel('finance')->info('Invoice already exists for current billing period', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $existingInvoice->id,
            ]);

            return;
        }

        // Load the customer relationship
        $subscription->loadMissing('customer');
        $customer = $subscription->customer;

        if ($customer === null) {
            Log::channel('finance')->error('Cannot generate invoice: subscription has no customer', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        // Build invoice lines from subscription details
        $lines = $this->buildInvoiceLines($subscription);

        // Create the draft invoice
        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::MembershipService,
            customer: $customer,
            lines: $lines,
            sourceType: 'subscription',
            sourceId: $subscription->id,
            currency: $subscription->currency,
            dueDate: now()->addDays(InvoiceType::MembershipService->defaultDueDateDays() ?? 30),
            notes: $this->buildInvoiceNotes($subscription)
        );

        Log::channel('finance')->info('Generated INV0 invoice for subscription billing', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'total_amount' => $invoice->total_amount,
        ]);

        // Auto-issue if configured
        if ($event->autoIssue) {
            try {
                $this->invoiceService->issue($invoice);
                Log::channel('finance')->info('Auto-issued INV0 invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ]);
            } catch (\Throwable $e) {
                Log::channel('finance')->error('Failed to auto-issue INV0 invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build invoice lines from subscription plan details.
     *
     * @return array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, metadata: array<string, mixed>}>
     */
    protected function buildInvoiceLines($subscription): array
    {
        $description = $this->buildLineDescription($subscription);

        // Get tax rate from subscription metadata or use default
        $taxRate = $subscription->metadata['tax_rate'] ?? '0.00';

        return [
            [
                'description' => $description,
                'quantity' => '1',
                'unit_price' => $subscription->amount,
                'tax_rate' => $taxRate,
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'plan_type' => $subscription->plan_type->value,
                    'plan_name' => $subscription->plan_name,
                    'billing_cycle' => $subscription->billing_cycle->value,
                    'billing_period_start' => $subscription->next_billing_date?->toDateString(),
                    'billing_period_end' => $subscription->calculateNextBillingDate()?->subDay()->toDateString(),
                ],
            ],
        ];
    }

    /**
     * Build the line description from subscription details.
     */
    protected function buildLineDescription($subscription): string
    {
        $planName = $subscription->plan_name;
        $billingCycleLabel = $subscription->billing_cycle->label();

        // Format billing period
        $periodStart = $subscription->next_billing_date?->format('M d, Y') ?? 'N/A';
        $periodEnd = $subscription->calculateNextBillingDate()?->subDay()->format('M d, Y') ?? 'N/A';

        return "{$planName} ({$billingCycleLabel}) - {$periodStart} to {$periodEnd}";
    }

    /**
     * Build notes for the invoice.
     */
    protected function buildInvoiceNotes($subscription): string
    {
        $notes = "Subscription billing for {$subscription->plan_name}.";

        if ($subscription->hasStripeSubscription()) {
            $notes .= " Stripe subscription: {$subscription->stripe_subscription_id}.";
        }

        return $notes;
    }

    /**
     * Check if an existing invoice is for the current billing period.
     *
     * This prevents duplicate invoices for the same billing period while
     * allowing new invoices for subsequent periods.
     */
    protected function isRecentBillingPeriod(Invoice $invoice, $subscription): bool
    {
        // If no next_billing_date on subscription, consider recent
        if ($subscription->next_billing_date === null) {
            return true;
        }

        // Invoice created within the last billing cycle is considered recent
        $billingCycleMonths = $subscription->getBillingCycleMonths();
        $cutoffDate = now()->subMonths($billingCycleMonths)->startOfDay();

        return $invoice->created_at >= $cutoffDate;
    }
}
