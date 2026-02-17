<?php

namespace App\AI\Tools\Inventory;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Inventory\InventoryCase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CaseIntegrityStatusTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get case integrity status breakdown (intact vs broken).';
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
        $total = InventoryCase::count();

        $statusCounts = InventoryCase::query()
            ->select('integrity_status', DB::raw('COUNT(*) as count'))
            ->groupBy('integrity_status')
            ->pluck('count', 'integrity_status');

        $intactCount = (int) ($statusCounts[CaseIntegrityStatus::Intact->value] ?? 0);
        $brokenCount = (int) ($statusCounts[CaseIntegrityStatus::Broken->value] ?? 0);
        $intactPercentage = $total > 0 ? round(($intactCount / $total) * 100, 1) : 0;

        return (string) json_encode([
            'total_cases' => $total,
            'intact_count' => $intactCount,
            'broken_count' => $brokenCount,
            'intact_percentage' => $intactPercentage,
        ]);
    }
}
