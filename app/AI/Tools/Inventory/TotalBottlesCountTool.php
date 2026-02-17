<?php

namespace App\AI\Tools\Inventory;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\OwnershipType;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TotalBottlesCountTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get total bottles count in inventory with breakdown by state and ownership type.';
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
        $total = SerializedBottle::count();

        $stateCounts = SerializedBottle::query()
            ->select('state', DB::raw('COUNT(*) as count'))
            ->groupBy('state')
            ->pluck('count', 'state');

        $byState = [];
        foreach (BottleState::cases() as $state) {
            $byState[$state->label()] = (int) ($stateCounts[$state->value] ?? 0);
        }

        $ownershipCounts = SerializedBottle::query()
            ->select('ownership_type', DB::raw('COUNT(*) as count'))
            ->groupBy('ownership_type')
            ->pluck('count', 'ownership_type');

        $byOwnership = [];
        foreach (OwnershipType::cases() as $type) {
            $byOwnership[$type->label()] = (int) ($ownershipCounts[$type->value] ?? 0);
        }

        return (string) json_encode([
            'total_bottles' => $total,
            'by_state' => $byState,
            'by_ownership' => $byOwnership,
        ]);
    }
}
