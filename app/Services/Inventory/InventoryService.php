<?php

namespace App\Services\Inventory;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Jobs\Inventory\SyncCommittedInventoryJob;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for managing inventory queries and validations.
 *
 * Centralizes inventory logic for committed quantities, free stock,
 * and consumption eligibility checks.
 */
class InventoryService
{
    /**
     * Get the committed quantity for an allocation.
     *
     * Committed quantity = count of vouchers that are NOT redeemed.
     * This includes Issued and Locked vouchers (unredeemed vouchers).
     *
     * This method first checks for a cached value (synced by SyncCommittedInventoryJob)
     * for performance. If no cached value exists, it falls back to a live query.
     *
     * @return int The number of unredeemed vouchers for this allocation
     *
     * @throws \InvalidArgumentException If allocation is null
     */
    public function getCommittedQuantity(Allocation $allocation): int
    {
        // Try to get cached value first (synced by SyncCommittedInventoryJob)
        $cachedValue = SyncCommittedInventoryJob::getCachedCommittedQuantity($allocation);

        if ($cachedValue !== null) {
            return $cachedValue;
        }

        // Fallback to live query if cache miss
        return $this->getCommittedQuantityLive($allocation);
    }

    /**
     * Get the committed quantity for an allocation (live query, bypasses cache).
     *
     * Use this when you need guaranteed fresh data, e.g., after voucher changes.
     *
     * @return int The number of unredeemed vouchers for this allocation
     */
    public function getCommittedQuantityLive(Allocation $allocation): int
    {
        return Voucher::where('allocation_id', $allocation->id)
            ->whereIn('lifecycle_state', [
                VoucherLifecycleState::Issued,
                VoucherLifecycleState::Locked,
            ])
            ->count();
    }

    /**
     * Get the free quantity for an allocation.
     *
     * Free quantity = physical bottles - committed quantity.
     * Physical bottles are those that are available for fulfillment (stored state).
     *
     * @return int The number of free bottles (can be negative if oversold)
     *
     * @throws \InvalidArgumentException If allocation is null
     */
    public function getFreeQuantity(Allocation $allocation): int
    {
        $physicalBottles = $this->getPhysicalBottleCount($allocation);
        $committedQuantity = $this->getCommittedQuantity($allocation);

        return $physicalBottles - $committedQuantity;
    }

    /**
     * Check if a bottle can be consumed (e.g., for events).
     *
     * A bottle can be consumed if:
     * - It is in 'stored' state
     * - It is not committed (no unredeemed voucher is tied to it)
     *
     * Note: Individual bottle-to-voucher binding doesn't exist yet in the system,
     * so we check at the allocation level if there's free quantity available.
     *
     * @return bool True if the bottle can be consumed
     *
     * @throws \InvalidArgumentException If bottle is null
     */
    public function canConsume(SerializedBottle $bottle): bool
    {
        // Bottle must be in stored state to be consumable
        if ($bottle->state !== BottleState::Stored) {
            return false;
        }

        // Check ownership - only crurated_owned bottles can be consumed for events
        if (! $bottle->ownership_type->canConsumeForEvents()) {
            return false;
        }

        // Check if there's free quantity for this allocation
        $allocation = $bottle->allocation;
        if ($allocation === null) {
            throw new \InvalidArgumentException(
                'Bottle must have an allocation to check consumption eligibility'
            );
        }

        // If free quantity is positive, consumption is allowed
        return $this->getFreeQuantity($allocation) > 0;
    }

    /**
     * Get all bottles stored at a specific location.
     *
     * Only returns bottles with state = 'stored'.
     *
     * @return Collection<int, SerializedBottle>
     *
     * @throws \InvalidArgumentException If location is null
     */
    public function getBottlesAtLocation(Location $location): Collection
    {
        return SerializedBottle::where('current_location_id', $location->id)
            ->where('state', BottleState::Stored)
            ->get();
    }

    /**
     * Get all bottles by allocation lineage.
     *
     * Returns all serialized bottles that belong to a specific allocation,
     * regardless of their current state or location.
     *
     * @return Collection<int, SerializedBottle>
     *
     * @throws \InvalidArgumentException If allocation is null
     */
    public function getBottlesByAllocationLineage(Allocation $allocation): Collection
    {
        return SerializedBottle::where('allocation_id', $allocation->id)
            ->get();
    }

