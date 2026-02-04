<?php

namespace App\Filament\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\BottlingInstructionResource;
use App\Filament\Resources\Procurement\InboundResource;
use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Filament\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\BottlingInstruction;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ProcurementDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Procurement Overview';

    protected static string $view = 'filament.pages.procurement-dashboard';

    // ========================================
    // Summary Metrics (4 main widgets)
    // ========================================

    /**
     * Get summary metrics for the 4 main dashboard cards.
     *
     * @return array{total_intents: int, pending_approvals: int, pending_inbounds: int, bottling_deadlines_30d: int}
     */
    public function getSummaryMetrics(): array
    {
        return [
            'total_intents' => ProcurementIntent::whereIn('status', [
                ProcurementIntentStatus::Draft->value,
                ProcurementIntentStatus::Approved->value,
                ProcurementIntentStatus::Executed->value,
            ])->count(),
            'pending_approvals' => ProcurementIntent::where('status', ProcurementIntentStatus::Draft->value)->count(),
            'pending_inbounds' => Inbound::whereIn('status', [
                InboundStatus::Recorded->value,
                InboundStatus::Routed->value,
            ])->count(),
            'bottling_deadlines_30d' => BottlingInstruction::where('status', BottlingInstructionStatus::Active->value)
                ->where('bottling_deadline', '<=', Carbon::now()->addDays(30))
                ->where('bottling_deadline', '>=', Carbon::today())
                ->count(),
        ];
    }

    // ========================================
    // Intent Status Distribution
    // ========================================

    /**
     * Get procurement intent counts by status.
     *
     * @return array<string, int>
     */
    public function getIntentStatusCounts(): array
    {
        $counts = [];
        foreach (ProcurementIntentStatus::cases() as $status) {
            $counts[$status->value] = ProcurementIntent::where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get intent status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getIntentStatusMeta(): array
    {
        $meta = [];
        foreach (ProcurementIntentStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    // ========================================
    // PO Status Distribution
    // ========================================

    /**
     * Get purchase order counts by status.
     *
     * @return array<string, int>
     */
    public function getPOStatusCounts(): array
    {
        $counts = [];
        foreach (PurchaseOrderStatus::cases() as $status) {
            $counts[$status->value] = PurchaseOrder::where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get PO status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getPOStatusMeta(): array
    {
        $meta = [];
        foreach (PurchaseOrderStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    // ========================================
    // Inbound Status Distribution
    // ========================================

    /**
     * Get inbound counts by status.
     *
     * @return array<string, int>
     */
    public function getInboundStatusCounts(): array
    {
        $counts = [];
        foreach (InboundStatus::cases() as $status) {
            $counts[$status->value] = Inbound::where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get inbound status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getInboundStatusMeta(): array
    {
        $meta = [];
        foreach (InboundStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    // ========================================
    // Bottling Instruction Metrics
    // ========================================

    /**
     * Get bottling instruction deadline counts.
     *
     * @return array{next_30_days: int, next_60_days: int, next_90_days: int, past_deadline: int}
     */
    public function getBottlingDeadlineCounts(): array
    {
        $baseQuery = fn () => BottlingInstruction::where('status', BottlingInstructionStatus::Active->value);

        return [
            'next_30_days' => $baseQuery()
                ->where('bottling_deadline', '>=', Carbon::today())
                ->where('bottling_deadline', '<=', Carbon::now()->addDays(30))
                ->count(),
            'next_60_days' => $baseQuery()
                ->where('bottling_deadline', '>=', Carbon::today())
                ->where('bottling_deadline', '<=', Carbon::now()->addDays(60))
                ->count(),
            'next_90_days' => $baseQuery()
                ->where('bottling_deadline', '>=', Carbon::today())
                ->where('bottling_deadline', '<=', Carbon::now()->addDays(90))
                ->count(),
            'past_deadline' => $baseQuery()
                ->where('bottling_deadline', '<', Carbon::today())
                ->count(),
        ];
    }

    /**
     * Get bottling preference collection metrics.
     *
     * @return array{pending: int, partial: int, complete: int, defaulted: int}
     */
    public function getBottlingPreferenceCounts(): array
    {
        $baseQuery = fn () => BottlingInstruction::where('status', BottlingInstructionStatus::Active->value);

        return [
            'pending' => $baseQuery()->where('preference_status', BottlingPreferenceStatus::Pending->value)->count(),
            'partial' => $baseQuery()->where('preference_status', BottlingPreferenceStatus::Partial->value)->count(),
            'complete' => $baseQuery()->where('preference_status', BottlingPreferenceStatus::Complete->value)->count(),
            'defaulted' => $baseQuery()->where('preference_status', BottlingPreferenceStatus::Defaulted->value)->count(),
        ];
    }

    // ========================================
    // Exception/Alert Counts
    // ========================================

    /**
     * Get counts of items requiring attention.
     *
     * @return array{pending_ownership: int, unlinked_inbounds: int, overdue_pos: int, variance_pos: int}
     */
    public function getExceptionCounts(): array
    {
        return [
            'pending_ownership' => Inbound::where('ownership_flag', OwnershipFlag::Pending->value)
                ->whereIn('status', [InboundStatus::Recorded->value, InboundStatus::Routed->value])
                ->count(),
            'unlinked_inbounds' => Inbound::whereNull('procurement_intent_id')
                ->whereIn('status', [InboundStatus::Recorded->value, InboundStatus::Routed->value])
                ->count(),
            'overdue_pos' => PurchaseOrder::whereIn('status', [
                PurchaseOrderStatus::Sent->value,
                PurchaseOrderStatus::Confirmed->value,
            ])
                ->where('expected_delivery_end', '<', Carbon::today())
                ->count(),
            'variance_pos' => $this->getSignificantVariancePOCount(),
        ];
    }

    /**
     * Get count of POs with significant variance (>10%).
     */
    private function getSignificantVariancePOCount(): int
    {
        return PurchaseOrder::whereHas('inbounds')
            ->get()
            ->filter(fn (PurchaseOrder $po) => $po->hasSignificantVariance())
            ->count();
    }

    // ========================================
    // Items Awaiting Action Lists
    // ========================================

    /**
     * Get intents awaiting approval (draft status).
     *
     * @return Collection<int, ProcurementIntent>
     */
    public function getIntentsAwaitingApproval(): Collection
    {
        return ProcurementIntent::with(['productReference'])
            ->where('status', ProcurementIntentStatus::Draft->value)
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get();
    }

    /**
     * Get inbounds with pending ownership.
     *
     * @return Collection<int, Inbound>
     */
    public function getInboundsWithPendingOwnership(): Collection
    {
        return Inbound::with(['productReference'])
            ->where('ownership_flag', OwnershipFlag::Pending->value)
            ->whereIn('status', [InboundStatus::Recorded->value, InboundStatus::Routed->value])
            ->orderBy('received_date', 'asc')
            ->take(5)
            ->get();
    }

    /**
     * Get bottling instructions with urgent deadlines (<14 days).
     *
     * @return Collection<int, BottlingInstruction>
     */
    public function getUrgentBottlingInstructions(): Collection
    {
        return BottlingInstruction::with(['liquidProduct.wineVariant.wineMaster'])
            ->where('status', BottlingInstructionStatus::Active->value)
            ->where('bottling_deadline', '>=', Carbon::today())
            ->where('bottling_deadline', '<=', Carbon::now()->addDays(14))
            ->orderBy('bottling_deadline', 'asc')
            ->take(5)
            ->get();
    }

    // ========================================
    // Quick Links and URLs
    // ========================================

    /**
     * Get URL to create a new procurement intent.
     */
    public function getCreateIntentUrl(): string
    {
        return ProcurementIntentResource::getUrl('create');
    }

    /**
     * Get URL to record a new inbound.
     */
    public function getCreateInboundUrl(): string
    {
        return InboundResource::getUrl('create');
    }

    /**
     * Get URL to procurement intents list with pending approvals filter.
     */
    public function getPendingApprovalsUrl(): string
    {
        return ProcurementIntentResource::getUrl('index', [
            'tableFilters' => [
                'status' => [
                    'values' => [ProcurementIntentStatus::Draft->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to intents list.
     */
    public function getIntentsListUrl(): string
    {
        return ProcurementIntentResource::getUrl('index');
    }

    /**
     * Get URL to purchase orders list.
     */
    public function getPurchaseOrdersListUrl(): string
    {
        return PurchaseOrderResource::getUrl('index');
    }

    /**
     * Get URL to bottling instructions list.
     */
    public function getBottlingInstructionsListUrl(): string
    {
        return BottlingInstructionResource::getUrl('index');
    }

    /**
     * Get URL to inbounds list.
     */
    public function getInboundsListUrl(): string
    {
        return InboundResource::getUrl('index');
    }

    /**
     * Get URL to inbounds list with pending ownership filter.
     */
    public function getPendingOwnershipInboundsUrl(): string
    {
        return InboundResource::getUrl('index', [
            'tableFilters' => [
                'ownership_pending' => true,
            ],
        ]);
    }

    /**
     * Get URL to inbounds list with unlinked filter.
     */
    public function getUnlinkedInboundsUrl(): string
    {
        return InboundResource::getUrl('index', [
            'tableFilters' => [
                'unlinked' => true,
            ],
        ]);
    }

    /**
     * Get URL to POs with variance filter.
     */
    public function getVariancePOsUrl(): string
    {
        return PurchaseOrderResource::getUrl('index', [
            'tableFilters' => [
                'variance' => 'significant_variance',
            ],
        ]);
    }

    /**
     * Get URL to overdue POs.
     */
    public function getOverduePOsUrl(): string
    {
        return PurchaseOrderResource::getUrl('index', [
            'tableFilters' => [
                'overdue' => true,
            ],
        ]);
    }

    /**
     * Get URL to bottling instructions with deadline filter.
     */
    public function getBottlingDeadlinesUrl(): string
    {
        return BottlingInstructionResource::getUrl('index', [
            'tableFilters' => [
                'deadline_urgent' => true,
            ],
        ]);
    }

    /**
     * Get URL to view a specific intent.
     */
    public function getIntentViewUrl(ProcurementIntent $intent): string
    {
        return ProcurementIntentResource::getUrl('view', ['record' => $intent]);
    }

    /**
     * Get URL to view a specific inbound.
     */
    public function getInboundViewUrl(Inbound $inbound): string
    {
        return InboundResource::getUrl('view', ['record' => $inbound]);
    }

    /**
     * Get URL to view a specific bottling instruction.
     */
    public function getBottlingInstructionViewUrl(BottlingInstruction $instruction): string
    {
        return BottlingInstructionResource::getUrl('view', ['record' => $instruction]);
    }
}
