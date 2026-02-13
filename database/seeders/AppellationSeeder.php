<?php

namespace Database\Seeders;

use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Region;
use Illuminate\Database\Seeder;

/**
 * AppellationSeeder - Creates wine appellations for fine wine trading
 *
 * Comprehensive coverage of Tier 1 + Tier 2 appellations across all
 * major wine-producing countries. Includes Burgundy Grand Crus,
 * Bordeaux AOCs, Italian DOCGs, Spanish DOs, and New World GIs.
 */
class AppellationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $count = 0;

        // =================================================================
        // Resolve countries
        // =================================================================
        $countries = Country::all()->keyBy('iso_code');

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
        $hungary = $countries->get('HU');

        if ($italy === null || $france === null) {
            $this->command->warn('Required countries (IT, FR) not found. Run CountrySeeder first.');

            return;
        }

        // =================================================================
        // Pre-load all regions keyed by "name|country_id"
        // =================================================================
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

        // =================================================================
        // FRENCH APPELLATIONS
        // =================================================================
        $appellations = [];

        // -----------------------------------------------------------------
        // Bordeaux AOCs
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Bordeaux AOC', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Haut-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Haut-Medoc', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Margaux AOC', 'country_id' => $france->id, 'region_id' => $r('Margaux', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Pauillac AOC', 'country_id' => $france->id, 'region_id' => $r('Pauillac', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Pessac-Leognan AOC', 'country_id' => $france->id, 'region_id' => $r('Pessac-Leognan', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Julien AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Julien', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Estephe AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Estephe', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Pomerol AOC', 'country_id' => $france->id, 'region_id' => $r('Pomerol', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Emilion Grand Cru', 'country_id' => $france->id, 'region_id' => $r('Saint-Emilion', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Emilion AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Emilion', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Sauternes AOC', 'country_id' => $france->id, 'region_id' => $r('Sauternes', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Barsac AOC', 'country_id' => $france->id, 'region_id' => $r('Barsac', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Graves AOC', 'country_id' => $france->id, 'region_id' => $r('Graves', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Moulis-en-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Moulis-en-Medoc', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Listrac-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Listrac-Medoc', $france), 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Burgundy Grand Crus - Cote de Nuits
        // -----------------------------------------------------------------
        $cdnId = $r('Cote de Nuits', $france);

        $appellations[] = ['name' => 'Romanee-Conti Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'La Tache Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Richebourg Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Romanee-Saint-Vivant Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'La Romanee Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'La Grande Rue Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Musigny Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Bonnes-Mares Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chambertin-Clos de Beze Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Charmes-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Mazis-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Latricieres-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chapelle-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Griotte-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Ruchottes-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Clos de la Roche Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Clos Saint-Denis Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Clos des Lambrays Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Clos de Tart Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Clos de Vougeot Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Echezeaux Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Grands-Echezeaux Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Burgundy Grand Crus - Cote de Beaune
        // -----------------------------------------------------------------
        $cdbId = $r('Cote de Beaune', $france);

        $appellations[] = ['name' => 'Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chevalier-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Bienvenues-Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Criots-Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Corton-Charlemagne Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Corton Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Burgundy Village AOCs - Cote de Nuits
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Gevrey-Chambertin AOC', 'country_id' => $france->id, 'region_id' => $r('Gevrey-Chambertin', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Vosne-Romanee AOC', 'country_id' => $france->id, 'region_id' => $r('Vosne-Romanee', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chambolle-Musigny AOC', 'country_id' => $france->id, 'region_id' => $r('Chambolle-Musigny', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Morey-Saint-Denis AOC', 'country_id' => $france->id, 'region_id' => $r('Morey-Saint-Denis', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Nuits-Saint-Georges AOC', 'country_id' => $france->id, 'region_id' => $r('Nuits-Saint-Georges', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Vougeot AOC', 'country_id' => $france->id, 'region_id' => $r('Vougeot', $france), 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Burgundy Village AOCs - Cote de Beaune
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Meursault AOC', 'country_id' => $france->id, 'region_id' => $r('Meursault', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Puligny-Montrachet AOC', 'country_id' => $france->id, 'region_id' => $r('Puligny-Montrachet', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chassagne-Montrachet AOC', 'country_id' => $france->id, 'region_id' => $r('Chassagne-Montrachet', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Pommard AOC', 'country_id' => $france->id, 'region_id' => $r('Pommard', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Volnay AOC', 'country_id' => $france->id, 'region_id' => $r('Volnay', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Beaune AOC', 'country_id' => $france->id, 'region_id' => $r('Beaune', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Aloxe-Corton AOC', 'country_id' => $france->id, 'region_id' => $r('Aloxe-Corton', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Savigny-les-Beaune AOC', 'country_id' => $france->id, 'region_id' => $r('Savigny-les-Beaune', $france), 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Aubin AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Aubin', $france), 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Burgundy - Chablis
        // -----------------------------------------------------------------
        $chabId = $r('Chablis', $france);

        $appellations[] = ['name' => 'Chablis Grand Cru AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chablis Premier Cru AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chablis AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Champagne
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Champagne AOC', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france), 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Rhone Valley
        // -----------------------------------------------------------------
        $nrId = $r('Northern Rhone', $france);
        $srId = $r('Southern Rhone', $france);

        $appellations[] = ['name' => 'Hermitage AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Cote-Rotie AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Condrieu AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Cornas AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Saint-Joseph AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Crozes-Hermitage AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chateauneuf-du-Pape AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Gigondas AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Vacqueyras AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Loire Valley
        // -----------------------------------------------------------------
        $loireId = $r('Loire Valley', $france);

        $appellations[] = ['name' => 'Sancerre AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Pouilly-Fume AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Vouvray AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Savennieres AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Chinon AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Alsace
        // -----------------------------------------------------------------
        $alsaceId = $r('Alsace', $france);

        $appellations[] = ['name' => 'Alsace Grand Cru AOC', 'country_id' => $france->id, 'region_id' => $alsaceId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Alsace AOC', 'country_id' => $france->id, 'region_id' => $alsaceId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Jura
        // -----------------------------------------------------------------
        $juraId = $r('Jura', $france);

        $appellations[] = ['name' => 'Chateau-Chalon AOC', 'country_id' => $france->id, 'region_id' => $juraId, 'system' => 'aoc'];
        $appellations[] = ['name' => 'Arbois AOC', 'country_id' => $france->id, 'region_id' => $juraId, 'system' => 'aoc'];

        // -----------------------------------------------------------------
        // Provence
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Bandol AOC', 'country_id' => $france->id, 'region_id' => $r('Provence', $france), 'system' => 'aoc'];

        // =================================================================
        // ITALIAN APPELLATIONS
        // =================================================================

        // Piedmont
        $appellations[] = ['name' => 'Barolo DOCG', 'country_id' => $italy->id, 'region_id' => $r('Barolo', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Barbaresco DOCG', 'country_id' => $italy->id, 'region_id' => $r('Barbaresco', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Langhe DOC', 'country_id' => $italy->id, 'region_id' => $r('Langhe', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Roero DOCG', 'country_id' => $italy->id, 'region_id' => $r('Roero', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Barbera d\'Asti DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Gattinara DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Gavi DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => 'docg'];

        // Tuscany
        $appellations[] = ['name' => 'Brunello di Montalcino DOCG', 'country_id' => $italy->id, 'region_id' => $r('Montalcino', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Rosso di Montalcino DOC', 'country_id' => $italy->id, 'region_id' => $r('Montalcino', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Chianti Classico DOCG', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Bolgheri Sassicaia DOC', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Bolgheri DOC Superiore', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Bolgheri DOC', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Toscana IGT', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy), 'system' => 'igt'];
        $appellations[] = ['name' => 'Vino Nobile di Montepulciano DOCG', 'country_id' => $italy->id, 'region_id' => $r('Montepulciano', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Morellino di Scansano DOCG', 'country_id' => $italy->id, 'region_id' => $r('Maremma', $italy), 'system' => 'docg'];

        // Veneto
        $appellations[] = ['name' => 'Amarone della Valpolicella DOCG', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Valpolicella Ripasso DOC', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy), 'system' => 'doc'];
        $appellations[] = ['name' => 'Soave DOC', 'country_id' => $italy->id, 'region_id' => $r('Soave', $italy), 'system' => 'doc'];

        // Lombardy
        $appellations[] = ['name' => 'Franciacorta DOCG', 'country_id' => $italy->id, 'region_id' => $r('Franciacorta', $italy), 'system' => 'docg'];

        // Sicily
        $appellations[] = ['name' => 'Etna DOC', 'country_id' => $italy->id, 'region_id' => $r('Etna', $italy), 'system' => 'doc'];

        // Campania
        $appellations[] = ['name' => 'Taurasi DOCG', 'country_id' => $italy->id, 'region_id' => $r('Campania', $italy), 'system' => 'docg'];
        $appellations[] = ['name' => 'Fiano di Avellino DOCG', 'country_id' => $italy->id, 'region_id' => $r('Campania', $italy), 'system' => 'docg'];

        // Trentino-Alto Adige
        $appellations[] = ['name' => 'Alto Adige DOC', 'country_id' => $italy->id, 'region_id' => $r('Trentino-Alto Adige', $italy), 'system' => 'doc'];

        // =================================================================
        // SPANISH APPELLATIONS
        // =================================================================
        if ($spain !== null) {
            $appellations[] = ['name' => 'Rioja DOCa', 'country_id' => $spain->id, 'region_id' => $r('Rioja', $spain), 'system' => 'doca'];
            $appellations[] = ['name' => 'Ribera del Duero DO', 'country_id' => $spain->id, 'region_id' => $r('Ribera del Duero', $spain), 'system' => 'do'];
            $appellations[] = ['name' => 'Priorat DOCa', 'country_id' => $spain->id, 'region_id' => $r('Priorat', $spain), 'system' => 'doca'];
            $appellations[] = ['name' => 'Rias Baixas DO', 'country_id' => $spain->id, 'region_id' => $r('Rias Baixas', $spain), 'system' => 'do'];
            $appellations[] = ['name' => 'Jerez DO', 'country_id' => $spain->id, 'region_id' => $r('Jerez', $spain), 'system' => 'do'];
            $appellations[] = ['name' => 'Toro DO', 'country_id' => $spain->id, 'region_id' => $r('Toro', $spain), 'system' => 'do'];
            $appellations[] = ['name' => 'Bierzo DO', 'country_id' => $spain->id, 'region_id' => $r('Bierzo', $spain), 'system' => 'do'];
        }

        // =================================================================
        // PORTUGUESE APPELLATIONS
        // =================================================================
        if ($portugal !== null) {
            $appellations[] = ['name' => 'Douro DOC', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal), 'system' => 'doc_pt'];
            $appellations[] = ['name' => 'Porto DOC', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal), 'system' => 'doc_pt'];
            $appellations[] = ['name' => 'Dao DOC', 'country_id' => $portugal->id, 'region_id' => $r('Dao', $portugal), 'system' => 'doc_pt'];
            $appellations[] = ['name' => 'Alentejo DOC', 'country_id' => $portugal->id, 'region_id' => $r('Alentejo', $portugal), 'system' => 'doc_pt'];
            $appellations[] = ['name' => 'Madeira DOC', 'country_id' => $portugal->id, 'region_id' => $r('Madeira', $portugal), 'system' => 'doc_pt'];
        }

        // =================================================================
        // GERMAN APPELLATIONS (VDP classification)
        // =================================================================
        if ($germany !== null) {
            $appellations[] = ['name' => 'Mosel VDP', 'country_id' => $germany->id, 'region_id' => $r('Mosel', $germany), 'system' => 'vdp'];
            $appellations[] = ['name' => 'Rheingau VDP', 'country_id' => $germany->id, 'region_id' => $r('Rheingau', $germany), 'system' => 'vdp'];
            $appellations[] = ['name' => 'Pfalz VDP', 'country_id' => $germany->id, 'region_id' => $r('Pfalz', $germany), 'system' => 'vdp'];
            $appellations[] = ['name' => 'Nahe VDP', 'country_id' => $germany->id, 'region_id' => $r('Nahe', $germany), 'system' => 'vdp'];
            $appellations[] = ['name' => 'Rheinhessen VDP', 'country_id' => $germany->id, 'region_id' => $r('Rheinhessen', $germany), 'system' => 'vdp'];
        }

        // =================================================================
        // AUSTRIAN APPELLATIONS (DAC)
        // =================================================================
        if ($austria !== null) {
            $appellations[] = ['name' => 'Wachau DAC', 'country_id' => $austria->id, 'region_id' => $r('Wachau', $austria), 'system' => 'dac'];
            $appellations[] = ['name' => 'Kamptal DAC', 'country_id' => $austria->id, 'region_id' => $r('Kamptal', $austria), 'system' => 'dac'];
            $appellations[] = ['name' => 'Kremstal DAC', 'country_id' => $austria->id, 'region_id' => $r('Kremstal', $austria), 'system' => 'dac'];
        }

        // =================================================================
        // USA APPELLATIONS (AVA)
        // =================================================================
        if ($us !== null) {
            $appellations[] = ['name' => 'Napa Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Rutherford AVA', 'country_id' => $us->id, 'region_id' => $r('Rutherford', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Oakville AVA', 'country_id' => $us->id, 'region_id' => $r('Oakville', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Stags Leap District AVA', 'country_id' => $us->id, 'region_id' => $r('Stags Leap District', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Howell Mountain AVA', 'country_id' => $us->id, 'region_id' => $r('Howell Mountain', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Sonoma Coast AVA', 'country_id' => $us->id, 'region_id' => $r('Sonoma Coast', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Russian River Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Russian River Valley', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Willamette Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Willamette Valley', $us), 'system' => 'ava'];
            $appellations[] = ['name' => 'Paso Robles AVA', 'country_id' => $us->id, 'region_id' => $r('Paso Robles', $us), 'system' => 'ava'];
        }

        // =================================================================
        // AUSTRALIAN APPELLATIONS (GI)
        // =================================================================
        if ($australia !== null) {
            $appellations[] = ['name' => 'Barossa Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Barossa Valley', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'McLaren Vale GI', 'country_id' => $australia->id, 'region_id' => $r('McLaren Vale', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Clare Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Clare Valley', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Eden Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Eden Valley', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Coonawarra GI', 'country_id' => $australia->id, 'region_id' => $r('Coonawarra', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Margaret River GI', 'country_id' => $australia->id, 'region_id' => $r('Margaret River', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Yarra Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Yarra Valley', $australia), 'system' => 'gi'];
            $appellations[] = ['name' => 'Hunter Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Hunter Valley', $australia), 'system' => 'gi'];
        }

        // =================================================================
        // NEW ZEALAND APPELLATIONS (GI)
        // =================================================================
        if ($nz !== null) {
            $appellations[] = ['name' => 'Marlborough GI', 'country_id' => $nz->id, 'region_id' => $r('Marlborough', $nz), 'system' => 'gi'];
            $appellations[] = ['name' => 'Central Otago GI', 'country_id' => $nz->id, 'region_id' => $r('Central Otago', $nz), 'system' => 'gi'];
            $appellations[] = ['name' => "Hawke's Bay GI", 'country_id' => $nz->id, 'region_id' => $r("Hawke's Bay", $nz), 'system' => 'gi'];
        }

        // =================================================================
        // SOUTH AFRICAN APPELLATIONS (GI / WO)
        // =================================================================
        if ($za !== null) {
            $appellations[] = ['name' => 'Stellenbosch GI', 'country_id' => $za->id, 'region_id' => $r('Stellenbosch', $za), 'system' => 'gi'];
            $appellations[] = ['name' => 'Swartland GI', 'country_id' => $za->id, 'region_id' => $r('Swartland', $za), 'system' => 'gi'];
            $appellations[] = ['name' => 'Constantia GI', 'country_id' => $za->id, 'region_id' => $r('Constantia', $za), 'system' => 'gi'];
        }

        // =================================================================
        // ARGENTINIAN APPELLATIONS
        // =================================================================
        if ($argentina !== null) {
            $appellations[] = ['name' => 'Mendoza GI', 'country_id' => $argentina->id, 'region_id' => $r('Mendoza', $argentina), 'system' => 'gi'];
            $appellations[] = ['name' => 'Uco Valley GI', 'country_id' => $argentina->id, 'region_id' => $r('Uco Valley', $argentina), 'system' => 'gi'];
        }

        // =================================================================
        // CHILEAN APPELLATIONS (DO)
        // =================================================================
        if ($chile !== null) {
            $appellations[] = ['name' => 'Maipo Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Maipo Valley', $chile), 'system' => 'do'];
            $appellations[] = ['name' => 'Colchagua Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Colchagua Valley', $chile), 'system' => 'do'];
            $appellations[] = ['name' => 'Aconcagua Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Aconcagua Valley', $chile), 'system' => 'do'];
        }

        // =================================================================
        // HUNGARIAN APPELLATIONS
        // =================================================================
        if ($hungary !== null) {
            $appellations[] = ['name' => 'Tokaj AOC', 'country_id' => $hungary->id, 'region_id' => $r('Tokaj', $hungary), 'system' => 'other'];
        }

        // =================================================================
        // Create all appellations
        // =================================================================
        foreach ($appellations as $sortOrder => $data) {
            Appellation::firstOrCreate(
                [
                    'name' => $data['name'],
                    'country_id' => $data['country_id'],
                ],
                [
                    'region_id' => $data['region_id'],
                    'system' => $data['system'],
                    'is_active' => true,
                    'sort_order' => $sortOrder + 1,
                ]
            );

            $count++;
        }

        $this->command->info("Created {$count} appellations.");
    }
}
