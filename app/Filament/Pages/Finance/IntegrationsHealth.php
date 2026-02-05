<?php

namespace App\Filament\Pages\Finance;

use App\Enums\Finance\ReconciliationStatus;
use App\Jobs\Finance\ProcessStripeWebhookJob;
use App\Models\Finance\Payment;
use App\Models\Finance\StripeWebhook;
use App\Models\Finance\XeroSyncLog;
use App\Services\Finance\XeroIntegrationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Integrations Health page for Finance module.
 *
 * This page allows Finance Operators to:
 * - Monitor Stripe integration health (last webhook, failed events, pending reconciliations)
 * - Monitor Xero integration health (last sync, pending syncs, failed syncs)
 * - See alerts for integration issues
 * - Retry failed webhook events and Xero syncs
 */
class IntegrationsHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Integrations Health';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 60;

    protected static ?string $title = 'Integrations Health';

    protected static string $view = 'filament.pages.finance.integrations-health';

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
    // Stripe Health Metrics
    // =========================================================================

    /**
     * Get the last received webhook.
     */
    public function getLastWebhookReceived(): ?StripeWebhook
    {
        return StripeWebhook::query()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the count of failed webhook events.
     */
    public function getFailedWebhooksCount(): int
    {
        return StripeWebhook::failed()->count();
    }

    /**
     * Get the count of pending webhook events.
     */
    public function getPendingWebhooksCount(): int
    {
        return StripeWebhook::pending()
            ->whereNull('error_message')
            ->count();
    }

    /**
     * Get the count of pending reconciliations.
     */
    public function getPendingReconciliationsCount(): int
    {
        return Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Pending)
            ->count();
    }

    /**
     * Get the count of mismatched reconciliations.
     */
    public function getMismatchedReconciliationsCount(): int
    {
        return Payment::query()
            ->where('reconciliation_status', ReconciliationStatus::Mismatched)
            ->count();
    }

    /**
     * Get the total webhooks received today.
     */
    public function getTodayWebhooksCount(): int
    {
        return StripeWebhook::query()
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get the total webhooks processed today.
     */
    public function getTodayProcessedCount(): int
    {
        return StripeWebhook::processed()
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Check if no webhooks have been received in the last hour.
     */
    public function hasNoRecentWebhooks(): bool
    {
        $oneHourAgo = now()->subHour();

        return ! StripeWebhook::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->exists();
    }

    /**
     * Get the time since last webhook.
     */
    public function getTimeSinceLastWebhook(): ?string
    {
        $lastWebhook = $this->getLastWebhookReceived();

        if ($lastWebhook === null) {
            return null;
        }

        return $lastWebhook->created_at->diffForHumans();
    }

    /**
     * Check if there are any Stripe health alerts.
     */
    public function hasStripeAlerts(): bool
    {
        return $this->hasNoRecentWebhooks()
            || $this->getFailedWebhooksCount() > 0;
    }

    // =========================================================================
    // Failed Webhooks List
    // =========================================================================

    /**
     * Get recent failed webhooks for display.
     *
     * @return Collection<int, StripeWebhook>
     */
    public function getFailedWebhooks(): Collection
    {
        return StripeWebhook::failed()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Retry a single failed webhook.
     */
    public function retryWebhook(int $webhookId): void
    {
        $webhook = StripeWebhook::find($webhookId);

        if ($webhook === null) {
            Notification::make()
                ->title('Webhook not found')
                ->danger()
                ->send();

            return;
        }

        if (! $webhook->canRetry()) {
            Notification::make()
                ->title('Webhook cannot be retried')
                ->body('This webhook has already been processed or is not in a failed state.')
                ->danger()
                ->send();

            return;
        }

        $previousRetryCount = $webhook->retry_count;

        // Mark for retry (clears error, increments retry count, sets last_retry_at)
        $webhook->markForRetry();

        // Log the retry attempt
        Log::channel('finance')->info('Stripe webhook retry initiated', [
            'webhook_id' => $webhook->id,
            'event_id' => $webhook->event_id,
            'event_type' => $webhook->event_type,
            'retry_count' => $webhook->retry_count,
            'previous_retry_count' => $previousRetryCount,
            'initiated_by' => auth()->id(),
        ]);

        // Dispatch job for reprocessing
        ProcessStripeWebhookJob::dispatch($webhook);

        Notification::make()
            ->title('Retry queued')
            ->body("Webhook {$webhook->event_id} has been queued for reprocessing (retry #{$webhook->retry_count}).")
            ->success()
            ->send();
    }

    /**
     * Retry all failed webhooks.
     */
    public function retryAllFailed(): void
    {
        $failedWebhooks = StripeWebhook::failed()->get();
        $count = $failedWebhooks->count();

        if ($count === 0) {
            Notification::make()
                ->title('No failed webhooks')
                ->body('There are no failed webhooks to retry.')
                ->warning()
                ->send();

            return;
        }

        // Log bulk retry initiation
        Log::channel('finance')->info('Stripe webhook bulk retry initiated', [
            'count' => $count,
            'initiated_by' => auth()->id(),
            'webhook_ids' => $failedWebhooks->pluck('id')->toArray(),
        ]);

        foreach ($failedWebhooks as $webhook) {
            // Mark for retry (clears error, increments retry count, sets last_retry_at)
            $webhook->markForRetry();

            // Log individual retry within bulk operation
            Log::channel('finance')->info('Stripe webhook retry queued (bulk)', [
                'webhook_id' => $webhook->id,
                'event_id' => $webhook->event_id,
                'event_type' => $webhook->event_type,
                'retry_count' => $webhook->retry_count,
            ]);

            // Dispatch job for reprocessing
            ProcessStripeWebhookJob::dispatch($webhook);
        }

        Notification::make()
            ->title('Retry queued')
            ->body("{$count} failed webhooks have been queued for reprocessing.")
            ->success()
            ->send();
    }

    // =========================================================================
    // Stripe Integration Summary
    // =========================================================================

    /**
     * Get Stripe integration health summary.
     *
     * @return array{
     *     status: string,
     *     status_color: string,
     *     last_webhook: string|null,
     *     last_webhook_time: \Carbon\Carbon|null,
     *     failed_count: int,
     *     pending_count: int,
     *     pending_reconciliations: int,
     *     mismatched_reconciliations: int,
     *     today_received: int,
     *     today_processed: int,
     *     alerts: array<string>
     * }
     */
    public function getStripeHealthSummary(): array
    {
        $lastWebhook = $this->getLastWebhookReceived();
        $failedCount = $this->getFailedWebhooksCount();
        $pendingCount = $this->getPendingWebhooksCount();
        $hasNoRecent = $this->hasNoRecentWebhooks();

        $alerts = [];
        $status = 'healthy';
        $statusColor = 'success';

        if ($lastWebhook === null) {
            $status = 'unknown';
            $statusColor = 'gray';
            $alerts[] = 'No webhooks have ever been received. Verify Stripe webhook configuration.';
        } elseif ($hasNoRecent) {
            $status = 'warning';
            $statusColor = 'warning';
            $alerts[] = 'No webhooks received in the last hour. Check Stripe connectivity.';
        }

        if ($failedCount > 0) {
            $status = $status === 'healthy' ? 'warning' : $status;
            $statusColor = $statusColor === 'success' ? 'warning' : $statusColor;
            $alerts[] = "{$failedCount} webhook(s) failed to process. Review and retry.";
        }

        if ($failedCount > 10) {
            $status = 'critical';
            $statusColor = 'danger';
        }

        return [
            'status' => $status,
            'status_color' => $statusColor,
            'last_webhook' => $lastWebhook?->event_type,
            'last_webhook_time' => $lastWebhook?->created_at,
            'failed_count' => $failedCount,
            'pending_count' => $pendingCount,
            'pending_reconciliations' => $this->getPendingReconciliationsCount(),
            'mismatched_reconciliations' => $this->getMismatchedReconciliationsCount(),
            'today_received' => $this->getTodayWebhooksCount(),
            'today_processed' => $this->getTodayProcessedCount(),
            'alerts' => $alerts,
        ];
    }

    // =========================================================================
    // Xero Health Metrics
    // =========================================================================

    /**
     * Get Xero integration health summary.
     *
     * @return array{
     *     status: string,
     *     status_color: string,
     *     sync_enabled: bool,
     *     pending_count: int,
     *     failed_count: int,
     *     synced_today: int,
     *     last_sync: \Carbon\Carbon|null,
     *     last_sync_type: string|null,
     *     alerts: array<string>,
     *     is_healthy: bool
     * }
     */
    public function getXeroHealthSummary(): array
    {
        return app(XeroIntegrationService::class)->getIntegrationHealth();
    }

    /**
     * Check if there are any Xero health alerts.
     */
    public function hasXeroAlerts(): bool
    {
        $summary = $this->getXeroHealthSummary();

        return ! empty($summary['alerts']);
    }

    // =========================================================================
    // Xero Failed Syncs List
    // =========================================================================

    /**
     * Get recent failed Xero syncs for display.
     *
     * @return Collection<int, XeroSyncLog>
     */
    public function getFailedXeroSyncs(): Collection
    {
        return app(XeroIntegrationService::class)->getFailedSyncs(20);
    }

    /**
     * Retry a single failed Xero sync.
     */
    public function retryXeroSync(int $syncLogId): void
    {
        $syncLog = XeroSyncLog::find($syncLogId);

        if ($syncLog === null) {
            Notification::make()
                ->title('Sync log not found')
                ->danger()
                ->send();

            return;
        }

        if (! $syncLog->canRetry()) {
            Notification::make()
                ->title('Sync cannot be retried')
                ->body('This sync has already been processed or is not in a failed state.')
                ->danger()
                ->send();

            return;
        }

        // Log the retry attempt
        Log::channel('finance')->info('Xero sync retry initiated from UI', [
            'sync_log_id' => $syncLog->id,
            'sync_type' => $syncLog->sync_type->value,
            'retry_count' => $syncLog->retry_count,
            'initiated_by' => auth()->id(),
        ]);

        $xeroService = app(XeroIntegrationService::class);
        $success = $xeroService->retryFailed($syncLog);

        if ($success) {
            Notification::make()
                ->title('Retry successful')
                ->body("Sync for {$syncLog->sync_type->label()} completed successfully.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Retry failed')
                ->body('The sync could not be completed. Check the error message.')
                ->danger()
                ->send();
        }
    }

    /**
     * Retry all failed Xero syncs.
     */
    public function retryAllFailedXeroSyncs(): void
    {
        $xeroService = app(XeroIntegrationService::class);
        $failedSyncs = $this->getFailedXeroSyncs();
        $count = $failedSyncs->count();

        if ($count === 0) {
            Notification::make()
                ->title('No failed syncs')
                ->body('There are no failed Xero syncs to retry.')
                ->warning()
                ->send();

            return;
        }

        // Log bulk retry initiation
        Log::channel('finance')->info('Xero bulk retry initiated from UI', [
            'count' => $count,
            'initiated_by' => auth()->id(),
            'sync_log_ids' => $failedSyncs->pluck('id')->toArray(),
        ]);

        $successCount = $xeroService->retryAllFailed();

        Notification::make()
            ->title('Bulk retry completed')
            ->body("{$successCount} of {$count} failed syncs were retried successfully.")
            ->success()
            ->send();
    }

    // =========================================================================
    // Display Helpers
    // =========================================================================

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

    /**
     * Format timestamp for display.
     */
    public function formatTimestamp(?Carbon $timestamp): string
    {
        if ($timestamp === null) {
            return 'Never';
        }

        return $timestamp->format('M j, Y H:i:s').' ('.$timestamp->diffForHumans().')';
    }
}
