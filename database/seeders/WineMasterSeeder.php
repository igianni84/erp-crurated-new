<?php

namespace Database\Seeders;

use App\Models\Pim\WineMaster;
use Illuminate\Database\Seeder;

/**
 * WineMasterSeeder - Creates realistic Italian and French wine masters
 */
class WineMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wines = [
            // Piedmont - Barolo
            [
                'name' => 'Barolo Cannubi',
                'producer' => 'Giacomo Conterno',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Exceptional single-vineyard Barolo from the legendary Cannubi cru. Deep garnet color with notes of tar, roses, and dark cherries.',
                'liv_ex_code' => 'GCON-BAR-CAN',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 38,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Barolo Monfortino',
                'producer' => 'Giacomo Conterno',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG Riserva',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'The iconic Monfortino, produced only in exceptional vintages. One of Italy\'s most sought-after wines.',
                'liv_ex_code' => 'GCON-BAR-MON',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 62,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Barolo Falletto',
                'producer' => 'Bruno Giacosa',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Elegant and powerful Barolo from the Falletto vineyard in Serralunga d\'Alba.',
                'liv_ex_code' => 'BGIA-BAR-FAL',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 38,
                    'alcohol_min' => 13.0,
                ],
            ],
            // Piedmont - Barbaresco
            [
                'name' => 'Barbaresco Asili',
                'producer' => 'Bruno Giacosa',
                'appellation' => 'Barbaresco DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Outstanding Barbaresco from the prestigious Asili cru. Floral aromatics with red cherry and spice.',
                'liv_ex_code' => 'BGIA-BAR-ASI',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 26,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Barbaresco Sori Tildin',
                'producer' => 'Gaja',
                'appellation' => 'Langhe DOC',
                'classification' => 'DOC',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Iconic single-vineyard wine from Angelo Gaja. Complex, age-worthy, and elegant.',
                'liv_ex_code' => 'GAJA-LAN-STI',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 13.0,
                ],
            ],
            // Tuscany - Brunello
            [
                'name' => 'Brunello di Montalcino',
                'producer' => 'Biondi-Santi',
                'appellation' => 'Brunello di Montalcino DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'The original Brunello producer. Traditional style with exceptional aging potential.',
                'liv_ex_code' => 'BSAN-BRU-ANN',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese Grosso'],
                    'min_aging_months' => 48,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Brunello di Montalcino Riserva',
                'producer' => 'Biondi-Santi',
                'appellation' => 'Brunello di Montalcino DOCG',
                'classification' => 'DOCG Riserva',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Only produced in exceptional vintages. Legendary wines with 50+ years of aging potential.',
                'liv_ex_code' => 'BSAN-BRU-RIS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese Grosso'],
                    'min_aging_months' => 72,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Brunello di Montalcino Poggio alle Mura',
                'producer' => 'Castello Banfi',
                'appellation' => 'Brunello di Montalcino DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Single estate Brunello with modern elegance and rich fruit expression.',
                'liv_ex_code' => 'BANF-BRU-PAM',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese Grosso'],
                    'min_aging_months' => 48,
                    'alcohol_min' => 12.5,
                ],
            ],
            // Tuscany - Super Tuscans
            [
                'name' => 'Sassicaia',
                'producer' => 'Tenuta San Guido',
                'appellation' => 'Bolgheri Sassicaia DOC',
                'classification' => 'DOC',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'The original Super Tuscan. Bordeaux-style blend from the Bolgheri coast.',
                'liv_ex_code' => 'TSGD-SAS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Cabernet Franc'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 12.0,
                ],
            ],
            [
                'name' => 'Ornellaia',
                'producer' => 'Tenuta dell\'Ornellaia',
                'appellation' => 'Bolgheri DOC Superiore',
                'classification' => 'DOC Superiore',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Elegant Bordeaux-style blend. Complex and sophisticated with great aging potential.',
                'liv_ex_code' => 'TORN-ORN',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc', 'Petit Verdot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.0,
                ],
            ],
            [
                'name' => 'Tignanello',
                'producer' => 'Marchesi Antinori',
                'appellation' => 'Toscana IGT',
                'classification' => 'IGT',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Pioneering Super Tuscan blending Sangiovese with international varieties.',
                'liv_ex_code' => 'ANTI-TIG',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese', 'Cabernet Sauvignon', 'Cabernet Franc'],
                    'min_aging_months' => 14,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Solaia',
                'producer' => 'Marchesi Antinori',
                'appellation' => 'Toscana IGT',
                'classification' => 'IGT',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Antinori\'s flagship wine. Cabernet-dominant blend from the Tignanello estate.',
                'liv_ex_code' => 'ANTI-SOL',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Sangiovese', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.5,
                ],
            ],
            // Veneto - Amarone
            [
                'name' => 'Amarone della Valpolicella Classico',
                'producer' => 'Giuseppe Quintarelli',
                'appellation' => 'Amarone della Valpolicella DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Veneto',
                'description' => 'Legendary Amarone from the master of the appellation. Years of aging before release.',
                'liv_ex_code' => 'QUIN-AMA',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Corvina', 'Rondinella', 'Molinara'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 14.0,
                ],
            ],
            [
                'name' => 'Amarone della Valpolicella Classico',
                'producer' => 'Bertani',
                'appellation' => 'Amarone della Valpolicella DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Veneto',
                'description' => 'Historic producer with traditional style. Extended bottle aging before release.',
                'liv_ex_code' => 'BERT-AMA',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Corvina', 'Rondinella'],
                    'min_aging_months' => 48,
                    'alcohol_min' => 14.0,
                ],
            ],
            // Bordeaux - Left Bank
            [
                'name' => 'Chateau Margaux',
                'producer' => 'Chateau Margaux',
                'appellation' => 'Margaux AOC',
                'classification' => 'Premier Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'First Growth Bordeaux. Elegant and refined with exceptional balance.',
                'liv_ex_code' => 'CMRG',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc', 'Petit Verdot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Chateau Latour',
                'producer' => 'Chateau Latour',
                'appellation' => 'Pauillac AOC',
                'classification' => 'Premier Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'First Growth from Pauillac. Powerful, structured, and built to age.',
                'liv_ex_code' => 'CLAT',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            // Burgundy
            [
                'name' => 'Romanee-Conti Grand Cru',
                'producer' => 'Domaine de la Romanee-Conti',
                'appellation' => 'Romanee-Conti Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'The most prestigious wine in the world. Monopole Grand Cru.',
                'liv_ex_code' => 'DRC-RC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'La Tache Grand Cru',
                'producer' => 'Domaine de la Romanee-Conti',
                'appellation' => 'La Tache Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'Monopole Grand Cru from DRC. Complex, silky, and age-worthy.',
                'liv_ex_code' => 'DRC-LT',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Musigny Grand Cru',
                'producer' => 'Domaine Georges de Vogue',
                'appellation' => 'Musigny Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'Legendary producer in Chambolle-Musigny. Ethereal and elegant.',
                'liv_ex_code' => 'VOGUE-MUS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
        ];

        foreach ($wines as $wineData) {
            WineMaster::firstOrCreate(
                [
                    'name' => $wineData['name'],
                    'producer' => $wineData['producer'],
                ],
                $wineData
            );
        }
    }
}
