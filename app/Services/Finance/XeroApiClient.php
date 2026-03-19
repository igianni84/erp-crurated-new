<?php

namespace App\Services\Finance;

use App\Models\Finance\XeroToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Configuration;

/**
 * Thin wrapper around the Xero SDK.
 *
 * Handles OAuth2 token management (auto-refresh) and provides
 * a configured AccountingApi instance for the XeroIntegrationService.
 */
class XeroApiClient
{
    protected ?AccountingApi $accountingApi = null;

    /**
     * Check if Xero OAuth credentials are configured in env.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.xero.client_id'))
            && ! empty(config('services.xero.client_secret'));
    }

    /**
     * Check if there is a valid (active, non-expired) Xero connection.
     */
    public function isConnected(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = XeroToken::getActive();

        return $token !== null && ! $token->isExpired();
    }

    /**
     * Get the active tenant ID.
     */
    public function getTenantId(): ?string
    {
        $token = XeroToken::getActive();

        if ($token !== null && $token->tenant_id !== null) {
            return $token->tenant_id;
        }

        return config('services.xero.tenant_id');
    }

    /**
     * Get a configured AccountingApi instance.
     *
     * Automatically refreshes the token if it's about to expire.
     *
     * @throws RuntimeException If no active token exists or refresh fails
     */
    public function getAccountingApi(): AccountingApi
    {
        if ($this->accountingApi !== null) {
            return $this->accountingApi;
        }

        $token = XeroToken::getActive();

        if ($token === null) {
            throw new RuntimeException('No active Xero token. Please connect to Xero first.');
        }

        // Auto-refresh if expiring within 5 minutes
        if ($token->expiresWithin(5)) {
            $token = $this->refreshToken($token);
        }

        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($token->access_token);

        $this->accountingApi = new AccountingApi(null, $config);

        return $this->accountingApi;
    }

    /**
     * Build the OAuth2 authorization URL.
     */
    public function getAuthorizationUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => config('services.xero.redirect_uri'),
            'scope' => 'openid profile email accounting.transactions accounting.contacts offline_access',
            'state' => csrf_token(),
        ]);

        return 'https://login.xero.com/identity/connect/authorize?'.$params;
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException If token exchange fails
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.xero.client_id'),
                (string) config('services.xero.client_secret'),
            )
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.xero.redirect_uri'),
            ]);

        if ($response->failed()) {
            Log::channel('finance')->error('Xero token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to exchange Xero authorization code: '.$response->body());
        }

        $tokenData = $response->json();

        // Fetch tenant connections
        $tenantId = $this->fetchTenantId($tokenData['access_token']);

        $tokenData['tenant_id'] = $tenantId;

        // Store the token
        XeroToken::storeToken($tokenData);

        Log::channel('finance')->info('Xero OAuth token stored successfully', [
            'tenant_id' => $tenantId,
        ]);

        return $tokenData;
    }

    /**
     * Refresh an expired token.
     *
     * @throws RuntimeException If refresh fails
     */
    public function refreshToken(XeroToken $token): XeroToken
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.xero.client_id'),
                (string) config('services.xero.client_secret'),
            )
            ->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

        if ($response->failed()) {
            Log::channel('finance')->error('Xero token refresh failed', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Failed to refresh Xero token. Please reconnect.');
        }

        $tokenData = $response->json();
        $token->refreshWith($tokenData);

        // Reset cached API instance
        $this->accountingApi = null;

        Log::channel('finance')->info('Xero token refreshed successfully');

        return $token;
    }

    /**
     * Fetch the tenant ID from Xero connections endpoint.
     */
    protected function fetchTenantId(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://api.xero.com/connections');

            if ($response->successful()) {
                $connections = $response->json();
                if (! empty($connections) && isset($connections[0]['tenantId'])) {
                    return $connections[0]['tenantId'];
                }
            }
        } catch (\Throwable $e) {
            Log::channel('finance')->warning('Failed to fetch Xero tenant ID', [
                'error' => $e->getMessage(),
            ]);
        }

        return config('services.xero.tenant_id');
    }

    /**
     * Revoke the current connection by deactivating the token.
     */
    public function disconnect(): void
    {
        XeroToken::where('is_active', true)->update(['is_active' => false]);
        $this->accountingApi = null;

        Log::channel('finance')->info('Xero connection disconnected');
    }
}
