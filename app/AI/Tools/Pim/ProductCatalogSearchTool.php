<?php

namespace App\AI\Tools\Pim;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Pim\WineMaster;
use App\Services\Pim\CatalogSearchService;
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

    /** @return array<string, mixed> */
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
        $results = [];

        /** @var CatalogSearchService $searchService */
        $searchService = app(CatalogSearchService::class);

        if ($type === 'wine_master' || $type === 'all') {
            $variants = WineMaster::search((string) $searchQuery)
                ->query(function ($builder): void {
                    $builder->with('wineVariants');
                })
                ->get()
                ->take(20);

            foreach ($variants as $master) {
                foreach ($master->wineVariants as $variant) {
                    $results[] = [
                        'name' => $master->name,
                        'producer' => $master->producer_name,
                        'appellation' => $master->appellation_name,
                        'vintage' => $variant->vintage_year,
                        'lifecycle_status' => $variant->lifecycle_status->value,
                        'type' => 'wine_master',
                    ];
                }

                if ($master->wineVariants->isEmpty()) {
                    $results[] = [
                        'name' => $master->name,
                        'producer' => $master->producer_name,
                        'appellation' => $master->appellation_name,
                        'vintage' => null,
                        'lifecycle_status' => null,
                        'type' => 'wine_master',
                    ];
                }
            }
        }

        if ($type === 'sellable_sku' || $type === 'all') {
            $skus = $searchService->searchSkus((string) $searchQuery);

            foreach ($skus as $sku) {
                /** @var \App\Models\Pim\WineVariant|null $variant */
                $variant = $sku->wineVariant;
                /** @var \App\Models\Pim\WineMaster|null $master */
                $master = $variant !== null ? $variant->wineMaster : null;

                $results[] = [
                    'name' => $master !== null ? $master->name : 'Unknown',
                    'producer' => $master !== null ? $master->producer_name : '',
                    'appellation' => $master !== null ? $master->appellation_name : '',
                    'vintage' => $variant?->vintage_year,
                    'format' => $sku->format !== null ? $sku->format->name : 'Unknown',
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
