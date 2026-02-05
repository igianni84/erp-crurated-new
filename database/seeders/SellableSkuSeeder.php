<?php

namespace Database\Seeders;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * SellableSkuSeeder - Creates SellableSku records for commercial operations
 *
 * SellableSku = WineVariant × Format × CaseConfiguration
 * This is the commercial unit used in price books, offers, and sales.
 */
class SellableSkuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get formats
        $format750 = Format::where('volume_ml', 750)->first();
        $format1500 = Format::where('volume_ml', 1500)->first();
        $format375 = Format::where('volume_ml', 375)->first();

        if (! $format750) {
            $this->command->warn('750ml format not found. Run FormatSeeder first.');

            return;
        }

        // Get case configurations
        $caseConfigs = CaseConfiguration::all()->keyBy(function ($config) {
            return $config->bottles_per_case.'_'.$config->case_type.'_'.$config->format_id;
        });

        // Get wine variants
        $variants = WineVariant::with('wineMaster')->get();

        if ($variants->isEmpty()) {
            $this->command->warn('No wine variants found. Run WineVariantSeeder first.');

            return;
        }

        // Define configuration patterns for different wine types
        $premiumWines = ['Barolo Monfortino', 'Romanee-Conti Grand Cru', 'La Tache Grand Cru', 'Musigny Grand Cru'];
        $bordeauxWines = ['Chateau Margaux', 'Chateau Latour'];
        $superTuscans = ['Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia'];

        foreach ($variants as $variant) {
            $wineName = $variant->wineMaster->name ?? '';

            // Standard 750ml 6-pack OWC (Original Wood Case) - most wines
            $owc6x750Config = $this->findCaseConfig($caseConfigs, 6, 'owc', $format750->id);
            if ($owc6x750Config) {
                $this->createSku($variant, $format750, $owc6x750Config);
            }

            // 750ml 12-pack OWC for premium wines
            $owc12x750Config = $this->findCaseConfig($caseConfigs, 12, 'owc', $format750->id);
            if ($owc12x750Config && in_array($wineName, array_merge($bordeauxWines, $superTuscans))) {
                $this->createSku($variant, $format750, $owc12x750Config);
            }

            // Loose/single bottles (1x750ml)
            $looseConfig = $this->findCaseConfig($caseConfigs, 1, 'none', $format750->id);
            if ($looseConfig) {
                $this->createSku($variant, $format750, $looseConfig);
            }

            // Magnums (1500ml) for premium wines
            if ($format1500 && in_array($wineName, array_merge($premiumWines, $bordeauxWines, ['Sassicaia', 'Barolo Cannubi']))) {
                $magnumConfig = $this->findCaseConfig($caseConfigs, 1, 'owc', $format1500->id);
                if ($magnumConfig) {
                    $this->createSku($variant, $format1500, $magnumConfig);
                }

                // 3-pack magnums for Bordeaux
                $magnum3Config = $this->findCaseConfig($caseConfigs, 3, 'owc', $format1500->id);
                if ($magnum3Config && in_array($wineName, $bordeauxWines)) {
                    $this->createSku($variant, $format1500, $magnum3Config);
                }
            }

            // Half bottles for select wines (dessert, older vintages)
            if ($format375 && fake()->boolean(10)) {
                $halfBottleConfig = $this->findCaseConfig($caseConfigs, 12, 'owc', $format375->id);
                if ($halfBottleConfig) {
                    $this->createSku($variant, $format375, $halfBottleConfig);
                }
            }
        }

        // Create some composite/bundle SKUs (2-3 examples)
        $this->createCompositeSku($variants);
    }

    /**
     * Find a case configuration by parameters.
     */
    private function findCaseConfig($caseConfigs, int $bottlesPerCase, string $caseType, string $formatId): ?CaseConfiguration
    {
        $key = $bottlesPerCase.'_'.$caseType.'_'.$formatId;

        if ($caseConfigs->has($key)) {
            return $caseConfigs->get($key);
        }

        // Fallback to database query
        return CaseConfiguration::where('bottles_per_case', $bottlesPerCase)
            ->where('case_type', $caseType)
            ->where('format_id', $formatId)
            ->first();
    }

    /**
     * Create a SellableSku record.
     */
    private function createSku(WineVariant $variant, Format $format, CaseConfiguration $caseConfig): ?SellableSku
    {
        // First check if this exact combination already exists
        $existingSku = SellableSku::where('wine_variant_id', $variant->id)
            ->where('format_id', $format->id)
            ->where('case_configuration_id', $caseConfig->id)
            ->first();

        if ($existingSku) {
            return $existingSku;
        }

        // Determine status distribution: 80% active, 15% draft, 5% retired
        $statusRandom = fake()->numberBetween(1, 100);
        $status = match (true) {
            $statusRandom <= 80 => SellableSku::STATUS_ACTIVE,
            $statusRandom <= 95 => SellableSku::STATUS_DRAFT,
            default => SellableSku::STATUS_RETIRED,
        };

        // Determine source: 70% generated, 20% liv_ex, 10% manual
        $sourceRandom = fake()->numberBetween(1, 100);
        $source = match (true) {
            $sourceRandom <= 70 => SellableSku::SOURCE_GENERATED,
            $sourceRandom <= 90 => SellableSku::SOURCE_LIV_EX,
            default => SellableSku::SOURCE_MANUAL,
        };

        // Generate a unique SKU code to avoid conflicts
        $skuCode = $this->generateUniqueSkuCode($variant, $format, $caseConfig);

        return SellableSku::create([
            'wine_variant_id' => $variant->id,
            'format_id' => $format->id,
            'case_configuration_id' => $caseConfig->id,
            'sku_code' => $skuCode,
            'lifecycle_status' => $status,
            'is_intrinsic' => fake()->boolean(70),
            'is_producer_original' => fake()->boolean(50),
            'is_verified' => fake()->boolean(30),
            'source' => $source,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            'is_composite' => false,
        ]);
    }

    /**
     * Generate a unique SKU code.
     * Format: {WINE_CODE}-{VINTAGE}-{FORMAT}-{CASE} with suffix if needed
     */
    private function generateUniqueSkuCode(WineVariant $variant, Format $format, CaseConfiguration $caseConfig): string
    {
        $wineMaster = $variant->wineMaster;

        // Generate wine code from name - use more characters for uniqueness
        $wineName = preg_replace('/[^a-zA-Z0-9]/', '', $wineMaster->name) ?: 'WINE';
        $wineCode = strtoupper(substr($wineName, 0, 4));

        // Vintage year
        $vintage = $variant->vintage_year;

        // Format volume in ml
        $formatCode = $format->volume_ml;

        // Case configuration code
        $caseType = match ($caseConfig->case_type) {
            'owc' => 'OWC',
            'oc' => 'OC',
            'none' => 'L',
            default => $caseConfig->case_type,
        };
        $caseCode = $caseConfig->bottles_per_case.$caseType;

        $baseSkuCode = "{$wineCode}-{$vintage}-{$formatCode}-{$caseCode}";

        // Check if this code already exists and add suffix if needed
        $existingCount = SellableSku::where('sku_code', 'LIKE', $baseSkuCode.'%')->count();

        if ($existingCount === 0) {
            return $baseSkuCode;
        }

        // Add a suffix to make it unique (e.g., BARB-2019-750-6OWC-2)
        return $baseSkuCode.'-'.($existingCount + 1);
    }

    /**
     * Create composite/bundle SKU examples.
     *
     * Note: Composite SKUs require a unique combination of wine_variant_id, format_id,
     * and case_configuration_id. We use a different case configuration (3-pack OWC)
     * for bundles to avoid conflicts with single-bottle non-composite SKUs.
     */
    private function createCompositeSku($variants): void
    {
        // Get some premium wine variants for bundles
        $sassicaia = $variants->first(fn ($v) => $v->wineMaster && $v->wineMaster->name === 'Sassicaia');
        $ornellaia = $variants->first(fn ($v) => $v->wineMaster && $v->wineMaster->name === 'Ornellaia');
        $tignanello = $variants->first(fn ($v) => $v->wineMaster && $v->wineMaster->name === 'Tignanello');

        if (! $sassicaia || ! $ornellaia || ! $tignanello) {
            return;
        }

        $format750 = Format::where('volume_ml', 750)->first();

        // Use a 3-pack OWC config for the bundle (different from single bottles)
        // This creates a "3 bottles of Sassicaia" bundle conceptually
        $bundleConfig = CaseConfiguration::where('bottles_per_case', 3)
            ->where('case_type', 'owc')
            ->where('format_id', $format750->id)
            ->first();

        if (! $bundleConfig) {
            // Fallback: skip composite SKU creation if no suitable config exists
            return;
        }

        // Check if this combination already exists (to avoid constraint violation)
        $exists = SellableSku::where('wine_variant_id', $sassicaia->id)
            ->where('format_id', $format750->id)
            ->where('case_configuration_id', $bundleConfig->id)
            ->exists();

        if ($exists) {
            return;
        }

        // Create a "Super Tuscan Trio" composite SKU
        // Note: The actual composite items would be created by a separate seeder or admin
        // This just creates the parent composite SKU structure
        SellableSku::create([
            'wine_variant_id' => $sassicaia->id, // Primary reference wine
            'format_id' => $format750->id,
            'case_configuration_id' => $bundleConfig->id,
            'is_composite' => true,
            'sku_code' => 'BUNDLE-SUPER-TUSCAN-TRIO-'.$sassicaia->vintage_year,
            'lifecycle_status' => SellableSku::STATUS_DRAFT, // Bundles start as draft
            'is_intrinsic' => false,
            'is_producer_original' => false,
            'is_verified' => false,
            'source' => SellableSku::SOURCE_MANUAL,
            'notes' => 'Super Tuscan Trio Bundle: 1x Sassicaia, 1x Ornellaia, 1x Tignanello',
        ]);
    }
}
