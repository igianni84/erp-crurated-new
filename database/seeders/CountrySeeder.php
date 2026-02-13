<?php

namespace Database\Seeders;

use App\Models\Pim\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Seed major wine-producing countries.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'France', 'iso_code' => 'FR', 'iso_code_3' => 'FRA', 'sort_order' => 1],
            ['name' => 'Italy', 'iso_code' => 'IT', 'iso_code_3' => 'ITA', 'sort_order' => 2],
            ['name' => 'Spain', 'iso_code' => 'ES', 'iso_code_3' => 'ESP', 'sort_order' => 3],
            ['name' => 'Portugal', 'iso_code' => 'PT', 'iso_code_3' => 'PRT', 'sort_order' => 4],
            ['name' => 'Germany', 'iso_code' => 'DE', 'iso_code_3' => 'DEU', 'sort_order' => 5],
            ['name' => 'Austria', 'iso_code' => 'AT', 'iso_code_3' => 'AUT', 'sort_order' => 6],
            ['name' => 'United States', 'iso_code' => 'US', 'iso_code_3' => 'USA', 'sort_order' => 7],
            ['name' => 'Australia', 'iso_code' => 'AU', 'iso_code_3' => 'AUS', 'sort_order' => 8],
            ['name' => 'New Zealand', 'iso_code' => 'NZ', 'iso_code_3' => 'NZL', 'sort_order' => 9],
            ['name' => 'South Africa', 'iso_code' => 'ZA', 'iso_code_3' => 'ZAF', 'sort_order' => 10],
            ['name' => 'Chile', 'iso_code' => 'CL', 'iso_code_3' => 'CHL', 'sort_order' => 11],
            ['name' => 'Argentina', 'iso_code' => 'AR', 'iso_code_3' => 'ARG', 'sort_order' => 12],
            ['name' => 'United Kingdom', 'iso_code' => 'GB', 'iso_code_3' => 'GBR', 'sort_order' => 13],
            ['name' => 'Switzerland', 'iso_code' => 'CH', 'iso_code_3' => 'CHE', 'sort_order' => 14],
            ['name' => 'Hungary', 'iso_code' => 'HU', 'iso_code_3' => 'HUN', 'sort_order' => 15],
            ['name' => 'Greece', 'iso_code' => 'GR', 'iso_code_3' => 'GRC', 'sort_order' => 16],
            ['name' => 'Lebanon', 'iso_code' => 'LB', 'iso_code_3' => 'LBN', 'sort_order' => 17],
            ['name' => 'Israel', 'iso_code' => 'IL', 'iso_code_3' => 'ISR', 'sort_order' => 18],
        ];

        foreach ($countries as $data) {
            Country::firstOrCreate(
                ['iso_code' => $data['iso_code']],
                $data
            );
        }

        $this->command->info('Created '.count($countries).' countries.');
    }
}
