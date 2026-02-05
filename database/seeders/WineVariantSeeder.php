<?php

namespace Database\Seeders;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * WineVariantSeeder - Creates wine variants with realistic vintages
 */
class WineVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define which wines get which vintages (more recent for entry-level, older for prestigious)
        $vintageConfigs = [
            // Premium wines - older vintages
            'Barolo Monfortino' => [2015, 2016, 2017],
            'Brunello di Montalcino Riserva' => [2015, 2016],
            'Romanee-Conti Grand Cru' => [2017, 2018, 2019],
            'La Tache Grand Cru' => [2017, 2018, 2019],
            'Chateau Margaux' => [2015, 2016, 2018, 2019, 2020],
            'Chateau Latour' => [2015, 2016, 2018, 2019],
            'Sassicaia' => [2017, 2018, 2019, 2020],
            'Ornellaia' => [2017, 2018, 2019, 2020, 2021],

            // Standard wines - more vintages
            'Barolo Cannubi' => [2017, 2018, 2019, 2020],
            'Barolo Falletto' => [2017, 2018, 2019, 2020],
            'Barbaresco Asili' => [2018, 2019, 2020, 2021],
            'Barbaresco Sori Tildin' => [2018, 2019, 2020],
            'Brunello di Montalcino' => [2017, 2018, 2019, 2020],
            'Brunello di Montalcino Poggio alle Mura' => [2017, 2018, 2019],
            'Tignanello' => [2018, 2019, 2020, 2021],
            'Solaia' => [2018, 2019, 2020],
            'Amarone della Valpolicella Classico' => [2015, 2016, 2017, 2018],
            'Musigny Grand Cru' => [2017, 2018, 2019],
        ];

        // Critic scores for realistic data
        $criticScoreRanges = [
            'Barolo Monfortino' => ['parker' => [97, 100], 'jancis' => [18.5, 19.5]],
            'Romanee-Conti Grand Cru' => ['parker' => [98, 100], 'jancis' => [19.0, 20.0]],
            'La Tache Grand Cru' => ['parker' => [96, 99], 'jancis' => [18.5, 19.5]],
            'Chateau Margaux' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Chateau Latour' => ['parker' => [95, 99], 'jancis' => [18.0, 19.0]],
            'Sassicaia' => ['parker' => [94, 98], 'jancis' => [17.5, 19.0]],
            'Ornellaia' => ['parker' => [93, 97], 'jancis' => [17.5, 18.5]],
            'default' => ['parker' => [90, 96], 'jancis' => [16.5, 18.0]],
        ];

        // Alcohol percentages by wine type
        $alcoholRanges = [
            'Amarone' => [15.0, 16.5],
            'Barolo' => [13.5, 14.5],
            'Barbaresco' => [13.5, 14.5],
            'Brunello' => [13.5, 14.5],
            'Chateau' => [13.0, 14.0],
            'default' => [13.0, 14.5],
        ];

        foreach ($vintageConfigs as $wineName => $vintages) {
            $wineMaster = WineMaster::where('name', $wineName)->first();

            if (! $wineMaster) {
                continue;
            }

            foreach ($vintages as $vintage) {
                // Determine alcohol range
                $alcoholRange = $this->getAlcoholRange($wineName, $alcoholRanges);
                $alcohol = fake()->randomFloat(1, $alcoholRange[0], $alcoholRange[1]);

                // Determine critic scores
                $criticRange = $criticScoreRanges[$wineName] ?? $criticScoreRanges['default'];
                $criticScores = [
                    'robert_parker' => fake()->numberBetween($criticRange['parker'][0], $criticRange['parker'][1]),
                    'jancis_robinson' => fake()->randomFloat(1, $criticRange['jancis'][0], $criticRange['jancis'][1]),
                    'wine_spectator' => fake()->numberBetween($criticRange['parker'][0] - 2, $criticRange['parker'][1]),
                ];

                // Drinking window
                $drinkingStart = $vintage + fake()->numberBetween(3, 8);
                $drinkingEnd = $vintage + fake()->numberBetween(15, 35);

                // Production notes
                $productionNotes = $this->generateProductionNotes($wineMaster, $vintage);

                WineVariant::firstOrCreate(
                    [
                        'wine_master_id' => $wineMaster->id,
                        'vintage_year' => $vintage,
                    ],
                    [
                        'alcohol_percentage' => $alcohol,
                        'drinking_window_start' => $drinkingStart,
                        'drinking_window_end' => $drinkingEnd,
                        'critic_scores' => $criticScores,
                        'production_notes' => $productionNotes,
                        'lifecycle_status' => ProductLifecycleStatus::Published,
                        'data_source' => DataSource::Manual,
                        'lwin_code' => 'LWIN'.fake()->numerify('######'),
                        'internal_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $wineMaster->name), 0, 4)).'-'.$vintage,
                    ]
                );
            }
        }
    }

    /**
     * Get alcohol range based on wine name
     */
    private function getAlcoholRange(string $wineName, array $ranges): array
    {
        foreach ($ranges as $key => $range) {
            if ($key !== 'default' && str_contains($wineName, $key)) {
                return $range;
            }
        }

        return $ranges['default'];
    }

    /**
     * Generate production notes
     */
    private function generateProductionNotes(WineMaster $wineMaster, int $vintage): array
    {
        $conditions = [
            'excellent' => 'Exceptional growing season with ideal conditions throughout.',
            'very_good' => 'Very good vintage with optimal ripeness achieved.',
            'good' => 'Good vintage with balanced fruit and acidity.',
            'challenging' => 'Challenging vintage that required careful vineyard selection.',
        ];

        $harvestMethods = ['Hand-harvested', 'Hand-selected', 'Manually picked at optimal ripeness'];
        $fermentations = ['French oak barrels', 'Slavonian oak casks', 'Stainless steel tanks with subsequent oak aging'];
        $agingPeriods = ['18 months', '24 months', '30 months', '36 months', '48 months'];

        $condition = array_rand($conditions);

        return [
            'vintage_conditions' => $conditions[$condition],
            'harvest' => fake()->randomElement($harvestMethods),
            'fermentation' => 'Fermented in '.fake()->randomElement($fermentations),
            'aging' => fake()->randomElement($agingPeriods).' in oak',
            'production_bottles' => fake()->numberBetween(5000, 50000),
        ];
    }
}
