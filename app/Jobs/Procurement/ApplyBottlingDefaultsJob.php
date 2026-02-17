<?php

namespace App\Jobs\Procurement;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Procurement\BottlingInstruction;
use App\Models\User;
use App\Notifications\Procurement\BottlingDefaultsAppliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Job to apply default bottling rules when deadline expires.
 *
 * This job should be scheduled to run daily to check for bottling instructions
 * that have passed their deadline and still have pending/partial preferences.
 *
 * When found, it applies the default_bottling_rule and marks the instruction
 * as having defaults applied.
 */
class ApplyBottlingDefaultsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $appliedCount = 0;

        // Find all bottling instructions that need defaults applied:
        // - status = active (draft instructions shouldn't have defaults applied)
        // - deadline <= today (deadline has passed or is today)
        // - preference_status is pending or partial (not complete - already done)
        $instructions = BottlingInstruction::query()
            ->where('status', BottlingInstructionStatus::Active)
            ->whereDate('bottling_deadline', '<=', now())
            ->whereIn('preference_status', [
                BottlingPreferenceStatus::Pending,
                BottlingPreferenceStatus::Partial,
            ])
            ->whereNull('defaults_applied_at') // Don't re-apply defaults
            ->get();

        foreach ($instructions as $instruction) {
            /** @var BottlingInstruction $instruction */
            if ($this->applyDefaults($instruction)) {
                $appliedCount++;
            }
        }

        if ($appliedCount > 0) {
            Log::info("Applied bottling defaults to {$appliedCount} instructions past deadline");
        }
    }

    /**
     * Apply default bottling rule to an instruction.
     */
    protected function applyDefaults(BottlingInstruction $instruction): bool
    {
        $oldPreferenceStatus = $instruction->preference_status;

        // Update the instruction
        $instruction->preference_status = BottlingPreferenceStatus::Defaulted;
        $instruction->defaults_applied_at = now();
        $instruction->save();

        // Create audit log for this automatic action
        AuditLog::create([
            'auditable_type' => BottlingInstruction::class,
            'auditable_id' => $instruction->id,
            'event' => AuditLog::EVENT_DEFAULTS_APPLIED,
            'old_values' => [
                'preference_status' => $oldPreferenceStatus->value,
                'defaults_applied_at' => null,
            ],
            'new_values' => [
                'preference_status' => BottlingPreferenceStatus::Defaulted->value,
                'defaults_applied_at' => $instruction->defaults_applied_at->toIso8601String(),
                'default_bottling_rule' => $instruction->default_bottling_rule,
                'reason' => 'Deadline passed - automatic defaults applied',
            ],
            'user_id' => null, // System action, no user
        ]);

        Log::info("Applied bottling defaults to instruction {$instruction->id}", [
            'instruction_id' => $instruction->id,
            'deadline' => $instruction->bottling_deadline->toDateString(),
            'default_rule' => $instruction->default_bottling_rule,
        ]);

        // Notify Ops users (Editor role and above)
        $this->notifyOpsUsers($instruction);

        return true;
    }

    /**
     * Send notification to Ops users about defaults being applied.
     */
    protected function notifyOpsUsers(BottlingInstruction $instruction): void
    {
        // Get users with Editor role or above (Ops users)
        $opsUsers = User::query()
            ->whereIn('role', [
                UserRole::Editor->value,
                UserRole::Manager->value,
                UserRole::Admin->value,
                UserRole::SuperAdmin->value,
            ])
            ->get();

        if ($opsUsers->isEmpty()) {
            Log::warning('No Ops users found to notify about bottling defaults applied', [
                'instruction_id' => $instruction->id,
            ]);

            return;
        }

        Notification::send($opsUsers, new BottlingDefaultsAppliedNotification($instruction));
    }

    /**
     * Get the count of instructions that need defaults applied.
     * Useful for monitoring/dashboard purposes.
     */
    public static function getInstructionsNeedingDefaults(): int
    {
        return BottlingInstruction::query()
            ->where('status', BottlingInstructionStatus::Active)
            ->whereDate('bottling_deadline', '<=', now())
            ->whereIn('preference_status', [
                BottlingPreferenceStatus::Pending,
                BottlingPreferenceStatus::Partial,
            ])
            ->whereNull('defaults_applied_at')
            ->count();
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ApplyBottlingDefaultsJob failed permanently', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
