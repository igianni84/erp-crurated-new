<?php

namespace App\Services\Inventory;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Inventory\BottleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
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
     * @return int The number of unredeemed vouchers for this allocation
     *
     * @throws \InvalidArgumentException If allocation is null
     */
    public function getCommittedQuantity(Allocation $allocation): int
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
}
