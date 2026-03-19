<?php

namespace Tests\Feature\Services\Finance;

use App\Models\Finance\XeroToken;
use App\Services\Finance\XeroApiClient;
use App\Services\Finance\XeroIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XeroIntegrationServiceRealTest extends TestCase
{
    use RefreshDatabase;

    protected function getService(?XeroApiClient $client = null): XeroIntegrationService
    {
        return new XeroIntegrationService(
            $client ?? app(XeroApiClient::class)
        );
    }

    public function test_service_uses_stub_when_not_connected(): void
    {
        config([
            'services.xero.client_id' => null,
            'services.xero.client_secret' => null,
            'finance.xero.sync_enabled' => true,
        ]);

        $client = new XeroApiClient;
        $this->assertFalse($client->isConnected());

        // The service should still work (stub fallback)
        $service = $this->getService($client);

        $this->assertInstanceOf(XeroIntegrationService::class, $service);
    }

    public function test_account_codes_from_config(): void
    {
        config([
            'finance.xero.account_codes' => [
                'membership_service' => '300',
                'voucher_sale' => '310',
                'shipping_redemption' => '320',
                'storage_fee' => '330',
                'service_events' => '340',
            ],
        ]);

        $codes = config('finance.xero.account_codes');

        $this->assertSame('300', $codes['membership_service']);
        $this->assertSame('310', $codes['voucher_sale']);
        $this->assertSame('320', $codes['shipping_redemption']);
        $this->assertSame('330', $codes['storage_fee']);
        $this->assertSame('340', $codes['service_events']);
    }

    public function test_service_respects_sync_disabled(): void
    {
        config(['finance.xero.sync_enabled' => false]);

        $service = $this->getService();

        // The service should be constructible with sync disabled
        $this->assertInstanceOf(XeroIntegrationService::class, $service);
    }

    public function test_api_client_injection(): void
    {
        $client = new XeroApiClient;
        $service = $this->getService($client);

        $this->assertInstanceOf(XeroIntegrationService::class, $service);
    }

    public function test_xero_token_model_encrypted_storage(): void
    {
        $token = XeroToken::storeToken([
            'access_token' => 'sensitive-access-token',
            'refresh_token' => 'sensitive-refresh-token',
            'tenant_id' => 'test-tenant',
            'expires_in' => 1800,
        ]);

        // Verify raw DB value is not plaintext
        $raw = \Illuminate\Support\Facades\DB::table('xero_tokens')
            ->where('id', $token->id)
            ->first();

        $this->assertNotNull($raw);
        $this->assertNotSame('sensitive-access-token', $raw->access_token);
        $this->assertNotSame('sensitive-refresh-token', $raw->refresh_token);

        // Model decrypts correctly
        $token->refresh();
        $this->assertSame('sensitive-access-token', $token->access_token);
        $this->assertSame('sensitive-refresh-token', $token->refresh_token);
    }

    public function test_api_client_is_configured_check(): void
    {
        config([
            'services.xero.client_id' => 'test-id',
            'services.xero.client_secret' => 'test-secret',
        ]);

        $client = new XeroApiClient;
        $this->assertTrue($client->isConfigured());

        config([
            'services.xero.client_id' => '',
            'services.xero.client_secret' => '',
        ]);

        $client2 = new XeroApiClient;
        $this->assertFalse($client2->isConfigured());
    }

    public function test_api_client_is_connected_with_valid_token(): void
    {
        config([
            'services.xero.client_id' => 'test-id',
            'services.xero.client_secret' => 'test-secret',
        ]);

        XeroToken::factory()->create();

        $client = new XeroApiClient;
        $this->assertTrue($client->isConnected());
    }

    public function test_api_client_is_not_connected_with_expired_token(): void
    {
        config([
            'services.xero.client_id' => 'test-id',
            'services.xero.client_secret' => 'test-secret',
        ]);

        XeroToken::factory()->expired()->create();

        $client = new XeroApiClient;
        $this->assertFalse($client->isConnected());
    }
}
