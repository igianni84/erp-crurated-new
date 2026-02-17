<?php

namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class OutstandingInvoicesTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get invoices with outstanding balances, ordered by amount owed.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'min_amount' => $schema->number(),
            'invoice_type' => $schema->string()
                ->enum(array_map(fn (InvoiceType $t): string => $t->value, InvoiceType::cases())),
            'limit' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = (int) ($request['limit'] ?? 20);

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->with('customer.party');

        if (isset($request['invoice_type'])) {
            $type = InvoiceType::tryFrom((string) $request['invoice_type']);
            if ($type !== null) {
                $query->where('invoice_type', $type);
            }
        }

        if (isset($request['min_amount'])) {
            $query->whereRaw('(total_amount - amount_paid) >= ?', [(float) $request['min_amount']]);
        }

        $query->orderByRaw('(total_amount - amount_paid) DESC')
            ->limit($limit);

        $invoices = $query->get();

        $totalOutstanding = '0';
        $invoiceList = [];

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->getOutstandingAmount();
            $totalOutstanding = bcadd($totalOutstanding, $outstanding, 2);

            $invoiceList[] = [
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer?->getName() ?? 'Unknown',
                'invoice_type' => $invoice->invoice_type->label(),
                'total_amount' => $this->formatCurrency((string) $invoice->total_amount),
                'amount_paid' => $this->formatCurrency((string) $invoice->amount_paid),
                'outstanding' => $this->formatCurrency($outstanding),
                'issued_at' => $invoice->issued_at !== null ? $this->formatDate($invoice->issued_at) : null,
                'due_date' => $invoice->due_date !== null ? $this->formatDate($invoice->due_date) : null,
                'is_overdue' => $invoice->isOverdue(),
            ];
        }

        return (string) json_encode([
            'total_outstanding_amount' => $this->formatCurrency($totalOutstanding),
            'invoice_count' => count($invoiceList),
            'invoices' => $invoiceList,
        ]);
    }
}
