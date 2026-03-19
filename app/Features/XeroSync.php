<?php

namespace App\Features;

class XeroSync
{
    /**
     * Resolve the feature's initial value.
     *
     * Falls back to config/env when no Pennant override exists.
     */
    public function resolve(mixed $scope): bool
    {
        return (bool) config('finance.xero.sync_enabled', true);
    }
}
