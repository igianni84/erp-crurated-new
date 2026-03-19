<?php

namespace App\Services;

use App\Features\LivExIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Service for interacting with Liv-ex API.
 *
 * When configured (LIVEX_API_KEY set), queries the real Liv-ex API with caching.
 * When not configured, falls back to mock data for development.
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

    protected string $baseUrl;

    protected ?string $apiKey;

    protected int $searchTtl;

    protected int $detailTtl;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.livex.api_url', 'https://api.liv-ex.com');
        $this->apiKey = config('services.livex.api_key');
        $this->searchTtl = (int) config('services.livex.cache_search_ttl', 3600);
        $this->detailTtl = (int) config('services.livex.cache_detail_ttl', 86400);
    }

    /**
     * Check if the Liv-ex API is configured with credentials.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && Feature::for(null)->active(LivExIntegration::class);
    }

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

        if (! $this->isConfigured()) {
            return $this->mockSearch($query);
        }

        $cacheKey = 'livex:search:'.md5(Str::lower(trim($query)));

        /** @var list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}> $results */
        $results = Cache::remember($cacheKey, $this->searchTtl, function () use ($query): array {
            return $this->callSearchApi($query);
        });

        return $results;
    }

    /**
     * Get wine details by LWIN code.
     *
     * @return array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    public function getByLwin(string $lwin): ?array
    {
        if (! $this->isConfigured()) {
            return $this->mockGetByLwin($lwin);
        }

        $cacheKey = 'livex:detail:'.$lwin;

        /** @var array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null $result */
        $result = Cache::remember($cacheKey, $this->detailTtl, function () use ($lwin): ?array {
            return $this->callDetailApi($lwin);
        });

        return $result;
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

    /**
     * Call the Liv-ex search API.
     *
     * @return list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    protected function callSearchApi(string $query): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(10)
                ->get('/v1/search', [
                    'query' => trim($query),
                ]);

            if ($response->failed()) {
                Log::warning('Liv-ex search API returned error', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            return $this->normalizeSearchResults($response->json('results', []));
        } catch (\Throwable $e) {
            Log::error('Liv-ex search API call failed', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * Call the Liv-ex detail API for a specific LWIN.
     *
     * @return array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    protected function callDetailApi(string $lwin): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(10)
                ->get('/v1/wines/'.$lwin);

            if ($response->failed()) {
                Log::warning('Liv-ex detail API returned error', [
                    'status' => $response->status(),
                    'lwin' => $lwin,
                ]);

                return null;
            }

            return $this->normalizeWineData($response->json());
        } catch (\Throwable $e) {
            Log::error('Liv-ex detail API call failed', [
                'error' => $e->getMessage(),
                'lwin' => $lwin,
            ]);

            return null;
        }
    }

    /**
     * Normalize search results from the Liv-ex API response format.
     *
     * @param  array<int, mixed>  $results
     * @return list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    protected function normalizeSearchResults(array $results): array
    {
        $normalized = [];

        foreach ($results as $item) {
            if (! is_array($item)) {
                continue;
            }

            $wine = $this->normalizeWineData($item);
            if ($wine !== null) {
                $normalized[] = $wine;
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single wine record from the Liv-ex API.
     *
     * @return array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    protected function normalizeWineData(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $lwin = $data['lwin'] ?? $data['lwin_code'] ?? null;
        $name = $data['name'] ?? $data['wine_name'] ?? null;

        if ($lwin === null || $name === null) {
            return null;
        }

        return [
            'lwin' => (string) $lwin,
            'name' => (string) $name,
            'producer' => (string) ($data['producer'] ?? $data['producer_name'] ?? ''),
            'vintage' => (int) ($data['vintage'] ?? $data['vintage_year'] ?? 0),
            'appellation' => (string) ($data['appellation'] ?? $data['sub_region'] ?? ''),
            'country' => (string) ($data['country'] ?? ''),
            'region' => (string) ($data['region'] ?? ''),
            'classification' => isset($data['classification']) ? (string) $data['classification'] : null,
            'alcohol' => isset($data['alcohol']) ? (float) $data['alcohol'] : null,
            'drinking_window_start' => isset($data['drinking_window_start']) ? (int) $data['drinking_window_start'] : null,
            'drinking_window_end' => isset($data['drinking_window_end']) ? (int) $data['drinking_window_end'] : null,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'image_url' => isset($data['image_url']) ? (string) $data['image_url'] : null,
        ];
    }

    // =========================================================================
    // Mock Data (fallback when API is not configured)
    // =========================================================================

    /**
     * @var list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
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
     * Mock search for development.
     *
     * @return list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    protected function mockSearch(string $query): array
    {
        $query = Str::lower(trim($query));
        $results = [];

        foreach (self::MOCK_WINES as $wine) {
            if (Str::contains(Str::lower($wine['lwin']), $query)) {
                $results[] = $wine;

                continue;
            }

            if (Str::contains(Str::lower($wine['name']), $query)) {
                $results[] = $wine;

                continue;
            }

            if (Str::contains(Str::lower($wine['producer']), $query)) {
                $results[] = $wine;

                continue;
            }

            if (Str::contains(Str::lower($wine['appellation']), $query)) {
                $results[] = $wine;

                continue;
            }
        }

        return $results;
    }

    /**
     * Mock getByLwin for development.
     *
     * @return array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    protected function mockGetByLwin(string $lwin): ?array
    {
        foreach (self::MOCK_WINES as $wine) {
            if ($wine['lwin'] === $lwin) {
                return $wine;
            }
        }

        return null;
    }
}
