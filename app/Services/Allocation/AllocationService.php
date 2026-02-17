<?php

namespace App\Services\Allocation;

use App\Enums\Allocation\AllocationStatus;
use App\Models\Allocation\Allocation;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing Allocation lifecycle and consumption.
 *
 * Centralizes all allocation business logic including state transitions,
 * availability checks, and consumption operations.
 */
class AllocationService
{
    /**
     * Activate an allocation (draft → active).
     *
     * When activated, constraints become read-only and cannot be modified.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function activate(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Active)) {
            throw new InvalidArgumentException(
                "Cannot activate allocation: current status '{$allocation->status->label()}' does not allow transition to Active. "
                .'Only Draft allocations can be activated.'
            );
        }

        $oldStatus = $allocation->status;
        $allocation->status = AllocationStatus::Active;
        $allocation->save();

        $this->logStatusTransition($allocation, $oldStatus, AllocationStatus::Active);

        return $allocation;
    }

    /**
     * Close an allocation (active/exhausted → closed).
     *
     * Closed allocations cannot be reopened. Create a new allocation if needed.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function close(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Closed)) {
            throw new InvalidArgumentException(
                "Cannot close allocation: current status '{$allocation->status->label()}' does not allow transition to Closed. "
                .'Only Active or Exhausted allocations can be closed. Closed allocations cannot be reopened.'
            );
        }

        $oldStatus = $allocation->status;
        $allocation->status = AllocationStatus::Closed;
        $allocation->save();

        $this->logStatusTransition($allocation, $oldStatus, AllocationStatus::Closed);

        return $allocation;
    }

    /**
     * Consume allocation by incrementing sold_quantity.
     *
     * This method handles the consumption atomically to prevent race conditions.
     * If remaining_quantity becomes 0, the allocation is automatically marked as exhausted.
     *
     * @throws InvalidArgumentException If allocation cannot be consumed or quantity exceeds available
     */
    public function consumeAllocation(Allocation $allocation, int $quantity): Allocation
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity must be greater than zero'
            );
        }

        if (! $allocation->status->allowsConsumption()) {
            throw new InvalidArgumentException(
                "Cannot consume allocation: status '{$allocation->status->label()}' does not allow consumption. "
                .'Only Active allocations can be consumed.'
            );
        }

        // Check availability including active reservations
        if (! $this->checkAvailability($allocation, $quantity)) {
            $available = $this->getRemainingAvailable($allocation);
            throw new InvalidArgumentException(
                "Cannot consume {$quantity} units: only {$available} units available "
                .'(accounting for active reservations).'
            );
        }

        // Use transaction with locking to prevent race conditions
        return DB::transaction(function () use ($allocation, $quantity): Allocation {
            // Refresh with lock to ensure we have latest data
            $allocation = Allocation::lockForUpdate()->findOrFail($allocation->id);

            // Double-check availability after lock
            if (! $this->checkAvailability($allocation, $quantity)) {
                $available = $this->getRemainingAvailable($allocation);
                throw new InvalidArgumentException(
                    "Cannot consume {$quantity} units: only {$available} units available "
                    .'(accounting for active reservations).'
                );
            }

            $allocation->sold_quantity += $quantity;
            $allocation->save();

            // Auto-transition to exhausted if remaining is 0
            if ($allocation->remaining_quantity === 0) {
                $oldStatus = $allocation->status;
                $allocation->status = AllocationStatus::Exhausted;
                $allocation->save();
                $this->logStatusTransition($allocation, $oldStatus, AllocationStatus::Exhausted);
            }

            return $allocation->fresh() ?? $allocation;
        });
    }

    /**
     * Check if requested quantity is available for consumption.
     *
     * Availability considers both remaining_quantity and active reservations.
     * Formula: available = remaining_quantity - sum(active_reservations.quantity)
     */
    public function checkAvailability(Allocation $allocation, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        return $this->getRemainingAvailable($allocation) >= $quantity;
    }

    /**
     * Get the actual remaining available quantity.
     *
     * This accounts for active temporary reservations that are blocking quantity.
     * Formula: remaining - sum(active_reservations.quantity)
     */
    public function getRemainingAvailable(Allocation $allocation): int
    {
        $activeReservationsSum = $allocation->activeReservations()->sum('quantity');

        $available = $allocation->remaining_quantity - (int) $activeReservationsSum;

        // Ensure we never return negative availability
        return max(0, $available);
    }

    /**
     * Check if an allocation can be activated.
     */
    public function canActivate(Allocation $allocation): bool
    {
        return $allocation->status->canTransitionTo(AllocationStatus::Active);
    }

    /**
     * Check if an allocation can be closed.
     */
    public function canClose(Allocation $allocation): bool
    {
        return $allocation->status->canTransitionTo(AllocationStatus::Closed);
    }

    /**
     * Mark an allocation as exhausted.
     *
     * This is typically called automatically when remaining_quantity reaches 0,
     * but can also be called manually if needed.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function markAsExhausted(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Exhausted)) {
            throw new InvalidArgumentException(
                "Cannot mark allocation as exhausted: current status '{$allocation->status->label()}' does not allow this transition."
            );
        }

        $oldStatus = $allocation->status;
        $allocation->status = AllocationStatus::Exhausted;
        $allocation->save();

        $this->logStatusTransition($allocation, $oldStatus, AllocationStatus::Exhausted);

        return $allocation;
    }

    /**
     * Attempt a status transition with validation.
     *
     * Validates the transition is allowed and provides user-friendly error messages.
     *
     * @throws InvalidArgumentException If transition is not allowed
     */
    public function transitionTo(Allocation $allocation, AllocationStatus $targetStatus): Allocation
    {
        if (! $allocation->status->canTransitionTo($targetStatus)) {
            $allowedTransitions = $allocation->status->allowedTransitions();
            $allowedLabels = array_map(fn (AllocationStatus $s) => $s->label(), $allowedTransitions);

            $message = "Cannot transition allocation from '{$allocation->status->label()}' to '{$targetStatus->label()}'.";

            if ($allowedLabels !== []) {
                $message .= ' Allowed transitions: '.implode(', ', $allowedLabels).'.';
            } else {
                $message .= ' No transitions are allowed from this status.';
            }

            if ($allocation->status === AllocationStatus::Closed) {
                $message .= ' Closed allocations cannot be reopened - create a new allocation instead.';
            }

            throw new InvalidArgumentException($message);
        }

        return match ($targetStatus) {
            AllocationStatus::Active => $this->activate($allocation),
            AllocationStatus::Exhausted => $this->markAsExhausted($allocation),
            AllocationStatus::Closed => $this->close($allocation),
            default => throw new InvalidArgumentException("Unsupported target status: {$targetStatus->label()}"),
        };
    }

    /**
     * Check if a status transition is valid.
     */
    public function canTransitionTo(Allocation $allocation, AllocationStatus $targetStatus): bool
    {
        return $allocation->status->canTransitionTo($targetStatus);
    }

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        Allocation $allocation,
        AllocationStatus $oldStatus,
        AllocationStatus $newStatus
    ): void {
        $allocation->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => [
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
            ],
            'user_id' => Auth::id(),
        ]);
    }
}
