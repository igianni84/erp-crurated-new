<?php

namespace Database\Seeders;

use App\Enums\ProductLifecycleStatus;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * LiquidProductSeeder - Creates liquid products for en primeur wines
 *
 * Liquid products represent wine before bottling (en primeur/futures).
 * They define what formats and case configurations are allowed when bottling.
 */
class LiquidProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get published wine variants
        $variants = WineVariant::where('lifecycle_status', ProductLifecycleStatus::Published)
            ->with('wineMaster')
            ->get();

        if ($variants->isEmpty()) {
            $this->command->warn('No published wine variants found. Run WineVariantSeeder first.');

            return;
        }

        // Get formats and case configurations
        $formats = Format::all();
        $caseConfigs = CaseConfiguration::all();

        if ($formats->isEmpty() || $caseConfigs->isEmpty()) {
            $this->command->warn('Formats or case configurations not found. Run their seeders first.');

            return;
        }

        // Wines that typically have liquid products (en primeur)
        $enPrimeurWines = [
            // Bordeaux - classic en primeur
            'Chateau Margaux',
            'Chateau Latour',
            'Chateau Lafite Rothschild',
            'Chateau Mouton Rothschild',
            'Chateau Haut-Brion',
            'Petrus',
            'Chateau Cheval Blanc',
            'Chateau Ausone',
            'Le Pin',
            'Chateau Leoville Las Cases',
            'Chateau Cos d\'Estournel',
            // Italian wines with futures programs
            'Brunello di Montalcino Riserva',
            'Barolo Monfortino',
            'Masseto',
            'Sassicaia',
            'Ornellaia',
            // Burgundy
            'Romanee-Conti Grand Cru',
            'La Tache Grand Cru',
            'Richebourg Grand Cru',
            'Musigny Grand Cru',
            'Montrachet Grand Cru',
        ];

        // Get format IDs
        $format750 = $formats->where('volume_ml', 750)->first();
        $format375 = $formats->where('volume_ml', 375)->first();
        $format1500 = $formats->where('volume_ml', 1500)->first();
        $format3000 = $formats->where('volume_ml', 3000)->first();

        // Get case configuration IDs
        $owc6 = $caseConfigs->where('case_type', 'owc')->where('bottles_per_case', 6)->first();
        $owc12 = $caseConfigs->where('case_type', 'owc')->where('bottles_per_case', 12)->first();
        $oc12 = $caseConfigs->where('case_type', 'oc')->first();

        $totalCreated = 0;

        foreach ($variants as $variant) {
            // Only create liquid products for en primeur wines and recent vintages
            if (! $variant->wineMaster || ! in_array($variant->wineMaster->name, $enPrimeurWines)) {
                continue;
            }

            // Only recent vintages have liquid products (futures)
            if ($variant->vintage_year < 2020) {
                continue;
            }

            // Check if liquid product already exists
            $existing = LiquidProduct::where('wine_variant_id', $variant->id)->first();
            if ($existing) {
                continue;
            }

            // Determine allowed formats based on wine type
            $allowedFormats = [$format750?->id];

            // Premium wines get more format options
            if (in_array($variant->wineMaster->name, ['Chateau Margaux', 'Chateau Latour', 'Romanee-Conti Grand Cru', 'Petrus'])) {
                if ($format375) {
                    $allowedFormats[] = $format375->id;
                }
                if ($format1500) {
                    $allowedFormats[] = $format1500->id;
                }
                if ($format3000) {
                    $allowedFormats[] = $format3000->id;
                }
            } elseif (fake()->boolean(50)) {
                // Other wines have 50% chance of magnum format
                if ($format1500) {
                    $allowedFormats[] = $format1500->id;
                }
            }

            $allowedFormats = array_filter($allowedFormats);

            // Determine allowed case configurations
            $allowedCases = [];
            if ($owc6) {
                $allowedCases[] = $owc6->id;
            }
            if ($owc12) {
                $allowedCases[] = $owc12->id;
            }
            if ($oc12 && fake()->boolean(30)) {
                $allowedCases[] = $oc12->id;
            }

            // Determine bottling constraints
            $bottlingConstraints = $this->generateBottlingConstraints($variant);

            // Determine lifecycle status
            // 60% Published (available for purchase)
            // 20% Approved (ready to publish)
            // 15% In Review
            // 5% Draft
            $statusRandom = fake()->numberBetween(1, 100);
            $lifecycleStatus = match (true) {
                $statusRandom <= 60 => ProductLifecycleStatus::Published,
                $statusRandom <= 80 => ProductLifecycleStatus::Approved,
                $statusRandom <= 95 => ProductLifecycleStatus::InReview,
                default => ProductLifecycleStatus::Draft,
            };

            // Allowed equivalent units (how many bottle equivalents in liquid)
            // For en primeur, typically expressed in 750ml equivalents
            $allowedEquivalentUnits = [
                'unit_size_ml' => 750,
                'minimum_order' => fake()->randomElement([3, 6, 12]),
                'maximum_order' => fake()->randomElement([24, 48, 72, 120]),
            ];

            LiquidProduct::create([
                'wine_variant_id' => $variant->id,
                'allowed_equivalent_units' => $allowedEquivalentUnits,
                'allowed_final_formats' => $allowedFormats,
                'allowed_case_configurations' => $allowedCases,
                'bottling_constraints' => $bottlingConstraints,
                'serialization_required' => true,
                'lifecycle_status' => $lifecycleStatus,
            ]);

            $totalCreated++;
        }

        // Also create some liquid products for non-en-primeur wines (custom bottling)
        $otherVariants = WineVariant::where('lifecycle_status', ProductLifecycleStatus::Published)
            ->whereDoesntHave('liquidProduct')
            ->with('wineMaster')
            ->inRandomOrder()
            ->take(10)
            ->get();

        foreach ($otherVariants as $variant) {
            // Only recent vintages
            if ($variant->vintage_year < 2021) {
                continue;
            }

            $allowedFormats = [$format750?->id];
            if ($format1500 && fake()->boolean(30)) {
                $allowedFormats[] = $format1500->id;
            }
            $allowedFormats = array_filter($allowedFormats);

            $allowedCases = [];
            if ($owc6) {
                $allowedCases[] = $owc6->id;
            }
            if ($owc12 && fake()->boolean(50)) {
                $allowedCases[] = $owc12->id;
            }

            $bottlingConstraints = $this->generateBottlingConstraints($variant);

            $allowedEquivalentUnits = [
                'unit_size_ml' => 750,
                'minimum_order' => 6,
                'maximum_order' => 48,
            ];

            LiquidProduct::create([
                'wine_variant_id' => $variant->id,
                'allowed_equivalent_units' => $allowedEquivalentUnits,
                'allowed_final_formats' => $allowedFormats,
                'allowed_case_configurations' => $allowedCases,
                'bottling_constraints' => $bottlingConstraints,
                'serialization_required' => fake()->boolean(80),
                'lifecycle_status' => fake()->randomElement([
                    ProductLifecycleStatus::Draft,
                    ProductLifecycleStatus::InReview,
                    ProductLifecycleStatus::Approved,
                    ProductLifecycleStatus::Published,
                ]),
            ]);

            $totalCreated++;
        }

        $this->command->info("Created {$totalCreated} liquid products.");
    }

    /**
     * Generate bottling constraints based on wine type.
     */
    private function generateBottlingConstraints(WineVariant $variant): array
    {
        $wineName = $variant->wineMaster->name ?? '';
        $region = $variant->wineMaster->region ?? '';

        // Base constraints
        $constraints = [
            'cork_type' => fake()->randomElement(['natural', 'natural_long', 'diam']),
            'bottle_weight' => fake()->randomElement(['standard', 'heavy', 'premium_heavy']),
            'capsule_type' => fake()->randomElement(['tin', 'aluminum', 'wax']),
        ];

        // Regional requirements
        if (str_contains($region, 'Bordeaux')) {
            $constraints['bottle_shape'] = 'bordeaux';
            $constraints['label_requirements'] = [
                'appellation_required' => true,
                'classification_display' => true,
                'vintage_prominent' => true,
            ];
        } elseif (str_contains($region, 'Burgundy')) {
            $constraints['bottle_shape'] = 'burgundy';
            $constraints['label_requirements'] = [
                'domaine_name_required' => true,
                'vineyard_name_required' => true,
            ];
        } elseif (str_contains($region, 'Tuscany') || str_contains($region, 'Piedmont')) {
            $constraints['bottle_shape'] = fake()->randomElement(['bordeaux', 'burgundy']);
            $constraints['label_requirements'] = [
                'docg_seal' => true,
                'producer_name_required' => true,
            ];
        }

        // Bottling timeline
        $bottlingYear = $variant->vintage_year + fake()->numberBetween(2, 4);
        $constraints['earliest_bottling_date'] = "{$bottlingYear}-01-01";
        $constraints['latest_bottling_date'] = "{$bottlingYear}-12-31";
        $constraints['release_window_months'] = fake()->numberBetween(6, 24);

        // Storage requirements
        $constraints['storage_requirements'] = [
            'temperature_celsius' => fake()->numberBetween(12, 16),
            'humidity_percent' => fake()->numberBetween(65, 75),
            'light_exposure' => 'none',
        ];

        return $constraints;
    }
}
