<?php

namespace App\AI\Tools\Commercial;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\PriceBookEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EmpAlertsTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Identify products priced significantly above or below estimated market price.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'threshold_percent' => $schema->number()->min(5)->max(100)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        $threshold = (float) ($request['threshold_percent'] ?? 20.0);

        $entries = PriceBookEntry::query()
            ->with(['sellableSku.wineVariant.wineMaster', 'priceBook'])
            ->get();

        $empMap = [];
        $emps = EstimatedMarketPrice::all();
        foreach ($emps as $emp) {
            $empMap[$emp->sellable_sku_id] = $emp;
        }

        $alerts = [];
        foreach ($entries as $entry) {
            $emp = $empMap[$entry->sellable_sku_id] ?? null;
            if ($emp === null) {
                continue;
            }

            $ourPrice = (float) $entry->base_price;
            $marketPrice = (float) $emp->emp_value;

            if ($marketPrice <= 0) {
                continue;
            }

            $deviation = (($ourPrice - $marketPrice) / $marketPrice) * 100;

            if (abs($deviation) < $threshold) {
                continue;
            }

            $wineName = $entry->sellableSku->wineVariant->wineMaster->name ?? 'Unknown';

            $alerts[] = [
                'wine_name' => $wineName,
                'our_price' => $this->formatCurrency((string) $entry->base_price),
                'market_price' => $this->formatCurrency((string) $emp->emp_value),
                'deviation_percent' => round($deviation, 1),
                'direction' => $deviation > 0 ? 'above' : 'below',
                'confidence_level' => $emp->confidence_level->label(),
                'price_book_name' => $entry->priceBook->name ?? 'Unknown',
            ];
        }

        usort($alerts, fn (array $a, array $b): int => abs($b['deviation_percent']) <=> abs($a['deviation_percent']));

        return (string) json_encode([
            'threshold_percent' => $threshold,
            'total_alerts' => count($alerts),
            'alerts' => array_slice($alerts, 0, 50),
        ]);
    }
}
