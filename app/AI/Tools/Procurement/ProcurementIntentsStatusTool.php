<?php

namespace App\AI\Tools\Procurement;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Models\Procurement\ProcurementIntent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProcurementIntentsStatusTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get procurement intents distribution by status and orphaned demand count.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        $total = ProcurementIntent::count();

        $statusCounts = ProcurementIntent::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $byStatus = [];
        foreach (ProcurementIntentStatus::cases() as $status) {
            $byStatus[$status->label()] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $withoutPo = ProcurementIntent::query()
            ->whereDoesntHave('purchaseOrders')
            ->count();

        return (string) json_encode([
            'total_intents' => $total,
            'by_status' => $byStatus,
            'without_purchase_order' => $withoutPo,
        ]);
    }
}
