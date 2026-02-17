<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\LocationType;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\CaseResource;
use App\Filament\Resources\Inventory\InboundBatchResource;
use App\Filament\Resources\Inventory\LocationResource;
use App\Filament\Resources\Inventory\SerializedBottleResource;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\InventoryService;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Inventory Overview - Module B Control Tower Dashboard.
 *
 * Provides a dashboard view of all inventory-related metrics and alerts.
 * This is the landing page for the Inventory navigation group.
 *
 * Features 4 main widgets:
 * - Widget A: Global Inventory KPIs
 * - Widget B: Inventory by Location
 * - Widget C: Alerts & Exceptions
 * - Widget D: Recent Activity (optional, for quick links)
 *
 * Dashboard is read-only, non-transactional.
 * Links from each widget to filtered list corresponding.
 */
class InventoryOverview extends Page
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Inventory Overview';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Inventory Overview';

    protected string $view = 'filament.pages.inventory-overview';

    /**
     * Get the page heading.
     */
    public function getHeading(): string|Htmlable
    {
        return 'Inventory Overview';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Module B Control Tower - Physical inventory management dashboard';
    }

    // ========================================
    // Widget A: Global Inventory KPIs
    // ========================================

    /**
     * Get global inventory KPI metrics.
     *
     * @return array{
     *     total_serialized_bottles: int,
     *     unserialized_inbound: int,
     *     bottles_stored: int,
     *     bottles_reserved: int,
     *     bottles_shipped: int,
     *     bottles_consumed: int,
     *     bottles_destroyed: int,
     *     bottles_missing: int,
     *     committed_quantity: int,
     *     free_quantity: int,
     *     total_cases: int,
     *     intact_cases: int
     * }
     */
    public function getGlobalKpis(): array
    {
        // Total serialized bottles (all states except soft-deleted)
        $totalSerialized = SerializedBottle::count();

        // Unserialized inbound quantities
        $inventoryService = app(InventoryService::class);
        $unserializedInbound = $inventoryService->getPendingSerializationCount();

        // Bottles by state breakdown
        $bottlesStored = SerializedBottle::where('state', BottleState::Stored)->count();
        $bottlesReserved = SerializedBottle::where('state', BottleState::ReservedForPicking)->count();
        $bottlesShipped = SerializedBottle::where('state', BottleState::Shipped)->count();
        $bottlesConsumed = SerializedBottle::where('state', BottleState::Consumed)->count();
        $bottlesDestroyed = SerializedBottle::where('state', BottleState::Destroyed)->count();
        $bottlesMissing = SerializedBottle::where('state', BottleState::Missing)->count();

        // Committed vs Free: Sum across all allocations with physical bottles
        $totalCommitted = 0;
        $totalFree = 0;

        // Get distinct allocations with bottles
        $allocationIds = SerializedBottle::distinct()
            ->whereNotNull('allocation_id')
            ->pluck('allocation_id');

        foreach ($allocationIds as $allocationId) {
            /** @var Allocation|null $allocation */
            $allocation = Allocation::find($allocationId);
            if ($allocation instanceof Allocation) {
                $committed = $inventoryService->getCommittedQuantity($allocation);
                $free = $inventoryService->getFreeQuantity($allocation);
                $totalCommitted += $committed;
                $totalFree += max(0, $free); // Only count positive free quantities
            }
        }

        // Case statistics
        $totalCases = InventoryCase::count();
        $intactCases = InventoryCase::where('integrity_status', CaseIntegrityStatus::Intact)->count();

        return [
            'total_serialized_bottles' => $totalSerialized,
            'unserialized_inbound' => $unserializedInbound,
            'bottles_stored' => $bottlesStored,
            'bottles_reserved' => $bottlesReserved,
            'bottles_shipped' => $bottlesShipped,
            'bottles_consumed' => $bottlesConsumed,
            'bottles_destroyed' => $bottlesDestroyed,
            'bottles_missing' => $bottlesMissing,
            'committed_quantity' => $totalCommitted,
            'free_quantity' => $totalFree,
            'total_cases' => $totalCases,
            'intact_cases' => $intactCases,
        ];
    }

    /**
     * Get bottle state metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getBottleStateMeta(): array
    {
        $meta = [];
        foreach (BottleState::cases() as $state) {
            $meta[$state->value] = [
                'label' => $state->label(),
                'color' => $state->color(),
                'icon' => $state->icon(),
            ];
        }

        return $meta;
    }

    // ========================================
    // Widget B: Inventory by Location
    // ========================================

    /**
     * Get top locations by bottle count.
     *
     * @return Collection<int, array{location: Location, bottle_count: int, case_count: int}>
     */
    public function getTopLocationsByBottleCount(int $limit = 10): Collection
    {
        $locations = Location::query()
            ->withCount([
                'serializedBottles as stored_bottle_count' => function (Builder $query): void {
                    $query->where('state', BottleState::Stored);
                },
                'cases as intact_case_count' => function (Builder $query): void {
                    $query->where('integrity_status', CaseIntegrityStatus::Intact);
                },
            ])
            ->orderByDesc('stored_bottle_count')
            ->limit($limit)
            ->get();

        return $locations->map(function (Location $location): array {
            /** @var int $bottleCount */
            $bottleCount = $location->stored_bottle_count ?? 0;
            /** @var int $caseCount */
            $caseCount = $location->intact_case_count ?? 0;

            return [
                'location' => $location,
                'bottle_count' => $bottleCount,
                'case_count' => $caseCount,
            ];
        });
    }

    /**
     * Get warehouse vs consignee breakdown.
     *
     * @return array{
     *     warehouse_bottles: int,
     *     warehouse_locations: int,
     *     consignee_bottles: int,
     *     consignee_locations: int
     * }
     */
    public function getLocationTypeBreakdown(): array
    {
        $warehouseTypes = [
            LocationType::MainWarehouse,
            LocationType::SatelliteWarehouse,
        ];

        // Warehouse breakdown
        $warehouseLocations = Location::query()
            ->whereIn('location_type', $warehouseTypes)
            ->where('status', 'active')
            ->get();

        $warehouseBottles = SerializedBottle::query()
            ->where('state', BottleState::Stored)
            ->whereIn('current_location_id', $warehouseLocations->pluck('id'))
            ->count();

        // Consignee breakdown
        $consigneeLocations = Location::query()
            ->where('location_type', LocationType::Consignee)
            ->where('status', 'active')
            ->get();

        $consigneeBottles = SerializedBottle::query()
            ->where('state', BottleState::Stored)
            ->whereIn('current_location_id', $consigneeLocations->pluck('id'))
            ->count();

        return [
            'warehouse_bottles' => $warehouseBottles,
            'warehouse_locations' => $warehouseLocations->count(),
            'consignee_bottles' => $consigneeBottles,
            'consignee_locations' => $consigneeLocations->count(),
        ];
    }

    // ========================================
    // Widget C: Alerts & Exceptions
    // ========================================

    /**
     * Get alerts requiring attention.
     *
     * @return array{
     *     serialization_pending: int,
     *     batches_with_discrepancy: int,
     *     committed_at_risk: int,
     *     wms_sync_errors: int,
     *     unresolved_exceptions: int
     * }
     */
    public function getAlerts(): array
    {
        // Serialization pending count
        $inventoryService = app(InventoryService::class);
        $serializationPending = $inventoryService->getPendingSerializationCount();

        // Batches with discrepancy
        $batchesWithDiscrepancy = InboundBatch::query()
            ->where('serialization_status', InboundBatchStatus::Discrepancy)
            ->count();

        // Committed inventory at risk (free < 10% of committed)
        $committedAtRisk = 0;
        $allocationIds = SerializedBottle::distinct()
            ->whereNotNull('allocation_id')
            ->where('state', BottleState::Stored)
            ->pluck('allocation_id');

        foreach ($allocationIds as $allocationId) {
            /** @var Allocation|null $allocation */
            $allocation = Allocation::find($allocationId);
            if ($allocation instanceof Allocation) {
                $committed = $inventoryService->getCommittedQuantity($allocation);
                $free = $inventoryService->getFreeQuantity($allocation);

                // Alert if committed > 0 and free < 10% of committed
                if ($committed > 0 && $free < ($committed * 0.1)) {
                    $committedAtRisk++;
                }
            }
        }

        // WMS sync errors (count of WMS-related inventory exceptions in last 7 days)
        $wmsSyncErrors = InventoryException::query()
            ->whereIn('exception_type', [
                'wms_serialization_blocked',
                'wms_event_duplicate_ignored',
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Unresolved exceptions
        $unresolvedExceptions = InventoryException::query()
            ->whereNull('resolved_at')
            ->count();

        return [
            'serialization_pending' => $serializationPending,
            'batches_with_discrepancy' => $batchesWithDiscrepancy,
            'committed_at_risk' => $committedAtRisk,
            'wms_sync_errors' => $wmsSyncErrors,
            'unresolved_exceptions' => $unresolvedExceptions,
        ];
    }

    /**
     * Check if any alerts are critical (count > 0).
     */
    public function hasAlerts(): bool
    {
        $alerts = $this->getAlerts();

        return $alerts['batches_with_discrepancy'] > 0
            || $alerts['committed_at_risk'] > 0
            || $alerts['wms_sync_errors'] > 0;
    }

    /**
     * Get recent inventory exceptions.
     *
     * @return Collection<int, InventoryException>
     */
    public function getRecentExceptions(int $limit = 5): Collection
    {
        return InventoryException::query()
            ->with(['serializedBottle', 'case', 'inboundBatch', 'creator'])
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ========================================
    // Widget D: Recent Activity
    // ========================================

    /**
     * Get recent movements summary.
     *
     * @return array{
     *     today_count: int,
     *     this_week_count: int,
     *     last_movement_at: string|null
     * }
     */
    public function getRecentMovementsSummary(): array
    {
        $todayCount = InventoryMovement::query()
            ->whereDate('executed_at', today())
            ->count();

        $weekCount = InventoryMovement::query()
            ->where('executed_at', '>=', now()->subDays(7))
            ->count();

        $lastMovement = InventoryMovement::query()
            ->orderByDesc('executed_at')
            ->first();

        return [
            'today_count' => $todayCount,
            'this_week_count' => $weekCount,
            'last_movement_at' => $lastMovement?->executed_at?->diffForHumans(),
        ];
    }

    /**
     * Get ownership breakdown.
     *
     * @return array<string, int>
     */
    public function getOwnershipBreakdown(): array
    {
        $breakdown = [];

        foreach (OwnershipType::cases() as $type) {
            $breakdown[$type->value] = SerializedBottle::query()
                ->where('state', BottleState::Stored)
                ->where('ownership_type', $type)
                ->count();
        }

        return $breakdown;
    }

    /**
     * Get ownership type metadata.
     *
     * @return array<string, array{label: string, color: string}>
     */
    public function getOwnershipTypeMeta(): array
    {
        $meta = [];
        foreach (OwnershipType::cases() as $type) {
            $meta[$type->value] = [
                'label' => $type->label(),
                'color' => $type->color(),
            ];
        }

        return $meta;
    }

    // ========================================
    // Quick Links and URLs
    // ========================================

    /**
     * Get URL to serialized bottles list filtered by state.
     */
    public function getBottlesByStateUrl(BottleState $state): string
    {
        return SerializedBottleResource::getUrl('index', [
            'filters' => [
                'state' => [
                    'values' => [$state->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to all serialized bottles.
     */
    public function getBottlesUrl(): string
    {
        return SerializedBottleResource::getUrl('index');
    }

    /**
     * Get URL to locations list.
     */
    public function getLocationsUrl(): string
    {
        return LocationResource::getUrl('index');
    }

    /**
     * Get URL to location detail.
     */
    public function getLocationViewUrl(Location $location): string
    {
        return LocationResource::getUrl('view', ['record' => $location]);
    }

    /**
     * Get URL to bottles filtered by location.
     */
    public function getBottlesByLocationUrl(Location $location): string
    {
        return SerializedBottleResource::getUrl('index', [
            'filters' => [
                'current_location_id' => $location->id,
            ],
        ]);
    }

    /**
     * Get URL to serialization queue.
     */
    public function getSerializationQueueUrl(): string
    {
        return SerializationQueue::getUrl();
    }

    /**
     * Get URL to inbound batches with discrepancy.
     */
    public function getDiscrepancyBatchesUrl(): string
    {
        return InboundBatchResource::getUrl('index', [
            'filters' => [
                'serialization_status' => [
                    'values' => [InboundBatchStatus::Discrepancy->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to cases list.
     */
    public function getCasesUrl(): string
    {
        return CaseResource::getUrl('index');
    }

    /**
     * Get URL to intact cases.
     */
    public function getIntactCasesUrl(): string
    {
        return CaseResource::getUrl('index', [
            'filters' => [
                'integrity_status' => CaseIntegrityStatus::Intact->value,
            ],
        ]);
    }

    /**
     * Get URL to bottles from at-risk allocations.
     */
    public function getAtRiskBottlesUrl(): string
    {
        $inventoryService = app(InventoryService::class);
        $atRiskAllocationIds = $inventoryService->getAtRiskAllocationIds();

        if ($atRiskAllocationIds->isEmpty()) {
            return SerializedBottleResource::getUrl('index');
        }

        // Return URL to bottles filtered by at-risk allocation IDs
        // Note: For multiple allocation IDs, we link to the first one or the general list
        // Since Filament doesn't easily support multi-value filters via URL, we'll link to
        // the general bottle list. The alert message explains the context.
        $firstAllocationId = $atRiskAllocationIds->first();

        return SerializedBottleResource::getUrl('index', [
            'filters' => [
                'allocation_id' => $firstAllocationId,
            ],
        ]);
    }

    /**
     * Get at-risk allocation details for display.
     *
     * @return Collection<int, array{allocation: Allocation, committed: int, free: int, risk_percentage: float}>
     */
    public function getAtRiskAllocationDetails(): Collection
    {
        $inventoryService = app(InventoryService::class);

        return $inventoryService->getAtRiskAllocations();
    }

    /**
     * Get WMS sync errors for display.
     *
     * @return Collection<int, InventoryException>
     */
    public function getWmsSyncErrors(): Collection
    {
        return InventoryException::query()
            ->with(['serializedBottle', 'case', 'inboundBatch', 'creator'])
            ->whereIn('exception_type', [
                'wms_serialization_blocked',
                'wms_event_duplicate_ignored',
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }
}
