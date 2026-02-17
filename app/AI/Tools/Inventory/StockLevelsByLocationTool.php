<?php

namespace App\AI\Tools\Inventory;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Inventory\BottleState;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StockLevelsByLocationTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get current stock levels per warehouse location, optionally filtered by bottle state.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location_id' => $schema->string(),
            'state' => $schema->string()
                ->enum(['stored', 'reserved_for_picking', 'shipped', 'consumed', 'destroyed', 'missing', 'mis_serialized'])
                ->default('stored'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $stateValue = $request['state'] ?? 'stored';
        $state = BottleState::tryFrom($stateValue);

        $query = SerializedBottle::query();

        if ($state !== null) {
            $query->where('state', $state);
        }

        if (isset($request['location_id'])) {
            $query->where('current_location_id', (string) $request['location_id']);
        }

        $total = (clone $query)->count();

        $locationCounts = (clone $query)
            ->select('current_location_id', DB::raw('COUNT(*) as count'))
            ->groupBy('current_location_id')
            ->with('currentLocation')
            ->get();

        $byLocation = [];
        foreach ($locationCounts as $row) {
            $location = $row->currentLocation;
            $byLocation[] = [
                'location_name' => $location !== null ? $location->name : 'Unknown',
                'location_type' => $location !== null ? $location->location_type->label() : 'Unknown',
                'country' => $location !== null ? $location->country : 'Unknown',
                'bottle_count' => (int) $row->getAttribute('count'),
            ];
        }

        usort($byLocation, fn (array $a, array $b): int => $b['bottle_count'] <=> $a['bottle_count']);

        return (string) json_encode([
            'total_bottles' => $total,
            'state_filter' => $state !== null ? $state->label() : 'all',
            'by_location' => $byLocation,
        ]);
    }
}
