<?php

namespace App\AI\Tools\Allocation;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Voucher;
use App\Models\Pim\Producer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BottlesSoldByProducerTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get how many bottles of a specific producer (or top producers) have been sold.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'producer_name' => $schema->string(),
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_7_days', 'last_30_days'])
                ->default('this_year'),
            'limit' => $schema->integer()->min(1)->max(50)->default(10),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->parsePeriod($request['period'] ?? 'this_year');
        $limit = (int) ($request['limit'] ?? 10);
        $producerName = $request['producer_name'] ?? null;

        $query = Voucher::query()
            ->whereIn('lifecycle_state', [VoucherLifecycleState::Issued, VoucherLifecycleState::Locked, VoucherLifecycleState::Redeemed])
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('allocation.wineVariant.wineMaster.producerRelation');

        if ($producerName !== null) {
            $like = '%'.$producerName.'%';

            // Check disambiguation
            $matchingProducers = Producer::where('name', 'LIKE', $like)->limit(10)->get();
            $disambiguation = $this->disambiguateResults($matchingProducers, (string) $producerName, 'name');
            if ($disambiguation !== null) {
                return (string) json_encode(['message' => $disambiguation]);
            }

            $producer = $matchingProducers->first();
            if ($producer === null) {
                return (string) json_encode(['message' => "No producer found matching '{$producerName}'."]);
            }

            // Get vouchers for this specific producer
            $vouchers = (clone $query)
                ->whereHas('allocation.wineVariant.wineMaster', function ($q) use ($producer): void {
                    $q->where('producer_id', $producer->id);
                })
                ->with('allocation.wineVariant.wineMaster')
                ->get();

            $wineBreakdown = [];
            foreach ($vouchers as $voucher) {
                $wineName = $voucher->allocation->wineVariant->wineMaster->name ?? 'Unknown';
                if (! isset($wineBreakdown[$wineName])) {
                    $wineBreakdown[$wineName] = 0;
                }
                $wineBreakdown[$wineName]++;
            }

            arsort($wineBreakdown);

            $topWines = [];
            foreach ($wineBreakdown as $name => $count) {
                $topWines[] = ['wine_name' => $name, 'count' => $count];
            }

            return (string) json_encode([
                'producers' => [[
                    'producer_name' => $producer->name,
                    'bottles_sold' => $vouchers->count(),
                    'top_wines' => $topWines,
                ]],
            ]);
        }

        // No specific producer — show top producers
        $vouchers = (clone $query)
            ->with('allocation.wineVariant.wineMaster.producerRelation')
            ->get();

        $producerCounts = [];
        foreach ($vouchers as $voucher) {
            $pName = $voucher->allocation->wineVariant->wineMaster->producer_name ?? 'Unknown';
            if (! isset($producerCounts[$pName])) {
                $producerCounts[$pName] = 0;
            }
            $producerCounts[$pName]++;
        }

        arsort($producerCounts);
        $producerCounts = array_slice($producerCounts, 0, $limit, true);

        $producers = [];
        foreach ($producerCounts as $name => $count) {
            $producers[] = [
                'producer_name' => $name,
                'bottles_sold' => $count,
            ];
        }

        return (string) json_encode([
            'producers' => $producers,
        ]);
    }
}
