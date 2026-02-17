<?php

namespace App\AI\Tools\Pim;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProductCatalogSearchTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search the product catalog by name, producer, appellation, or SKU code.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string(),
            'type' => $schema->string()
                ->enum(['wine_master', 'sellable_sku', 'all'])
                ->default('all'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Overview;
    }

    public function handle(Request $request): Stringable|string
    {
        $searchQuery = $request['query'] ?? '';
        if (mb_strlen((string) $searchQuery) < 2) {
            return (string) json_encode(['message' => 'Search query must be at least 2 characters.']);
        }

        $type = $request['type'] ?? 'all';
        $like = '%'.$searchQuery.'%';
        $results = [];

        if ($type === 'wine_master' || $type === 'all') {
            $masters = WineMaster::query()
                ->where(function ($q) use ($like): void {
                    $q->where('name', 'LIKE', $like)
                        ->orWhere('producer', 'LIKE', $like)
                        ->orWhere('appellation', 'LIKE', $like);
                })
                ->with('wineVariants')
                ->limit(20)
                ->get();

            foreach ($masters as $master) {
                foreach ($master->wineVariants as $variant) {
                    $results[] = [
                        'name' => $master->name,
                        'producer' => $master->producer_name,
                        'appellation' => $master->appellation ?? '',
                        'vintage' => $variant->vintage_year,
                        'lifecycle_status' => $variant->lifecycle_status ?? 'unknown',
                        'type' => 'wine_master',
                    ];
                }

                if ($master->wineVariants->isEmpty()) {
                    $results[] = [
                        'name' => $master->name,
                        'producer' => $master->producer_name,
                        'appellation' => $master->appellation ?? '',
                        'vintage' => null,
                        'lifecycle_status' => null,
                        'type' => 'wine_master',
                    ];
                }
            }
        }

        if ($type === 'sellable_sku' || $type === 'all') {
            $skus = SellableSku::query()
                ->where('sku_code', 'LIKE', $like)
                ->with(['wineVariant.wineMaster', 'format'])
                ->limit(20)
                ->get();

            foreach ($skus as $sku) {
                $results[] = [
                    'name' => $sku->wineVariant->wineMaster->name ?? 'Unknown',
                    'producer' => $sku->wineVariant->wineMaster->producer_name ?? '',
                    'appellation' => $sku->wineVariant->wineMaster->appellation ?? '',
                    'vintage' => $sku->wineVariant->vintage_year ?? null,
                    'format' => $sku->format->name ?? 'Unknown',
                    'sku_code' => $sku->sku_code,
                    'lifecycle_status' => $sku->lifecycle_status,
                    'type' => 'sellable_sku',
                ];
            }
        }

        return (string) json_encode([
            'total' => count($results),
            'results' => array_slice($results, 0, 20),
        ]);
    }
}
