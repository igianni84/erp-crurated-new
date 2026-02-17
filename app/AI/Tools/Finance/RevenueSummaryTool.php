<?php

namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RevenueSummaryTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get revenue summary for a given period, optionally grouped by invoice type.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_7_days', 'last_30_days'])
                ->default('this_month'),
            'group_by' => $schema->string()
                ->enum(['invoice_type', 'currency', 'none'])
                ->default('invoice_type'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->parsePeriod($request['period'] ?? 'this_month');
        $groupBy = $request['group_by'] ?? 'invoice_type';

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->whereBetween('issued_at', [$from, $to]);

        $totals = (clone $query)->selectRaw('
            SUM(total_amount) as gross_revenue,
            SUM(tax_amount) as tax_total,
            SUM(total_amount - tax_amount) as net_revenue,
            SUM(amount_paid) as amount_collected,
            SUM(total_amount - amount_paid) as outstanding
        ')->first();

        $result = [
            'period' => $request['period'] ?? 'this_month',
            'gross_revenue' => $this->formatCurrency((string) ($totals?->getAttribute('gross_revenue') ?? '0')),
            'tax_total' => $this->formatCurrency((string) ($totals?->getAttribute('tax_total') ?? '0')),
            'net_revenue' => $this->formatCurrency((string) ($totals?->getAttribute('net_revenue') ?? '0')),
            'amount_collected' => $this->formatCurrency((string) ($totals?->getAttribute('amount_collected') ?? '0')),
            'outstanding' => $this->formatCurrency((string) ($totals?->getAttribute('outstanding') ?? '0')),
        ];

        if ($groupBy === 'invoice_type') {
            $breakdown = (clone $query)
                ->select('invoice_type', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('invoice_type')
                ->pluck('total', 'invoice_type');

            $counts = (clone $query)
                ->select('invoice_type', DB::raw('COUNT(*) as count'))
                ->groupBy('invoice_type')
                ->pluck('count', 'invoice_type');

            $byType = [];
            foreach (InvoiceType::cases() as $type) {
                $byType[$type->label()] = [
                    'amount' => $this->formatCurrency((string) ($breakdown[$type->value] ?? '0')),
                    'count' => (int) ($counts[$type->value] ?? 0),
                ];
            }
            $result['breakdown'] = $byType;
        }

        return (string) json_encode($result);
    }
}
