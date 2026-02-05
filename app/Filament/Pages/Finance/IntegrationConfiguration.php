<?php

namespace App\Filament\Pages\Finance;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integration Configuration page for Finance module.
 *
 * This page allows Admins to:
 * - View and verify Stripe API configuration
 * - View and verify Xero OAuth configuration
 * - Test connections to both services
 * - See sync settings and configuration status
 *
 * Note: Sensitive values (API keys, secrets) are stored in .env and cannot
 * be modified through this UI for security reasons. This page shows configuration
 * status and allows testing connections.
 */
class IntegrationConfiguration extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Integration Settings';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 81;

    protected static ?string $title = 'Integration Configuration';

    protected static string $view = 'filament.pages.finance.integration-configuration';

    // =========================================================================
    // Header Actions
    // =========================================================================

    /**
     * Get header actions for the page.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getRefreshAction(),
        ];
    }

    /**
     * Get the refresh action.
     */
    protected function getRefreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Refresh')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->action(fn () => $this->dispatch('$refresh'));
    }

    // =========================================================================
    // Stripe Configuration
    // =========================================================================

    /**
     * Get Stripe configuration status.
     *
     * @return array{
     *     configured: bool,
     *     has_key: bool,
     *     has_secret: bool,
     *     has_webhook_secret: bool,
     *     key_preview: string|null,
     *     webhook_tolerance: int,
     *     environment: string
     * }
     */
    public function getStripeConfig(): array
    {
        $key = config('services.stripe.key');
        $secret = config('services.stripe.secret');
        $webhookSecret = config('services.stripe.webhook_secret');

        $hasKey = ! empty($key);
        $hasSecret = ! empty($secret);
        $hasWebhookSecret = ! empty($webhookSecret);

        // Determine if this is a test or live key
        $environment = 'unknown';
        if ($hasKey) {
            if (Str::startsWith($key, 'pk_test_')) {
                $environment = 'test';
            } elseif (Str::startsWith($key, 'pk_live_')) {
                $environment = 'live';
            }
        }

        return [
            'configured' => $hasKey && $hasSecret && $hasWebhookSecret,
            'has_key' => $hasKey,
            'has_secret' => $hasSecret,
            'has_webhook_secret' => $hasWebhookSecret,
            'key_preview' => $hasKey ? $this->maskValue($key) : null,
            'webhook_tolerance' => (int) config('finance.stripe.webhook_tolerance', 300),
            'environment' => $environment,
        ];
    }

    /**
     * Test the Stripe connection.
     */
    public function testStripeConnection(): void
    {
        $config = $this->getStripeConfig();

        if (! $config['configured']) {
            Notification::make()
                ->title('Configuration Incomplete')
                ->body('Stripe is not fully configured. Please set STRIPE_KEY, STRIPE_SECRET, and STRIPE_WEBHOOK_SECRET in your .env file.')
                ->danger()
                ->send();

            return;
        }

        try {
            // Use Stripe API to verify credentials by fetching account info
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.stripe.secret'),
            ])->get('https://api.stripe.com/v1/account');

            if ($response->successful()) {
                $account = $response->json();
                $accountId = $account['id'] ?? 'Unknown';
                $accountName = $account['business_profile']['name'] ?? $account['email'] ?? 'Unknown';

                Log::channel('finance')->info('Stripe connection test successful', [
                    'account_id' => $accountId,
                    'tested_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Connection Successful')
                    ->body("Connected to Stripe account: {$accountName} ({$accountId})")
                    ->success()
                    ->send();
            } else {
                $error = $response->json('error.message', 'Unknown error');

                Log::channel('finance')->warning('Stripe connection test failed', [
                    'error' => $error,
                    'status' => $response->status(),
                    'tested_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Connection Failed')
                    ->body("Stripe API error: {$error}")
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::channel('finance')->error('Stripe connection test exception', [
                'error' => $e->getMessage(),
                'tested_by' => auth()->id(),
            ]);

            Notification::make()
                ->title('Connection Error')
                ->body("Could not connect to Stripe: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }

    // =========================================================================
    // Xero Configuration
    // =========================================================================

    /**
     * Get Xero configuration status.
     *
     * @return array{
     *     configured: bool,
     *     sync_enabled: bool,
     *     max_retry_count: int,
     *     has_client_id: bool,
     *     has_client_secret: bool,
     *     has_tenant_id: bool,
     *     client_id_preview: string|null,
     *     tenant_id_preview: string|null,
     *     oauth_configured: bool
     * }
     */
    public function getXeroConfig(): array
    {
        // Check for Xero OAuth credentials
        // These would typically be in config/services.php or config/xero.php
        $clientId = config('services.xero.client_id', config('xero.client_id'));
        $clientSecret = config('services.xero.client_secret', config('xero.client_secret'));
        $tenantId = config('services.xero.tenant_id', config('xero.tenant_id'));

        $hasClientId = ! empty($clientId);
        $hasClientSecret = ! empty($clientSecret);
        $hasTenantId = ! empty($tenantId);

        $oauthConfigured = $hasClientId && $hasClientSecret;

        return [
            'configured' => $oauthConfigured && $hasTenantId,
            'sync_enabled' => (bool) config('finance.xero.sync_enabled', true),
            'max_retry_count' => (int) config('finance.xero.max_retry_count', 3),
            'has_client_id' => $hasClientId,
            'has_client_secret' => $hasClientSecret,
            'has_tenant_id' => $hasTenantId,
            'client_id_preview' => $hasClientId ? $this->maskValue((string) $clientId) : null,
            'tenant_id_preview' => $hasTenantId ? $this->maskValue((string) $tenantId) : null,
            'oauth_configured' => $oauthConfigured,
        ];
    }

    /**
     * Test the Xero connection.
     */
    public function testXeroConnection(): void
    {
        $config = $this->getXeroConfig();

        if (! $config['oauth_configured']) {
            Notification::make()
                ->title('Configuration Incomplete')
                ->body('Xero OAuth credentials are not configured. Please set XERO_CLIENT_ID and XERO_CLIENT_SECRET in your .env file.')
                ->danger()
                ->send();

            return;
        }

        if (! $config['sync_enabled']) {
            Notification::make()
                ->title('Sync Disabled')
                ->body('Xero sync is currently disabled. Set XERO_SYNC_ENABLED=true in your .env file to enable.')
                ->warning()
                ->send();

            return;
        }

        // Note: Full Xero OAuth2 connection test would require an access token
        // For now, we verify the configuration is present and sync is enabled
        // In production, you would use the Xero PHP SDK to test the connection

        Log::channel('finance')->info('Xero configuration verified', [
            'client_id_present' => $config['has_client_id'],
            'tenant_id_present' => $config['has_tenant_id'],
            'sync_enabled' => $config['sync_enabled'],
            'tested_by' => auth()->id(),
        ]);

        if (! $config['has_tenant_id']) {
            Notification::make()
                ->title('Tenant ID Missing')
                ->body('Xero OAuth credentials are set, but XERO_TENANT_ID is missing. Complete the OAuth flow to obtain the tenant ID.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Configuration Valid')
            ->body('Xero OAuth credentials and tenant ID are configured. Sync is enabled.')
            ->success()
            ->send();
    }

    // =========================================================================
    // Finance Configuration
    // =========================================================================

    /**
     * Get general finance configuration settings.
     *
     * @return array{
     *     subscription_overdue_days: int,
     *     storage_overdue_days: int,
     *     immediate_invoice_alert_hours: int,
     *     base_currency: string,
     *     seller_country: string,
     *     storage_billing_cycle: string,
     *     storage_auto_issue: bool
     * }
     */
    public function getFinanceConfig(): array
    {
        return [
            'subscription_overdue_days' => (int) config('finance.subscription_overdue_suspension_days', 14),
            'storage_overdue_days' => (int) config('finance.storage_overdue_block_days', 30),
            'immediate_invoice_alert_hours' => (int) config('finance.immediate_invoice_alert_hours', 24),
            'base_currency' => (string) config('finance.pricing.base_currency', 'EUR'),
            'seller_country' => (string) config('finance.pricing.seller_country', 'IT'),
            'storage_billing_cycle' => (string) config('finance.storage.billing_cycle', 'monthly'),
            'storage_auto_issue' => (bool) config('finance.storage.auto_issue_invoices', true),
        ];
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Mask a sensitive value for display.
     *
     * Shows only the first 4 and last 4 characters with dots in between.
     */
    protected function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        $visibleChars = 4;

        return substr($value, 0, $visibleChars)
            .str_repeat('•', min(16, $length - ($visibleChars * 2)))
            .substr($value, -$visibleChars);
    }

    /**
     * Get environment variable requirement status.
     *
     * @return array<string, list<array{key: string, set: bool, description: string}>>
     */
    public function getRequiredEnvVars(): array
    {
        return [
            'stripe' => [
                [
                    'key' => 'STRIPE_KEY',
                    'set' => ! empty(config('services.stripe.key')),
                    'description' => 'Stripe publishable API key (pk_test_... or pk_live_...)',
                ],
                [
                    'key' => 'STRIPE_SECRET',
                    'set' => ! empty(config('services.stripe.secret')),
                    'description' => 'Stripe secret API key (sk_test_... or sk_live_...)',
                ],
                [
                    'key' => 'STRIPE_WEBHOOK_SECRET',
                    'set' => ! empty(config('services.stripe.webhook_secret')),
                    'description' => 'Stripe webhook signing secret (whsec_...)',
                ],
            ],
            'xero' => [
                [
                    'key' => 'XERO_CLIENT_ID',
                    'set' => ! empty(config('services.xero.client_id', config('xero.client_id'))),
                    'description' => 'Xero OAuth2 client ID from developer portal',
                ],
                [
                    'key' => 'XERO_CLIENT_SECRET',
                    'set' => ! empty(config('services.xero.client_secret', config('xero.client_secret'))),
                    'description' => 'Xero OAuth2 client secret',
                ],
                [
                    'key' => 'XERO_TENANT_ID',
                    'set' => ! empty(config('services.xero.tenant_id', config('xero.tenant_id'))),
                    'description' => 'Xero organisation tenant ID (obtained after OAuth)',
                ],
                [
                    'key' => 'XERO_SYNC_ENABLED',
                    'set' => true, // Always set (has default)
                    'description' => 'Enable/disable Xero sync (default: true)',
                ],
            ],
        ];
    }

    /**
     * Get the webhook endpoint URL for Stripe.
     */
    public function getStripeWebhookUrl(): string
    {
        return url('/api/webhooks/stripe');
    }

    /**
     * Get overall configuration health status.
     *
     * @return array{
     *     status: string,
     *     stripe_ready: bool,
     *     xero_ready: bool,
     *     issues: array<string>
     * }
     */
    public function getConfigurationHealth(): array
    {
        $stripeConfig = $this->getStripeConfig();
        $xeroConfig = $this->getXeroConfig();

        $issues = [];

        if (! $stripeConfig['configured']) {
            $issues[] = 'Stripe is not fully configured';
        }

        if ($stripeConfig['environment'] === 'test') {
            $issues[] = 'Stripe is using test mode credentials';
        }

        if (! $xeroConfig['sync_enabled']) {
            $issues[] = 'Xero sync is disabled';
        }

        if (! $xeroConfig['configured']) {
            $issues[] = 'Xero is not fully configured';
        }

        $status = 'healthy';
        if (count($issues) > 2) {
            $status = 'critical';
        } elseif (count($issues) > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'stripe_ready' => $stripeConfig['configured'],
            'xero_ready' => $xeroConfig['configured'] && $xeroConfig['sync_enabled'],
            'issues' => $issues,
        ];
    }

    /**
     * Get status badge classes.
     */
    public function getStatusBadgeClasses(string $status): string
    {
        return match ($status) {
            'healthy' => 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400',
            'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400',
            'critical' => 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }
}