    /**
     * Get the count of physical bottles available for fulfillment.
     *
     * Physical bottles are those that are in 'stored' state and thus
     * available for fulfillment.
     *
     * @return int The count of physical bottles available
     */
    protected function getPhysicalBottleCount(Allocation $allocation): int
    {
        return SerializedBottle::where('allocation_id', $allocation->id)
            ->where('state', BottleState::Stored)
            ->count();
    }

    /**
     * Get the count of bottles pending serialization (inbound wine pending serialization).
     *
     * This returns the total quantity of bottles in inbound batches that have:
     * - Status pending_serialization or partially_serialized
     * - A receiving location authorized for serialization
     *
     * Used by Inventory Overview to show "Inbound wine pending serialization" count.
     * Note: This is a normal operational state, not an exception.
     *
     * @return int The total count of bottles awaiting serialization
     */
    public function getPendingSerializationCount(): int
    {
        // Get batches that are pending or partially serialized at authorized locations
        $batches = InboundBatch::query()
            ->whereIn('serialization_status', [
                InboundBatchStatus::PendingSerialization,
                InboundBatchStatus::PartiallySerialized,
            ])
            ->whereHas('receivingLocation', function ($query): void {
                $query->where('serialization_authorized', true)
                    ->where('status', 'active');
            })
            ->get();

        // Sum up the remaining unserialized bottles
        $totalRemaining = 0;
        foreach ($batches as $batch) {
            $totalRemaining += $batch->remaining_unserialized;
        }

        return $totalRemaining;
    }

    /**
     * Check if a bottle is committed for voucher fulfillment.
     *
     * A bottle is considered committed if:
     * - It is in 'stored' state (available for fulfillment)
     * - Its allocation has no free quantity (all physical bottles are committed)
     *
     * This is used to show warnings in the UI when committed bottles cannot be consumed.
     *
     * @return bool True if the bottle is committed (reserved for customer fulfillment)
     */
    public function isCommittedForFulfillment(SerializedBottle $bottle): bool
    {
        // If bottle is not in stored state, it's not "committed" in the traditional sense
        // (it may be shipped, consumed, etc.)
        if ($bottle->state !== BottleState::Stored) {
            return false;
        }

        $allocation = $bottle->allocation;
        if ($allocation === null) {
            return false;
        }

        // If free quantity is 0 or negative, the bottle is committed
        return $this->getFreeQuantity($allocation) <= 0;
    }

    /**
     * Get the reason why a bottle cannot be consumed.
     *
     * Returns a human-readable explanation of why canConsume() returns false.
     *
     * @return string|null The reason, or null if the bottle can be consumed
     */
    public function getCannotConsumeReason(SerializedBottle $bottle): ?string
    {
        // Check state first
        if ($bottle->state !== BottleState::Stored) {
            return "Bottle is in '{$bottle->state->label()}' state and cannot be consumed";
        }

        // Check ownership
        if (! $bottle->ownership_type->canConsumeForEvents()) {
            return "Bottle ownership type '{$bottle->ownership_type->label()}' does not allow event consumption";
        }

        // Check allocation
        $allocation = $bottle->allocation;
        if ($allocation === null) {
            return 'Bottle must have an allocation to be consumed';
        }

        // Check free quantity
        $freeQuantity = $this->getFreeQuantity($allocation);
        if ($freeQuantity <= 0) {
            return 'This bottle is reserved for customer fulfillment';
        }

        return null;
    }

    /**
     * Get committed bottles at a location.
     *
     * Returns bottles that are stored and owned by Crurated but cannot be consumed
     * because they are committed to vouchers.
     *
     * @return Collection<int, SerializedBottle>
     */
    public function getCommittedBottlesAtLocation(Location $location): Collection
    {
        // Get all stored, Crurated-owned bottles at this location
        $bottles = SerializedBottle::query()
            ->with(['allocation'])
            ->where('current_location_id', $location->id)
            ->where('state', BottleState::Stored)
            ->where('ownership_type', \App\Enums\Inventory\OwnershipType::CururatedOwned->value)
            ->get();

        // Filter to only committed bottles
        return $bottles->filter(function (SerializedBottle $bottle): bool {
            return $this->isCommittedForFulfillment($bottle);
        });
    }

