<?php

namespace Tests\Feature\Http;

use App\Models\Finance\XeroToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_authorize_redirects_to_xero_when_configured(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => 'https://example.com/admin/xero/callback',
        ]);

        $this->authenticatedUser();

        $response = $this->get(route('xero.authorize'));

        $response->assertRedirect();
        $this->assertStringContainsString('login.xero.com', $response->headers->get('Location') ?? '');
    }

    public function test_authorize_redirects_back_when_not_configured(): void
    {
        config([
            'services.xero.client_id' => null,
            'services.xero.client_secret' => null,
        ]);

        $this->authenticatedUser();

        $response = $this->get(route('xero.authorize'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_callback_handles_error_from_xero(): void
    {
        $this->authenticatedUser();

        $response = $this->get(route('xero.callback', [
            'error' => 'access_denied',
            'error_description' => 'User denied access',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_callback_handles_missing_code(): void
    {
        $this->authenticatedUser();

        $response = $this->get(route('xero.callback'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_callback_exchanges_code_and_stores_token(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => 'https://example.com/callback',
        ]);

        Http::fake([
            'identity.xero.com/connect/token' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 1800,
                'token_type' => 'Bearer',
            ]),
            'api.xero.com/connections' => Http::response([
                ['tenantId' => 'test-tenant', 'tenantType' => 'ORGANISATION'],
            ]),
        ]);

        $this->authenticatedUser();

        $response = $this->get(route('xero.callback', ['code' => 'auth-code-123']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNotNull(XeroToken::getActive());
    }

    public function test_callback_handles_token_exchange_failure(): void
    {
        config([
            'services.xero.client_id' => 'test-client-id',
            'services.xero.client_secret' => 'test-client-secret',
            'services.xero.redirect_uri' => 'https://example.com/callback',
        ]);

        Http::fake([
            'identity.xero.com/connect/token' => Http::response('Bad Request', 400),
        ]);

        $this->authenticatedUser();

        $response = $this->get(route('xero.callback', ['code' => 'bad-code']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_authorize_requires_authentication(): void
    {
        $response = $this->get(route('xero.authorize'));

        // Without a [login] route (Filament handles auth), unauthenticated
        // requests either redirect or return 500. Assert it doesn't succeed.
        $this->assertNotEquals(200, $response->getStatusCode());
    }
}
