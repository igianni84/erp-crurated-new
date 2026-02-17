<?php

namespace App\AI\Tools\Customer;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TopCustomersByRevenueTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get top customers ranked by revenue for a given period.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_7_days', 'last_30_days'])
                ->default('this_month'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(10),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->parsePeriod($request['period'] ?? 'this_month');
        $limit = (int) ($request['limit'] ?? 10);

        $results = Invoice::query()
            ->select('customer_id', DB::raw('SUM(total_amount) as total_revenue'), DB::raw('COUNT(*) as invoice_count'))
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
            ->whereBetween('issued_at', [$from, $to])
            ->groupBy('customer_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->with('customer.party', 'customer.activeMembership')
            ->get();

        $customers = [];
        foreach ($results as $row) {
            $customer = $row->customer;
            if ($customer === null) {
                continue;
            }

            $membership = $customer->activeMembership;

            $customers[] = [
                'customer_name' => $customer->getName(),
                'email' => $customer->email,
                'total_revenue' => $this->formatCurrency((string) $row->getAttribute('total_revenue')),
                'invoice_count' => (int) $row->getAttribute('invoice_count'),
                'membership_tier' => $membership?->tier?->label() ?? 'None',
            ];
        }

        return (string) json_encode([
            'period' => $request['period'] ?? 'this_month',
            'top_customers' => $customers,
            'count' => count($customers),
        ]);
    }
}
