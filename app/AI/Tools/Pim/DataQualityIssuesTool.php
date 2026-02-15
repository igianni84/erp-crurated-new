<?php

namespace App\AI\Tools\Pim;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DataQualityIssuesTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Identify data quality issues in the PIM (orphaned records, missing fields).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        $issues = [];

        // WineMasters without any WineVariants
        $mastersWithoutVariants = WineMaster::query()
            ->whereDoesntHave('wineVariants')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        if (count($mastersWithoutVariants) > 0) {
            $total = WineMaster::whereDoesntHave('wineVariants')->count();
            $issues[] = [
                'type' => 'WineMaster without WineVariants',
                'severity' => 'high',
                'count' => $total,
                'sample_names' => $mastersWithoutVariants,
            ];
        }

        // WineVariants without any SellableSku
        $variantsWithoutSkus = WineVariant::query()
            ->whereDoesntHave('sellableSkus')
            ->with('wineMaster')
            ->limit(10)
            ->get()
            ->map(fn (WineVariant $v): string => ($v->wineMaster->name ?? 'Unknown').' '.$v->vintage_year)
            ->toArray();

        if (count($variantsWithoutSkus) > 0) {
            $total = WineVariant::whereDoesntHave('sellableSkus')->count();
            $issues[] = [
                'type' => 'WineVariant without SellableSku',
                'severity' => 'high',
                'count' => $total,
                'sample_names' => $variantsWithoutSkus,
            ];
        }

        // SellableSkus without CaseConfiguration
        $skusWithoutCase = SellableSku::query()
            ->whereNull('case_configuration_id')
            ->limit(10)
            ->pluck('sku_code')
            ->toArray();

        if (count($skusWithoutCase) > 0) {
            $total = SellableSku::whereNull('case_configuration_id')->count();
            $issues[] = [
                'type' => 'SellableSku without CaseConfiguration',
                'severity' => 'medium',
                'count' => $total,
                'sample_names' => $skusWithoutCase,
            ];
        }

        // WineMasters with null producer_id
        $mastersNoProducer = WineMaster::query()
            ->whereNull('producer_id')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        if (count($mastersNoProducer) > 0) {
            $total = WineMaster::whereNull('producer_id')->count();
            $issues[] = [
                'type' => 'WineMaster with missing producer',
                'severity' => 'medium',
                'count' => $total,
                'sample_names' => $mastersNoProducer,
            ];
        }

        // WineMasters with null country_id
        $mastersNoCountry = WineMaster::query()
            ->whereNull('country_id')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        if (count($mastersNoCountry) > 0) {
            $total = WineMaster::whereNull('country_id')->count();
            $issues[] = [
                'type' => 'WineMaster with missing country',
                'severity' => 'low',
                'count' => $total,
                'sample_names' => $mastersNoCountry,
            ];
        }

        // WineMasters with null region_id
        $mastersNoRegion = WineMaster::query()
            ->whereNull('region_id')
            ->limit(10)
            ->pluck('name')
            ->toArray();

        if (count($mastersNoRegion) > 0) {
            $total = WineMaster::whereNull('region_id')->count();
            $issues[] = [
                'type' => 'WineMaster with missing region',
                'severity' => 'low',
                'count' => $total,
                'sample_names' => $mastersNoRegion,
            ];
        }

        return (string) json_encode([
            'total_issues' => count($issues),
            'issues' => $issues,
        ]);
    }
}
