<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Service for interacting with Liv-ex API.
 *
 * Note: This is a mock implementation for development purposes.
 * In production, this would integrate with the actual Liv-ex API.
 */
class LivExService
{
    /**
     * Fields that are locked when imported from Liv-ex.
     *
     * @var list<string>
     */
    public const LOCKED_FIELDS = [
        'name',
        'producer',
        'appellation',
        'country',
        'region',
        'vintage_year',
        'lwin_code',
    ];

    /**
     * Mock wine database for development.
     *
     * @var array<int, array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    private const MOCK_WINES = [
        [
            'lwin' => 'LWIN1100001',
            'name' => 'Sassicaia',
            'producer' => 'Tenuta San Guido',
            'vintage' => 2018,
            'appellation' => 'Bolgheri Sassicaia DOC',
            'country' => 'Italy',
            'region' => 'Tuscany',
            'classification' => 'Super Tuscan',
            'alcohol' => 14.0,
            'drinking_window_start' => 2025,
            'drinking_window_end' => 2045,
            'description' => 'Iconic Super Tuscan wine from Tenuta San Guido. Blend of Cabernet Sauvignon and Cabernet Franc.',
            'image_url' => 'https://images.vivino.com/thumbs/ApnQAQg_9yf5Wj9SYq7iDw_375x500.jpg',
        ],
        [
            'lwin' => 'LWIN1100002',
            'name' => 'Sassicaia',
            'producer' => 'Tenuta San Guido',
            'vintage' => 2019,
            'appellation' => 'Bolgheri Sassicaia DOC',
            'country' => 'Italy',
            'region' => 'Tuscany',
            'classification' => 'Super Tuscan',
            'alcohol' => 14.0,
            'drinking_window_start' => 2026,
            'drinking_window_end' => 2046,
            'description' => 'Outstanding vintage of the legendary Super Tuscan.',
            'image_url' => 'https://images.vivino.com/thumbs/ApnQAQg_9yf5Wj9SYq7iDw_375x500.jpg',
        ],
        [
            'lwin' => 'LWIN1100003',
            'name' => 'Tignanello',
            'producer' => 'Marchesi Antinori',
            'vintage' => 2019,
            'appellation' => 'Toscana IGT',
            'country' => 'Italy',
            'region' => 'Tuscany',
            'classification' => 'Super Tuscan',
            'alcohol' => 14.5,
            'drinking_window_start' => 2024,
            'drinking_window_end' => 2040,
            'description' => 'Pioneer of the Super Tuscan category. Sangiovese-based blend with Cabernet Sauvignon and Cabernet Franc.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN1100004',
            'name' => 'Ornellaia',
            'producer' => 'Tenuta dell\'Ornellaia',
            'vintage' => 2018,
            'appellation' => 'Bolgheri DOC Superiore',
            'country' => 'Italy',
            'region' => 'Tuscany',
            'classification' => 'Super Tuscan',
            'alcohol' => 14.5,
            'drinking_window_start' => 2025,
            'drinking_window_end' => 2050,
            'description' => 'Flagship wine of Tenuta dell\'Ornellaia. Bordeaux-style blend.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN2200001',
            'name' => 'Château Margaux',
            'producer' => 'Château Margaux',
            'vintage' => 2015,
            'appellation' => 'Margaux',
            'country' => 'France',
            'region' => 'Bordeaux',
            'classification' => 'First Growth',
            'alcohol' => 13.5,
            'drinking_window_start' => 2025,
            'drinking_window_end' => 2060,
            'description' => 'First Growth Bordeaux from the Margaux appellation. Exceptional 2015 vintage.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN2200002',
            'name' => 'Château Lafite Rothschild',
            'producer' => 'Château Lafite Rothschild',
            'vintage' => 2016,
            'appellation' => 'Pauillac',
            'country' => 'France',
            'region' => 'Bordeaux',
            'classification' => 'First Growth',
            'alcohol' => 13.0,
            'drinking_window_start' => 2028,
            'drinking_window_end' => 2065,
            'description' => 'Legendary First Growth from Pauillac. Elegant and refined.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN2200003',
            'name' => 'Château Latour',
            'producer' => 'Château Latour',
            'vintage' => 2010,
            'appellation' => 'Pauillac',
            'country' => 'France',
            'region' => 'Bordeaux',
            'classification' => 'First Growth',
            'alcohol' => 14.0,
            'drinking_window_start' => 2030,
            'drinking_window_end' => 2070,
            'description' => 'One of the greatest vintages of Château Latour. Immense aging potential.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN3300001',
            'name' => 'Opus One',
            'producer' => 'Opus One Winery',
            'vintage' => 2019,
            'appellation' => 'Napa Valley',
            'country' => 'USA',
            'region' => 'California',
            'classification' => null,
            'alcohol' => 14.5,
            'drinking_window_start' => 2025,
            'drinking_window_end' => 2045,
            'description' => 'Joint venture between Robert Mondavi and Baron Philippe de Rothschild. Bordeaux-style blend.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN3300002',
            'name' => 'Screaming Eagle',
            'producer' => 'Screaming Eagle',
            'vintage' => 2018,
            'appellation' => 'Napa Valley',
            'country' => 'USA',
            'region' => 'California',
            'classification' => 'Cult Wine',
            'alcohol' => 14.8,
            'drinking_window_start' => 2028,
            'drinking_window_end' => 2055,
            'description' => 'Iconic cult Cabernet Sauvignon from Oakville, Napa Valley.',
            'image_url' => null,
        ],
        [
            'lwin' => 'LWIN4400001',
            'name' => 'Barolo Monfortino Riserva',
            'producer' => 'Giacomo Conterno',
            'vintage' => 2014,
            'appellation' => 'Barolo DOCG',
            'country' => 'Italy',
            'region' => 'Piedmont',
            'classification' => 'Riserva',
            'alcohol' => 14.0,
            'drinking_window_start' => 2030,
            'drinking_window_end' => 2060,
            'description' => 'Legendary Barolo from the Monfortino cru. Extended aging in large oak barrels.',
            'image_url' => null,
        ],
    ];

    /**
     * Search Liv-ex wines by LWIN code or name.
     *
     * @return list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    public function search(string $query): array
    {
        if (empty(trim($query))) {
            return [];
        }

        $query = Str::lower(trim($query));
        $results = [];

        foreach (self::MOCK_WINES as $wine) {
            // Search by LWIN code (exact or partial match)
            if (Str::contains(Str::lower($wine['lwin']), $query)) {
                $results[] = $wine;

                continue;
            }

            // Search by name
            if (Str::contains(Str::lower($wine['name']), $query)) {
                $results[] = $wine;

                continue;
            }

            // Search by producer
            if (Str::contains(Str::lower($wine['producer']), $query)) {
                $results[] = $wine;

                continue;
            }

            // Search by appellation
            if (Str::contains(Str::lower($wine['appellation']), $query)) {
                $results[] = $wine;

                continue;
            }
        }

        return $results;
    }

    /**
     * Get wine details by LWIN code.
     *
     * @return array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    public function getByLwin(string $lwin): ?array
    {
        foreach (self::MOCK_WINES as $wine) {
            if ($wine['lwin'] === $lwin) {
                return $wine;
            }
        }

        return null;
    }

    /**
     * Get the list of fields that are locked when importing from Liv-ex.
     *
     * @return list<string>
     */
    public function getLockedFields(): array
    {
        return self::LOCKED_FIELDS;
    }
}
