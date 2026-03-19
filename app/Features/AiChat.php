<?php

namespace App\Features;

class AiChat
{
    /**
     * Resolve the feature's initial value.
     *
     * Enabled by default.
     */
    public function resolve(mixed $scope): bool
    {
        return (bool) config('features.ai_chat_enabled', true);
    }
}
