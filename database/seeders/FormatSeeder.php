<?php

namespace Database\Seeders;

use App\Models\Pim\Format;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            [
                'name' => 'Half Bottle',
                'volume_ml' => 375,
                'is_standard' => true,
                'allowed_for_liquid_conversion' => true,
            ],
            [
                'name' => 'Standard Bottle',
                'volume_ml' => 750,
                'is_standard' => true,
                'allowed_for_liquid_conversion' => true,
            ],
            [
                'name' => 'Magnum',
                'volume_ml' => 1500,
                'is_standard' => true,
                'allowed_for_liquid_conversion' => true,
            ],
            [
                'name' => 'Double Magnum',
                'volume_ml' => 3000,
                'is_standard' => true,
                'allowed_for_liquid_conversion' => true,
            ],
            [
                'name' => 'Imperial',
                'volume_ml' => 6000,
                'is_standard' => true,
                'allowed_for_liquid_conversion' => true,
            ],
        ];

        foreach ($formats as $format) {
            Format::firstOrCreate(
                ['volume_ml' => $format['volume_ml']],
                $format
            );
        }
    }
}
