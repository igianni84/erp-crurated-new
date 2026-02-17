<?php

namespace App\Services\Procurement;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Models\AuditLog;
use App\Models\Procurement\BottlingInstruction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing BottlingInstruction lifecycle.
 *
 * Centralizes all bottling instruction business logic including
 * state transitions, preference tracking, and default application.
 */
class BottlingInstructionService
{
    /**
     * Activate a bottling instruction (draft → active).
     *
     * Activating an instruction makes it ready for customer preference collection.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function activate(BottlingInstruction $instruction): BottlingInstruction
    {
        if (! $instruction->status->canTransitionTo(BottlingInstructionStatus::Active)) {
            throw new InvalidArgumentException(
                "Cannot activate bottling instruction: current status '{$instruction->status->label()}' does not allow transition to Active. "
                .'Only Draft instructions can be activated.'
            );
        }

        $oldStatus = $instruction->status;

        return DB::transaction(function () use ($instruction, $oldStatus): BottlingInstruction {
            $instruction->status = BottlingInstructionStatus::Active;
            $instruction->save();

            $this->logStatusTransition($instruction, $oldStatus, BottlingInstructionStatus::Active);

            return $instruction;
        });
    }

    /**
     * Mark a bottling instruction as executed (active → executed).
     *
     * Indicates that bottling has been completed.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function markExecuted(BottlingInstruction $instruction): BottlingInstruction
    {
        if (! $instruction->status->canTransitionTo(BottlingInstructionStatus::Executed)) {
            throw new InvalidArgumentException(
                "Cannot mark bottling instruction as executed: current status '{$instruction->status->label()}' does not allow transition to Executed. "
                .'Only Active instructions can be marked as executed.'
            );
        }

        $oldStatus = $instruction->status;

        return DB::transaction(function () use ($instruction, $oldStatus): BottlingInstruction {
            $instruction->status = BottlingInstructionStatus::Executed;
            $instruction->save();

            $this->logStatusTransition($instruction, $oldStatus, BottlingInstructionStatus::Executed, [
                'preference_status' => $instruction->preference_status->value,
                'preference_status_label' => $instruction->preference_status->label(),
            ]);

            return $instruction;
        });
    }

    /**
     * Apply default bottling rule to an instruction.
     *
     * This is called when the deadline passes and not all preferences
     * have been collected. Sets preference_status to 'defaulted'.
     *
     * @throws InvalidArgumentException If defaults cannot be applied
     */
    public function applyDefaults(BottlingInstruction $instruction): BottlingInstruction
    {
        // Validate that instruction is in a state where defaults can be applied
        if ($instruction->status !== BottlingInstructionStatus::Active) {
            throw new InvalidArgumentException(
                "Cannot apply defaults to bottling instruction: current status '{$instruction->status->label()}' is not Active. "
                .'Defaults can only be applied to Active instructions.'
            );
        }

        // Validate that preferences are not already complete
        if ($instruction->preference_status === BottlingPreferenceStatus::Complete) {
            throw new InvalidArgumentException(
                'Cannot apply defaults to bottling instruction: all preferences have already been collected.'
            );
        }

        // Validate that defaults haven't already been applied
        if ($instruction->preference_status === BottlingPreferenceStatus::Defaulted) {
            throw new InvalidArgumentException(
                'Cannot apply defaults to bottling instruction: defaults have already been applied.'
            );
        }

        $oldPreferenceStatus = $instruction->preference_status;

        return DB::transaction(function () use ($instruction, $oldPreferenceStatus): BottlingInstruction {
            $instruction->preference_status = BottlingPreferenceStatus::Defaulted;
            $instruction->defaults_applied_at = now();
            $instruction->save();

            $this->logDefaultsApplication($instruction, $oldPreferenceStatus);

            return $instruction;
        });
    }

