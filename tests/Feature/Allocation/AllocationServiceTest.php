<?php

namespace Tests\Feature\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Models\Allocation\Allocation;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\Allocation\AllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AllocationService $service;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AllocationService::class);

        $wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
            'country' => 'Italy',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $this->format = Format::create([
            'name' => 'Standard Bottle',
            'volume_ml' => 750,
            'is_standard' => true,
            'allowed_for_liquid_conversion' => true,
        ]);
    }

    /**
     * Helper to create an allocation with given attributes.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createAllocation(array $overrides = []): Allocation
    {
        return Allocation::create(array_merge([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::OwnedStock,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'status' => AllocationStatus::Draft,
        ], $overrides));
    }

    public function test_activate_sets_status_to_active(): void
    {
        $allocation = $this->createAllocation();

        $result = $this->service->activate($allocation);

        $this->assertEquals(AllocationStatus::Active, $result->status);
        $this->assertEquals(AllocationStatus::Active, $allocation->fresh()?->status);
    }

    public function test_allocation_created_with_explicit_status_can_be_activated(): void
    {
        // Regression: without explicit status in create data, the in-memory
        // model has null status even if DB has default 'draft' (the root bug).
        // Explicit status = 'draft' ensures the model is immediately usable.
        $allocation = $this->createAllocation(['status' => AllocationStatus::Draft]);

        $result = $this->service->activate($allocation);

        $this->assertEquals(AllocationStatus::Active, $result->status);
    }

    public function test_activate_fails_from_active_status(): void
    {
        $allocation = $this->createAllocation(['status' => AllocationStatus::Active]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot activate allocation');
        $this->service->activate($allocation);
    }

    public function test_activate_fails_from_closed_status(): void
    {
        $allocation = $this->createAllocation(['status' => AllocationStatus::Closed]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->activate($allocation);
    }

    public function test_close_active_allocation(): void
    {
        $allocation = $this->createAllocation(['status' => AllocationStatus::Active]);

        $result = $this->service->close($allocation);

        $this->assertEquals(AllocationStatus::Closed, $result->status);
    }

    public function test_close_fails_from_draft_status(): void
    {
        $allocation = $this->createAllocation();

        $this->expectException(InvalidArgumentException::class);
        $this->service->close($allocation);
    }

    public function test_consume_allocation_decrements_remaining(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 100,
            'sold_quantity' => 0,
        ]);

        $result = $this->service->consumeAllocation($allocation, 10);

        $this->assertEquals(10, $result->sold_quantity);
        $this->assertEquals(90, $result->remaining_quantity);
    }

    public function test_consume_fails_when_not_active(): void
    {
        $allocation = $this->createAllocation();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not allow consumption');
        $this->service->consumeAllocation($allocation, 1);
    }

    public function test_consume_fails_when_exceeding_available(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 10,
            'sold_quantity' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only 10 units available');
        $this->service->consumeAllocation($allocation, 15);
    }

    public function test_consume_auto_exhausts_when_fully_consumed(): void
    {
        $allocation = $this->createAllocation([
            'status' => AllocationStatus::Active,
            'total_quantity' => 10,
            'sold_quantity' => 0,
        ]);

        $result = $this->service->consumeAllocation($allocation, 10);

        $this->assertEquals(AllocationStatus::Exhausted, $result->status);
    }

    public function test_can_activate_returns_true_for_draft(): void
    {
        $allocation = $this->createAllocation();

        $this->assertTrue($this->service->canActivate($allocation));
    }

    public function test_can_activate_returns_false_for_active(): void
    {
        $allocation = $this->createAllocation(['status' => AllocationStatus::Active]);

        $this->assertFalse($this->service->canActivate($allocation));
    }
}
