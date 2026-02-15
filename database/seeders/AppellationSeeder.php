<?php

namespace Database\Seeders;

use App\Enums\Pim\AppellationSystem;
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
        $appellations[] = ['name' => 'Bordeaux AOC', 'country_id' => $france->id, 'region_id' => $r('Bordeaux', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Haut-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Haut-Medoc', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Margaux AOC', 'country_id' => $france->id, 'region_id' => $r('Margaux', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Pauillac AOC', 'country_id' => $france->id, 'region_id' => $r('Pauillac', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Pessac-Leognan AOC', 'country_id' => $france->id, 'region_id' => $r('Pessac-Leognan', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Julien AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Julien', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Estephe AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Estephe', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Pomerol AOC', 'country_id' => $france->id, 'region_id' => $r('Pomerol', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Emilion Grand Cru', 'country_id' => $france->id, 'region_id' => $r('Saint-Emilion', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Emilion AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Emilion', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Sauternes AOC', 'country_id' => $france->id, 'region_id' => $r('Sauternes', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Barsac AOC', 'country_id' => $france->id, 'region_id' => $r('Barsac', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Graves AOC', 'country_id' => $france->id, 'region_id' => $r('Graves', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Moulis-en-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Moulis-en-Medoc', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Listrac-Medoc AOC', 'country_id' => $france->id, 'region_id' => $r('Listrac-Medoc', $france), 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Burgundy Grand Crus - Cote de Nuits
        // -----------------------------------------------------------------
        $cdnId = $r('Cote de Nuits', $france);

        $appellations[] = ['name' => 'Romanee-Conti Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'La Tache Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Richebourg Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Romanee-Saint-Vivant Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'La Romanee Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'La Grande Rue Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Musigny Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Bonnes-Mares Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chambertin-Clos de Beze Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Charmes-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Mazis-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Latricieres-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chapelle-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Griotte-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Ruchottes-Chambertin Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Clos de la Roche Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Clos Saint-Denis Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Clos des Lambrays Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Clos de Tart Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Clos de Vougeot Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Echezeaux Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Grands-Echezeaux Grand Cru', 'country_id' => $france->id, 'region_id' => $cdnId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Burgundy Grand Crus - Cote de Beaune
        // -----------------------------------------------------------------
        $cdbId = $r('Cote de Beaune', $france);

        $appellations[] = ['name' => 'Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chevalier-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Bienvenues-Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Criots-Batard-Montrachet Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Corton-Charlemagne Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Corton Grand Cru', 'country_id' => $france->id, 'region_id' => $cdbId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Burgundy Village AOCs - Cote de Nuits
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Gevrey-Chambertin AOC', 'country_id' => $france->id, 'region_id' => $r('Gevrey-Chambertin', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Vosne-Romanee AOC', 'country_id' => $france->id, 'region_id' => $r('Vosne-Romanee', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chambolle-Musigny AOC', 'country_id' => $france->id, 'region_id' => $r('Chambolle-Musigny', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Morey-Saint-Denis AOC', 'country_id' => $france->id, 'region_id' => $r('Morey-Saint-Denis', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Nuits-Saint-Georges AOC', 'country_id' => $france->id, 'region_id' => $r('Nuits-Saint-Georges', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Vougeot AOC', 'country_id' => $france->id, 'region_id' => $r('Vougeot', $france), 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Burgundy Village AOCs - Cote de Beaune
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Meursault AOC', 'country_id' => $france->id, 'region_id' => $r('Meursault', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Puligny-Montrachet AOC', 'country_id' => $france->id, 'region_id' => $r('Puligny-Montrachet', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chassagne-Montrachet AOC', 'country_id' => $france->id, 'region_id' => $r('Chassagne-Montrachet', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Pommard AOC', 'country_id' => $france->id, 'region_id' => $r('Pommard', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Volnay AOC', 'country_id' => $france->id, 'region_id' => $r('Volnay', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Beaune AOC', 'country_id' => $france->id, 'region_id' => $r('Beaune', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Aloxe-Corton AOC', 'country_id' => $france->id, 'region_id' => $r('Aloxe-Corton', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Savigny-les-Beaune AOC', 'country_id' => $france->id, 'region_id' => $r('Savigny-les-Beaune', $france), 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Aubin AOC', 'country_id' => $france->id, 'region_id' => $r('Saint-Aubin', $france), 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Burgundy - Chablis
        // -----------------------------------------------------------------
        $chabId = $r('Chablis', $france);

        $appellations[] = ['name' => 'Chablis Grand Cru AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chablis Premier Cru AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chablis AOC', 'country_id' => $france->id, 'region_id' => $chabId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Champagne
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Champagne AOC', 'country_id' => $france->id, 'region_id' => $r('Champagne', $france), 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Rhone Valley
        // -----------------------------------------------------------------
        $nrId = $r('Northern Rhone', $france);
        $srId = $r('Southern Rhone', $france);

        $appellations[] = ['name' => 'Hermitage AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Cote-Rotie AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Condrieu AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Cornas AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Saint-Joseph AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Crozes-Hermitage AOC', 'country_id' => $france->id, 'region_id' => $nrId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chateauneuf-du-Pape AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Gigondas AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Vacqueyras AOC', 'country_id' => $france->id, 'region_id' => $srId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Loire Valley
        // -----------------------------------------------------------------
        $loireId = $r('Loire Valley', $france);

        $appellations[] = ['name' => 'Sancerre AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Pouilly-Fume AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Vouvray AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Savennieres AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Chinon AOC', 'country_id' => $france->id, 'region_id' => $loireId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Alsace
        // -----------------------------------------------------------------
        $alsaceId = $r('Alsace', $france);

        $appellations[] = ['name' => 'Alsace Grand Cru AOC', 'country_id' => $france->id, 'region_id' => $alsaceId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Alsace AOC', 'country_id' => $france->id, 'region_id' => $alsaceId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Jura
        // -----------------------------------------------------------------
        $juraId = $r('Jura', $france);

        $appellations[] = ['name' => 'Chateau-Chalon AOC', 'country_id' => $france->id, 'region_id' => $juraId, 'system' => AppellationSystem::AOC];
        $appellations[] = ['name' => 'Arbois AOC', 'country_id' => $france->id, 'region_id' => $juraId, 'system' => AppellationSystem::AOC];

        // -----------------------------------------------------------------
        // Provence
        // -----------------------------------------------------------------
        $appellations[] = ['name' => 'Bandol AOC', 'country_id' => $france->id, 'region_id' => $r('Provence', $france), 'system' => AppellationSystem::AOC];

        // =================================================================
        // ITALIAN APPELLATIONS
        // =================================================================

        // Piedmont
        $appellations[] = ['name' => 'Barolo DOCG', 'country_id' => $italy->id, 'region_id' => $r('Barolo', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Barbaresco DOCG', 'country_id' => $italy->id, 'region_id' => $r('Barbaresco', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Langhe DOC', 'country_id' => $italy->id, 'region_id' => $r('Langhe', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Roero DOCG', 'country_id' => $italy->id, 'region_id' => $r('Roero', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Barbera d\'Asti DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Gattinara DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Gavi DOCG', 'country_id' => $italy->id, 'region_id' => $r('Piedmont', $italy), 'system' => AppellationSystem::DOCG];

        // Tuscany
        $appellations[] = ['name' => 'Brunello di Montalcino DOCG', 'country_id' => $italy->id, 'region_id' => $r('Montalcino', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Rosso di Montalcino DOC', 'country_id' => $italy->id, 'region_id' => $r('Montalcino', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Chianti Classico DOCG', 'country_id' => $italy->id, 'region_id' => $r('Chianti Classico', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Bolgheri Sassicaia DOC', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Bolgheri DOC Superiore', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Bolgheri DOC', 'country_id' => $italy->id, 'region_id' => $r('Bolgheri', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Toscana IGT', 'country_id' => $italy->id, 'region_id' => $r('Tuscany', $italy), 'system' => AppellationSystem::IGT];
        $appellations[] = ['name' => 'Vino Nobile di Montepulciano DOCG', 'country_id' => $italy->id, 'region_id' => $r('Montepulciano', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Morellino di Scansano DOCG', 'country_id' => $italy->id, 'region_id' => $r('Maremma', $italy), 'system' => AppellationSystem::DOCG];

        // Veneto
        $appellations[] = ['name' => 'Amarone della Valpolicella DOCG', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Valpolicella Ripasso DOC', 'country_id' => $italy->id, 'region_id' => $r('Valpolicella', $italy), 'system' => AppellationSystem::DOC];
        $appellations[] = ['name' => 'Soave DOC', 'country_id' => $italy->id, 'region_id' => $r('Soave', $italy), 'system' => AppellationSystem::DOC];

        // Lombardy
        $appellations[] = ['name' => 'Franciacorta DOCG', 'country_id' => $italy->id, 'region_id' => $r('Franciacorta', $italy), 'system' => AppellationSystem::DOCG];

        // Sicily
        $appellations[] = ['name' => 'Etna DOC', 'country_id' => $italy->id, 'region_id' => $r('Etna', $italy), 'system' => AppellationSystem::DOC];

        // Campania
        $appellations[] = ['name' => 'Taurasi DOCG', 'country_id' => $italy->id, 'region_id' => $r('Campania', $italy), 'system' => AppellationSystem::DOCG];
        $appellations[] = ['name' => 'Fiano di Avellino DOCG', 'country_id' => $italy->id, 'region_id' => $r('Campania', $italy), 'system' => AppellationSystem::DOCG];

        // Trentino-Alto Adige
        $appellations[] = ['name' => 'Alto Adige DOC', 'country_id' => $italy->id, 'region_id' => $r('Trentino-Alto Adige', $italy), 'system' => AppellationSystem::DOC];

        // =================================================================
        // SPANISH APPELLATIONS
        // =================================================================
        if ($spain !== null) {
            $appellations[] = ['name' => 'Rioja DOCa', 'country_id' => $spain->id, 'region_id' => $r('Rioja', $spain), 'system' => AppellationSystem::DOCa];
            $appellations[] = ['name' => 'Ribera del Duero DO', 'country_id' => $spain->id, 'region_id' => $r('Ribera del Duero', $spain), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Priorat DOCa', 'country_id' => $spain->id, 'region_id' => $r('Priorat', $spain), 'system' => AppellationSystem::DOCa];
            $appellations[] = ['name' => 'Rias Baixas DO', 'country_id' => $spain->id, 'region_id' => $r('Rias Baixas', $spain), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Jerez DO', 'country_id' => $spain->id, 'region_id' => $r('Jerez', $spain), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Toro DO', 'country_id' => $spain->id, 'region_id' => $r('Toro', $spain), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Bierzo DO', 'country_id' => $spain->id, 'region_id' => $r('Bierzo', $spain), 'system' => AppellationSystem::DO];
        }

        // =================================================================
        // PORTUGUESE APPELLATIONS
        // =================================================================
        if ($portugal !== null) {
            $appellations[] = ['name' => 'Douro DOC', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal), 'system' => AppellationSystem::DOCPt];
            $appellations[] = ['name' => 'Porto DOC', 'country_id' => $portugal->id, 'region_id' => $r('Douro', $portugal), 'system' => AppellationSystem::DOCPt];
            $appellations[] = ['name' => 'Dao DOC', 'country_id' => $portugal->id, 'region_id' => $r('Dao', $portugal), 'system' => AppellationSystem::DOCPt];
            $appellations[] = ['name' => 'Alentejo DOC', 'country_id' => $portugal->id, 'region_id' => $r('Alentejo', $portugal), 'system' => AppellationSystem::DOCPt];
            $appellations[] = ['name' => 'Madeira DOC', 'country_id' => $portugal->id, 'region_id' => $r('Madeira', $portugal), 'system' => AppellationSystem::DOCPt];
        }

        // =================================================================
        // GERMAN APPELLATIONS (VDP classification)
        // =================================================================
        if ($germany !== null) {
            $appellations[] = ['name' => 'Mosel VDP', 'country_id' => $germany->id, 'region_id' => $r('Mosel', $germany), 'system' => AppellationSystem::VdP];
            $appellations[] = ['name' => 'Rheingau VDP', 'country_id' => $germany->id, 'region_id' => $r('Rheingau', $germany), 'system' => AppellationSystem::VdP];
            $appellations[] = ['name' => 'Pfalz VDP', 'country_id' => $germany->id, 'region_id' => $r('Pfalz', $germany), 'system' => AppellationSystem::VdP];
            $appellations[] = ['name' => 'Nahe VDP', 'country_id' => $germany->id, 'region_id' => $r('Nahe', $germany), 'system' => AppellationSystem::VdP];
            $appellations[] = ['name' => 'Rheinhessen VDP', 'country_id' => $germany->id, 'region_id' => $r('Rheinhessen', $germany), 'system' => AppellationSystem::VdP];
        }

        // =================================================================
        // AUSTRIAN APPELLATIONS (DAC)
        // =================================================================
        if ($austria !== null) {
            $appellations[] = ['name' => 'Wachau DAC', 'country_id' => $austria->id, 'region_id' => $r('Wachau', $austria), 'system' => AppellationSystem::DAC];
            $appellations[] = ['name' => 'Kamptal DAC', 'country_id' => $austria->id, 'region_id' => $r('Kamptal', $austria), 'system' => AppellationSystem::DAC];
            $appellations[] = ['name' => 'Kremstal DAC', 'country_id' => $austria->id, 'region_id' => $r('Kremstal', $austria), 'system' => AppellationSystem::DAC];
        }

        // =================================================================
        // USA APPELLATIONS (AVA)
        // =================================================================
        if ($us !== null) {
            $appellations[] = ['name' => 'Napa Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Napa Valley', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Rutherford AVA', 'country_id' => $us->id, 'region_id' => $r('Rutherford', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Oakville AVA', 'country_id' => $us->id, 'region_id' => $r('Oakville', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Stags Leap District AVA', 'country_id' => $us->id, 'region_id' => $r('Stags Leap District', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Howell Mountain AVA', 'country_id' => $us->id, 'region_id' => $r('Howell Mountain', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Sonoma Coast AVA', 'country_id' => $us->id, 'region_id' => $r('Sonoma Coast', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Russian River Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Russian River Valley', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Willamette Valley AVA', 'country_id' => $us->id, 'region_id' => $r('Willamette Valley', $us), 'system' => AppellationSystem::AVA];
            $appellations[] = ['name' => 'Paso Robles AVA', 'country_id' => $us->id, 'region_id' => $r('Paso Robles', $us), 'system' => AppellationSystem::AVA];
        }

        // =================================================================
        // AUSTRALIAN APPELLATIONS (GI)
        // =================================================================
        if ($australia !== null) {
            $appellations[] = ['name' => 'Barossa Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Barossa Valley', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'McLaren Vale GI', 'country_id' => $australia->id, 'region_id' => $r('McLaren Vale', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Clare Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Clare Valley', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Eden Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Eden Valley', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Coonawarra GI', 'country_id' => $australia->id, 'region_id' => $r('Coonawarra', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Margaret River GI', 'country_id' => $australia->id, 'region_id' => $r('Margaret River', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Yarra Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Yarra Valley', $australia), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Hunter Valley GI', 'country_id' => $australia->id, 'region_id' => $r('Hunter Valley', $australia), 'system' => AppellationSystem::GI];
        }

        // =================================================================
        // NEW ZEALAND APPELLATIONS (GI)
        // =================================================================
        if ($nz !== null) {
            $appellations[] = ['name' => 'Marlborough GI', 'country_id' => $nz->id, 'region_id' => $r('Marlborough', $nz), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Central Otago GI', 'country_id' => $nz->id, 'region_id' => $r('Central Otago', $nz), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => "Hawke's Bay GI", 'country_id' => $nz->id, 'region_id' => $r("Hawke's Bay", $nz), 'system' => AppellationSystem::GI];
        }

        // =================================================================
        // SOUTH AFRICAN APPELLATIONS (GI / WO)
        // =================================================================
        if ($za !== null) {
            $appellations[] = ['name' => 'Stellenbosch GI', 'country_id' => $za->id, 'region_id' => $r('Stellenbosch', $za), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Swartland GI', 'country_id' => $za->id, 'region_id' => $r('Swartland', $za), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Constantia GI', 'country_id' => $za->id, 'region_id' => $r('Constantia', $za), 'system' => AppellationSystem::GI];
        }

        // =================================================================
        // ARGENTINIAN APPELLATIONS
        // =================================================================
        if ($argentina !== null) {
            $appellations[] = ['name' => 'Mendoza GI', 'country_id' => $argentina->id, 'region_id' => $r('Mendoza', $argentina), 'system' => AppellationSystem::GI];
            $appellations[] = ['name' => 'Uco Valley GI', 'country_id' => $argentina->id, 'region_id' => $r('Uco Valley', $argentina), 'system' => AppellationSystem::GI];
        }

        // =================================================================
        // CHILEAN APPELLATIONS (DO)
        // =================================================================
        if ($chile !== null) {
            $appellations[] = ['name' => 'Maipo Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Maipo Valley', $chile), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Colchagua Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Colchagua Valley', $chile), 'system' => AppellationSystem::DO];
            $appellations[] = ['name' => 'Aconcagua Valley DO', 'country_id' => $chile->id, 'region_id' => $r('Aconcagua Valley', $chile), 'system' => AppellationSystem::DO];
        }

        // =================================================================
        // HUNGARIAN APPELLATIONS
        // =================================================================
        if ($hungary !== null) {
            $appellations[] = ['name' => 'Tokaj AOC', 'country_id' => $hungary->id, 'region_id' => $r('Tokaj', $hungary), 'system' => AppellationSystem::Other];
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