    /**
     * Update the preference status based on current collection progress.
     *
     * Recalculates the preference_status based on how many preferences
     * have been collected vs the total expected.
     *
     * @param  int  $collectedCount  The number of preferences collected
     * @param  int|null  $totalCount  The total expected (defaults to bottle_equivalents)
     *
     * @throws InvalidArgumentException If instruction is not in a valid state
     */
    public function updatePreferenceStatus(
        BottlingInstruction $instruction,
        int $collectedCount,
        ?int $totalCount = null
    ): BottlingInstruction {
        // Validate that preference collection is still allowed
        if (! $instruction->canCollectPreferences()) {
            throw new InvalidArgumentException(
                "Cannot update preference status: instruction status '{$instruction->status->label()}' "
                ."with preference status '{$instruction->preference_status->label()}' does not allow preference updates."
            );
        }

        $total = $totalCount ?? $instruction->bottle_equivalents;

        if ($collectedCount < 0) {
            throw new InvalidArgumentException(
                'Collected count cannot be negative.'
            );
        }

        if ($collectedCount > $total) {
            throw new InvalidArgumentException(
                "Collected count ({$collectedCount}) cannot exceed total count ({$total})."
            );
        }

        // Determine the new preference status
        $newStatus = $this->calculatePreferenceStatus($collectedCount, $total);

        // Only update if status actually changed
        if ($instruction->preference_status === $newStatus) {
            return $instruction;
        }

        $oldPreferenceStatus = $instruction->preference_status;

        return DB::transaction(function () use ($instruction, $oldPreferenceStatus, $newStatus, $collectedCount, $total): BottlingInstruction {
            $instruction->preference_status = $newStatus;
            $instruction->save();

            $this->logPreferenceStatusUpdate($instruction, $oldPreferenceStatus, $newStatus, $collectedCount, $total);

            return $instruction;
        });
    }

    /**
     * Get the preference collection progress for an instruction.
     *
     * Returns the number of collected preferences, pending preferences,
     * total expected, and percentage complete.
     *
     * @return array{collected: int, pending: int, total: int, percentage: float}
     */
    public function getPreferenceProgress(BottlingInstruction $instruction): array
    {
        $progress = $instruction->getPreferenceProgress();

        return [
            'collected' => $progress['collected'],
            'pending' => $progress['pending'],
            'total' => $progress['total'],
            'percentage' => $instruction->getPreferenceProgressPercentage(),
        ];
    }

    /**
     * Calculate the preference status based on collection progress.
     */
    protected function calculatePreferenceStatus(int $collected, int $total): BottlingPreferenceStatus
    {
        if ($total === 0) {
            return BottlingPreferenceStatus::Complete;
        }

        if ($collected === 0) {
            return BottlingPreferenceStatus::Pending;
        }

        if ($collected >= $total) {
            return BottlingPreferenceStatus::Complete;
        }

        return BottlingPreferenceStatus::Partial;
    }

    /**
     * Log a status transition to the audit log.
     *
     * @param  array<string, mixed>  $additionalContext
     */
    protected function logStatusTransition(
        BottlingInstruction $instruction,
        BottlingInstructionStatus $oldStatus,
        BottlingInstructionStatus $newStatus,
        array $additionalContext = []
    ): void {
        $newValues = [
            'status' => $newStatus->value,
            'status_label' => $newStatus->label(),
        ];

        if ($additionalContext !== []) {
            $newValues = array_merge($newValues, $additionalContext);
        }

        $instruction->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log the application of defaults to the audit log.
     */
    protected function logDefaultsApplication(
        BottlingInstruction $instruction,
        BottlingPreferenceStatus $oldPreferenceStatus
    ): void {
        $instruction->auditLogs()->create([
            'event' => AuditLog::EVENT_DEFAULTS_APPLIED,
            'old_values' => [
                'preference_status' => $oldPreferenceStatus->value,
                'preference_status_label' => $oldPreferenceStatus->label(),
                'defaults_applied_at' => null,
            ],
            'new_values' => [
                'preference_status' => BottlingPreferenceStatus::Defaulted->value,
                'preference_status_label' => BottlingPreferenceStatus::Defaulted->label(),
                'defaults_applied_at' => $instruction->defaults_applied_at?->toIso8601String(),
                'default_bottling_rule' => $instruction->default_bottling_rule,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log a preference status update to the audit log.
     */
    protected function logPreferenceStatusUpdate(
        BottlingInstruction $instruction,
        BottlingPreferenceStatus $oldStatus,
        BottlingPreferenceStatus $newStatus,
        int $collectedCount,
        int $totalCount
    ): void {
        $instruction->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'preference_status' => $oldStatus->value,
                'preference_status_label' => $oldStatus->label(),
            ],
            'new_values' => [
                'preference_status' => $newStatus->value,
                'preference_status_label' => $newStatus->label(),
                'collected_count' => $collectedCount,
                'total_count' => $totalCount,
                'percentage' => $totalCount > 0 ? round(($collectedCount / $totalCount) * 100, 1) : 0,
            ],
            'user_id' => Auth::id(),
        ]);
    }
}
