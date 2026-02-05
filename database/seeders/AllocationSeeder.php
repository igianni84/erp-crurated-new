<?php

namespace Database\Seeders;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * AllocationSeeder - Creates wine allocations for selling
 */
class AllocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the 750ml format (standard bottle)
        $format750 = Format::where('volume_ml', 750)->first();
        $format1500 = Format::where('volume_ml', 1500)->first();

        if (! $format750) {
            $this->command->warn('750ml format not found. Run FormatSeeder first.');

            return;
        }

        // Get wine variants
        $variants = WineVariant::with('wineMaster')->get();

        if ($variants->isEmpty()) {
            $this->command->warn('No wine variants found. Run WineVariantSeeder first.');

            return;
        }

        $allocationConfigs = [
            // Producer allocations - premium wines with limited quantities
            [
                'wines' => ['Barolo Monfortino', 'Romanee-Conti Grand Cru', 'La Tache Grand Cru'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [6, 24],
                'serialization_required' => true,
            ],
            // Owned stock - more common wines with higher quantities
            [
                'wines' => ['Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia', 'Brunello di Montalcino'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 120],
                'serialization_required' => true,
            ],
            // Passive consignment - Bordeaux
            [
                'wines' => ['Chateau Margaux', 'Chateau Latour'],
                'source_type' => AllocationSourceType::PassiveConsignment,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 60],
                'serialization_required' => true,
            ],
            // Third party custody - Barbaresco, Barolo
            [
                'wines' => ['Barolo Cannubi', 'Barolo Falletto', 'Barbaresco Asili', 'Barbaresco Sori Tildin'],
                'source_type' => AllocationSourceType::ThirdPartyCustody,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [12, 72],
                'serialization_required' => false,
            ],
            // Liquid allocations - en primeur
            [
                'wines' => ['Brunello di Montalcino Riserva', 'Musigny Grand Cru'],
                'source_type' => AllocationSourceType::ProducerAllocation,
                'supply_form' => AllocationSupplyForm::Liquid,
                'quantity_range' => [12, 36],
                'serialization_required' => false,
            ],
            // Amarone - owned stock
            [
                'wines' => ['Amarone della Valpolicella Classico'],
                'source_type' => AllocationSourceType::OwnedStock,
                'supply_form' => AllocationSupplyForm::Bottled,
                'quantity_range' => [24, 96],
                'serialization_required' => true,
            ],
        ];

        foreach ($allocationConfigs as $config) {
            foreach ($config['wines'] as $wineName) {
                // Find variants for this wine
                $wineVariants = $variants->filter(function ($variant) use ($wineName) {
                    return $variant->wineMaster && $variant->wineMaster->name === $wineName;
                });

                foreach ($wineVariants as $variant) {
                    $totalQty = fake()->numberBetween($config['quantity_range'][0], $config['quantity_range'][1]);

                    // Determine sold quantity and status
                    $statusRandom = fake()->numberBetween(1, 100);
                    if ($statusRandom <= 10) {
                        // 10% draft
                        $status = AllocationStatus::Draft;
                        $soldQty = 0;
                    } elseif ($statusRandom <= 80) {
                        // 70% active with some sales
                        $status = AllocationStatus::Active;
                        $soldQty = fake()->numberBetween(0, (int) ($totalQty * 0.7));
                    } elseif ($statusRandom <= 95) {
                        // 15% exhausted
                        $status = AllocationStatus::Exhausted;
                        $soldQty = $totalQty;
                    } else {
                        // 5% closed
                        $status = AllocationStatus::Closed;
                        $soldQty = fake()->numberBetween((int) ($totalQty * 0.5), $totalQty);
                    }

                    // Create 750ml allocation
                    Allocation::firstOrCreate(
                        [
                            'wine_variant_id' => $variant->id,
                            'format_id' => $format750->id,
                            'source_type' => $config['source_type'],
                        ],
                        [
                            'supply_form' => $config['supply_form'],
                            'total_quantity' => $totalQty,
                            'sold_quantity' => $soldQty,
                            'expected_availability_start' => now()->subMonths(fake()->numberBetween(1, 12)),
                            'expected_availability_end' => now()->addMonths(fake()->numberBetween(6, 24)),
                            'serialization_required' => $config['serialization_required'],
                            'status' => $status,
                        ]
                    );

                    // Create some 1500ml (magnum) allocations for premium wines
                    if ($format1500 && in_array($wineName, ['Barolo Monfortino', 'Romanee-Conti Grand Cru', 'Sassicaia', 'Chateau Margaux'])) {
                        $magnumQty = fake()->numberBetween(3, 12);
                        $magnumSold = fake()->numberBetween(0, (int) ($magnumQty * 0.5));

                        Allocation::firstOrCreate(
                            [
                                'wine_variant_id' => $variant->id,
                                'format_id' => $format1500->id,
                                'source_type' => $config['source_type'],
                            ],
                            [
                                'supply_form' => AllocationSupplyForm::Bottled,
                                'total_quantity' => $magnumQty,
                                'sold_quantity' => $magnumSold,
                                'expected_availability_start' => now()->subMonths(fake()->numberBetween(1, 6)),
                                'expected_availability_end' => now()->addMonths(fake()->numberBetween(12, 36)),
                                'serialization_required' => true,
                                'status' => AllocationStatus::Active,
                            ]
                        );
                    }
                }
            }
        }
    }
}
