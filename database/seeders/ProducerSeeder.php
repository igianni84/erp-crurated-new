<?php

namespace Database\Seeders;

use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use Illuminate\Database\Seeder;

/**
 * ProducerSeeder - Creates wine producers for fine wine trading
 *
 * Comprehensive coverage of Tier 1 + Tier 2 producers across all
 * major wine-producing countries. Includes Liv-ex Power 100 level
 * producers from France, Italy, Spain, USA, Australia, and more.
 */
class ProducerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $count = 0;

        // =================================================================
        // Pre-load countries and regions
        // =================================================================
        $countries = Country::all()->keyBy('iso_code');
        $regionsByKey = [];
        foreach (Region::all() as $region) {
            $regionsByKey[$region->name.'|'.$region->country_id] = $region;
        }

        $r = function (string $name, ?Country $country) use ($regionsByKey): ?string {
            if ($country === null) {
                return null;
            }

            return ($regionsByKey[$name.'|'.$country->id] ?? null)?->id;
        };

        $italy = $countries->get('IT');
        $france = $countries->get('FR');
        $spain = $countries->get('ES');
        $portugal = $countries->get('PT');
        $germany = $countries->get('DE');
        $austria = $countries->get('AT');
        $us = $countries->get('US');
        $australia = $countries->get('AU');
        $nz = $countries->get('NZ');
        $za = $countries->get('ZA');
        $argentina = $countries->get('AR');
        $chile = $countries->get('CL');
        $lebanon = $countries->get('LB');

        if ($italy === null || $france === null) {
            $this->command->warn('Required countries (IT, FR) not found. Run CountrySeeder first.');

            return;
        }

        $producers = [];

        // =================================================================
        // ITALY - Piedmont
        // =================================================================
        $producers[] = ['name' => 'Giacomo Conterno', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Bruno Giacosa', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Roberto Voerzio', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Paolo Scavino', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Aldo Conterno', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Gaja', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Bruno Rocca', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Bartolo Mascarello', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Giuseppe Mascarello', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Luciano Sandrone', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Vietti', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];
        $producers[] = ['name' => 'Ceretto', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy)];

        // =================================================================
        // ITALY - Tuscany
        // =================================================================
        $producers[] = ['name' => 'Biondi-Santi', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Castello Banfi', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Casanova di Neri', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Il Marroneto', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Tenuta San Guido', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy)];
        $producers[] = ['name' => 'Tenuta dell\'Ornellaia', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy)];
        $producers[] = ['name' => 'Marchesi Antinori', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Fontodi', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy)];
        $producers[] = ['name' => 'Castello di Ama', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy)];
        $producers[] = ['name' => 'Felsina', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy)];
        $producers[] = ['name' => 'Soldera', 'country_id' => $italy->id, 'region_id' => $r('Montalcino', $italy)];
        $producers[] = ['name' => 'Montevertine', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy)];
        $producers[] = ['name' => 'Isole e Olena', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy)];

        // =================================================================
        // ITALY - Veneto
        // =================================================================
        $producers[] = ['name' => 'Giuseppe Quintarelli', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy)];
        $producers[] = ['name' => 'Bertani', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy)];
        $producers[] = ['name' => 'Allegrini', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy)];
        $producers[] = ['name' => 'Dal Forno Romano', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy)];

        // =================================================================
        // ITALY - Other regions
        // =================================================================
        $producers[] = ['name' => 'Valentini', 'country_id' => $italy->id, 'region_id' => $r('Abruzzo', $italy)];
        $producers[] = ['name' => 'Mastroberardino', 'country_id' => $italy->id, 'region_id' => $r('Campania', $italy)];
        $producers[] = ['name' => 'Planeta', 'country_id' => $italy->id, 'region_id' => $r('Sicily', $italy)];

        // =================================================================
        // FRANCE - Bordeaux
        // =================================================================
        $producers[] = ['name' => 'Chateau Margaux', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Latour', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Lafite Rothschild', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Mouton Rothschild', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Haut-Brion', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Leoville Las Cases', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Cos d\'Estournel', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Petrus', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Le Pin', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Cheval Blanc', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Ausone', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Palmer', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Lynch-Bages', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Pichon Baron', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Pichon Lalande', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Ducru-Beaucaillou', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau d\'Yquem', 'country_id' => $france->id, 'region_id' => $r('Sauternes', $france)];
        $producers[] = ['name' => 'Chateau Pontet-Canet', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau La Mission Haut-Brion', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Vieux Chateau Certan', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Angelus', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];
        $producers[] = ['name' => 'Chateau Figeac', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france)];

        // =================================================================
        // FRANCE - Burgundy
        // =================================================================
        $producers[] = ['name' => 'Domaine de la Romanee-Conti', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Georges de Vogue', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Armand Rousseau', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Dujac', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Coche-Dury', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Leroy', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Leflaive', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine des Comtes Lafon', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Roumier', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Ponsot', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Mugnier', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Raveneau', 'country_id' => $france->id, 'region_id' => $r('Chablis', $france)];
        $producers[] = ['name' => 'Maison Joseph Drouhin', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];
        $producers[] = ['name' => 'Domaine Roulot', 'country_id' => $france->id, 'region_id' => $r('Burgundy', $france)];

        // =================================================================
        // FRANCE - Champagne
        // =================================================================
        $producers[] = ['name' => 'Moet & Chandon', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Louis Roederer', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Krug', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Salon', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Bollinger', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Taittinger', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Pol Roger', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Veuve Clicquot', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Jacques Selosse', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Egly-Ouriet', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];
        $producers[] = ['name' => 'Ruinart', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france)];

        // =================================================================
        // FRANCE - Rhone Valley
        // =================================================================
        $producers[] = ['name' => 'Chateau de Beaucastel', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'Paul Jaboulet Aine', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'E. Guigal', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'Jean-Louis Chave', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'Auguste Clape', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'Rene Rostaing', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];
        $producers[] = ['name' => 'Chateau Rayas', 'country_id' => $france->id, 'region_id' => $r('Rhone Valley', $france)];

        // =================================================================
        // FRANCE - Loire Valley
        // =================================================================
        $producers[] = ['name' => 'Domaine Huet', 'country_id' => $france->id, 'region_id' => $r('Loire Valley', $france)];
        $producers[] = ['name' => 'Didier Dagueneau', 'country_id' => $france->id, 'region_id' => $r('Loire Valley', $france)];

        // =================================================================
        // FRANCE - Alsace
        // =================================================================
        $producers[] = ['name' => 'Domaine Zind-Humbrecht', 'country_id' => $france->id, 'region_id' => $r('Alsace', $france)];

        // =================================================================
        // SPAIN
        // =================================================================
        if ($spain !== null) {
            $producers[] = ['name' => 'Vega Sicilia', 'country_id' => $spain->id, 'region_id' => $r('Ribera del Duero', $spain)];
            $producers[] = ['name' => 'Dominio de Pingus', 'country_id' => $spain->id, 'region_id' => $r('Ribera del Duero', $spain)];
            $producers[] = ['name' => 'Alvaro Palacios', 'country_id' => $spain->id, 'region_id' => $r('Priorat', $spain)];
            $producers[] = ['name' => 'Clos Mogador', 'country_id' => $spain->id, 'region_id' => $r('Priorat', $spain)];
            $producers[] = ['name' => 'Bodegas Muga', 'country_id' => $spain->id, 'region_id' => $r('Rioja', $spain)];
            $producers[] = ['name' => 'La Rioja Alta', 'country_id' => $spain->id, 'region_id' => $r('Rioja', $spain)];
            $producers[] = ['name' => 'CVNE', 'country_id' => $spain->id, 'region_id' => $r('Rioja', $spain)];
            $producers[] = ['name' => 'Torres', 'country_id' => $spain->id, 'region_id' => $r('Penedes', $spain)];
        }

        // =================================================================
        // PORTUGAL
        // =================================================================
        if ($portugal !== null) {
            $producers[] = ['name' => 'Quinta do Noval', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal)];
            $producers[] = ['name' => 'Dow\'s', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal)];
            $producers[] = ['name' => 'Graham\'s', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal)];
            $producers[] = ['name' => 'Taylor\'s', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal)];
        }

        // =================================================================
        // GERMANY
        // =================================================================
        if ($germany !== null) {
            $producers[] = ['name' => 'Egon Muller', 'country_id' => $germany->id, 'region_id' => $r('Mosel', $germany)];
            $producers[] = ['name' => 'Joh. Jos. Prum', 'country_id' => $germany->id, 'region_id' => $r('Mosel', $germany)];
            $producers[] = ['name' => 'Weingut Donnhoff', 'country_id' => $germany->id, 'region_id' => $r('Nahe', $germany)];
            $producers[] = ['name' => 'Fritz Haag', 'country_id' => $germany->id, 'region_id' => $r('Mosel', $germany)];
            $producers[] = ['name' => 'Robert Weil', 'country_id' => $germany->id, 'region_id' => $r('Rheingau', $germany)];
        }

        // =================================================================
        // AUSTRIA
        // =================================================================
        if ($austria !== null) {
            $producers[] = ['name' => 'Weingut F.X. Pichler', 'country_id' => $austria->id, 'region_id' => $r('Wachau', $austria)];
            $producers[] = ['name' => 'Weingut Knoll', 'country_id' => $austria->id, 'region_id' => $r('Wachau', $austria)];
        }

        // =================================================================
        // UNITED STATES
        // =================================================================
        if ($us !== null) {
            $producers[] = ['name' => 'Screaming Eagle', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Harlan Estate', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Opus One', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Ridge Vineyards', 'country_id' => $us->id, 'region_id' => $r('Santa Cruz Mountains', $us)];
            $producers[] = ['name' => 'Joseph Phelps', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Dominus Estate', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Caymus', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
            $producers[] = ['name' => 'Stag\'s Leap Wine Cellars', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us)];
        }

        // =================================================================
        // AUSTRALIA
        // =================================================================
        if ($australia !== null) {
            $producers[] = ['name' => 'Penfolds', 'country_id' => $australia->id, 'region_id' => $r('South Australia', $australia)];
            $producers[] = ['name' => 'Henschke', 'country_id' => $australia->id, 'region_id' => $r('Eden Valley', $australia)];
            $producers[] = ['name' => 'Torbreck', 'country_id' => $australia->id, 'region_id' => $r('Barossa Valley', $australia)];
            $producers[] = ['name' => 'Clarendon Hills', 'country_id' => $australia->id, 'region_id' => $r('McLaren Vale', $australia)];
            $producers[] = ['name' => 'Leeuwin Estate', 'country_id' => $australia->id, 'region_id' => $r('Margaret River', $australia)];
            $producers[] = ['name' => 'Cullen', 'country_id' => $australia->id, 'region_id' => $r('Margaret River', $australia)];
        }

        // =================================================================
        // NEW ZEALAND
        // =================================================================
        if ($nz !== null) {
            $producers[] = ['name' => 'Cloudy Bay', 'country_id' => $nz->id, 'region_id' => $r('Marlborough', $nz)];
            $producers[] = ['name' => 'Felton Road', 'country_id' => $nz->id, 'region_id' => $r('Central Otago', $nz)];
            $producers[] = ['name' => 'Craggy Range', 'country_id' => $nz->id, 'region_id' => $r("Hawke's Bay", $nz)];
        }

        // =================================================================
        // SOUTH AFRICA
        // =================================================================
        if ($za !== null) {
            $producers[] = ['name' => 'Kanonkop', 'country_id' => $za->id, 'region_id' => $r('Stellenbosch', $za)];
            $producers[] = ['name' => 'Mullineux', 'country_id' => $za->id, 'region_id' => $r('Swartland', $za)];
        }

        // =================================================================
        // ARGENTINA
        // =================================================================
        if ($argentina !== null) {
            $producers[] = ['name' => 'Catena Zapata', 'country_id' => $argentina->id, 'region_id' => $r('Mendoza', $argentina)];
            $producers[] = ['name' => 'Achaval-Ferrer', 'country_id' => $argentina->id, 'region_id' => $r('Mendoza', $argentina)];
        }

        // =================================================================
        // CHILE
        // =================================================================
        if ($chile !== null) {
            $producers[] = ['name' => 'Almaviva', 'country_id' => $chile->id, 'region_id' => $r('Maipo Valley', $chile)];
            $producers[] = ['name' => 'Sena', 'country_id' => $chile->id, 'region_id' => $r('Aconcagua Valley', $chile)];
            $producers[] = ['name' => 'Concha y Toro', 'country_id' => $chile->id, 'region_id' => $r('Maipo Valley', $chile)];
        }

        // =================================================================
        // LEBANON
        // =================================================================
        if ($lebanon !== null) {
            $producers[] = ['name' => 'Chateau Musar', 'country_id' => $lebanon->id, 'region_id' => $r('Bekaa Valley', $lebanon)];
        }

        // =================================================================
        // Create all producers
        // =================================================================
        foreach ($producers as $sortOrder => $data) {
            Producer::firstOrCreate(
                [
                    'name' => $data['name'],
                    'country_id' => $data['country_id'],
                ],
                [
                    'region_id' => $data['region_id'],
                    'is_active' => true,
                    'sort_order' => $sortOrder + 1,
                ]
            );

            $count++;
        }

        $this->command->info("Created {$count} producers.");
    }
}
