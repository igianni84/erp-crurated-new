<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\ProductLifecycleStatus;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * AllocationSeeder - Creates comprehensive wine allocations
 *
 * Creates allocations across all statuses:
 * - Draft: Newly created allocations not yet active
 * - Active: Currently available for sale
 * - Exhausted: Fully sold out
 * - Closed: Manually closed before exhaustion
 *
 * Source types:
 * - ProducerAllocation: Direct from producer
 * - OwnedStock: Purchased and owned by Crurated
 * - PassiveConsignment: Held on consignment (custody only)
 * - ThirdPartyCustody: Owned but stored at third party
 */
class AllocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get formats
        $format375 = Format::where('volume_ml', 375)->first();
        $format750 = Format::where('volume_ml', 750)->first();
        $format1500 = Format::where('volume_ml', 1500)->first();
        $format3000 = Format::where('volume_ml', 3000)->first();

        if (! $format750) {
            $this->command->warn('750ml format not found. Run FormatSeeder first.');

            return;
        }

        // Get published wine variants
        $variants = WineVariant::with('wineMaster')
            ->where('lifecycle_status', ProductLifecycleStatus::Published)
            ->get();

        if ($variants->isEmpty()) {
            $this->command->warn('No published wine variants found. Run WineVariantSeeder first.');

            return;
        }

        // Allocation configurations by wine category
        $allocationConfigs = [
            // =========================================================================
            // ULTRA PREMIUM - VERY LIMITED QUANTITIES
            // =========================================================================
            [
                'wines' => ['Romanee-Conti Grand Cru', 'La Tache Grand Cru', 'Richebourg Grand Cru', 'Montrachet Grand Cru'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [3, 12],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Petrus', 'Le Pin'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 18],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // PREMIUM PIEDMONT - PRODUCER ALLOCATIONS
            // =========================================================================
            [
                'wines' => ['Barolo Monfortino', 'Barolo Falletto', 'Barbaresco Asili'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 24],
                'serialization_required' => true,
                'formats' => [$format750, $format1500],
            ],
            [
                'wines' => ['Barolo Cannubi', 'Barolo Brunate', 'Barolo Rocche dell\'Annunziata', 'Barolo Bussia'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 48],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Barbaresco Sori Tildin', 'Barbaresco Sori San Lorenzo', 'Barbaresco Rabaja'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 36],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // PREMIUM TUSCANY - MIXED SOURCES
            // =========================================================================
            [
                'wines' => ['Masseto'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 18],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Sassicaia', 'Ornellaia', 'Solaia'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 120],
                'serialization_required' => true,
                'formats' => [$format750, $format1500, $format3000],
            ],
            [
                'wines' => ['Tignanello', 'Guado al Tasso', 'Flaccianello della Pieve'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [36, 144],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Brunello di Montalcino', 'Brunello di Montalcino Poggio alle Mura', 'Brunello di Montalcino Cerretalto'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 96],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Brunello di Montalcino Riserva'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 24],
                'serialization_required' => true,
                'formats' => [$format750, $format1500],
            ],
            [
                'wines' => ['Chianti Classico Gran Selezione', 'Chianti Classico Riserva'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [48, 192],
                'serialization_required' => false,
                'formats' => [$format750],
            ],

            // =========================================================================
            // VENETO - AMARONE
            // =========================================================================
            [
                'wines' => ['Amarone della Valpolicella Classico', 'Amarone della Valpolicella TB'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 72],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Amarone della Valpolicella'],
                'source_type' => AllocationSourceType::ThirdPartyCustody,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [36, 120],
                'serialization_required' => false,
                'formats' => [$format750],
            ],

            // =========================================================================
            // BORDEAUX - LEFT BANK
            // =========================================================================
            [
                'wines' => ['Chateau Margaux', 'Chateau Latour', 'Chateau Lafite Rothschild', 'Chateau Mouton Rothschild', 'Chateau Haut-Brion'],
                'source_type' => AllocationSourceType::PassiveConsignment,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 60],
                'serialization_required' => true,
                'formats' => [$format750, $format1500, $format3000],
            ],
            [
                'wines' => ['Chateau Leoville Las Cases', 'Chateau Cos d\'Estournel'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 96],
                'serialization_required' => true,
                'formats' => [$format750, $format1500],
            ],

            // =========================================================================
            // BORDEAUX - RIGHT BANK
            // =========================================================================
            [
                'wines' => ['Chateau Cheval Blanc', 'Chateau Ausone'],
                'source_type' => AllocationSourceType::PassiveConsignment,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 24],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // BURGUNDY - RED
            // =========================================================================
            [
                'wines' => ['Musigny Grand Cru', 'Chambertin Grand Cru', 'Clos de la Roche Grand Cru'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 18],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // BURGUNDY - WHITE
            // =========================================================================
            [
                'wines' => ['Corton-Charlemagne Grand Cru'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 18],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // CHAMPAGNE
            // =========================================================================
            [
                'wines' => ['Dom Perignon', 'Cristal'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 96],
                'serialization_required' => true,
                'formats' => [$format750, $format1500],
            ],
            [
                'wines' => ['Krug Grande Cuvee'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 72],
                'serialization_required' => true,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Salon Le Mesnil'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 24],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // RHONE VALLEY
            // =========================================================================
            [
                'wines' => ['Chateauneuf-du-Pape Hommage a Jacques Perrin', 'Hermitage La Chapelle', 'Cote Rotie La Landonne'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 48],
                'serialization_required' => true,
                'formats' => [$format750],
            ],

            // =========================================================================
            // LIQUID ALLOCATIONS (En Primeur)
            // =========================================================================
            [
                'wines' => ['Brunello di Montalcino Riserva', 'Barolo Monfortino'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Liquid,
                'quantity_range' => [12, 36],
                'serialization_required' => false,
                'formats' => [$format750],
            ],
            [
                'wines' => ['Chateau Margaux', 'Chateau Latour', 'Chateau Lafite Rothschild'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Liquid,
                'quantity_range' => [24, 72],
                'serialization_required' => false,
                'formats' => [$format750, $format1500],
            ],
        ];

        $totalCreated = 0;

        foreach ($allocationConfigs as $config) {
            foreach ($config['wines'] as $wineName) {
                // Find variants for this wine
                $wineVariants = $variants->filter(function ($variant) use ($wineName) {
                    return $variant->wineMaster && $variant->wineMaster->name === $wineName;
                });

                foreach ($wineVariants as $variant) {
                    // Create allocation for each specified format
                    $formats = array_filter($config['formats']);

                    foreach ($formats as $format) {
                        $totalQty = fake()->numberBetween($config['quantity_range'][0], $config['quantity_range'][1]);

                        // All allocations start Active with sold_quantity=0.
                        // VoucherSeeder will consume them via VoucherService (increments sold_quantity).
                        // 5% Draft (not available for sale yet)
                        // 95% Active (ready for VoucherSeeder to consume)
                        $statusRandom = fake()->numberBetween(1, 100);
                        $status = $statusRandom <= 5 ? AllocationStatus::Draft : AllocationStatus::Active;
                        $soldQty = 0;

                        // Determine availability dates
                        $availabilityStart = now()->subMonths(fake()->numberBetween(1, 18));
                        $availabilityEnd = now()->addMonths(fake()->numberBetween(6, 36));

                        // Create the allocation
                        Allocation::firstOrCreate(
                            [
                                'wine_variant_id' => $variant->id,
                                'format_id' => $format->id,
                                'source_type' => $config['source_type'],
                                'supply_form' => $config['supply_form'],
                            ],
                            [
                                'total_quantity' => $totalQty,
                                'sold_quantity' => $soldQty,
                                'expected_availability_start' => $availabilityStart,
                                'expected_availability_end' => $availabilityEnd,
                                'serialization_required' => $config['serialization_required'],
                                'status' => $status,
                            ]
                        );

                        $totalCreated++;
                    }
                }
            }
        }

        // Create some additional allocations for wines not in the config
        $remainingVariants = $variants->reject(function ($variant) use ($allocationConfigs) {
            $wineName = $variant->wineMaster?->name;
            foreach ($allocationConfigs as $config) {
                if (in_array($wineName, $config['wines'])) {
                    return true;
                }
            }

            return false;
        });

        foreach ($remainingVariants->take(30) as $variant) {
            $totalQty = fake()->numberBetween(12, 72);
            $statusRandom = fake()->numberBetween(1, 100);

            $status = $statusRandom <= 10 ? AllocationStatus::Draft : AllocationStatus::Active;
            $soldQty = 0;

            Allocation::firstOrCreate(
                [
                    'wine_variant_id' => $variant->id,
                    'format_id' => $format750->id,
                    'source_type' => fake()->randomElement([
                        AllocationSourceType::OwnedStock,
                        AllocationSourceType::OwnedStock,
                        AllocationSourceType::ProducerAllocation,
                        AllocationSourceType::ThirdPartyCustody,
                    ]),
                    'supply_form' => AllocationSupplyForm::Bottled,
                ],
                [
                    'total_quantity' => $totalQty,
                    'sold_quantity' => $soldQty,
                    'expected_availability_start' => now()->subMonths(fake()->numberBetween(1, 12)),
                    'expected_availability_end' => now()->addMonths(fake()->numberBetween(6, 24)),
                    'serialization_required' => fake()->boolean(70),
                    'status' => $status,
                ]
            );

            $totalCreated++;
        }

        $this->command->info("Created {$totalCreated} allocations.");
    }
}
