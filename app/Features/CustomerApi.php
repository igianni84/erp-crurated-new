<?php

namespace App\Features;

class CustomerApi
{
    /**
     * Resolve the feature's initial value.
     *
     * Active when explicitly enabled via config.
     */
    public function resolve(mixed $scope): bool
    {
        return (bool) config('customer-api.enabled', false);
    }
}
