<?php

namespace App\Services\Pim;

use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use Illuminate\Support\Facades\Cache;

/**
 * Caches static PIM lookup data (countries, regions, appellations, producers)
 * to reduce repetitive DB queries from Filament select dropdowns.
 *
 * Cache is invalidated automatically by PimCacheObserver when models change.
 */
class PimCacheService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('cache.pim_ttl', 3600);
    }

    /**
     * @return array<string, string> [id => name]
     */
    public function getActiveCountries(): array
    {
        return Cache::remember('pim:countries:active', $this->ttl, function (): array {
            return Country::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Country $c): array => [$c->id => $c->name])
                ->toArray();
        });
    }

    /**
     * @return array<string, string> [id => name]
     */
    public function getActiveProducers(): array
    {
        return Cache::remember('pim:producers:active', $this->ttl, function (): array {
            return Producer::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Producer $p): array => [$p->id => $p->name])
                ->toArray();
        });
    }

    /**
     * Regions for a country, with parent > child labels.
     *
     * @return array<string, string> [id => "Parent > Child" or "Name"]
     */
    public function getRegionsForCountry(string $countryId): array
    {
        return Cache::remember("pim:regions:{$countryId}", $this->ttl, function () use ($countryId): array {
            return Region::where('is_active', true)
                ->where('country_id', $countryId)
                ->with('parentRegion')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function (Region $r): array {
                    $parent = $r->parentRegion;
                    $label = $parent !== null
                        ? $parent->name.' > '.$r->name
                        : $r->name;

                    return [$r->id => $label];
                })
                ->toArray();
        });
    }

    /**
     * Simple region list (without parent labels) for sub-resource forms.
     *
     * @return array<string, string> [id => name]
     */
    public function getSimpleRegionsForCountry(string $countryId): array
    {
        return Cache::remember("pim:regions_simple:{$countryId}", $this->ttl, function () use ($countryId): array {
            return Region::where('is_active', true)
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Region $r): array => [$r->id => $r->name])
                ->toArray();
        });
    }

    /**
     * Appellations for a country, optionally filtered by region.
     *
     * @return array<string, string> [id => name]
     */
    public function getAppellationsForCountry(string $countryId, ?string $regionId = null): array
    {
        $key = $regionId !== null
            ? "pim:appellations:{$countryId}:{$regionId}"
            : "pim:appellations:{$countryId}:all";

        return Cache::remember($key, $this->ttl, function () use ($countryId, $regionId): array {
            $query = Appellation::where('is_active', true)
                ->where('country_id', $countryId);

            if ($regionId !== null) {
                $query->where(function ($q) use ($regionId): void {
                    $q->where('region_id', $regionId)
                        ->orWhereNull('region_id');
                });
            }

            return $query
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Appellation $a): array => [$a->id => $a->name])
                ->toArray();
        });
    }

    public function clearCountryCache(): void
    {
        Cache::forget('pim:countries:active');
    }

    public function clearProducerCache(): void
    {
        Cache::forget('pim:producers:active');
    }

    public function clearRegionCache(): void
    {
        // Clear all country-specific region caches by iterating active countries
        $countryIds = Country::where('is_active', true)->pluck('id');
        foreach ($countryIds as $id) {
            Cache::forget("pim:regions:{$id}");
            Cache::forget("pim:regions_simple:{$id}");
        }
    }

    public function clearAppellationCache(): void
    {
        // Clear all country-specific appellation caches
        $countryIds = Country::where('is_active', true)->pluck('id');
        foreach ($countryIds as $id) {
            Cache::forget("pim:appellations:{$id}:all");
            // Also clear region-filtered variants
            $regionIds = Region::where('country_id', $id)->pluck('id');
            foreach ($regionIds as $regionId) {
                Cache::forget("pim:appellations:{$id}:{$regionId}");
            }
        }
    }

    public function clearAll(): void
    {
        $this->clearCountryCache();
        $this->clearProducerCache();
        $this->clearRegionCache();
        $this->clearAppellationCache();
    }
}
