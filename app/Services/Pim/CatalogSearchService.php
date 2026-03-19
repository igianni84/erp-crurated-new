<?php

namespace App\Services\Pim;

use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CatalogSearchService
{
    /**
     * Search the wine catalog via Scout (WineVariant index).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WineVariant>
     */
    public function searchCatalog(
        string $query,
        array $filters = [],
        ?string $sort = null,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        $search = WineVariant::search($query);

        $meilisearchFilters = $this->buildMeilisearchFilters($filters);

        if ($meilisearchFilters !== '' && config('scout.driver') === 'meilisearch') {
            $search->options(['filter' => $meilisearchFilters]);
        }

        if ($sort !== null && config('scout.driver') === 'meilisearch') {
            $search->options(['sort' => [$this->buildSortString($sort)]]);
        }

        $useEloquentFilters = config('scout.driver') !== 'meilisearch';

        /** @var LengthAwarePaginator<int, WineVariant> $results */
        $results = $search->query(function ($builder) use ($filters, $useEloquentFilters): void {
            $builder->with(['wineMaster', 'sellableSkus' => function ($q): void {
                $q->where('lifecycle_status', SellableSku::STATUS_ACTIVE)
                    ->with('format');
            }]);

            if ($useEloquentFilters) {
                $this->applyEloquentFilters($builder, $filters);
            }
        })->paginate($perPage, 'page', $page);

        return $results;
    }

    /**
     * Search SKUs (for AI tools).
     *
     * @return Collection<int, SellableSku>
     */
    public function searchSkus(string $query, int $limit = 20): Collection
    {
        /** @var Collection<int, SellableSku> $results */
        $results = SellableSku::search($query)
            ->query(function ($builder): void {
                $builder->with(['wineVariant.wineMaster', 'format']);
            })
            ->get()
            ->take($limit);

        return $results;
    }

    /**
     * Build Meilisearch filter string from filter array.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildMeilisearchFilters(array $filters): string
    {
        $parts = [];

        if (isset($filters['country']) && $filters['country'] !== '') {
            $parts[] = 'country_name = "'.addslashes((string) $filters['country']).'"';
        }

        if (isset($filters['region']) && $filters['region'] !== '') {
            $parts[] = 'region_name = "'.addslashes((string) $filters['region']).'"';
        }

        if (isset($filters['producer']) && $filters['producer'] !== '') {
            $parts[] = 'producer_name = "'.addslashes((string) $filters['producer']).'"';
        }

        if (isset($filters['appellation']) && $filters['appellation'] !== '') {
            $parts[] = 'appellation_name = "'.addslashes((string) $filters['appellation']).'"';
        }

        if (isset($filters['vintage_min'])) {
            $parts[] = 'vintage_year >= '.(int) $filters['vintage_min'];
        }

        if (isset($filters['vintage_max'])) {
            $parts[] = 'vintage_year <= '.(int) $filters['vintage_max'];
        }

        if (isset($filters['format']) && $filters['format'] !== '') {
            $parts[] = 'format_names = "'.addslashes((string) $filters['format']).'"';
        }

        return implode(' AND ', $parts);
    }

    protected function buildSortString(string $sort): string
    {
        return match ($sort) {
            'vintage_asc' => 'vintage_year:asc',
            'vintage_desc' => 'vintage_year:desc',
            'name_asc' => 'wine_name:asc',
            'name_desc' => 'wine_name:desc',
            'newest' => 'created_at:desc',
            default => 'vintage_year:desc',
        };
    }

    /**
     * Apply filters as Eloquent constraints (for collection driver).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<WineVariant>  $builder
     * @param  array<string, mixed>  $filters
     */
    protected function applyEloquentFilters($builder, array $filters): void
    {
        if (isset($filters['country']) && $filters['country'] !== '') {
            $builder->whereHas('wineMaster', function ($q) use ($filters): void {
                $q->whereHas('countryRelation', function ($cq) use ($filters): void {
                    $cq->where('name', $filters['country']);
                })->orWhere('country', $filters['country']);
            });
        }

        if (isset($filters['vintage_min'])) {
            $builder->where('vintage_year', '>=', (int) $filters['vintage_min']);
        }

        if (isset($filters['vintage_max'])) {
            $builder->where('vintage_year', '<=', (int) $filters['vintage_max']);
        }
    }
}
