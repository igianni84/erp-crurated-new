<?php

namespace Database\Seeders;

use App\Models\Pim\WineMaster;
use Illuminate\Database\Seeder;

/**
 * WineMasterSeeder - Creates comprehensive Italian and French wine masters
 *
 * Includes wines from:
 * - Piedmont (Barolo, Barbaresco)
 * - Tuscany (Brunello, Super Tuscans, Chianti)
 * - Veneto (Amarone, Valpolicella)
 * - Bordeaux (Left Bank, Right Bank)
 * - Burgundy (Grand Crus, Premier Crus)
 * - Champagne
 * - Rhône Valley
 */
class WineMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wines = [
            // =========================================================================
            // PIEDMONT - BAROLO
            // =========================================================================
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
            [
                'name' => 'Barolo Brunate',
                'producer' => 'Roberto Voerzio',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Single-vineyard Barolo from the famous Brunate cru in La Morra. Rich and concentrated.',
                'liv_ex_code' => 'RVOR-BAR-BRU',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 38,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Barolo Rocche dell\'Annunziata',
                'producer' => 'Paolo Scavino',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Premier cru Barolo with great finesse. Floral bouquet and silky tannins.',
                'liv_ex_code' => 'PSCA-BAR-ROC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 38,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Barolo Bussia',
                'producer' => 'Aldo Conterno',
                'appellation' => 'Barolo DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Classic Barolo from the Bussia cru in Monforte d\'Alba. Powerful and age-worthy.',
                'liv_ex_code' => 'ACON-BAR-BUS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 38,
                    'alcohol_min' => 13.0,
                ],
            ],

            // =========================================================================
            // PIEDMONT - BARBARESCO
            // =========================================================================
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
            [
                'name' => 'Barbaresco Sori San Lorenzo',
                'producer' => 'Gaja',
                'appellation' => 'Langhe DOC',
                'classification' => 'DOC',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Another iconic single-vineyard from Gaja. Powerful yet refined.',
                'liv_ex_code' => 'GAJA-LAN-SSL',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Barbaresco Rabaja',
                'producer' => 'Bruno Rocca',
                'appellation' => 'Barbaresco DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Piedmont',
                'description' => 'Elegant Barbaresco from the Rabaja vineyard. Perfumed and refined.',
                'liv_ex_code' => 'BROC-BAR-RAB',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Nebbiolo'],
                    'min_aging_months' => 26,
                    'alcohol_min' => 12.5,
                ],
            ],

            // =========================================================================
            // TUSCANY - BRUNELLO DI MONTALCINO
            // =========================================================================
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
            [
                'name' => 'Brunello di Montalcino Cerretalto',
                'producer' => 'Casanova di Neri',
                'appellation' => 'Brunello di Montalcino DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Single-vineyard Brunello. Rich, concentrated, and modern in style.',
                'liv_ex_code' => 'CNER-BRU-CER',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese Grosso'],
                    'min_aging_months' => 48,
                    'alcohol_min' => 14.0,
                ],
            ],
            [
                'name' => 'Brunello di Montalcino Madonna delle Grazie',
                'producer' => 'Il Marroneto',
                'appellation' => 'Brunello di Montalcino DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Small production Brunello from high-altitude vineyards. Elegant and complex.',
                'liv_ex_code' => 'IMAR-BRU-MDG',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese Grosso'],
                    'min_aging_months' => 48,
                    'alcohol_min' => 13.5,
                ],
            ],

            // =========================================================================
            // TUSCANY - SUPER TUSCANS
            // =========================================================================
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
                'name' => 'Masseto',
                'producer' => 'Tenuta dell\'Ornellaia',
                'appellation' => 'Toscana IGT',
                'classification' => 'IGT',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Italy\'s most celebrated Merlot. Rich, opulent, and highly sought after.',
                'liv_ex_code' => 'TORN-MAS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Merlot'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 14.0,
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
            [
                'name' => 'Guado al Tasso',
                'producer' => 'Marchesi Antinori',
                'appellation' => 'Bolgheri DOC Superiore',
                'classification' => 'DOC Superiore',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Antinori\'s Bolgheri estate flagship. Elegant Bordeaux-style blend.',
                'liv_ex_code' => 'ANTI-GAT',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.5,
                ],
            ],
            [
                'name' => 'Flaccianello della Pieve',
                'producer' => 'Fontodi',
                'appellation' => 'Toscana IGT',
                'classification' => 'IGT',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Pure Sangiovese from the heart of Chianti Classico. Powerful and age-worthy.',
                'liv_ex_code' => 'FONT-FLA',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 14.0,
                ],
            ],

            // =========================================================================
            // TUSCANY - CHIANTI CLASSICO
            // =========================================================================
            [
                'name' => 'Chianti Classico Gran Selezione',
                'producer' => 'Castello di Ama',
                'appellation' => 'Chianti Classico DOCG',
                'classification' => 'Gran Selezione',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Top-tier Chianti Classico from single vineyards. Elegant and refined.',
                'liv_ex_code' => 'CAMA-CHI-GS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese', 'Merlot'],
                    'min_aging_months' => 30,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Chianti Classico Riserva',
                'producer' => 'Felsina',
                'appellation' => 'Chianti Classico DOCG',
                'classification' => 'Riserva',
                'country' => 'Italy',
                'region' => 'Tuscany',
                'description' => 'Classic Chianti Riserva with traditional character. Balanced and food-friendly.',
                'liv_ex_code' => 'FELS-CHI-RIS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Sangiovese'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 12.5,
                ],
            ],

            // =========================================================================
            // VENETO - AMARONE
            // =========================================================================
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
            [
                'name' => 'Amarone della Valpolicella',
                'producer' => 'Allegrini',
                'appellation' => 'Amarone della Valpolicella DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Veneto',
                'description' => 'Modern style Amarone with concentrated fruit and polished tannins.',
                'liv_ex_code' => 'ALLE-AMA',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Corvina', 'Rondinella', 'Oseleta'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 15.0,
                ],
            ],
            [
                'name' => 'Amarone della Valpolicella TB',
                'producer' => 'Dal Forno Romano',
                'appellation' => 'Amarone della Valpolicella DOCG',
                'classification' => 'DOCG',
                'country' => 'Italy',
                'region' => 'Veneto',
                'description' => 'Cult Amarone with extreme concentration. Limited production.',
                'liv_ex_code' => 'DALF-AMA-TB',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Corvina', 'Rondinella', 'Croatina', 'Oseleta'],
                    'min_aging_months' => 36,
                    'alcohol_min' => 16.0,
                ],
            ],

            // =========================================================================
            // BORDEAUX - LEFT BANK (Médoc)
            // =========================================================================
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
            [
                'name' => 'Chateau Lafite Rothschild',
                'producer' => 'Chateau Lafite Rothschild',
                'appellation' => 'Pauillac AOC',
                'classification' => 'Premier Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'First Growth with legendary elegance. Complex aromatics and silky texture.',
                'liv_ex_code' => 'CLAF',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc', 'Petit Verdot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Chateau Mouton Rothschild',
                'producer' => 'Chateau Mouton Rothschild',
                'appellation' => 'Pauillac AOC',
                'classification' => 'Premier Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'First Growth known for opulence and rich fruit. Famous artistic labels.',
                'liv_ex_code' => 'CMOU',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Chateau Haut-Brion',
                'producer' => 'Chateau Haut-Brion',
                'appellation' => 'Pessac-Leognan AOC',
                'classification' => 'Premier Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'The oldest First Growth. Unique smoky minerality from Graves terroir.',
                'liv_ex_code' => 'CHBR',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Merlot', 'Cabernet Sauvignon', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Chateau Leoville Las Cases',
                'producer' => 'Chateau Leoville Las Cases',
                'appellation' => 'Saint-Julien AOC',
                'classification' => 'Deuxieme Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'Super Second often compared to First Growths. Deep and age-worthy.',
                'liv_ex_code' => 'CLLC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],
            [
                'name' => 'Chateau Cos d\'Estournel',
                'producer' => 'Chateau Cos d\'Estournel',
                'appellation' => 'Saint-Estephe AOC',
                'classification' => 'Deuxieme Grand Cru Classe',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'Super Second with exotic, spicy character. Distinctive pagoda-topped chateau.',
                'liv_ex_code' => 'CCOS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Sauvignon', 'Merlot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 12.5,
                ],
            ],

            // =========================================================================
            // BORDEAUX - RIGHT BANK (Saint-Emilion & Pomerol)
            // =========================================================================
            [
                'name' => 'Petrus',
                'producer' => 'Petrus',
                'appellation' => 'Pomerol AOC',
                'classification' => 'Pomerol',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'The most prestigious Pomerol. Almost pure Merlot from clay soils.',
                'liv_ex_code' => 'PETR',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Merlot', 'Cabernet Franc'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Le Pin',
                'producer' => 'Le Pin',
                'appellation' => 'Pomerol AOC',
                'classification' => 'Pomerol',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'Cult Pomerol with tiny production. Voluptuous and hedonistic.',
                'liv_ex_code' => 'LPIN',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Merlot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.5,
                ],
            ],
            [
                'name' => 'Chateau Cheval Blanc',
                'producer' => 'Chateau Cheval Blanc',
                'appellation' => 'Saint-Emilion Grand Cru',
                'classification' => 'Premier Grand Cru Classe A',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'One of only four Premier Grand Cru Classe A. Unique Cabernet Franc character.',
                'liv_ex_code' => 'CCBL',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Franc', 'Merlot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Chateau Ausone',
                'producer' => 'Chateau Ausone',
                'appellation' => 'Saint-Emilion Grand Cru',
                'classification' => 'Premier Grand Cru Classe A',
                'country' => 'France',
                'region' => 'Bordeaux',
                'description' => 'Historic estate with exceptional terroir. Elegant and minerally.',
                'liv_ex_code' => 'CAUS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Cabernet Franc', 'Merlot'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],

            // =========================================================================
            // BURGUNDY - COTE DE NUITS (Red)
            // =========================================================================
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
                'name' => 'Richebourg Grand Cru',
                'producer' => 'Domaine de la Romanee-Conti',
                'appellation' => 'Richebourg Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'Powerful Grand Cru from DRC. Rich and opulent.',
                'liv_ex_code' => 'DRC-RIC',
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
            [
                'name' => 'Chambertin Grand Cru',
                'producer' => 'Domaine Armand Rousseau',
                'appellation' => 'Chambertin Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'King of wines. The finest expression of Gevrey-Chambertin.',
                'liv_ex_code' => 'ROUS-CHM',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Clos de la Roche Grand Cru',
                'producer' => 'Domaine Dujac',
                'appellation' => 'Clos de la Roche Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'Signature wine of Domaine Dujac. Pure and perfumed.',
                'liv_ex_code' => 'DUJA-CLR',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],

            // =========================================================================
            // BURGUNDY - COTE DE BEAUNE (White)
            // =========================================================================
            [
                'name' => 'Montrachet Grand Cru',
                'producer' => 'Domaine de la Romanee-Conti',
                'appellation' => 'Montrachet Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'The greatest white wine in the world. Exceptional power and complexity.',
                'liv_ex_code' => 'DRC-MON',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Chardonnay'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Corton-Charlemagne Grand Cru',
                'producer' => 'Domaine Coche-Dury',
                'appellation' => 'Corton-Charlemagne Grand Cru',
                'classification' => 'Grand Cru',
                'country' => 'France',
                'region' => 'Burgundy',
                'description' => 'Legendary white Burgundy. Mineral and powerful.',
                'liv_ex_code' => 'COCH-CC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Chardonnay'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],

            // =========================================================================
            // CHAMPAGNE
            // =========================================================================
            [
                'name' => 'Dom Perignon',
                'producer' => 'Moet & Chandon',
                'appellation' => 'Champagne AOC',
                'classification' => 'Prestige Cuvee',
                'country' => 'France',
                'region' => 'Champagne',
                'description' => 'The original prestige cuvee. Elegant and complex with extended aging.',
                'liv_ex_code' => 'DPER',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Chardonnay', 'Pinot Noir'],
                    'min_aging_months' => 84,
                    'alcohol_min' => 12.0,
                ],
            ],
            [
                'name' => 'Cristal',
                'producer' => 'Louis Roederer',
                'appellation' => 'Champagne AOC',
                'classification' => 'Prestige Cuvee',
                'country' => 'France',
                'region' => 'Champagne',
                'description' => 'Iconic prestige Champagne. Pure, precise, and mineral-driven.',
                'liv_ex_code' => 'CRIS',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir', 'Chardonnay'],
                    'min_aging_months' => 72,
                    'alcohol_min' => 12.0,
                ],
            ],
            [
                'name' => 'Krug Grande Cuvee',
                'producer' => 'Krug',
                'appellation' => 'Champagne AOC',
                'classification' => 'Prestige Cuvee',
                'country' => 'France',
                'region' => 'Champagne',
                'description' => 'Multi-vintage prestige cuvee. Rich and complex with notes of toast and brioche.',
                'liv_ex_code' => 'KRUG-GC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Pinot Noir', 'Chardonnay', 'Pinot Meunier'],
                    'min_aging_months' => 72,
                    'alcohol_min' => 12.0,
                ],
            ],
            [
                'name' => 'Salon Le Mesnil',
                'producer' => 'Salon',
                'appellation' => 'Champagne AOC',
                'classification' => 'Blanc de Blancs',
                'country' => 'France',
                'region' => 'Champagne',
                'description' => 'Single vineyard, single vintage, single grape. Ultimate purity.',
                'liv_ex_code' => 'SALO',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Chardonnay'],
                    'min_aging_months' => 120,
                    'alcohol_min' => 12.0,
                ],
            ],

            // =========================================================================
            // RHONE VALLEY
            // =========================================================================
            [
                'name' => 'Chateauneuf-du-Pape Hommage a Jacques Perrin',
                'producer' => 'Chateau de Beaucastel',
                'appellation' => 'Chateauneuf-du-Pape AOC',
                'classification' => 'AOC',
                'country' => 'France',
                'region' => 'Rhone Valley',
                'description' => 'Tribute cuvee. Old vine Mourvèdre with incredible concentration.',
                'liv_ex_code' => 'BEAU-HJP',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Mourvedre', 'Grenache', 'Syrah', 'Counoise'],
                    'min_aging_months' => 24,
                    'alcohol_min' => 14.5,
                ],
            ],
            [
                'name' => 'Hermitage La Chapelle',
                'producer' => 'Paul Jaboulet Aine',
                'appellation' => 'Hermitage AOC',
                'classification' => 'AOC',
                'country' => 'France',
                'region' => 'Rhone Valley',
                'description' => 'Legendary Northern Rhône Syrah. Powerful and age-worthy.',
                'liv_ex_code' => 'JABO-LAC',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Syrah'],
                    'min_aging_months' => 18,
                    'alcohol_min' => 13.0,
                ],
            ],
            [
                'name' => 'Cote Rotie La Landonne',
                'producer' => 'E. Guigal',
                'appellation' => 'Cote-Rotie AOC',
                'classification' => 'AOC',
                'country' => 'France',
                'region' => 'Rhone Valley',
                'description' => 'One of the legendary La-La wines. Dark and powerful Syrah.',
                'liv_ex_code' => 'GUIG-LAN',
                'regulatory_attributes' => [
                    'grape_varieties' => ['Syrah'],
                    'min_aging_months' => 42,
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

        $this->command->info('Created '.count($wines).' wine masters.');
    }
}
