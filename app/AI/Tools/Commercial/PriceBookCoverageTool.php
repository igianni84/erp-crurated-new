<?php

namespace App\AI\Tools\Commercial;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PriceBookCoverageTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Get price book coverage vs total active sellable SKUs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'price_book_id' => $schema->string(),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        $totalActiveSkus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)->count();

        $query = PriceBook::query()->withCount('entries');

        if (isset($request['price_book_id'])) {
            $query->where('id', (string) $request['price_book_id']);
        }

        $priceBooks = $query->orderBy('name')->get();

        $list = [];
        foreach ($priceBooks as $book) {
            $entryCount = (int) $book->getAttribute('entries_count');
            $coverage = $totalActiveSkus > 0 ? round(($entryCount / $totalActiveSkus) * 100, 1) : 0;
            $missing = max(0, $totalActiveSkus - $entryCount);

            $list[] = [
                'name' => $book->name,
                'status' => $book->status->label(),
                'total_entries' => $entryCount,
                'total_active_skus' => $totalActiveSkus,
                'coverage_percentage' => $coverage,
                'missing_count' => $missing,
            ];
        }

        return (string) json_encode([
            'price_books' => $list,
        ]);
    }
}
