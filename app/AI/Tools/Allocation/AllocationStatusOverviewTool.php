<?php

namespace App\AI\Tools\Allocation;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Allocation\AllocationStatus;
use App\Models\Allocation\Allocation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AllocationStatusOverviewTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get an overview of allocations by status with utilization metrics.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $total = Allocation::count();

        $byStatus = [];
        $statusCounts = Allocation::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        foreach (AllocationStatus::cases() as $status) {
            $byStatus[$status->label()] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $activeSummary = Allocation::query()
            ->where('status', AllocationStatus::Active)
            ->selectRaw('SUM(total_quantity) as total_qty, SUM(sold_quantity) as sold_qty')
            ->first();

        $totalQty = (int) ($activeSummary?->getAttribute('total_qty') ?? 0);
        $soldQty = (int) ($activeSummary?->getAttribute('sold_qty') ?? 0);
        $remainingQty = $totalQty - $soldQty;
        $utilization = $totalQty > 0 ? round(($soldQty / $totalQty) * 100, 1) : 0;

        return (string) json_encode([
            'total_allocations' => $total,
            'by_status' => $byStatus,
            'active_summary' => [
                'total_quantity' => $totalQty,
                'sold_quantity' => $soldQty,
                'remaining_quantity' => $remainingQty,
                'utilization_percentage' => $utilization,
            ],
        ]);
    }
}
