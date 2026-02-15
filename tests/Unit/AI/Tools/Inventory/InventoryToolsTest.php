<?php

namespace Tests\Unit\AI\Tools\Inventory;

use App\AI\Tools\Inventory\CaseIntegrityStatusTool;
use App\AI\Tools\Inventory\StockLevelsByLocationTool;
use App\AI\Tools\Inventory\TotalBottlesCountTool;
use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Enums\Inventory\OwnershipType;
use App\Enums\UserRole;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Unit tests for Inventory AI tools:
 * - StockLevelsByLocationTool
 * - TotalBottlesCountTool
 * - CaseIntegrityStatusTool
 */
class InventoryToolsTest extends TestCase
{
    use RefreshDatabase;

    protected Location $locationA;

    protected Location $locationB;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected Allocation $allocation;

    protected InboundBatch $inboundBatch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two locations
        $this->locationA = Location::create([
            'name' => 'Warehouse London',
            'location_type' => LocationType::MainWarehouse,
            'country' => 'UK',
            'status' => LocationStatus::Active,
            'serialization_authorized' => true,
        ]);

        $this->locationB = Location::create([
            'name' => 'Warehouse Paris',
            'location_type' => LocationType::SatelliteWarehouse,
            'country' => 'France',
            'status' => LocationStatus::Active,
            'serialization_authorized' => true,
        ]);

        // Create PIM dependencies
        $this->wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
            'country' => 'Italy',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $this->format = Format::create([
            'name' => 'Standard 750ml',
            'volume_ml' => 750,
        ]);

        // Create allocation
        $this->allocation = Allocation::create([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'source_type' => AllocationSourceType::ProducerAllocation,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'remaining_quantity' => 100,
            'status' => AllocationStatus::Active,
            'serialization_required' => true,
        ]);

