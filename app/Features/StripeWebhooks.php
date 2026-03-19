<?php

namespace App\Features;

class StripeWebhooks
{
    /**
     * Resolve the feature's initial value.
     *
     * Active when Stripe keys are configured.
     */
    public function resolve(mixed $scope): bool
    {
        return ! empty(config('services.stripe.key'))
            && ! empty(config('services.stripe.secret'))
            && ! empty(config('services.stripe.webhook_secret'));
    }
}
