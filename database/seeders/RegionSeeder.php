<?php

namespace Database\Seeders;

use App\Models\Pim\Country;
use App\Models\Pim\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Seed wine regions with hierarchical parent/child relationships.
     *
     * Covers all major fine wine trading regions (Tier 1 + Tier 2).
     */
    public function run(): void
    {
        $count = 0;

        // =================================================================
        // France (FR)
        // =================================================================
        $france = Country::where('iso_code', 'FR')->first();

        if ($france !== null) {
            // Bordeaux and sub-regions
            $bordeaux = $this->createRegion('Bordeaux', $france->id, null, 1);
            $this->createRegion('Medoc', $france->id, $bordeaux->id, 1);
            $this->createRegion('Pauillac', $france->id, $bordeaux->id, 2);
            $this->createRegion('Margaux', $france->id, $bordeaux->id, 3);
            $this->createRegion('Saint-Julien', $france->id, $bordeaux->id, 4);
            $this->createRegion('Saint-Estephe', $france->id, $bordeaux->id, 5);
            $this->createRegion('Graves', $france->id, $bordeaux->id, 6);
            $this->createRegion('Pessac-Leognan', $france->id, $bordeaux->id, 7);
            $this->createRegion('Saint-Emilion', $france->id, $bordeaux->id, 8);
            $this->createRegion('Pomerol', $france->id, $bordeaux->id, 9);
            $this->createRegion('Sauternes', $france->id, $bordeaux->id, 10);
            $this->createRegion('Barsac', $france->id, $bordeaux->id, 11);
            $this->createRegion('Haut-Medoc', $france->id, $bordeaux->id, 12);
            $this->createRegion('Moulis-en-Medoc', $france->id, $bordeaux->id, 13);
            $this->createRegion('Listrac-Medoc', $france->id, $bordeaux->id, 14);

            // Burgundy and sub-regions
            $burgundy = $this->createRegion('Burgundy', $france->id, null, 2);
            $coteDeNuits = $this->createRegion('Cote de Nuits', $france->id, $burgundy->id, 1);
            $coteDeBeaune = $this->createRegion('Cote de Beaune', $france->id, $burgundy->id, 2);
            $this->createRegion('Chablis', $france->id, $burgundy->id, 3);
            $this->createRegion('Maconnais', $france->id, $burgundy->id, 4);

            // Burgundy communes - Cote de Nuits
            $this->createRegion('Gevrey-Chambertin', $france->id, $coteDeNuits->id, 1);
            $this->createRegion('Vosne-Romanee', $france->id, $coteDeNuits->id, 2);
            $this->createRegion('Chambolle-Musigny', $france->id, $coteDeNuits->id, 3);
            $this->createRegion('Morey-Saint-Denis', $france->id, $coteDeNuits->id, 4);
            $this->createRegion('Nuits-Saint-Georges', $france->id, $coteDeNuits->id, 5);
            $this->createRegion('Vougeot', $france->id, $coteDeNuits->id, 6);
            $this->createRegion('Flagey-Echezeaux', $france->id, $coteDeNuits->id, 7);

            // Burgundy communes - Cote de Beaune
            $this->createRegion('Meursault', $france->id, $coteDeBeaune->id, 1);
            $this->createRegion('Puligny-Montrachet', $france->id, $coteDeBeaune->id, 2);
            $this->createRegion('Chassagne-Montrachet', $france->id, $coteDeBeaune->id, 3);
            $this->createRegion('Beaune', $france->id, $coteDeBeaune->id, 4);
            $this->createRegion('Pommard', $france->id, $coteDeBeaune->id, 5);
            $this->createRegion('Volnay', $france->id, $coteDeBeaune->id, 6);
            $this->createRegion('Aloxe-Corton', $france->id, $coteDeBeaune->id, 7);
            $this->createRegion('Savigny-les-Beaune', $france->id, $coteDeBeaune->id, 8);
            $this->createRegion('Saint-Aubin', $france->id, $coteDeBeaune->id, 9);

            // Champagne
            $this->createRegion('Champagne', $france->id, null, 3);

            // Rhone Valley and sub-regions
            $rhone = $this->createRegion('Rhone Valley', $france->id, null, 4);
            $this->createRegion('Northern Rhone', $france->id, $rhone->id, 1);
            $this->createRegion('Southern Rhone', $france->id, $rhone->id, 2);

            // Loire Valley
            $this->createRegion('Loire Valley', $france->id, null, 5);

            // Alsace
            $this->createRegion('Alsace', $france->id, null, 6);

            // Languedoc-Roussillon
            $this->createRegion('Languedoc-Roussillon', $france->id, null, 7);

            // Provence
            $this->createRegion('Provence', $france->id, null, 8);

            // Jura
            $this->createRegion('Jura', $france->id, null, 9);

            // Beaujolais
            $this->createRegion('Beaujolais', $france->id, null, 10);

            $count += 46;
        }

        // =================================================================
        // Italy (IT)
        // =================================================================
        $italy = Country::where('iso_code', 'IT')->first();

        if ($italy !== null) {
            // Piedmont and sub-regions
            $piedmont = $this->createRegion('Piedmont', $italy->id, null, 1);
            $this->createRegion('Langhe', $italy->id, $piedmont->id, 1);
            $this->createRegion('Barolo', $italy->id, $piedmont->id, 2);
            $this->createRegion('Barbaresco', $italy->id, $piedmont->id, 3);
            $this->createRegion('Roero', $italy->id, $piedmont->id, 4);

            // Tuscany and sub-regions
            $tuscany = $this->createRegion('Tuscany', $italy->id, null, 2);
            $this->createRegion('Montalcino', $italy->id, $tuscany->id, 1);
            $this->createRegion('Bolgheri', $italy->id, $tuscany->id, 2);
            $this->createRegion('Chianti Classico', $italy->id, $tuscany->id, 3);
            $this->createRegion('Maremma', $italy->id, $tuscany->id, 4);
            $this->createRegion('Montepulciano', $italy->id, $tuscany->id, 5);

            // Veneto and sub-regions
            $veneto = $this->createRegion('Veneto', $italy->id, null, 3);
            $this->createRegion('Valpolicella', $italy->id, $veneto->id, 1);
            $this->createRegion('Soave', $italy->id, $veneto->id, 2);

            // Lombardy and sub-regions
            $lombardy = $this->createRegion('Lombardy', $italy->id, null, 4);
            $this->createRegion('Franciacorta', $italy->id, $lombardy->id, 1);

            // Sicily and sub-regions
            $sicily = $this->createRegion('Sicily', $italy->id, null, 5);
            $this->createRegion('Etna', $italy->id, $sicily->id, 1);

            // Campania
            $this->createRegion('Campania', $italy->id, null, 6);

            // Trentino-Alto Adige
            $this->createRegion('Trentino-Alto Adige', $italy->id, null, 7);

            // Abruzzo
            $this->createRegion('Abruzzo', $italy->id, null, 8);

            // Friuli Venezia Giulia
            $this->createRegion('Friuli Venezia Giulia', $italy->id, null, 9);

            // Umbria
            $this->createRegion('Umbria', $italy->id, null, 10);

            $count += 24;
        }

        // =================================================================
        // Spain (ES)
        // =================================================================
        $spain = Country::where('iso_code', 'ES')->first();

        if ($spain !== null) {
            $this->createRegion('Rioja', $spain->id, null, 1);
            $this->createRegion('Ribera del Duero', $spain->id, null, 2);
            $this->createRegion('Priorat', $spain->id, null, 3);
            $this->createRegion('Jerez', $spain->id, null, 4);
            $this->createRegion('Rias Baixas', $spain->id, null, 5);
            $this->createRegion('Toro', $spain->id, null, 6);
            $this->createRegion('Bierzo', $spain->id, null, 7);
            $this->createRegion('Penedes', $spain->id, null, 8);

            $count += 8;
        }

        // =================================================================
        // Portugal (PT)
        // =================================================================
        $portugal = Country::where('iso_code', 'PT')->first();

        if ($portugal !== null) {
            $this->createRegion('Douro', $portugal->id, null, 1);
            $this->createRegion('Alentejo', $portugal->id, null, 2);
            $this->createRegion('Dao', $portugal->id, null, 3);
            $this->createRegion('Madeira', $portugal->id, null, 4);

            $count += 4;
        }

        // =================================================================
        // Germany (DE)
        // =================================================================
        $germany = Country::where('iso_code', 'DE')->first();

        if ($germany !== null) {
            $this->createRegion('Mosel', $germany->id, null, 1);
            $this->createRegion('Rheingau', $germany->id, null, 2);
            $this->createRegion('Pfalz', $germany->id, null, 3);
            $this->createRegion('Nahe', $germany->id, null, 4);
            $this->createRegion('Rheinhessen', $germany->id, null, 5);
            $this->createRegion('Baden', $germany->id, null, 6);

            $count += 6;
        }

        // =================================================================
        // Austria (AT)
        // =================================================================
        $austria = Country::where('iso_code', 'AT')->first();

        if ($austria !== null) {
            $this->createRegion('Wachau', $austria->id, null, 1);
            $this->createRegion('Burgenland', $austria->id, null, 2);
            $this->createRegion('Kamptal', $austria->id, null, 3);
            $this->createRegion('Kremstal', $austria->id, null, 4);

            $count += 4;
        }

        // =================================================================
        // United States (US)
        // =================================================================
        $us = Country::where('iso_code', 'US')->first();

        if ($us !== null) {
            // California and sub-regions
            $california = $this->createRegion('California', $us->id, null, 1);
            $napaValley = $this->createRegion('Napa Valley', $us->id, $california->id, 1);
            $sonoma = $this->createRegion('Sonoma', $us->id, $california->id, 2);
            $this->createRegion('Paso Robles', $us->id, $california->id, 3);
            $this->createRegion('Santa Cruz Mountains', $us->id, $california->id, 4);
            $this->createRegion('Santa Barbara County', $us->id, $california->id, 5);

            // Napa Valley sub-AVAs
            $this->createRegion('Rutherford', $us->id, $napaValley->id, 1);
            $this->createRegion('Oakville', $us->id, $napaValley->id, 2);
            $this->createRegion('Stags Leap District', $us->id, $napaValley->id, 3);
            $this->createRegion('Howell Mountain', $us->id, $napaValley->id, 4);

            // Sonoma sub-AVAs
            $this->createRegion('Russian River Valley', $us->id, $sonoma->id, 1);
            $this->createRegion('Sonoma Coast', $us->id, $sonoma->id, 2);

            // Oregon and sub-regions
            $oregon = $this->createRegion('Oregon', $us->id, null, 2);
            $this->createRegion('Willamette Valley', $us->id, $oregon->id, 1);

            // Washington State
            $this->createRegion('Washington State', $us->id, null, 3);

            $count += 15;
        }

        // =================================================================
        // Australia (AU)
        // =================================================================
        $australia = Country::where('iso_code', 'AU')->first();

        if ($australia !== null) {
            // South Australia and sub-regions
            $southAustralia = $this->createRegion('South Australia', $australia->id, null, 1);
            $this->createRegion('Barossa Valley', $australia->id, $southAustralia->id, 1);
            $this->createRegion('McLaren Vale', $australia->id, $southAustralia->id, 2);
            $this->createRegion('Clare Valley', $australia->id, $southAustralia->id, 3);
            $this->createRegion('Eden Valley', $australia->id, $southAustralia->id, 4);
            $this->createRegion('Coonawarra', $australia->id, $southAustralia->id, 5);
            $this->createRegion('Adelaide Hills', $australia->id, $southAustralia->id, 6);

            // Western Australia and sub-regions
            $westernAustralia = $this->createRegion('Western Australia', $australia->id, null, 2);
            $this->createRegion('Margaret River', $australia->id, $westernAustralia->id, 1);

            // Victoria and sub-regions
            $victoria = $this->createRegion('Victoria', $australia->id, null, 3);
            $this->createRegion('Yarra Valley', $australia->id, $victoria->id, 1);

            // New South Wales and sub-regions
            $nsw = $this->createRegion('New South Wales', $australia->id, null, 4);
            $this->createRegion('Hunter Valley', $australia->id, $nsw->id, 1);

            // Tasmania
            $this->createRegion('Tasmania', $australia->id, null, 5);

            $count += 14;
        }

        // =================================================================
        // New Zealand (NZ)
        // =================================================================
        $nz = Country::where('iso_code', 'NZ')->first();

        if ($nz !== null) {
            $this->createRegion('Marlborough', $nz->id, null, 1);
            $this->createRegion('Central Otago', $nz->id, null, 2);
            $this->createRegion("Hawke's Bay", $nz->id, null, 3);
            $this->createRegion('Martinborough', $nz->id, null, 4);

            $count += 4;
        }

        // =================================================================
        // South Africa (ZA)
        // =================================================================
        $za = Country::where('iso_code', 'ZA')->first();

        if ($za !== null) {
            $this->createRegion('Stellenbosch', $za->id, null, 1);
            $this->createRegion('Swartland', $za->id, null, 2);
            $this->createRegion('Constantia', $za->id, null, 3);
            $this->createRegion('Hemel-en-Aarde', $za->id, null, 4);
            $this->createRegion('Franschhoek', $za->id, null, 5);

            $count += 5;
        }

        // =================================================================
        // Argentina (AR)
        // =================================================================
        $argentina = Country::where('iso_code', 'AR')->first();

        if ($argentina !== null) {
            $mendoza = $this->createRegion('Mendoza', $argentina->id, null, 1);
            $this->createRegion('Lujan de Cuyo', $argentina->id, $mendoza->id, 1);
            $this->createRegion('Uco Valley', $argentina->id, $mendoza->id, 2);
            $this->createRegion('Patagonia', $argentina->id, null, 2);

            $count += 4;
        }

        // =================================================================
        // Chile (CL)
        // =================================================================
        $chile = Country::where('iso_code', 'CL')->first();

        if ($chile !== null) {
            $this->createRegion('Maipo Valley', $chile->id, null, 1);
            $this->createRegion('Colchagua Valley', $chile->id, null, 2);
            $this->createRegion('Casablanca Valley', $chile->id, null, 3);
            $this->createRegion('Aconcagua Valley', $chile->id, null, 4);

            $count += 4;
        }

        // =================================================================
        // Lebanon (LB)
        // =================================================================
        $lebanon = Country::where('iso_code', 'LB')->first();

        if ($lebanon !== null) {
            $this->createRegion('Bekaa Valley', $lebanon->id, null, 1);

            $count += 1;
        }

        // =================================================================
        // Hungary (HU)
        // =================================================================
        $hungary = Country::where('iso_code', 'HU')->first();

        if ($hungary !== null) {
            $this->createRegion('Tokaj', $hungary->id, null, 1);

            $count += 1;
        }

        // =================================================================
        // Greece (GR)
        // =================================================================
        $greece = Country::where('iso_code', 'GR')->first();

        if ($greece !== null) {
            $this->createRegion('Santorini', $greece->id, null, 1);
            $this->createRegion('Naoussa', $greece->id, null, 2);

            $count += 2;
        }

        $this->command->info("Created {$count} wine regions.");
    }

    /**
     * Create a region using firstOrCreate.
     */
    private function createRegion(string $name, string $countryId, ?string $parentRegionId, int $sortOrder): Region
    {
        return Region::firstOrCreate(
            [
                'name' => $name,
                'country_id' => $countryId,
                'parent_region_id' => $parentRegionId,
            ],
            [
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }
}
