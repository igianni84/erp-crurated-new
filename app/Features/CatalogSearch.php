<?php

namespace App\Features;

class CatalogSearch
{
    /**
     * Resolve the feature's initial value.
     *
     * Active when explicitly enabled via config.
     */
    public function resolve(mixed $scope): bool
    {
        return (bool) config('catalog-search.enabled', false);
    }
}
