<?php

namespace Database\Seeders;

use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use App\Services\Finance\CreditNoteService;
use Illuminate\Database\Seeder;

/**
 * CreditNoteSeeder - Creates credit notes via CreditNoteService lifecycle.
 *
 * Uses CreditNoteService::createDraft() → issue() → apply().
 * Credit notes are formal corrections to invoices with mandatory reasons.
 */
class CreditNoteSeeder extends Seeder
{
    public function run(): void
    {
        $creditNoteService = app(CreditNoteService::class);

        // Get paid invoices for credit notes
        $paidInvoices = Invoice::where('status', InvoiceStatus::Paid)
            ->with(['customer', 'lines'])
            ->get();

        if ($paidInvoices->isEmpty()) {
            $this->command->warn('No paid invoices found. Run PaymentSeeder first.');

            return;
        }

        // Create credit notes for ~10% of paid invoices
        $invoicesForCreditNotes = $paidInvoices->random(max(1, (int) ($paidInvoices->count() * 0.1)));

        $reasons = [
            'Price adjustment due to pricing error',
            'Partial order cancellation by customer',
            'Wine quality issue - replacement provided',
            'Duplicate charge correction',
            'Promotional discount applied retrospectively',
            'Shipping damage - partial refund',
            'Customer goodwill gesture',
        ];

        foreach ($invoicesForCreditNotes as $invoice) {
            // Credit 10-50% of invoice total
            $creditPercentage = fake()->randomFloat(2, 0.10, 0.50);
            $creditAmount = bcmul($invoice->total_amount, (string) $creditPercentage, 2);
            $reason = fake()->randomElement($reasons);

            // Target distribution: 20% draft, 40% issued, 40% applied
            $targetRandom = fake()->numberBetween(1, 100);

            try {
                $creditNote = $creditNoteService->createDraft($invoice, $creditAmount, $reason);

                if ($targetRandom > 20) {
                    // Issue
                    $creditNoteService->issue($creditNote);
                    $creditNote->refresh();

                    if ($targetRandom > 60) {
                        // Apply (auto-updates invoice status)
                        $creditNoteService->apply($creditNote);
                    }
                }
                // ≤20% left as Draft
            } catch (\Throwable $e) {
                $this->command->warn("Credit note failed for invoice {$invoice->id}: {$e->getMessage()}");
            }
        }
    }
}
