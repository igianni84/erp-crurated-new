<?php

namespace Database\Seeders;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use Illuminate\Database\Seeder;

class CaseConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure formats exist first
        $this->call(FormatSeeder::class);

        $format750 = Format::where('volume_ml', 750)->first();
        $format1500 = Format::where('volume_ml', 1500)->first();
        $format375 = Format::where('volume_ml', 375)->first();

        if (! $format750 || ! $format1500) {
            $this->command->warn('Required formats (750ml, 1500ml) not found. Skipping case configuration seeding.');

            return;
        }

        if (! $format375) {
            $this->command->warn('375ml format not found. 12x375ml OWC config will be skipped.');
        }

        $configurations = [
            [
                'name' => '6x750ml OWC',
                'format_id' => $format750->id,
                'bottles_per_case' => 6,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
            [
                'name' => '6x750ml OC',
                'format_id' => $format750->id,
                'bottles_per_case' => 6,
                'case_type' => 'oc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
            [
                'name' => '12x750ml OWC',
                'format_id' => $format750->id,
                'bottles_per_case' => 12,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
            [
                'name' => '1x1500ml OWC',
                'format_id' => $format1500->id,
                'bottles_per_case' => 1,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => false,
            ],
            [
                'name' => 'Loose',
                'format_id' => $format750->id,
                'bottles_per_case' => 1,
                'case_type' => 'none',
                'is_original_from_producer' => false,
                'is_breakable' => false,
            ],
            // Additional configurations needed by SellableSkuSeeder
            [
                'name' => '3x750ml OWC',
                'format_id' => $format750->id,
                'bottles_per_case' => 3,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
            [
                'name' => '3x1500ml OWC',
                'format_id' => $format1500->id,
                'bottles_per_case' => 3,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
            [
                'name' => '12x375ml OWC',
                'format_id' => $format375?->id,
                'bottles_per_case' => 12,
                'case_type' => 'owc',
                'is_original_from_producer' => true,
                'is_breakable' => true,
            ],
        ];

        foreach ($configurations as $config) {
            if ($config['format_id'] === null) {
                continue;
            }
            CaseConfiguration::firstOrCreate(
                ['name' => $config['name']],
                $config
            );
        }
    }
}
