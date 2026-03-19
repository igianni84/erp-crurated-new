<?php

namespace App\Filament\Pages\Finance;

use App\Models\Finance\XeroToken;
use App\Services\Finance\XeroApiClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Xero connection management page.
 *
 * Allows admins to connect/disconnect Xero OAuth2 and view connection status.
 */
class XeroConnection extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Xero Connection';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.finance.xero-connection';

    protected static ?string $title = 'Xero Connection';

    protected static ?string $slug = 'xero-connection';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return method_exists($user, 'hasRole')
            && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    protected function getHeaderActions(): array
    {
        $client = app(XeroApiClient::class);

        $actions = [];

        if ($client->isConnected()) {
            $actions[] = Action::make('disconnect')
                ->label('Disconnect Xero')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disconnect Xero')
                ->modalDescription('This will revoke the Xero connection. Sync will fall back to stub mode until you reconnect.')
                ->action(function () use ($client): void {
                    $client->disconnect();

                    Notification::make()
                        ->title('Xero disconnected')
                        ->body('The Xero connection has been revoked.')
                        ->warning()
                        ->send();

                    $this->redirect(static::getUrl());
                });
        } elseif ($client->isConfigured()) {
            $actions[] = Action::make('connect')
                ->label('Connect to Xero')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->url(route('xero.authorize'));
        }

        return $actions;
    }

    /**
     * Get the current connection status data for the view.
     *
     * @return array<string, mixed>
     */
    public function getConnectionStatus(): array
    {
        $client = app(XeroApiClient::class);
        $token = XeroToken::getActive();

        $tenantId = $token !== null ? $token->tenant_id : config('services.xero.tenant_id');

        return [
            'configured' => $client->isConfigured(),
            'connected' => $client->isConnected(),
            'tenant_id' => $tenantId,
            'token_expires_at' => $token?->expires_at,
            'token_created_at' => $token?->created_at,
            'sync_enabled' => (bool) config('finance.xero.sync_enabled', true),
        ];
    }
}
