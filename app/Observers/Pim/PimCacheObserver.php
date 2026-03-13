<?php

namespace App\Observers\Pim;

use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Services\Pim\PimCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observes PIM model changes and invalidates relevant caches.
 *
 * Registered for: Country, Region, Producer, Appellation.
 */
class PimCacheObserver
{
    public function __construct(protected PimCacheService $cache) {}

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    protected function invalidate(Model $model): void
    {
        match (true) {
            $model instanceof Country => $this->cache->clearAll(),
            $model instanceof Region => $this->onRegionChange(),
            $model instanceof Producer => $this->cache->clearProducerCache(),
            $model instanceof Appellation => $this->cache->clearAppellationCache(),
            default => null,
        };
    }

    protected function onRegionChange(): void
    {
        $this->cache->clearRegionCache();
        $this->cache->clearAppellationCache();
    }
}
