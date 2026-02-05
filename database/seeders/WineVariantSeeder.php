<?php

namespace Database\Seeders;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Illuminate\Database\Seeder;

/**
 * WineVariantSeeder - Creates wine variants with realistic vintages
 *
 * Creates variants across all lifecycle statuses:
 * - Draft: Newly entered, not yet reviewed
 * - In Review: Being validated by product team
 * - Approved: Ready to publish
 * - Published: Available for sale
 * - Archived: No longer active
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
            // Premium wines - multiple vintages including older ones
            'Barolo Monfortino' => [2010, 2013, 2015, 2016, 2017],
            'Brunello di Montalcino Riserva' => [2010, 2012, 2015, 2016],
            'Romanee-Conti Grand Cru' => [2015, 2016, 2017, 2018, 2019],
            'La Tache Grand Cru' => [2015, 2016, 2017, 2018, 2019],
            'Richebourg Grand Cru' => [2016, 2017, 2018, 2019],
            'Chateau Margaux' => [2009, 2010, 2015, 2016, 2018, 2019, 2020],
            'Chateau Latour' => [2009, 2010, 2015, 2016, 2018, 2019],
            'Chateau Lafite Rothschild' => [2009, 2010, 2015, 2016, 2018, 2019, 2020],
            'Chateau Mouton Rothschild' => [2010, 2015, 2016, 2018, 2019, 2020],
            'Chateau Haut-Brion' => [2009, 2010, 2015, 2016, 2018, 2019],
            'Petrus' => [2010, 2015, 2016, 2018, 2019],
            'Le Pin' => [2015, 2016, 2018, 2019],
            'Chateau Cheval Blanc' => [2010, 2015, 2016, 2018, 2019],
            'Chateau Ausone' => [2010, 2015, 2016, 2018, 2019],
            'Sassicaia' => [2015, 2016, 2017, 2018, 2019, 2020],
            'Ornellaia' => [2016, 2017, 2018, 2019, 2020, 2021],
            'Masseto' => [2016, 2017, 2018, 2019, 2020],

            // Standard wines - more recent vintages
            'Barolo Cannubi' => [2016, 2017, 2018, 2019, 2020],
            'Barolo Falletto' => [2016, 2017, 2018, 2019, 2020],
            'Barolo Brunate' => [2017, 2018, 2019, 2020],
            'Barolo Rocche dell\'Annunziata' => [2017, 2018, 2019, 2020],
            'Barolo Bussia' => [2016, 2017, 2018, 2019, 2020],
            'Barbaresco Asili' => [2017, 2018, 2019, 2020, 2021],
            'Barbaresco Sori Tildin' => [2017, 2018, 2019, 2020],
            'Barbaresco Sori San Lorenzo' => [2017, 2018, 2019, 2020],
            'Barbaresco Rabaja' => [2018, 2019, 2020, 2021],
            'Brunello di Montalcino' => [2016, 2017, 2018, 2019, 2020],
            'Brunello di Montalcino Poggio alle Mura' => [2016, 2017, 2018, 2019],
            'Brunello di Montalcino Cerretalto' => [2016, 2017, 2018, 2019],
            'Brunello di Montalcino Madonna delle Grazie' => [2016, 2017, 2018, 2019],
            'Tignanello' => [2017, 2018, 2019, 2020, 2021],
            'Solaia' => [2017, 2018, 2019, 2020],
            'Guado al Tasso' => [2018, 2019, 2020, 2021],
            'Flaccianello della Pieve' => [2017, 2018, 2019, 2020],
            'Chianti Classico Gran Selezione' => [2018, 2019, 2020, 2021],
            'Chianti Classico Riserva' => [2018, 2019, 2020, 2021],
            'Amarone della Valpolicella Classico' => [2011, 2012, 2015, 2016, 2017, 2018],
            'Amarone della Valpolicella' => [2016, 2017, 2018, 2019],
            'Amarone della Valpolicella TB' => [2013, 2015, 2016, 2017],
            'Musigny Grand Cru' => [2016, 2017, 2018, 2019],
            'Chambertin Grand Cru' => [2016, 2017, 2018, 2019],
            'Clos de la Roche Grand Cru' => [2017, 2018, 2019, 2020],
            'Montrachet Grand Cru' => [2017, 2018, 2019, 2020],
            'Corton-Charlemagne Grand Cru' => [2017, 2018, 2019, 2020],
            'Chateau Leoville Las Cases' => [2015, 2016, 2018, 2019, 2020],
            'Chateau Cos d\'Estournel' => [2015, 2016, 2018, 2019, 2020],
            'Dom Perignon' => [2008, 2010, 2012, 2013, 2015],
            'Cristal' => [2008, 2012, 2013, 2014, 2015],
            'Krug Grande Cuvee' => [2008, 2010, 2012],
            'Salon Le Mesnil' => [2007, 2008, 2012],
            'Chateauneuf-du-Pape Hommage a Jacques Perrin' => [2015, 2016, 2017, 2019],
            'Hermitage La Chapelle' => [2015, 2016, 2017, 2018, 2019],
            'Cote Rotie La Landonne' => [2015, 2016, 2017, 2018, 2019],
        ];

        // Critic scores for realistic data
        $criticScoreRanges = [
            'Barolo Monfortino' => ['parker' => [97, 100], 'jancis' => [18.5, 19.5]],
            'Romanee-Conti Grand Cru' => ['parker' => [98, 100], 'jancis' => [19.0, 20.0]],
            'La Tache Grand Cru' => ['parker' => [96, 99], 'jancis' => [18.5, 19.5]],
            'Richebourg Grand Cru' => ['parker' => [95, 99], 'jancis' => [18.0, 19.5]],
            'Chateau Margaux' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Chateau Latour' => ['parker' => [95, 99], 'jancis' => [18.0, 19.0]],
            'Chateau Lafite Rothschild' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Chateau Mouton Rothschild' => ['parker' => [94, 100], 'jancis' => [17.5, 19.0]],
            'Chateau Haut-Brion' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Petrus' => ['parker' => [97, 100], 'jancis' => [18.5, 20.0]],
            'Le Pin' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Chateau Cheval Blanc' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Chateau Ausone' => ['parker' => [95, 100], 'jancis' => [18.0, 19.5]],
            'Masseto' => ['parker' => [95, 100], 'jancis' => [18.0, 19.0]],
            'Sassicaia' => ['parker' => [94, 98], 'jancis' => [17.5, 19.0]],
            'Ornellaia' => ['parker' => [93, 97], 'jancis' => [17.5, 18.5]],
            'Musigny Grand Cru' => ['parker' => [95, 99], 'jancis' => [18.0, 19.5]],
            'Chambertin Grand Cru' => ['parker' => [95, 99], 'jancis' => [18.0, 19.5]],
            'Montrachet Grand Cru' => ['parker' => [96, 100], 'jancis' => [18.5, 20.0]],
            'Dom Perignon' => ['parker' => [94, 98], 'jancis' => [18.0, 19.0]],
            'Cristal' => ['parker' => [95, 99], 'jancis' => [18.0, 19.5]],
            'Krug Grande Cuvee' => ['parker' => [94, 97], 'jancis' => [18.0, 19.0]],
            'default' => ['parker' => [90, 96], 'jancis' => [16.5, 18.0]],
        ];

        // Alcohol percentages by wine type
        $alcoholRanges = [
            'Amarone' => [15.0, 16.5],
            'Barolo' => [13.5, 14.5],
            'Barbaresco' => [13.5, 14.5],
            'Brunello' => [13.5, 14.5],
            'Chateau' => [13.0, 14.0],
            'Champagne' => [12.0, 12.5],
            'Dom Perignon' => [12.0, 12.5],
            'Cristal' => [12.0, 12.5],
            'Krug' => [12.0, 12.5],
            'Salon' => [12.0, 12.5],
            'Petrus' => [13.5, 14.5],
            'default' => [13.0, 14.5],
        ];

        // Data sources distribution
        $dataSources = [
            DataSource::Manual,
            DataSource::Manual,
            DataSource::Manual,
            DataSource::LivEx,
        ];

        $totalCreated = 0;

        foreach ($vintageConfigs as $wineName => $vintages) {
            $wineMaster = WineMaster::where('name', $wineName)->first();

            if (! $wineMaster) {
                continue;
            }

            foreach ($vintages as $index => $vintage) {
                // Determine alcohol range
                $alcoholRange = $this->getAlcoholRange($wineName, $alcoholRanges);
                $alcohol = fake()->randomFloat(1, $alcoholRange[0], $alcoholRange[1]);

                // Determine critic scores
                $criticRange = $criticScoreRanges[$wineName] ?? $criticScoreRanges['default'];
                $criticScores = [
                    'robert_parker' => fake()->numberBetween($criticRange['parker'][0], $criticRange['parker'][1]),
                    'jancis_robinson' => fake()->randomFloat(1, $criticRange['jancis'][0], $criticRange['jancis'][1]),
                    'wine_spectator' => fake()->numberBetween($criticRange['parker'][0] - 2, $criticRange['parker'][1]),
                    'decanter' => fake()->numberBetween($criticRange['parker'][0] - 1, $criticRange['parker'][1]),
                ];

                // Drinking window
                $drinkingStart = $vintage + fake()->numberBetween(3, 8);
                $drinkingEnd = $vintage + fake()->numberBetween(15, 35);

                // Production notes
                $productionNotes = $this->generateProductionNotes($wineMaster, $vintage);

                // Determine lifecycle status with distribution:
                // 70% Published (most variants should be sellable)
                // 10% Draft (new entries)
                // 10% In Review (being validated)
                // 5% Approved (ready to publish)
                // 5% Archived (old vintages)
                $statusRandom = fake()->numberBetween(1, 100);
                $lifecycleStatus = match (true) {
                    $statusRandom <= 70 => ProductLifecycleStatus::Published,
                    $statusRandom <= 80 => ProductLifecycleStatus::Draft,
                    $statusRandom <= 90 => ProductLifecycleStatus::InReview,
                    $statusRandom <= 95 => ProductLifecycleStatus::Approved,
                    default => ProductLifecycleStatus::Archived,
                };

                // Older vintages more likely to be archived
                if ($vintage < 2015 && fake()->boolean(30)) {
                    $lifecycleStatus = ProductLifecycleStatus::Archived;
                }

                // Recent vintages (2021+) more likely to be in review/draft
                if ($vintage >= 2021 && fake()->boolean(40)) {
                    $lifecycleStatus = fake()->randomElement([
                        ProductLifecycleStatus::Draft,
                        ProductLifecycleStatus::InReview,
                    ]);
                }

                // Data source
                $dataSource = fake()->randomElement($dataSources);

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
                        'lifecycle_status' => $lifecycleStatus,
                        'data_source' => $dataSource,
                        'lwin_code' => 'LWIN'.fake()->numerify('######'),
                        'internal_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $wineMaster->name), 0, 4)).'-'.$vintage,
                    ]
                );

                $totalCreated++;
            }
        }

        // Also create variants for wines without specific vintage configs
        $allWineMasters = WineMaster::whereNotIn('name', array_keys($vintageConfigs))->get();

        foreach ($allWineMasters as $wineMaster) {
            // Create 2-4 vintages for each wine
            $numVintages = fake()->numberBetween(2, 4);
            $vintages = [];

            for ($i = 0; $i < $numVintages; $i++) {
                $vintages[] = fake()->numberBetween(2017, 2021);
            }

            $vintages = array_unique($vintages);

            foreach ($vintages as $vintage) {
                $alcoholRange = $this->getAlcoholRange($wineMaster->name, $alcoholRanges);
                $alcohol = fake()->randomFloat(1, $alcoholRange[0], $alcoholRange[1]);

                $criticRange = $criticScoreRanges['default'];
                $criticScores = [
                    'robert_parker' => fake()->numberBetween($criticRange['parker'][0], $criticRange['parker'][1]),
                    'jancis_robinson' => fake()->randomFloat(1, $criticRange['jancis'][0], $criticRange['jancis'][1]),
                    'wine_spectator' => fake()->numberBetween($criticRange['parker'][0] - 2, $criticRange['parker'][1]),
                ];

                $drinkingStart = $vintage + fake()->numberBetween(3, 8);
                $drinkingEnd = $vintage + fake()->numberBetween(12, 25);

                $productionNotes = $this->generateProductionNotes($wineMaster, $vintage);

                // Status distribution for additional wines
                $statusRandom = fake()->numberBetween(1, 100);
                $lifecycleStatus = match (true) {
                    $statusRandom <= 75 => ProductLifecycleStatus::Published,
                    $statusRandom <= 85 => ProductLifecycleStatus::Draft,
                    $statusRandom <= 95 => ProductLifecycleStatus::InReview,
                    default => ProductLifecycleStatus::Approved,
                };

                $dataSource = fake()->randomElement($dataSources);

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
                        'lifecycle_status' => $lifecycleStatus,
                        'data_source' => $dataSource,
                        'lwin_code' => 'LWIN'.fake()->numerify('######'),
                        'internal_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $wineMaster->name), 0, 4)).'-'.$vintage,
                    ]
                );

                $totalCreated++;
            }
        }

        $this->command->info("Created {$totalCreated} wine variants.");
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
            'excellent' => 'Exceptional growing season with ideal conditions throughout. Warm days and cool nights led to perfect ripeness.',
            'very_good' => 'Very good vintage with optimal ripeness achieved. Balanced rainfall and sunshine.',
            'good' => 'Good vintage with balanced fruit and acidity. Some challenges overcome with careful viticulture.',
            'challenging' => 'Challenging vintage that required careful vineyard selection. Only the best parcels were used.',
            'classic' => 'Classic conditions with traditional character. Extended hang time for full phenolic maturity.',
        ];

        $harvestMethods = [
            'Hand-harvested at dawn to preserve freshness',
            'Hand-selected cluster by cluster',
            'Manually picked at optimal ripeness, with multiple passes through vineyard',
            'Hand-harvested and sorted in field and cellar',
            'Triple sorting: in vineyard, at reception, and on vibrating table',
        ];

        $fermentations = [
            'Temperature-controlled fermentation in French oak barrels',
            'Fermented in temperature-controlled Slavonian oak casks',
            'Stainless steel fermentation with indigenous yeasts, then transferred to oak',
            'Wild yeast fermentation in new French barriques',
            'Temperature-controlled in concrete eggs, then aged in oak',
        ];

        $agingPeriods = ['18 months', '24 months', '30 months', '36 months', '48 months'];
        $oakTypes = ['new French oak', 'French oak (50% new)', 'Slavonian oak', 'French and Austrian oak', 'large Slavonian casks'];

        $condition = array_rand($conditions);

        return [
            'vintage_conditions' => $conditions[$condition],
            'harvest' => fake()->randomElement($harvestMethods),
            'fermentation' => fake()->randomElement($fermentations),
            'aging' => fake()->randomElement($agingPeriods).' in '.fake()->randomElement($oakTypes),
            'production_bottles' => fake()->numberBetween(3000, 80000),
            'release_date' => fake()->dateTimeBetween("{$vintage}-01-01", ($vintage + 4).'-12-31')->format('Y-m'),
        ];
    }
}
