<?php

namespace App\Features;

class LivExIntegration
{
    /**
     * Resolve the feature's initial value.
     *
     * Active when Liv-ex API key is configured.
     */
    public function resolve(mixed $scope): bool
    {
        return ! empty(config('services.livex.api_key'));
    }
}
