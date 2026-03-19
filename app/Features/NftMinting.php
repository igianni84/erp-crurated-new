<?php

namespace App\Features;

class NftMinting
{
    /**
     * Resolve the feature's initial value.
     *
     * Disabled by default until blockchain integration is ready.
     */
    public function resolve(mixed $scope): bool
    {
        return (bool) config('features.nft_minting_enabled', false);
    }
}
