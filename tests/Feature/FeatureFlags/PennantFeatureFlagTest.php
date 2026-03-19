<?php

namespace Tests\Feature\FeatureFlags;

use App\Features\AiChat;
use App\Features\LivExIntegration;
use App\Features\NftMinting;
use App\Features\StripeWebhooks;
use App\Features\XeroSync;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Finance\XeroApiClient;
use App\Services\Finance\XeroIntegrationService;
use App\Services\LivExService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PennantFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Feature::flushCache();
    }

    // =========================================================================
    // Feature Defaults
    // =========================================================================

    public function test_xero_sync_defaults_to_config_value_true(): void
    {
        config(['finance.xero.sync_enabled' => true]);

        $this->assertTrue(Feature::for(null)->active(XeroSync::class));
    }

    public function test_xero_sync_defaults_to_config_value_false(): void
    {
        config(['finance.xero.sync_enabled' => false]);

        $this->assertFalse(Feature::for(null)->active(XeroSync::class));
    }

    public function test_livex_defaults_to_api_key_present(): void
    {
        config(['services.livex.api_key' => 'test-key']);

        $this->assertTrue(Feature::for(null)->active(LivExIntegration::class));
    }

    public function test_livex_defaults_to_api_key_absent(): void
    {
        config(['services.livex.api_key' => null]);

        $this->assertFalse(Feature::for(null)->active(LivExIntegration::class));
    }

    public function test_stripe_defaults_to_keys_configured(): void
    {
        config([
            'services.stripe.key' => 'pk_test_123',
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => 'whsec_123',
        ]);

        $this->assertTrue(Feature::for(null)->active(StripeWebhooks::class));
    }

    public function test_stripe_defaults_to_keys_missing(): void
    {
        config(['services.stripe.key' => null]);

        $this->assertFalse(Feature::for(null)->active(StripeWebhooks::class));
    }

    public function test_nft_minting_defaults_to_false(): void
    {
        config(['features.nft_minting_enabled' => false]);

        $this->assertFalse(Feature::for(null)->active(NftMinting::class));
    }

    public function test_ai_chat_defaults_to_true(): void
    {
        config(['features.ai_chat_enabled' => true]);

        $this->assertTrue(Feature::for(null)->active(AiChat::class));
    }

    // =========================================================================
    // Scoped Activate/Deactivate Overrides
    // =========================================================================

    public function test_activate_overrides_default(): void
    {
        config(['features.nft_minting_enabled' => false]);

        // Default should be false
        $this->assertFalse(Feature::for(null)->active(NftMinting::class));

        // Activate for null scope overrides
        Feature::for(null)->activate(NftMinting::class);

        $this->assertTrue(Feature::for(null)->active(NftMinting::class));
    }

    public function test_deactivate_overrides_default(): void
    {
        config(['features.ai_chat_enabled' => true]);

        $this->assertTrue(Feature::for(null)->active(AiChat::class));

        Feature::for(null)->deactivate(AiChat::class);

        $this->assertFalse(Feature::for(null)->active(AiChat::class));
    }

    public function test_purge_resets_to_default(): void
    {
        config(['features.ai_chat_enabled' => true]);

        Feature::for(null)->deactivate(AiChat::class);
        $this->assertFalse(Feature::for(null)->active(AiChat::class));

        Feature::purge(AiChat::class);
        $this->assertTrue(Feature::for(null)->active(AiChat::class));
    }

    // =========================================================================
    // Service Integration
    // =========================================================================

    public function test_xero_service_respects_pennant_flag_disabled(): void
    {
        config(['finance.xero.sync_enabled' => true]);
        Feature::for(null)->deactivate(XeroSync::class);

        $service = new XeroIntegrationService(app(XeroApiClient::class));

        $this->assertInstanceOf(XeroIntegrationService::class, $service);
    }

    public function test_livex_service_respects_pennant_flag_disabled(): void
    {
        config(['services.livex.api_key' => 'test-key']);
        Feature::for(null)->deactivate(LivExIntegration::class);

        $service = new LivExService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_livex_service_configured_when_pennant_enabled(): void
    {
        config(['services.livex.api_key' => 'test-key']);
        Feature::for(null)->activate(LivExIntegration::class);

        $service = new LivExService;
        $this->assertTrue($service->isConfigured());
    }

    // =========================================================================
    // Controller Integration
    // =========================================================================

    public function test_stripe_webhook_returns_503_when_disabled(): void
    {
        Feature::for(null)->deactivate(StripeWebhooks::class);

        $response = $this->postJson('/api/webhooks/stripe', ['test' => 'data']);

        $response->assertStatus(503);
        $response->assertJson(['message' => 'Stripe webhooks are currently disabled']);
    }

    public function test_ai_chat_returns_503_when_disabled(): void
    {
        $user = User::factory()->admin()->create();
        Feature::for(null)->deactivate(AiChat::class);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => 'test',
        ]);

        $response->assertStatus(503);
        $response->assertJson(['message' => 'AI Chat is currently disabled']);
    }

    // =========================================================================
    // NFT Job Integration
    // =========================================================================

    public function test_nft_job_skips_when_flag_disabled(): void
    {
        Feature::for(null)->deactivate(NftMinting::class);

        $bottle = SerializedBottle::factory()->create([
            'nft_reference' => null,
            'nft_minted_at' => null,
        ]);

        $job = new \App\Jobs\Inventory\MintProvenanceNftJob($bottle);
        $job->handle();

        $bottle->refresh();
        $this->assertNull($bottle->nft_reference);
        $this->assertNull($bottle->nft_minted_at);
    }

    // =========================================================================
    // Filament Page Access
    // =========================================================================

    public function test_feature_flags_page_accessible_by_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/feature-flags');

        $response->assertOk();
    }

    public function test_feature_flags_page_accessible_by_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->get('/admin/feature-flags');

        $response->assertOk();
    }

    public function test_feature_flags_page_blocked_for_viewer(): void
    {
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/admin/feature-flags');

        $response->assertForbidden();
    }

    public function test_feature_flags_page_blocked_for_editor(): void
    {
        $editor = User::factory()->editor()->create();

        $response = $this->actingAs($editor)->get('/admin/feature-flags');

        $response->assertForbidden();
    }
}
