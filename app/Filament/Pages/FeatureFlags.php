<?php

namespace App\Filament\Pages;

use App\Features\AiChat;
use App\Features\LivExIntegration;
use App\Features\NftMinting;
use App\Features\StripeWebhooks;
use App\Features\XeroSync;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Laravel\Pennant\Feature;

/**
 * Feature Flags management page.
 *
 * Allows admins to toggle runtime feature flags via Laravel Pennant.
 * Each feature has an env/config fallback that serves as the default.
 * Once toggled here, the Pennant DB value takes precedence.
 */
class FeatureFlags extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 97;

    protected static ?string $title = 'Feature Flags';

    protected string $view = 'filament.pages.feature-flags';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user === null || $user->role === null) {
            return false;
        }

        return in_array($user->role->value, ['super_admin', 'admin'], true);
    }

    /**
     * Get all feature definitions with their current state.
     *
     * @return list<array{class: class-string, name: string, description: string, active: bool, env_fallback: string, has_override: bool}>
     */
    public function getFeatures(): array
    {
        return [
            [
                'class' => XeroSync::class,
                'name' => 'Xero Sync',
                'description' => 'Synchronize invoices, credit notes, and payments with Xero accounting.',
                'active' => Feature::for(null)->active(XeroSync::class),
                'env_fallback' => 'XERO_SYNC_ENABLED (config: finance.xero.sync_enabled)',
                'has_override' => $this->hasStoredValue(XeroSync::class),
            ],
            [
                'class' => LivExIntegration::class,
                'name' => 'Liv-ex Integration',
                'description' => 'Query Liv-ex wine market data for LWIN lookups and pricing.',
                'active' => Feature::for(null)->active(LivExIntegration::class),
                'env_fallback' => 'LIVEX_API_KEY (active when key is set)',
                'has_override' => $this->hasStoredValue(LivExIntegration::class),
            ],
            [
                'class' => StripeWebhooks::class,
                'name' => 'Stripe Webhooks',
                'description' => 'Accept and process incoming Stripe webhook events.',
                'active' => Feature::for(null)->active(StripeWebhooks::class),
                'env_fallback' => 'STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET',
                'has_override' => $this->hasStoredValue(StripeWebhooks::class),
            ],
            [
                'class' => NftMinting::class,
                'name' => 'NFT Minting',
                'description' => 'Mint provenance NFTs for serialized bottles on the blockchain.',
                'active' => Feature::for(null)->active(NftMinting::class),
                'env_fallback' => 'NFT_MINTING_ENABLED (default: false)',
                'has_override' => $this->hasStoredValue(NftMinting::class),
            ],
            [
                'class' => AiChat::class,
                'name' => 'AI Chat',
                'description' => 'AI-powered assistant for ERP queries and operations.',
                'active' => Feature::for(null)->active(AiChat::class),
                'env_fallback' => 'AI_CHAT_ENABLED (default: true)',
                'has_override' => $this->hasStoredValue(AiChat::class),
            ],
        ];
    }

    /**
     * Toggle a feature flag on or off globally.
     */
    public function toggleFeature(string $featureClass): void
    {
        if (Feature::for(null)->active($featureClass)) {
            Feature::for(null)->deactivate($featureClass);
            $state = 'disabled';
        } else {
            Feature::for(null)->activate($featureClass);
            $state = 'enabled';
        }

        $name = class_basename($featureClass);

        Notification::make()
            ->title("Feature {$state}")
            ->body("{$name} has been {$state} globally.")
            ->success()
            ->send();
    }

    /**
     * Reset a feature flag to its env/config default.
     */
    public function resetFeature(string $featureClass): void
    {
        Feature::purge($featureClass);

        $name = class_basename($featureClass);

        Notification::make()
            ->title('Feature reset')
            ->body("{$name} has been reset to its default value.")
            ->info()
            ->send();
    }

    /**
     * Check if a feature has a stored override in Pennant.
     */
    protected function hasStoredValue(string $featureClass): bool
    {
        $stored = Feature::for(null)->load($featureClass);

        // If the stored array has a value that differs from null, it has been overridden
        // Pennant stores values in array format: ['FeatureName' => value]
        // After purge, the value will be resolved fresh from the class
        return ! empty($stored);
    }
}