    /**
     * Get detailed pending serialization statistics.
     *
     * Returns an array with:
     * - total_batches: Number of batches pending serialization
     * - pending_count: Batches with pending_serialization status
     * - partial_count: Batches with partially_serialized status
     * - total_bottles_remaining: Total bottles awaiting serialization
     *
     * @return array{total_batches: int, pending_count: int, partial_count: int, total_bottles_remaining: int}
     */
    public function getPendingSerializationStats(): array
    {
        $baseQuery = InboundBatch::query()
            ->whereIn('serialization_status', [
                InboundBatchStatus::PendingSerialization,
                InboundBatchStatus::PartiallySerialized,
            ])
            ->whereHas('receivingLocation', function ($query): void {
                $query->where('serialization_authorized', true)
                    ->where('status', 'active');
            });

        $totalBatches = (clone $baseQuery)->count();

        $pendingCount = (clone $baseQuery)
            ->where('serialization_status', InboundBatchStatus::PendingSerialization)
            ->count();

        $partialCount = (clone $baseQuery)
            ->where('serialization_status', InboundBatchStatus::PartiallySerialized)
            ->count();

        // Calculate total bottles remaining
        $batches = (clone $baseQuery)->get();
        $totalBottlesRemaining = 0;
        foreach ($batches as $batch) {
            $totalBottlesRemaining += $batch->remaining_unserialized;
        }

        return [
            'total_batches' => $totalBatches,
            'pending_count' => $pendingCount,
            'partial_count' => $partialCount,
            'total_bottles_remaining' => $totalBottlesRemaining,
        ];
    }

    /**
     * Get allocations that are at risk (free < 10% of committed).
     *
     * An allocation is at risk when:
     * - It has committed vouchers (unredeemed)
     * - The free quantity is less than 10% of committed quantity
     *
     * @return \Illuminate\Support\Collection<int, array{allocation: Allocation, committed: int, free: int, risk_percentage: float}>
     */
    public function getAtRiskAllocations(): \Illuminate\Support\Collection
    {
        /** @var array<int, array{allocation: Allocation, committed: int, free: int, risk_percentage: float}> $atRiskAllocations */
        $atRiskAllocations = [];

        // Get distinct allocations with stored bottles
        $allocationIds = SerializedBottle::distinct()
            ->whereNotNull('allocation_id')
            ->where('state', BottleState::Stored)
            ->pluck('allocation_id');

        foreach ($allocationIds as $allocationId) {
            /** @var Allocation|null $allocation */
            $allocation = Allocation::find($allocationId);
            if (! $allocation instanceof Allocation) {
                continue;
            }

            $committed = $this->getCommittedQuantity($allocation);
            $free = $this->getFreeQuantity($allocation);

            // Alert if committed > 0 and free < 10% of committed
            if ($committed > 0 && $free < ($committed * 0.1)) {
                $riskPercentage = round(($free / $committed) * 100, 1);
                $atRiskAllocations[] = [
                    'allocation' => $allocation,
                    'committed' => $committed,
                    'free' => $free,
                    'risk_percentage' => $riskPercentage,
                ];
            }
        }

        return collect($atRiskAllocations);
    }

    /**
     * Get bottles belonging to at-risk allocations.
     *
     * Returns all stored bottles from allocations where free < 10% of committed.
     *
     * @return Collection<int, SerializedBottle>
     */
    public function getBottlesFromAtRiskAllocations(): Collection
    {
        $atRiskAllocations = $this->getAtRiskAllocations();
        $allocationIds = $atRiskAllocations->pluck('allocation.id');

        if ($allocationIds->isEmpty()) {
            return new Collection;
        }

        return SerializedBottle::query()
            ->whereIn('allocation_id', $allocationIds)
            ->where('state', BottleState::Stored)
            ->get();
    }

