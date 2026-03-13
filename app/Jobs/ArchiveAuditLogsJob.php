<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to archive (delete) old audit log records.
 *
 * Both audit_logs and ai_audit_logs are immutable, append-only tables
 * that grow indefinitely. This job prunes records older than the
 * configured retention period in batches to avoid lock contention.
 *
 * Uses raw DB queries to bypass model immutability guards,
 * as archival is an authorized administrative operation.
 *
 * Configured via config/audit.php.
 */
class ArchiveAuditLogsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 600;

    protected bool $dryRun;

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    public function handle(): void
    {
        $auditDeleted = $this->archiveTable(
            'audit_logs',
            (int) config('audit.archival.retention_days', 365),
        );

        $aiAuditDeleted = $this->archiveTable(
            'ai_audit_logs',
            (int) config('audit.archival.ai_retention_days', 180),
        );

        Log::info('Audit log archival completed', [
            'dry_run' => $this->dryRun,
            'audit_logs_deleted' => $auditDeleted,
            'ai_audit_logs_deleted' => $aiAuditDeleted,
        ]);
    }

    protected function archiveTable(string $table, int $retentionDays): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        $batchSize = (int) config('audit.archival.batch_size', 5000);

        $totalEligible = DB::table($table)
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($totalEligible === 0) {
            return 0;
        }

        if ($this->dryRun) {
            Log::info("Audit archival dry run: {$table}", [
                'would_delete' => $totalEligible,
                'cutoff_date' => $cutoffDate->toDateString(),
                'retention_days' => $retentionDays,
            ]);

            return $totalEligible;
        }

        $totalDeleted = 0;

        do {
            $deleted = DB::table($table)
                ->where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted >= $batchSize);

        Log::info("Audit logs archived: {$table}", [
            'deleted' => $totalDeleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'retention_days' => $retentionDays,
        ]);

        return $totalDeleted;
    }

    /**
     * Get statistics about records eligible for archival.
     *
     * @return array{audit_logs: array{eligible: int, retention_days: int, cutoff: string}, ai_audit_logs: array{eligible: int, retention_days: int, cutoff: string}}
     */
    public static function getArchivalStatistics(): array
    {
        $auditRetention = (int) config('audit.archival.retention_days', 365);
        $auditCutoff = now()->subDays($auditRetention);

        $aiRetention = (int) config('audit.archival.ai_retention_days', 180);
        $aiCutoff = now()->subDays($aiRetention);

        return [
            'audit_logs' => [
                'eligible' => DB::table('audit_logs')
                    ->where('created_at', '<', $auditCutoff)
                    ->count(),
                'retention_days' => $auditRetention,
                'cutoff' => $auditCutoff->toDateString(),
            ],
            'ai_audit_logs' => [
                'eligible' => DB::table('ai_audit_logs')
                    ->where('created_at', '<', $aiCutoff)
                    ->count(),
                'retention_days' => $aiRetention,
                'cutoff' => $aiCutoff->toDateString(),
            ],
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ArchiveAuditLogsJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
