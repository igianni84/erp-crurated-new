<?php

namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class OverdueInvoicesTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Get overdue invoices to prioritize collection efforts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days_overdue_min' => $schema->integer()->min(0)->default(0),
            'limit' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        $daysMin = (int) ($request['days_overdue_min'] ?? 0);
        $limit = (int) ($request['limit'] ?? 20);

        $cutoffDate = Carbon::today()->subDays($daysMin);

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->when($daysMin > 0, function ($q) use ($cutoffDate): void {
                $q->where('due_date', '<=', $cutoffDate);
            })
            ->with('customer.party')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();

        $totalAmount = '0';
        $invoiceList = [];

        foreach ($invoices as $invoice) {
            $daysOverdue = $invoice->getDaysOverdue();
            $outstanding = $invoice->getOutstandingAmount();
            $totalAmount = bcadd($totalAmount, $outstanding, 2);

            $invoiceList[] = [
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer?->getName() ?? 'Unknown',
                'invoice_type' => $invoice->invoice_type->label(),
                'total_amount' => $this->formatCurrency((string) $invoice->total_amount),
                'due_date' => $invoice->due_date !== null ? $this->formatDate($invoice->due_date) : null,
                'days_overdue' => $daysOverdue,
            ];
        }

        return (string) json_encode([
            'total_overdue_count' => count($invoiceList),
            'total_overdue_amount' => $this->formatCurrency($totalAmount),
            'invoices' => $invoiceList,
        ]);
    }
}