    /**
     * Get allocation IDs that are at risk.
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getAtRiskAllocationIds(): \Illuminate\Support\Collection
    {
        return $this->getAtRiskAllocations()->pluck('allocation.id');
    }

    /**
     * Validate that a bottle can be used for a specific allocation (no cross-allocation substitution).
     *
     * This method enforces the critical business rule that bottles can ONLY fulfill
     * vouchers/orders from the SAME allocation lineage. Cross-allocation substitution
     * is strictly prohibited.
     *
     * US-B048: Allocation lineage substitution blocker
     *
     * @param  SerializedBottle  $bottle  The bottle to validate
     * @param  Allocation  $targetAllocation  The allocation the bottle is being used for
     * @return bool True if the bottle matches the allocation, false otherwise
     *
     * @throws \InvalidArgumentException If attempting cross-allocation substitution
     */
    public function validateAllocationLineageMatch(SerializedBottle $bottle, Allocation $targetAllocation): bool
    {
        if ($bottle->allocation_id !== $targetAllocation->id) {
            throw new \InvalidArgumentException(
                'Allocation lineage mismatch. Substitution not allowed. '.
                "Bottle allocation: {$bottle->allocation_id}, ".
                "Target allocation: {$targetAllocation->id}"
            );
        }

        return true;
    }

    /**
     * Check if a bottle matches a target allocation (without throwing exception).
     *
     * Use this method when you need to check allocation match without exception handling.
     *
     * @param  SerializedBottle  $bottle  The bottle to check
     * @param  Allocation  $targetAllocation  The allocation to match against
     * @return bool True if allocations match, false otherwise
     */
    public function bottleMatchesAllocation(SerializedBottle $bottle, Allocation $targetAllocation): bool
    {
        return $bottle->allocation_id === $targetAllocation->id;
    }

    /**
     * Filter a collection of bottles to only those matching a specific allocation.
     *
     * This helper ensures that only bottles from the correct allocation lineage
     * are returned, enforcing the no-substitution rule at the query level.
     *
     * @param  Collection<int, SerializedBottle>  $bottles  Collection of bottles to filter
     * @param  Allocation  $targetAllocation  The allocation to filter by
     * @return Collection<int, SerializedBottle> Filtered collection of matching bottles
     */
    public function filterBottlesByAllocation(Collection $bottles, Allocation $targetAllocation): Collection
    {
        return $bottles->filter(
            fn (SerializedBottle $bottle): bool => $bottle->allocation_id === $targetAllocation->id
        );
    }

    /**
     * Get bottles available for fulfillment for a specific allocation.
     *
     * Returns only bottles that:
     * - Match the target allocation (no substitution)
     * - Are in 'stored' state
     * - Are available for fulfillment
     *
     * @param  Allocation  $allocation  The allocation to get bottles for
     * @return Collection<int, SerializedBottle> Available bottles for this allocation
     */
    public function getAvailableBottlesForAllocation(Allocation $allocation): Collection
    {
        return SerializedBottle::query()
            ->where('allocation_id', $allocation->id)
            ->where('state', BottleState::Stored)
            ->get();
    }

    /**
     * Check if there are any available bottles for a specific allocation.
     *
     * @param  Allocation  $allocation  The allocation to check
     * @return bool True if bottles are available, false otherwise
     */
    public function hasAvailableBottlesForAllocation(Allocation $allocation): bool
    {
        return SerializedBottle::query()
            ->where('allocation_id', $allocation->id)
            ->where('state', BottleState::Stored)
            ->exists();
    }

    /**
     * Get the allocation lineage display string for a bottle.
     *
     * Returns a human-readable string showing the bottle's allocation lineage,
     * which should be prominently displayed in all inventory views.
     *
     * @param  SerializedBottle  $bottle  The bottle to get lineage for
     * @return string Human-readable allocation lineage
     */
    public function getAllocationLineageDisplay(SerializedBottle $bottle): string
    {
        $allocation = $bottle->allocation;
        if ($allocation === null) {
            return 'No Allocation (ERROR)';
        }

        return $allocation->getBottleSkuLabel().' (ID: '.substr($allocation->id, 0, 8).'...)';
    }
}
