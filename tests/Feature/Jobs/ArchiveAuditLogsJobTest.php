<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ArchiveAuditLogsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveAuditLogsJobTest extends TestCase
{
    use RefreshDatabase;

    private ?int $testUserId = null;

    private function getTestUserId(): int
    {
        if ($this->testUserId === null) {
            $this->testUserId = User::factory()->create()->id;
        }

        return $this->testUserId;
    }

    private function insertAuditLog(string $createdAt): void
    {
        DB::table('audit_logs')->insert([
            'id' => Str::uuid()->toString(),
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => Str::uuid()->toString(),
            'event' => 'created',
            'old_values' => null,
            'new_values' => json_encode(['name' => 'Test']),
            'user_id' => null,
            'created_at' => $createdAt,
        ]);
    }

    private function insertAiAuditLog(string $createdAt): void
    {
        DB::table('ai_audit_logs')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->getTestUserId(),
            'conversation_id' => Str::uuid()->toString(),
            'message_text' => 'test',
            'tools_invoked' => json_encode([]),
            'tokens_input' => 100,
            'tokens_output' => 50,
            'estimated_cost_eur' => '0.001000',
            'duration_ms' => 500,
            'error' => null,
            'metadata' => json_encode([]),
            'created_at' => $createdAt,
        ]);
    }

    public function test_dry_run_does_not_delete_records(): void
    {
        config(['audit.archival.retention_days' => 30]);

        $this->insertAuditLog(now()->subDays(60)->toDateTimeString());
        $this->insertAuditLog(now()->subDays(5)->toDateTimeString());

        $job = new ArchiveAuditLogsJob(dryRun: true);
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_deletes_audit_logs_older_than_retention(): void
    {
        config(['audit.archival.retention_days' => 30]);

        // 2 old records (> 30 days)
        $this->insertAuditLog(now()->subDays(60)->toDateTimeString());
        $this->insertAuditLog(now()->subDays(45)->toDateTimeString());
        // 1 recent record (< 30 days)
        $this->insertAuditLog(now()->subDays(5)->toDateTimeString());

        $job = new ArchiveAuditLogsJob;
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_deletes_ai_audit_logs_older_than_retention(): void
    {
        config(['audit.archival.ai_retention_days' => 30]);

        $this->insertAiAuditLog(now()->subDays(60)->toDateTimeString());
        $this->insertAiAuditLog(now()->subDays(10)->toDateTimeString());

        $job = new ArchiveAuditLogsJob;
        $job->handle();

        $this->assertDatabaseCount('ai_audit_logs', 1);
    }

    public function test_preserves_recent_records(): void
    {
        config([
            'audit.archival.retention_days' => 365,
            'audit.archival.ai_retention_days' => 180,
        ]);

        $this->insertAuditLog(now()->subDays(100)->toDateTimeString());
        $this->insertAiAuditLog(now()->subDays(90)->toDateTimeString());

        $job = new ArchiveAuditLogsJob;
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('ai_audit_logs', 1);
    }

    public function test_handles_empty_tables(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg): bool => str_contains($msg, 'Audit log archival completed'));

        $job = new ArchiveAuditLogsJob;
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('ai_audit_logs', 0);
    }

    public function test_statistics_returns_correct_counts(): void
    {
        config([
            'audit.archival.retention_days' => 30,
            'audit.archival.ai_retention_days' => 30,
        ]);

        $this->insertAuditLog(now()->subDays(60)->toDateTimeString());
        $this->insertAuditLog(now()->subDays(60)->toDateTimeString());
        $this->insertAuditLog(now()->subDays(5)->toDateTimeString());
        $this->insertAiAuditLog(now()->subDays(60)->toDateTimeString());

        $stats = ArchiveAuditLogsJob::getArchivalStatistics();

        $this->assertEquals(2, $stats['audit_logs']['eligible']);
        $this->assertEquals(30, $stats['audit_logs']['retention_days']);
        $this->assertEquals(1, $stats['ai_audit_logs']['eligible']);
        $this->assertEquals(30, $stats['ai_audit_logs']['retention_days']);
    }

    public function test_batch_deletion_works_correctly(): void
    {
        config([
            'audit.archival.retention_days' => 30,
            'audit.archival.batch_size' => 2,
        ]);

        // Insert 5 old records — should delete in 3 batches (2+2+1)
        for ($i = 0; $i < 5; $i++) {
            $this->insertAuditLog(now()->subDays(60)->toDateTimeString());
        }
        // 1 recent record stays
        $this->insertAuditLog(now()->subDays(5)->toDateTimeString());

        $job = new ArchiveAuditLogsJob;
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 1);
    }
}
