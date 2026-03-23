<?php

namespace Tests\Feature\Services\Inventory;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private Allocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
        $this->allocation = Allocation::factory()->active()->create(['total_quantity' => 50]);

        $this->actingAs(User::factory()->create());
    }

    // --- getCommittedQuantity / getCommittedQuantityLive ---

    public function test_committed_quantity_counts_issued_and_locked_vouchers(): void
    {
        Voucher::factory()->count(3)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);
        Voucher::factory()->count(2)->locked()->create([
            'allocation_id' => $this->allocation->id,
        ]);
        // These should NOT count
        Voucher::factory()->redeemed()->create(['allocation_id' => $this->allocation->id]);
        Voucher::factory()->cancelled()->create(['allocation_id' => $this->allocation->id]);

        $committed = $this->service->getCommittedQuantityLive($this->allocation);

        $this->assertEquals(5, $committed);
    }

    public function test_committed_quantity_zero_with_no_vouchers(): void
    {
        $committed = $this->service->getCommittedQuantityLive($this->allocation);

        $this->assertEquals(0, $committed);
    }

    public function test_committed_quantity_live_bypasses_cache(): void
    {
        Voucher::factory()->count(3)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        // Live should always return fresh data
        $live = $this->service->getCommittedQuantityLive($this->allocation);
        $this->assertEquals(3, $live);

        // Add another voucher
        Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $liveAfter = $this->service->getCommittedQuantityLive($this->allocation);
        $this->assertEquals(4, $liveAfter);
    }

    // --- getFreeQuantity ---

    public function test_free_quantity_is_physical_minus_committed(): void
    {
        // 5 stored bottles
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        // 3 committed vouchers
        Voucher::factory()->count(3)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $free = $this->service->getFreeQuantity($this->allocation);

        $this->assertEquals(2, $free);
    }

    public function test_free_quantity_can_be_negative(): void
    {
        // 2 stored bottles
        SerializedBottle::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        // 5 committed vouchers (oversold)
        Voucher::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $free = $this->service->getFreeQuantity($this->allocation);

        $this->assertLessThan(0, $free);
        $this->assertEquals(-3, $free);
    }

    // --- canConsume ---

    public function test_can_consume_true_for_stored_bottle(): void
    {
        // 5 bottles, 2 committed = 3 free
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::CururatedOwned,
        ]);
        Voucher::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $bottle = SerializedBottle::where('allocation_id', $this->allocation->id)->first();
        $this->assertNotNull($bottle);

        $this->assertTrue($this->service->canConsume($bottle));
    }

    public function test_can_consume_false_for_non_stored(): void
    {
        $bottle = SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
            'ownership_type' => OwnershipType::CururatedOwned,
        ]);

        $this->assertFalse($this->service->canConsume($bottle));
    }

    public function test_can_consume_false_for_non_crurated_owned(): void
    {
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);

        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::ThirdPartyOwned,
        ]);

        $this->assertFalse($this->service->canConsume($bottle));
    }

    public function test_can_consume_false_when_no_free_qty(): void
    {
        // 2 bottles, 2 committed = 0 free
        SerializedBottle::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::CururatedOwned,
        ]);
        Voucher::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $bottle = SerializedBottle::where('allocation_id', $this->allocation->id)->first();
        $this->assertNotNull($bottle);

        $this->assertFalse($this->service->canConsume($bottle));
    }

    // --- getBottlesAtLocation ---

    public function test_bottles_at_location_only_stored(): void
    {
        $location = Location::factory()->create();
        SerializedBottle::factory()->count(3)->create([
            'current_location_id' => $location->id,
            'state' => BottleState::Stored,
        ]);
        SerializedBottle::factory()->shipped()->create([
            'current_location_id' => $location->id,
        ]);

        $bottles = $this->service->getBottlesAtLocation($location);

        $this->assertCount(3, $bottles);
    }

    public function test_bottles_at_location_scoped(): void
    {
        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();
        SerializedBottle::factory()->count(3)->create([
            'current_location_id' => $locationA->id,
            'state' => BottleState::Stored,
        ]);
        SerializedBottle::factory()->count(2)->create([
            'current_location_id' => $locationB->id,
            'state' => BottleState::Stored,
        ]);

        $bottles = $this->service->getBottlesAtLocation($locationA);

        $this->assertCount(3, $bottles);
    }

    // --- getBottlesByAllocationLineage ---

    public function test_bottles_by_lineage_all_states(): void
    {
        SerializedBottle::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $bottles = $this->service->getBottlesByAllocationLineage($this->allocation);

        $this->assertCount(3, $bottles);
    }

    // --- isCommittedForFulfillment ---

    public function test_committed_for_fulfillment_true_when_free_zero(): void
    {
        // 1 bottle, 1 voucher = 0 free
        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        Voucher::factory()->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $this->assertTrue($this->service->isCommittedForFulfillment($bottle));
    }

    public function test_committed_for_fulfillment_false_when_free_positive(): void
    {
        // 5 bottles, 2 vouchers = 3 free
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        Voucher::factory()->count(2)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $bottle = SerializedBottle::where('allocation_id', $this->allocation->id)->first();
        $this->assertNotNull($bottle);

        $this->assertFalse($this->service->isCommittedForFulfillment($bottle));
    }

    public function test_committed_for_fulfillment_false_for_non_stored(): void
    {
        $bottle = SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $this->assertFalse($this->service->isCommittedForFulfillment($bottle));
    }

    // --- getAtRiskAllocations ---

    public function test_at_risk_allocations_threshold(): void
    {
        // 10 bottles, 10 vouchers = 0 free (0% of committed)
        SerializedBottle::factory()->count(10)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        Voucher::factory()->count(10)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $atRisk = $this->service->getAtRiskAllocations();

        $this->assertCount(1, $atRisk);
        $firstAtRisk = $atRisk->first();
        $this->assertNotNull($firstAtRisk);
        $this->assertEquals($this->allocation->id, $firstAtRisk['allocation']->id);
        $this->assertEquals(10, $firstAtRisk['committed']);
        $this->assertEquals(0, $firstAtRisk['free']);
    }

    public function test_at_risk_excludes_healthy(): void
    {
        // 10 bottles, 5 vouchers = 5 free (100% of committed) — healthy
        SerializedBottle::factory()->count(10)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        Voucher::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);

        $atRisk = $this->service->getAtRiskAllocations();

        $this->assertCount(0, $atRisk);
    }

    // --- validateAllocationLineageMatch ---

    public function test_lineage_match_passes(): void
    {
        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $result = $this->service->validateAllocationLineageMatch($bottle, $this->allocation);

        $this->assertTrue($result);
    }

    public function test_lineage_match_throws_mismatch(): void
    {
        $otherAllocation = Allocation::factory()->active()->create();
        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allocation lineage mismatch');

        $this->service->validateAllocationLineageMatch($bottle, $otherAllocation);
    }

    // --- getAvailableBottlesForAllocation / hasAvailableBottlesForAllocation ---

    public function test_available_bottles_only_stored(): void
    {
        SerializedBottle::factory()->count(3)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
        ]);
        // Different allocation
        SerializedBottle::factory()->create([
            'state' => BottleState::Stored,
        ]);

        $available = $this->service->getAvailableBottlesForAllocation($this->allocation);

        $this->assertCount(3, $available);
    }

    public function test_has_available_bottles(): void
    {
        $this->assertFalse($this->service->hasAvailableBottlesForAllocation($this->allocation));

        SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);

        $this->assertTrue($this->service->hasAvailableBottlesForAllocation($this->allocation));
    }

    // --- getCannotConsumeReason ---

    public function test_cannot_consume_reason_messages(): void
    {
        // Non-stored bottle
        $shipped = SerializedBottle::factory()->shipped()->create([
            'allocation_id' => $this->allocation->id,
        ]);
        $this->assertStringContainsString('state', (string) $this->service->getCannotConsumeReason($shipped));

        // Third-party owned
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
        ]);
        $thirdParty = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::ThirdPartyOwned,
        ]);
        $this->assertStringContainsString('ownership type', (string) $this->service->getCannotConsumeReason($thirdParty));

        // Committed (0 free)
        $alloc2 = Allocation::factory()->active()->create();
        $committed = SerializedBottle::factory()->create([
            'allocation_id' => $alloc2->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::CururatedOwned,
        ]);
        Voucher::factory()->create([
            'allocation_id' => $alloc2->id,
            'lifecycle_state' => VoucherLifecycleState::Issued,
        ]);
        $this->assertStringContainsString('reserved for customer', (string) $this->service->getCannotConsumeReason($committed));

        // Consumable — no reason
        SerializedBottle::factory()->count(5)->create([
            'allocation_id' => $alloc2->id,
            'state' => BottleState::Stored,
        ]);
        $ok = SerializedBottle::factory()->create([
            'allocation_id' => $alloc2->id,
            'state' => BottleState::Stored,
            'ownership_type' => OwnershipType::CururatedOwned,
        ]);
        $this->assertNull($this->service->getCannotConsumeReason($ok));
    }

    // --- getPendingSerializationCount / getPendingSerializationStats ---

    public function test_pending_serialization_count(): void
    {
        $location = Location::factory()->create([
            'serialization_authorized' => true,
            'status' => 'active',
        ]);

        InboundBatch::factory()->create([
            'receiving_location_id' => $location->id,
            'serialization_status' => InboundBatchStatus::PendingSerialization,
            'quantity_expected' => 10,
            'quantity_received' => 10,
        ]);
        InboundBatch::factory()->create([
            'receiving_location_id' => $location->id,
            'serialization_status' => InboundBatchStatus::PartiallySerialized,
            'quantity_expected' => 6,
            'quantity_received' => 6,
        ]);
        // Fully serialized should NOT count
        InboundBatch::factory()->fullySerialized()->create([
            'receiving_location_id' => $location->id,
        ]);

        $count = $this->service->getPendingSerializationCount();

        $this->assertGreaterThan(0, $count);
    }

    public function test_pending_serialization_stats(): void
    {
        $location = Location::factory()->create([
            'serialization_authorized' => true,
            'status' => 'active',
        ]);

        InboundBatch::factory()->count(2)->create([
            'receiving_location_id' => $location->id,
            'serialization_status' => InboundBatchStatus::PendingSerialization,
        ]);
        InboundBatch::factory()->create([
            'receiving_location_id' => $location->id,
            'serialization_status' => InboundBatchStatus::PartiallySerialized,
        ]);

        $stats = $this->service->getPendingSerializationStats();

        $this->assertArrayHasKey('total_batches', $stats);
        $this->assertArrayHasKey('pending_count', $stats);
        $this->assertArrayHasKey('partial_count', $stats);
        $this->assertArrayHasKey('total_bottles_remaining', $stats);
        $this->assertEquals(3, $stats['total_batches']);
        $this->assertEquals(2, $stats['pending_count']);
        $this->assertEquals(1, $stats['partial_count']);
    }

    // --- bottleMatchesAllocation ---

    public function test_bottle_matches_allocation_true(): void
    {
        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $this->assertTrue($this->service->bottleMatchesAllocation($bottle, $this->allocation));
    }

    public function test_bottle_matches_allocation_false(): void
    {
        $otherAllocation = Allocation::factory()->active()->create();
        $bottle = SerializedBottle::factory()->create([
            'allocation_id' => $this->allocation->id,
        ]);

        $this->assertFalse($this->service->bottleMatchesAllocation($bottle, $otherAllocation));
    }
}
