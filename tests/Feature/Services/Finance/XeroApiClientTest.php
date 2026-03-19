<?php

namespace Tests\Feature\Services\Finance;

use App\Models\Finance\XeroToken;
use App\Services\Finance\XeroApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroApiClientTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // isConfigured
    // =========================================================================

    public function test_is_configured_returns_false_when_no_credentials(): void
    {
        config([
            'services.xero.client_id' => null,
            'services.xero.client_secret' => null,
        ]);

        $client = new XeroApiClient;

        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_true_when_credentials_set(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
        ]);

        $client = new XeroApiClient;

        $this->assertTrue($client->isConfigured());
    }

    // =========================================================================
    // isConnected
    // =========================================================================

    public function test_is_connected_returns_false_when_not_configured(): void
    {
        config([
            'services.xero.client_id' => null,
            'services.xero.client_secret' => null,
        ]);

        $client = new XeroApiClient;

        $this->assertFalse($client->isConnected());
    }

    public function test_is_connected_returns_false_when_no_token(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
        ]);

        $client = new XeroApiClient;

        $this->assertFalse($client->isConnected());
    }

    public function test_is_connected_returns_true_with_valid_token(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
        ]);

        XeroToken::factory()->create();

        $client = new XeroApiClient;

        $this->assertTrue($client->isConnected());
    }

    public function test_is_connected_returns_false_with_expired_token(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
        ]);

        XeroToken::factory()->expired()->create();

        $client = new XeroApiClient;

        $this->assertFalse($client->isConnected());
    }

    // =========================================================================
    // getTenantId
    // =========================================================================

    public function test_get_tenant_id_returns_token_tenant_id(): void
    {
        $token = XeroToken::factory()->create([
            'tenant_id' => 'token-tenant-123',
        ]);

        config(['services.xero.tenant_id' => 'config-tenant-456']);

        $client = new XeroApiClient;

        $this->assertSame('token-tenant-123', $client->getTenantId());
    }

    public function test_get_tenant_id_falls_back_to_config(): void
    {
        config(['services.xero.tenant_id' => 'config-tenant-456']);

        $client = new XeroApiClient;

        $this->assertSame('config-tenant-456', $client->getTenantId());
    }

    // =========================================================================
    // exchangeCodeForToken
    // =========================================================================

    public function test_exchange_code_stores_token(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => 'https://example.com/callback',
        ]);

        Http::fake([
            'identity.xero.com/connect/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 1800,
                'token_type' => 'Bearer',
            ]),
            'api.xero.com/connections' => Http::response([
                ['tenantId' => 'discovered-tenant-id', 'tenantType' => 'ORGANISATION'],
            ]),
        ]);

        $client = new XeroApiClient;
        $result = $client->exchangeCodeForToken('auth-code-123');

        $this->assertSame('discovered-tenant-id', $result['tenant_id']);

        $token = XeroToken::getActive();
        $this->assertNotNull($token);
        $this->assertTrue($token->is_active);
        $this->assertSame('discovered-tenant-id', $token->tenant_id);
    }

    public function test_exchange_code_throws_on_failure(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => 'https://example.com/callback',
        ]);

        Http::fake([
            'identity.xero.com/connect/token' => Http::response('Unauthorized', 401),
        ]);

        $client = new XeroApiClient;

        $this->expectException(\RuntimeException::class);
        $client->exchangeCodeForToken('bad-code');
    }

    // =========================================================================
    // disconnect
    // =========================================================================

    public function test_disconnect_deactivates_tokens(): void
    {
        XeroToken::factory()->create();

        $client = new XeroApiClient;
        $client->disconnect();

        $this->assertNull(XeroToken::getActive());
    }

    // =========================================================================
    // getAuthorizationUrl
    // =========================================================================

    public function test_get_authorization_url_contains_client_id(): void
    {
        config([
            'services.xero.client_id' => 'my-client-id',
            'services.xero.redirect_uri' => 'https://example.com/callback',
        ]);

        $client = new XeroApiClient;
        $url = $client->getAuthorizationUrl();

        $this->assertStringContainsString('my-client-id', $url);
        $this->assertStringContainsString('login.xero.com', $url);
        $this->assertStringContainsString('accounting.transactions', $url);
    }
}
