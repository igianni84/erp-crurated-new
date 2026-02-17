<?php

namespace App\AI\Tools\Customer;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Models\Customer\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CustomerStatusSummaryTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get a summary of customers by status and type.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Overview;
    }

    public function handle(Request $request): Stringable|string
    {
        $total = Customer::count();

        $byStatus = [];
        $statusCounts = Customer::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        foreach (CustomerStatus::cases() as $status) {
            $byStatus[$status->label()] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $byType = [];
        $typeCounts = Customer::query()
            ->select('customer_type', DB::raw('COUNT(*) as count'))
            ->groupBy('customer_type')
            ->pluck('count', 'customer_type');

        foreach (CustomerType::cases() as $type) {
            $byType[$type->label()] = (int) ($typeCounts[$type->value] ?? 0);
        }

        $withActiveBlocks = Customer::query()
            ->whereHas('activeOperationalBlocks')
            ->count();

        return (string) json_encode([
            'total_customers' => $total,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'with_active_blocks' => $withActiveBlocks,
        ]);
    }
}
