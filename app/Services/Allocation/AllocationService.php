<?php

namespace App\Services\Allocation;

use App\Enums\Allocation\AllocationStatus;
use App\Models\Allocation\Allocation;
use Illuminate\Support\Facades\DB;

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
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function activate(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Active)) {
            throw new \InvalidArgumentException(
                "Cannot activate allocation: current status '{$allocation->status->label()}' does not allow transition to Active. "
                .'Only Draft allocations can be activated.'
            );
        }

        $allocation->status = AllocationStatus::Active;
        $allocation->save();

        return $allocation;
    }

    /**
     * Close an allocation (active/exhausted → closed).
     *
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function close(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Closed)) {
            throw new \InvalidArgumentException(
                "Cannot close allocation: current status '{$allocation->status->label()}' does not allow transition to Closed. "
                .'Only Active or Exhausted allocations can be closed.'
            );
        }

        $allocation->status = AllocationStatus::Closed;
        $allocation->save();

        return $allocation;
    }

    /**
     * Consume allocation by incrementing sold_quantity.
     *
     * This method handles the consumption atomically to prevent race conditions.
     * If remaining_quantity becomes 0, the allocation is automatically marked as exhausted.
     *
     * @throws \InvalidArgumentException If allocation cannot be consumed or quantity exceeds available
     */
    public function consumeAllocation(Allocation $allocation, int $quantity): Allocation
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity must be greater than zero'
            );
        }

        if (! $allocation->status->allowsConsumption()) {
            throw new \InvalidArgumentException(
                "Cannot consume allocation: status '{$allocation->status->label()}' does not allow consumption. "
                .'Only Active allocations can be consumed.'
            );
        }

        // Check availability including active reservations
        if (! $this->checkAvailability($allocation, $quantity)) {
            $available = $this->getRemainingAvailable($allocation);
            throw new \InvalidArgumentException(
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
                throw new \InvalidArgumentException(
                    "Cannot consume {$quantity} units: only {$available} units available "
                    .'(accounting for active reservations).'
                );
            }

            $allocation->sold_quantity += $quantity;
            $allocation->save();

            // Auto-transition to exhausted if remaining is 0
            if ($allocation->remaining_quantity === 0) {
                $allocation->status = AllocationStatus::Exhausted;
                $allocation->save();
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
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function markAsExhausted(Allocation $allocation): Allocation
    {
        if (! $allocation->status->canTransitionTo(AllocationStatus::Exhausted)) {
            throw new \InvalidArgumentException(
                "Cannot mark allocation as exhausted: current status '{$allocation->status->label()}' does not allow this transition."
            );
        }

        $allocation->status = AllocationStatus::Exhausted;
        $allocation->save();

        return $allocation;
    }
}