        // Create inbound batch
        $this->inboundBatch = InboundBatch::create([
            'source_type' => 'producer',
            'product_reference_type' => 'App\\Models\\Pim\\WineVariant',
            'product_reference_id' => $this->wineVariant->id,
            'allocation_id' => $this->allocation->id,
            'quantity_expected' => 50,
            'quantity_received' => 50,
            'packaging_type' => 'case',
            'receiving_location_id' => $this->locationA->id,
            'ownership_type' => OwnershipType::CururatedOwned,
            'received_date' => now()->toDateString(),
            'serialization_status' => 'fully_serialized',
        ]);
    }

    /**
     * Helper: create a SerializedBottle with sensible defaults.
     */
    private function createBottle(array $overrides = []): SerializedBottle
    {
        static $serial = 0;
        $serial++;

        return SerializedBottle::create(array_merge([
            'serial_number' => 'BTL-'.str_pad((string) $serial, 6, '0', STR_PAD_LEFT),
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'allocation_id' => $this->allocation->id,
            'inbound_batch_id' => $this->inboundBatch->id,
            'current_location_id' => $this->locationA->id,
            'ownership_type' => OwnershipType::CururatedOwned,
            'state' => BottleState::Stored,
            'serialized_at' => now(),
        ], $overrides));
    }

    // =========================================================================
    // StockLevelsByLocationTool
    // =========================================================================

    public function test_stock_levels_by_location_happy_path(): void
    {
        // Create 3 bottles at locationA, 2 at locationB -- all stored
        $this->createBottle(['current_location_id' => $this->locationA->id]);
        $this->createBottle(['current_location_id' => $this->locationA->id]);
        $this->createBottle(['current_location_id' => $this->locationA->id]);
        $this->createBottle(['current_location_id' => $this->locationB->id]);
        $this->createBottle(['current_location_id' => $this->locationB->id]);

        $tool = new StockLevelsByLocationTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_bottles', $data);
        $this->assertArrayHasKey('state_filter', $data);
        $this->assertArrayHasKey('by_location', $data);

        $this->assertEquals(5, $data['total_bottles']);
        $this->assertEquals('Stored', $data['state_filter']);
        $this->assertCount(2, $data['by_location']);

        // Sorted descending by bottle_count: locationA (3) first, then locationB (2)
        $this->assertEquals('Warehouse London', $data['by_location'][0]['location_name']);
        $this->assertEquals(3, $data['by_location'][0]['bottle_count']);
        $this->assertEquals('Warehouse Paris', $data['by_location'][1]['location_name']);
        $this->assertEquals(2, $data['by_location'][1]['bottle_count']);
    }

    public function test_stock_levels_by_location_filters_by_state_and_location(): void
    {
        // Create stored bottles at locationA
        $this->createBottle(['current_location_id' => $this->locationA->id, 'state' => BottleState::Stored]);
        $this->createBottle(['current_location_id' => $this->locationA->id, 'state' => BottleState::Stored]);

        // Create reserved bottle at locationA (different state)
        $this->createBottle(['current_location_id' => $this->locationA->id, 'state' => BottleState::ReservedForPicking]);

        // Create stored bottle at locationB
        $this->createBottle(['current_location_id' => $this->locationB->id, 'state' => BottleState::Stored]);

        $tool = new StockLevelsByLocationTool;

        // Filter by state = reserved_for_picking
        $result = $tool->handle(new Request(['state' => 'reserved_for_picking']));
        $data = json_decode($result, true);

        $this->assertEquals(1, $data['total_bottles']);
        $this->assertEquals('Reserved for Picking', $data['state_filter']);

        // Filter by location_id = locationA (default state = stored)
        $result = $tool->handle(new Request(['location_id' => $this->locationA->id]));
        $data = json_decode($result, true);

        $this->assertEquals(2, $data['total_bottles']);
        $this->assertCount(1, $data['by_location']);
        $this->assertEquals('Warehouse London', $data['by_location'][0]['location_name']);

        // Filter by both state AND location
        $result = $tool->handle(new Request([
            'state' => 'reserved_for_picking',
            'location_id' => $this->locationA->id,
        ]));
        $data = json_decode($result, true);

        $this->assertEquals(1, $data['total_bottles']);
    }

    public function test_stock_levels_by_location_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new StockLevelsByLocationTool;

        // StockLevelsByLocationTool requires Basic access level.
        // Viewer maps to Overview (10) which is less than Basic (20).
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_stock_levels_by_location_authorization_editor_allowed(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $tool = new StockLevelsByLocationTool;

        // Editor maps to Basic (20) which equals Basic (20).
        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // TotalBottlesCountTool
    // =========================================================================

    public function test_total_bottles_count_happy_path(): void
    {
        // Create bottles in various states and ownership types
        $this->createBottle(['state' => BottleState::Stored, 'ownership_type' => OwnershipType::CururatedOwned]);
        $this->createBottle(['state' => BottleState::Stored, 'ownership_type' => OwnershipType::CururatedOwned]);
        $this->createBottle(['state' => BottleState::ReservedForPicking, 'ownership_type' => OwnershipType::InCustody]);
        $this->createBottle(['state' => BottleState::Shipped, 'ownership_type' => OwnershipType::ThirdPartyOwned]);

        $tool = new TotalBottlesCountTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_bottles', $data);
        $this->assertArrayHasKey('by_state', $data);
        $this->assertArrayHasKey('by_ownership', $data);

        $this->assertEquals(4, $data['total_bottles']);

        // Verify state breakdown
        $this->assertEquals(2, $data['by_state']['Stored']);
        $this->assertEquals(1, $data['by_state']['Reserved for Picking']);
        $this->assertEquals(1, $data['by_state']['Shipped']);
        $this->assertEquals(0, $data['by_state']['Consumed']);

        // Verify ownership breakdown
        $this->assertEquals(2, $data['by_ownership']['Crurated Owned']);
        $this->assertEquals(1, $data['by_ownership']['In Custody']);
        $this->assertEquals(1, $data['by_ownership']['Third Party Owned']);
    }

    public function test_total_bottles_count_empty_inventory(): void
    {
        $tool = new TotalBottlesCountTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertEquals(0, $data['total_bottles']);

        // All state counts should be zero
        foreach ($data['by_state'] as $count) {
            $this->assertEquals(0, $count);
        }

        // All ownership counts should be zero
        foreach ($data['by_ownership'] as $count) {
            $this->assertEquals(0, $count);
        }
    }

    public function test_total_bottles_count_authorization_viewer_allowed(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new TotalBottlesCountTool;

        // TotalBottlesCountTool requires Overview access level.
        // Viewer maps to Overview (10) which equals Overview (10).
        $this->assertTrue($tool->authorizeForUser($viewer));
    }

    public function test_total_bottles_count_authorization_null_role_denied(): void
    {
        // The DB schema requires a non-null role, so we create a user
        // then force-set the role property to null to test the guard.
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        $user->role = null;

        $tool = new TotalBottlesCountTool;

        // User with null role should always be denied.
        $this->assertFalse($tool->authorizeForUser($user));
    }

    // =========================================================================
    // CaseIntegrityStatusTool
    // =========================================================================

    public function test_case_integrity_status_happy_path(): void
    {
        // Create case configuration dependency
        $caseConfig = CaseConfiguration::create([
            'name' => '6-pack OWC',
            'format_id' => $this->format->id,
            'bottles_per_case' => 6,
            'case_type' => 'owc',
            'is_original_from_producer' => true,
            'is_breakable' => true,
        ]);

        // Create 3 intact and 2 broken cases
        for ($i = 0; $i < 3; $i++) {
            InventoryCase::create([
                'case_configuration_id' => $caseConfig->id,
                'allocation_id' => $this->allocation->id,
                'current_location_id' => $this->locationA->id,
                'is_original' => true,
                'is_breakable' => true,
                'integrity_status' => CaseIntegrityStatus::Intact,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            InventoryCase::create([
                'case_configuration_id' => $caseConfig->id,
                'allocation_id' => $this->allocation->id,
                'current_location_id' => $this->locationA->id,
                'is_original' => true,
                'is_breakable' => true,
                'integrity_status' => CaseIntegrityStatus::Broken,
                'broken_at' => now(),
                'broken_reason' => 'Customer request',
            ]);
        }

        $tool = new CaseIntegrityStatusTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_cases', $data);
        $this->assertArrayHasKey('intact_count', $data);
        $this->assertArrayHasKey('broken_count', $data);
        $this->assertArrayHasKey('intact_percentage', $data);

        $this->assertEquals(5, $data['total_cases']);
        $this->assertEquals(3, $data['intact_count']);
        $this->assertEquals(2, $data['broken_count']);
        $this->assertEquals(60.0, $data['intact_percentage']);
    }

    public function test_case_integrity_status_all_intact(): void
    {
        $caseConfig = CaseConfiguration::create([
            'name' => '12-pack OWC',
            'format_id' => $this->format->id,
            'bottles_per_case' => 12,
            'case_type' => 'owc',
            'is_original_from_producer' => true,
            'is_breakable' => true,
        ]);

        // Create 4 intact cases
        for ($i = 0; $i < 4; $i++) {
            InventoryCase::create([
                'case_configuration_id' => $caseConfig->id,
                'allocation_id' => $this->allocation->id,
                'current_location_id' => $this->locationA->id,
                'is_original' => true,
                'is_breakable' => true,
                'integrity_status' => CaseIntegrityStatus::Intact,
            ]);
        }

        $tool = new CaseIntegrityStatusTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertEquals(4, $data['total_cases']);
        $this->assertEquals(4, $data['intact_count']);
        $this->assertEquals(0, $data['broken_count']);
        $this->assertEquals(100.0, $data['intact_percentage']);
    }

    public function test_case_integrity_status_empty_inventory(): void
    {
        $tool = new CaseIntegrityStatusTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode($result, true);

        $this->assertEquals(0, $data['total_cases']);
        $this->assertEquals(0, $data['intact_count']);
        $this->assertEquals(0, $data['broken_count']);
        // With 0 total, percentage should be 0
        $this->assertEquals(0, $data['intact_percentage']);
    }

    public function test_case_integrity_status_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $tool = new CaseIntegrityStatusTool;

        // CaseIntegrityStatusTool requires Basic access level.
        // Viewer maps to Overview (10) which is less than Basic (20).
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_case_integrity_status_authorization_admin_allowed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $tool = new CaseIntegrityStatusTool;

        // Admin maps to Full (60) which is greater than Basic (20).
        $this->assertTrue($tool->authorizeForUser($admin));
    }
}
