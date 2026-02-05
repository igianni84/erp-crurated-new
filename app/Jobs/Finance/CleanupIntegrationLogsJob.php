<?php

namespace App\Jobs\Finance;

use App\Models\Finance\StripeWebhook;
use App\Models\Finance\XeroSyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to clean up old integration logs.
 *
 * This job removes integration logs (Stripe webhooks, Xero sync logs)
 * that are older than the configured retention period.
 *
 * Note: This uses raw DB queries to bypass the model's delete protection,
 * as cleanup is an authorized administrative operation.
 *
 * Configured via:
 * - config/finance.php 'logs.stripe_webhook_retention_days'
 * - config/finance.php 'logs.xero_sync_retention_days'
 */
class CleanupIntegrationLogsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Whether to run in dry-run mode (log what would be deleted, don't delete).
     */
    protected bool $dryRun;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $stripeDeleted = $this->cleanupStripeWebhooks();
        $xeroDeleted = $this->cleanupXeroSyncLogs();

        Log::channel('finance')->info('Integration logs cleanup completed', [
            'dry_run' => $this->dryRun,
            'stripe_webhooks_deleted' => $stripeDeleted,
            'xero_sync_logs_deleted' => $xeroDeleted,
        ]);
    }

    /**
     * Clean up old Stripe webhook logs.
     */
    protected function cleanupStripeWebhooks(): int
    {
        $retentionDays = (int) config('finance.logs.stripe_webhook_retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        // Only delete processed webhooks that are old
        // Keep failed webhooks regardless of age for debugging
        $query = StripeWebhook::query()
            ->where('created_at', '<', $cutoffDate)
            ->where('processed', true);

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        if ($this->dryRun) {
            Log::channel('finance')->info('Stripe webhooks cleanup dry run', [
                'would_delete' => $count,
                'cutoff_date' => $cutoffDate->toDateString(),
                'retention_days' => $retentionDays,
            ]);

            return $count;
        }

        // Use raw DB delete to bypass model's delete protection
        // This is an authorized cleanup operation
        $deleted = DB::table('stripe_webhooks')
            ->where('created_at', '<', $cutoffDate)
            ->where('processed', true)
            ->delete();

        Log::channel('finance')->info('Stripe webhooks cleaned up', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'retention_days' => $retentionDays,
        ]);

        return $deleted;
    }

    /**
     * Clean up old Xero sync logs.
     */
    protected function cleanupXeroSyncLogs(): int
    {
        $retentionDays = (int) config('finance.logs.xero_sync_retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        // Only delete successfully synced logs that are old
        // Keep failed and pending logs regardless of age for debugging/retry
        $query = XeroSyncLog::query()
            ->where('created_at', '<', $cutoffDate)
            ->synced();

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        if ($this->dryRun) {
            Log::channel('finance')->info('Xero sync logs cleanup dry run', [
                'would_delete' => $count,
                'cutoff_date' => $cutoffDate->toDateString(),
                'retention_days' => $retentionDays,
            ]);

            return $count;
        }

        // Use raw DB delete to bypass model's delete protection
        // This is an authorized cleanup operation
        $deleted = DB::table('xero_sync_logs')
            ->where('created_at', '<', $cutoffDate)
            ->where('status', 'synced')
            ->delete();

        Log::channel('finance')->info('Xero sync logs cleaned up', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'retention_days' => $retentionDays,
        ]);

        return $deleted;
    }

    /**
     * Get statistics about logs eligible for cleanup.
     *
     * @return array{stripe: array{eligible: int, retention_days: int, cutoff: string}, xero: array{eligible: int, retention_days: int, cutoff: string}}
     */
    public static function getCleanupStatistics(): array
    {
        $stripeRetentionDays = (int) config('finance.logs.stripe_webhook_retention_days', 90);
        $stripeCutoff = now()->subDays($stripeRetentionDays);

        $xeroRetentionDays = (int) config('finance.logs.xero_sync_retention_days', 90);
        $xeroCutoff = now()->subDays($xeroRetentionDays);

        return [
            'stripe' => [
                'eligible' => StripeWebhook::query()
                    ->where('created_at', '<', $stripeCutoff)
                    ->where('processed', true)
                    ->count(),
                'retention_days' => $stripeRetentionDays,
                'cutoff' => $stripeCutoff->toDateString(),
            ],
            'xero' => [
                'eligible' => XeroSyncLog::query()
                    ->where('created_at', '<', $xeroCutoff)
                    ->synced()
                    ->count(),
                'retention_days' => $xeroRetentionDays,
                'cutoff' => $xeroCutoff->toDateString(),
            ],
        ];
    }
}
