<?php

namespace App\Filament\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
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
    // Widget A: Demand → Execution Knowability
    // ========================================

    /**
     * Get metrics for the Demand → Execution widget.
     *
     * @return array{vouchers_awaiting_sourcing: int, allocation_driven_pending: int, bottling_required_demand: int, inbound_expected_30d: int, inbound_overdue: int, inbound_overdue_ratio: float}
     */
    public function getDemandExecutionMetrics(): array
    {
        // Vouchers issued awaiting sourcing: Voucher-driven intents that are draft/approved but not yet executed
        $vouchersAwaitingSourcing = ProcurementIntent::where('trigger_type', ProcurementTriggerType::VoucherDriven->value)
            ->whereIn('status', [
                ProcurementIntentStatus::Draft->value,
                ProcurementIntentStatus::Approved->value,
            ])
            ->count();

        // Allocation-driven procurement pending: Allocation-driven intents that are draft/approved
        $allocationDrivenPending = ProcurementIntent::where('trigger_type', ProcurementTriggerType::AllocationDriven->value)
            ->whereIn('status', [
                ProcurementIntentStatus::Draft->value,
                ProcurementIntentStatus::Approved->value,
            ])
            ->count();

        // Bottling-required liquid demand: Intents for liquid products that need bottling (have active bottling instructions)
        $bottlingRequiredDemand = BottlingInstruction::where('status', BottlingInstructionStatus::Active->value)
            ->whereIn('preference_status', [
                BottlingPreferenceStatus::Pending->value,
                BottlingPreferenceStatus::Partial->value,
            ])
            ->count();

        // Inbound expected next 30 days (POs with delivery window ending in next 30 days)
        $inboundExpected30d = PurchaseOrder::whereIn('status', [
            PurchaseOrderStatus::Sent->value,
            PurchaseOrderStatus::Confirmed->value,
        ])
            ->whereNotNull('expected_delivery_end')
            ->where('expected_delivery_end', '>=', Carbon::today())
            ->where('expected_delivery_end', '<=', Carbon::now()->addDays(30))
            ->count();

        // Inbound overdue: POs with expected delivery window passed
        $inboundOverdue = PurchaseOrder::whereIn('status', [
            PurchaseOrderStatus::Sent->value,
            PurchaseOrderStatus::Confirmed->value,
        ])
            ->whereNotNull('expected_delivery_end')
            ->where('expected_delivery_end', '<', Carbon::today())
            ->count();

        // Calculate ratio (overdue vs total expected in last 30 days + overdue)
        $totalExpectedPool = $inboundExpected30d + $inboundOverdue;
        $overdueRatio = $totalExpectedPool > 0 ? round(($inboundOverdue / $totalExpectedPool) * 100, 1) : 0.0;

        return [
            'vouchers_awaiting_sourcing' => $vouchersAwaitingSourcing,
            'allocation_driven_pending' => $allocationDrivenPending,
            'bottling_required_demand' => $bottlingRequiredDemand,
            'inbound_expected_30d' => $inboundExpected30d,
            'inbound_overdue' => $inboundOverdue,
            'inbound_overdue_ratio' => $overdueRatio,
        ];
    }

    /**
     * Get the health status for a demand execution metric.
     *
     * @param  string  $metric  The metric name
     * @param  int|float  $value  The metric value
     * @return string Color class: 'success' (green/healthy), 'warning' (yellow/attention), 'danger' (red/critical)
     */
    public function getDemandExecutionHealthStatus(string $metric, int|float $value): string
    {
        return match ($metric) {
            'vouchers_awaiting_sourcing' => match (true) {
                $value === 0 => 'success',
                $value <= 5 => 'warning',
                default => 'danger',
            },
            'allocation_driven_pending' => match (true) {
                $value === 0 => 'success',
                $value <= 10 => 'warning',
                default => 'danger',
            },
            'bottling_required_demand' => match (true) {
                $value === 0 => 'success',
                $value <= 5 => 'warning',
                default => 'danger',
            },
            'inbound_overdue_ratio' => match (true) {
                $value === 0.0 => 'success',
                $value <= 10.0 => 'warning',
                default => 'danger',
            },
            default => 'gray',
        };
    }

    /**
     * Get URL to voucher-driven intents awaiting sourcing.
     */
    public function getVouchersAwaitingSourcingUrl(): string
    {
        return ProcurementIntentResource::getUrl('index', [
            'tableFilters' => [
                'trigger_type' => [
                    'value' => ProcurementTriggerType::VoucherDriven->value,
                ],
                'status' => [
                    'values' => [
                        ProcurementIntentStatus::Draft->value,
                        ProcurementIntentStatus::Approved->value,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get URL to allocation-driven intents pending.
     */
    public function getAllocationDrivenPendingUrl(): string
    {
        return ProcurementIntentResource::getUrl('index', [
            'tableFilters' => [
                'trigger_type' => [
                    'value' => ProcurementTriggerType::AllocationDriven->value,
                ],
                'status' => [
                    'values' => [
                        ProcurementIntentStatus::Draft->value,
                        ProcurementIntentStatus::Approved->value,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get URL to bottling instructions requiring attention.
     */
    public function getBottlingRequiredDemandUrl(): string
    {
        return BottlingInstructionResource::getUrl('index', [
            'tableFilters' => [
                'status' => [
                    'value' => BottlingInstructionStatus::Active->value,
                ],
            ],
        ]);
    }

    /**
     * Get URL to expected inbounds (POs).
     */
    public function getExpectedInboundsUrl(): string
    {
        return PurchaseOrderResource::getUrl('index', [
            'tableFilters' => [
                'status' => [
                    'values' => [
                        PurchaseOrderStatus::Sent->value,
                        PurchaseOrderStatus::Confirmed->value,
                    ],
                ],
            ],
        ]);
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

    // ========================================
    // Widget B: Bottling Risk
    // ========================================

    /**
     * Get metrics for the Bottling Risk widget.
     *
     * @return array{deadlines_30d: int, deadlines_60d: int, deadlines_90d: int, deadlines_14d: int, preferences_collected_pct: float, preferences_total: int, preferences_collected: int, default_fallback_count: int}
     */
    public function getBottlingRiskMetrics(): array
    {
        // Base query for active bottling instructions
        $baseQuery = fn () => BottlingInstruction::where('status', BottlingInstructionStatus::Active->value);

        // Deadline counts (cumulative - 60d includes 30d, etc.)
        $deadlines30d = $baseQuery()
            ->where('bottling_deadline', '>=', Carbon::today())
            ->where('bottling_deadline', '<=', Carbon::now()->addDays(30))
            ->count();

        $deadlines60d = $baseQuery()
            ->where('bottling_deadline', '>=', Carbon::today())
            ->where('bottling_deadline', '<=', Carbon::now()->addDays(60))
            ->count();

        $deadlines90d = $baseQuery()
            ->where('bottling_deadline', '>=', Carbon::today())
            ->where('bottling_deadline', '<=', Carbon::now()->addDays(90))
            ->count();

        // Urgent deadlines (< 14 days)
        $deadlines14d = $baseQuery()
            ->where('bottling_deadline', '>=', Carbon::today())
            ->where('bottling_deadline', '<=', Carbon::now()->addDays(14))
            ->count();

        // Preference collection stats
        // Active instructions that have preference collection ongoing
        $activeInstructions = $baseQuery()->get();

        $totalPreferencesExpected = 0;
        $totalPreferencesCollected = 0;

        foreach ($activeInstructions as $instruction) {
            /** @var BottlingInstruction $instruction */
            $progress = $instruction->getPreferenceProgress();
            $totalPreferencesExpected += $progress['total'];
            $totalPreferencesCollected += $progress['collected'];
        }

        $preferencesCollectedPct = $totalPreferencesExpected > 0
            ? round(($totalPreferencesCollected / $totalPreferencesExpected) * 100, 1)
            : 100.0; // If no preferences expected, consider 100% complete

        // Default fallback count: Instructions where defaults have been applied
        $defaultFallbackCount = BottlingInstruction::where('preference_status', BottlingPreferenceStatus::Defaulted->value)
            ->count();

        return [
            'deadlines_30d' => $deadlines30d,
            'deadlines_60d' => $deadlines60d,
            'deadlines_90d' => $deadlines90d,
            'deadlines_14d' => $deadlines14d,
            'preferences_collected_pct' => $preferencesCollectedPct,
            'preferences_total' => $totalPreferencesExpected,
            'preferences_collected' => $totalPreferencesCollected,
            'default_fallback_count' => $defaultFallbackCount,
        ];
    }

    /**
     * Get the health status for a bottling risk metric.
     *
     * @param  string  $metric  The metric name
     * @param  int|float  $value  The metric value
     * @return string Color class: 'success' (green/healthy), 'warning' (yellow/attention), 'danger' (red/critical)
     */
    public function getBottlingRiskHealthStatus(string $metric, int|float $value): string
    {
        return match ($metric) {
            'deadlines_14d' => match (true) {
                $value === 0 => 'success',
                $value <= 3 => 'warning',
                default => 'danger',
            },
            'deadlines_30d' => match (true) {
                $value === 0 => 'success',
                $value <= 5 => 'warning',
                default => 'danger',
            },
            'deadlines_60d', 'deadlines_90d' => match (true) {
                $value === 0 => 'success',
                $value <= 10 => 'warning',
                default => 'danger',
            },
            'preferences_collected_pct' => match (true) {
                $value >= 90.0 => 'success',
                $value >= 50.0 => 'warning',
                default => 'danger',
            },
            'default_fallback_count' => match (true) {
                $value === 0 => 'success',
                $value <= 3 => 'warning',
                default => 'danger',
            },
            default => 'gray',
        };
    }

    /**
     * Get URL to bottling instructions with deadlines in next 30 days.
     */
    public function getBottling30dDeadlinesUrl(): string
    {
        return BottlingInstructionResource::getUrl('index', [
            'tableFilters' => [
                'deadline_urgent' => true,
            ],
        ]);
    }

    /**
     * Get URL to bottling instructions with pending preferences.
     */
    public function getBottlingPendingPreferencesUrl(): string
    {
        return BottlingInstructionResource::getUrl('index', [
            'tableFilters' => [
                'preference_status' => [
                    'values' => [
                        BottlingPreferenceStatus::Pending->value,
                        BottlingPreferenceStatus::Partial->value,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get URL to bottling instructions where defaults have been applied.
     */
    public function getBottlingDefaultedUrl(): string
    {
        return BottlingInstructionResource::getUrl('index', [
            'tableFilters' => [
                'preference_status' => [
                    'values' => [
                        BottlingPreferenceStatus::Defaulted->value,
                    ],
                ],
            ],
        ]);
    }
}
