<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\PackagingPreference;
use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Enums\Inventory\LocationType;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderException;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Models\Inventory\Location;
use App\Services\Fulfillment\LateBindingService;
use App\Services\Fulfillment\ShippingOrderService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ViewShippingOrder extends ViewRecord
{
    protected static string $resource = ShippingOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var ShippingOrder $record */
        $record = $this->record;

        return "Shipping Order: #{$record->id}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getStatusBanner(),
                Tabs::make('Shipping Order Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getVouchersAndEligibilityTab(),
                        $this->getPlanningTab(),
                        // Future tabs will be added in subsequent stories:
                        // US-C027: Picking & Binding tab
                        // US-C028: Audit & Timeline tab
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - What, for whom, from where?
     * Read-only tab answering the core questions about this Shipping Order.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                $this->getCustomerAndDestinationSection(),
                $this->getShippingMethodSection(),
                $this->getPackagingSection(),
                $this->getVoucherSummarySection(),
            ]);
    }

    /**
     * Tab 2: Vouchers & Eligibility
     * Validate eligibility of each voucher before planning.
     */
    protected function getVouchersAndEligibilityTab(): Tab
    {
        return Tab::make('Vouchers & Eligibility')
            ->icon('heroicon-o-shield-check')
            ->badge(function (ShippingOrder $record): ?string {
                $ineligibleCount = $this->countIneligibleVouchers($record);

                return $ineligibleCount > 0 ? (string) $ineligibleCount : null;
            })
            ->badgeColor('danger')
            ->schema([
                $this->getEligibilityBannerSection(),
                $this->getVoucherEligibilityListSection(),
            ]);
    }

    /**
     * Tab 3: Planning
     * Plan the SO by verifying inventory availability.
     * Active only if status = draft or planned.
     */
    protected function getPlanningTab(): Tab
    {
        return Tab::make('Planning')
            ->icon('heroicon-o-clipboard-document-list')
            ->visible(fn (ShippingOrder $record): bool => $record->isDraft() || $record->isPlanned())
            ->badge(function (ShippingOrder $record): ?string {
                if ($record->isPlanned()) {
                    return '✓';
                }
                if ($record->isDraft() && $record->source_warehouse_id !== null) {
                    $availability = $this->getInventoryAvailability($record);
                    if (! $availability['all_available']) {
                        return '!';
                    }
                }

                return null;
            })
            ->badgeColor(fn (ShippingOrder $record): string => $record->isPlanned() ? 'success' : 'warning')
            ->schema([
                $this->getPlanningStatusBannerSection(),
                $this->getSourceWarehouseSection(),
                $this->getInventoryAvailabilitySection(),
                $this->getPlanningActionsSection(),
            ]);
    }

    /**
     * Banner showing planning status.
     */
    protected function getPlanningStatusBannerSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('planning_status_banner')
                    ->label('')
                    ->getStateUsing(function (ShippingOrder $record): string {
                        if ($record->isPlanned()) {
                            return '✓ This Shipping Order has been planned. Vouchers are locked and inventory is reserved.';
                        }

                        return 'Planning required. Select a source warehouse and verify inventory availability before proceeding.';
                    })
                    ->icon(fn (ShippingOrder $record): string => $record->isPlanned() ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle')
                    ->color(fn (ShippingOrder $record): string => $record->isPlanned() ? 'success' : 'info')
                    ->weight(FontWeight::Medium)
                    ->columnSpanFull(),
            ])
            ->extraAttributes(fn (ShippingOrder $record): array => [
                'class' => $record->isPlanned()
                    ? 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800'
                    : 'bg-info-50 dark:bg-info-900/20 border-info-200 dark:border-info-800',
            ])
            ->columnSpanFull();
    }

    /**
     * Section 1: Source Warehouse selection/confirmation.
     */
    protected function getSourceWarehouseSection(): Section
    {
        return Section::make('Source Warehouse')
            ->description('Where the shipment will be dispatched from')
            ->icon('heroicon-o-building-storefront')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('sourceWarehouse.name')
                            ->label('Selected Warehouse')
                            ->icon('heroicon-o-building-storefront')
                            ->default('Not selected')
                            ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id !== null)
                            ->weight(FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('sourceWarehouse.country')
                            ->label('Country')
                            ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id !== null),
                        TextEntry::make('sourceWarehouse.location_type')
                            ->label('Type')
                            ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id !== null)
                            ->formatStateUsing(fn (ShippingOrder $record): string => $record->sourceWarehouse?->location_type->label() ?? 'Unknown')
                            ->badge()
                            ->color(fn (ShippingOrder $record): string => $record->sourceWarehouse?->location_type->color() ?? 'gray'),
                        TextEntry::make('sourceWarehouse.serialization_authorized')
                            ->label('Serialization Authorized')
                            ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id !== null)
                            ->formatStateUsing(fn (ShippingOrder $record): string => $record->sourceWarehouse?->serialization_authorized ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (ShippingOrder $record): string => $record->sourceWarehouse?->serialization_authorized ? 'success' : 'warning'),
                    ]),
                TextEntry::make('no_warehouse_selected')
                    ->label('')
                    ->getStateUsing(fn (): string => 'No source warehouse selected. Please select a warehouse to check inventory availability.')
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id === null && $record->isDraft()),
            ]);
    }

    /**
     * Section 2: Inventory Availability per allocation lineage.
     */
    protected function getInventoryAvailabilitySection(): Section
    {
        return Section::make('Inventory Availability')
            ->description('Available inventory per allocation lineage')
            ->icon('heroicon-o-archive-box')
            ->visible(fn (ShippingOrder $record): bool => $record->source_warehouse_id !== null)
            ->schema([
                $this->getInventoryAvailabilitySummary(),
                TextEntry::make('inventory_availability_details')
                    ->label('')
                    ->getStateUsing(fn (ShippingOrder $record): string => $this->formatInventoryAvailability($record))
                    ->html()
                    ->columnSpanFull(),
                $this->getInsufficientInventoryWarning(),
                $this->getPreserveCasesWarning(),
            ]);
    }

    /**
     * Summary of inventory availability status.
     */
    protected function getInventoryAvailabilitySummary(): Grid
    {
        return Grid::make(3)
            ->schema([
                TextEntry::make('total_allocations')
                    ->label('Allocations')
                    ->getStateUsing(function (ShippingOrder $record): int {
                        $availability = $this->getInventoryAvailability($record);

                        return count($availability['allocations']);
                    })
                    ->badge()
                    ->color('info'),
                TextEntry::make('sufficient_allocations')
                    ->label('Sufficient')
                    ->getStateUsing(function (ShippingOrder $record): int {
                        $availability = $this->getInventoryAvailability($record);

                        return count(array_filter($availability['allocations'], fn ($a) => $a['status'] === 'sufficient'));
                    })
                    ->badge()
                    ->color('success'),
                TextEntry::make('insufficient_allocations')
                    ->label('Insufficient')
                    ->getStateUsing(function (ShippingOrder $record): int {
                        $availability = $this->getInventoryAvailability($record);

                        return count(array_filter($availability['allocations'], fn ($a) => $a['status'] === 'insufficient'));
                    })
                    ->badge()
                    ->color(function (ShippingOrder $record): string {
                        $availability = $this->getInventoryAvailability($record);
                        $insufficientCount = count(array_filter($availability['allocations'], fn ($a) => $a['status'] === 'insufficient'));

                        return $insufficientCount > 0 ? 'danger' : 'gray';
                    }),
            ]);
    }

    /**
     * Warning banner for insufficient inventory.
     */
    protected function getInsufficientInventoryWarning(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('insufficient_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => '⚠️ Insufficient eligible inventory for one or more allocations. '
                        .'This Shipping Order cannot proceed until inventory becomes available.')
                    ->color('danger')
                    ->weight(FontWeight::Bold)
                    ->columnSpanFull(),
            ])
            ->visible(function (ShippingOrder $record): bool {
                if ($record->isPlanned()) {
                    return false;
                }
                $availability = $this->getInventoryAvailability($record);

                return ! $availability['all_available'];
            })
            ->extraAttributes([
                'class' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800',
            ])
            ->columnSpanFull();
    }

    /**
     * Warning banner for preserve_cases when intact case is unavailable.
     */
    protected function getPreserveCasesWarning(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('preserve_cases_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => '⚠️ Original wooden case (OWC) not available for some allocations. '
                        .'This may delay shipment if preserve_cases packaging preference is required.')
                    ->color('warning')
                    ->weight(FontWeight::Medium)
                    ->columnSpanFull(),
            ])
            ->visible(function (ShippingOrder $record): bool {
                if ($record->isPlanned()) {
                    return false;
                }
                if ($record->packaging_preference !== PackagingPreference::PreserveCases) {
                    return false;
                }
                $availability = $this->getInventoryAvailability($record);

                return ! $availability['preserve_cases_satisfied'];
            })
            ->extraAttributes([
                'class' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800',
            ])
            ->columnSpanFull();
    }

    /**
     * Section for planning actions.
     */
    protected function getPlanningActionsSection(): Section
    {
        return Section::make('Planning Actions')
            ->description('Actions available for this Shipping Order')
            ->icon('heroicon-o-cog-6-tooth')
            ->visible(fn (ShippingOrder $record): bool => $record->isDraft())
            ->schema([
                TextEntry::make('planning_actions_info')
                    ->label('')
                    ->getStateUsing(function (ShippingOrder $record): string {
                        if ($record->source_warehouse_id === null) {
                            return 'Select a source warehouse before planning.';
                        }

                        $availability = $this->getInventoryAvailability($record);
                        $ineligibleCount = $this->countIneligibleVouchers($record);

                        if ($ineligibleCount > 0) {
                            return "Cannot plan: {$ineligibleCount} voucher(s) are ineligible. Resolve issues in the Vouchers & Eligibility tab.";
                        }

                        if (! $availability['all_available']) {
                            return 'Cannot plan: Insufficient inventory for one or more allocations.';
                        }

                        if (! $availability['preserve_cases_satisfied'] && $record->packaging_preference === PackagingPreference::PreserveCases) {
                            return 'Cannot plan: Preserve cases preference requires intact original case, but none available.';
                        }

                        return '✓ All requirements met. Ready to plan this Shipping Order.';
                    })
                    ->color(function (ShippingOrder $record): string {
                        if ($record->source_warehouse_id === null) {
                            return 'warning';
                        }

                        $availability = $this->getInventoryAvailability($record);
                        $ineligibleCount = $this->countIneligibleVouchers($record);

                        if ($ineligibleCount > 0 || ! $availability['all_available']) {
                            return 'danger';
                        }

                        if (! $availability['preserve_cases_satisfied'] && $record->packaging_preference === PackagingPreference::PreserveCases) {
                            return 'danger';
                        }

                        return 'success';
                    })
                    ->icon(function (ShippingOrder $record): string {
                        if ($record->source_warehouse_id === null) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        $availability = $this->getInventoryAvailability($record);
                        $ineligibleCount = $this->countIneligibleVouchers($record);

                        if ($ineligibleCount > 0 || ! $availability['all_available']) {
                            return 'heroicon-o-x-circle';
                        }

                        if (! $availability['preserve_cases_satisfied'] && $record->packaging_preference === PackagingPreference::PreserveCases) {
                            return 'heroicon-o-x-circle';
                        }

                        return 'heroicon-o-check-circle';
                    })
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Get inventory availability for the shipping order.
     *
     * @return array{allocations: array<string, array{allocation_id: string, required_quantity: int, available_quantity: int, available_bottles: list<string>, intact_case_available: bool, status: string}>, all_available: bool, preserve_cases_satisfied: bool}
     */
    protected function getInventoryAvailability(ShippingOrder $record): array
    {
        // Use cached result if available
        static $cache = [];
        $cacheKey = $record->id.'-'.$record->source_warehouse_id;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if ($record->source_warehouse_id === null) {
            $cache[$cacheKey] = [
                'allocations' => [],
                'all_available' => false,
                'preserve_cases_satisfied' => false,
            ];

            return $cache[$cacheKey];
        }

        /** @var LateBindingService $lateBindingService */
        $lateBindingService = app(LateBindingService::class);
        $availability = $lateBindingService->requestEligibleInventory($record);

        $cache[$cacheKey] = $availability;

        return $availability;
    }

    /**
     * Format inventory availability as HTML for display.
     */
    protected function formatInventoryAvailability(ShippingOrder $record): string
    {
        $availability = $this->getInventoryAvailability($record);

        if (empty($availability['allocations'])) {
            return '<div class="text-gray-400">No allocation data available.</div>';
        }

        // Load lines with their vouchers for wine info
        $record->load(['lines.voucher.wineVariant.wineMaster', 'lines.allocation']);

        // Create a map of allocation_id to wine name
        $allocationWineMap = [];
        foreach ($record->lines as $line) {
            $allocationId = $line->allocation_id;
            $wineName = 'Unknown Wine';
            if ($line->voucher !== null
                && $line->voucher->wineVariant !== null
                && $line->voucher->wineVariant->wineMaster !== null
            ) {
                $wineName = $line->voucher->wineVariant->wineMaster->name;
            }
            if (! isset($allocationWineMap[$allocationId])) {
                $allocationWineMap[$allocationId] = $wineName;
            }
        }

        $html = '<div class="overflow-x-auto">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Allocation</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Wine/SKU</th>';
        $html .= '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Required</th>';
        $html .= '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Available</th>';
        $html .= '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Intact Case</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">';

        foreach ($availability['allocations'] as $allocationId => $data) {
            $wineName = $allocationWineMap[$allocationId] ?? 'Unknown';
            $statusColor = match ($data['status']) {
                'sufficient' => 'text-success-600 bg-success-50 dark:bg-success-900/20',
                'intact_case_unavailable' => 'text-warning-600 bg-warning-50 dark:bg-warning-900/20',
                'insufficient' => 'text-danger-600 bg-danger-50 dark:bg-danger-900/20',
                default => 'text-gray-600 bg-gray-50 dark:bg-gray-900/20',
            };
            $statusLabel = match ($data['status']) {
                'sufficient' => 'Eligible inventory available',
                'intact_case_unavailable' => 'Bottles available, intact case unavailable',
                'insufficient' => 'Insufficient eligible inventory',
                default => 'Unknown',
            };
            $statusIcon = match ($data['status']) {
                'sufficient' => '✓',
                'intact_case_unavailable' => '⚠',
                'insufficient' => '✗',
                default => '?',
            };

            $shortAllocationId = substr($allocationId, 0, 8).'...';

            $html .= '<tr>';
            $html .= "<td class=\"px-4 py-2 text-sm font-mono text-gray-900 dark:text-gray-100\" title=\"{$allocationId}\">{$shortAllocationId}</td>";
            $html .= '<td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">'.e($wineName).'</td>';
            $html .= '<td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100">'.$data['required_quantity'].'</td>';
            $html .= '<td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100">'.$data['available_quantity'].'</td>';
            $html .= '<td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100">'.($data['intact_case_available'] ? '✓ Yes' : '✗ No').'</td>';
            $html .= "<td class=\"px-4 py-2 text-sm\"><span class=\"inline-flex items-center px-2 py-1 rounded-md {$statusColor}\">{$statusIcon} {$statusLabel}</span></td>";
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        // Add allocation lineage constraint note
        $html .= '<div class="mt-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400">';
        $html .= '<strong>Note:</strong> Allocation lineage is a HARD constraint. Cross-allocation substitution is not allowed. ';
        $html .= 'Each voucher must be fulfilled with a bottle from its specific allocation.';
        $html .= '</div>';

        return $html;
    }

    /**
     * Check if the shipping order can be planned.
     */
    protected function canPlanShippingOrder(ShippingOrder $record): bool
    {
        if (! $record->isDraft()) {
            return false;
        }

        if ($record->source_warehouse_id === null) {
            return false;
        }

        $ineligibleCount = $this->countIneligibleVouchers($record);
        if ($ineligibleCount > 0) {
            return false;
        }

        $availability = $this->getInventoryAvailability($record);
        if (! $availability['all_available']) {
            return false;
        }

        if (! $availability['preserve_cases_satisfied'] && $record->packaging_preference === PackagingPreference::PreserveCases) {
            return false;
        }

        return true;
    }

    /**
     * Blocking banner shown when one or more vouchers are ineligible.
     */
    protected function getEligibilityBannerSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('eligibility_banner')
                    ->label('')
                    ->getStateUsing(fn (): string => '⚠️ One or more vouchers are not eligible for fulfillment. '
                        .'This Shipping Order cannot proceed until all eligibility issues are resolved upstream.')
                    ->color('danger')
                    ->weight(FontWeight::Bold)
                    ->columnSpanFull(),
            ])
            ->visible(fn (ShippingOrder $record): bool => $this->countIneligibleVouchers($record) > 0)
            ->extraAttributes([
                'class' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800',
            ])
            ->columnSpanFull();
    }

    /**
     * Section displaying voucher eligibility details.
     */
    protected function getVoucherEligibilityListSection(): Section
    {
        return Section::make('Voucher Eligibility')
            ->description('Eligibility status of each voucher in this Shipping Order')
            ->icon('heroicon-o-clipboard-document-check')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('total_vouchers')
                            ->label('Total Vouchers')
                            ->getStateUsing(fn (ShippingOrder $record): int => $record->lines()->count())
                            ->badge()
                            ->color('info')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('eligible_vouchers')
                            ->label('Eligible')
                            ->getStateUsing(fn (ShippingOrder $record): int => $this->countEligibleVouchers($record))
                            ->badge()
                            ->color('success'),
                        TextEntry::make('ineligible_vouchers')
                            ->label('Ineligible')
                            ->getStateUsing(fn (ShippingOrder $record): int => $this->countIneligibleVouchers($record))
                            ->badge()
                            ->color(fn (ShippingOrder $record): string => $this->countIneligibleVouchers($record) > 0 ? 'danger' : 'gray'),
                    ]),
                RepeatableEntry::make('lines')
                    ->label('Vouchers')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('voucher.id')
                                            ->label('Voucher ID')
                                            ->url(fn (ShippingOrderLine $record): ?string => $record->voucher
                                                ? VoucherResource::getUrl('view', ['record' => $record->voucher])
                                                : null)
                                            ->openUrlInNewTab()
                                            ->color('primary')
                                            ->copyable()
                                            ->copyMessage('Voucher ID copied')
                                            ->limit(8),
                                        TextEntry::make('voucher.wineVariant.wineMaster.name')
                                            ->label('Wine/SKU')
                                            ->default('Unknown')
                                            ->weight(FontWeight::Medium),
                                        TextEntry::make('allocation.id')
                                            ->label('Allocation Lineage')
                                            ->limit(8)
                                            ->copyable()
                                            ->copyMessage('Allocation ID copied'),
                                        TextEntry::make('voucher.lifecycle_state')
                                            ->label('Voucher State')
                                            ->badge()
                                            ->formatStateUsing(fn (ShippingOrderLine $record): string => $record->voucher?->getLifecycleStateLabel() ?? 'Unknown')
                                            ->color(fn (ShippingOrderLine $record): string => $record->voucher?->getLifecycleStateColor() ?? 'gray')
                                            ->icon(fn (ShippingOrderLine $record): string => $record->voucher?->getLifecycleStateIcon() ?? 'heroicon-o-question-mark-circle'),
                                        TextEntry::make('eligibility_status')
                                            ->label('Eligibility')
                                            ->getStateUsing(fn (ShippingOrderLine $line): string => $this->getEligibilityStatusLabel($line))
                                            ->badge()
                                            ->color(fn (ShippingOrderLine $line): string => $this->getEligibilityStatusColor($line))
                                            ->icon(fn (ShippingOrderLine $line): string => $this->getEligibilityStatusIcon($line)),
                                    ]),
                                // Eligibility checks detail - shown for all vouchers
                                $this->getEligibilityChecksGroup(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * Get the eligibility checks group showing individual check results.
     */
    protected function getEligibilityChecksGroup(): Group
    {
        return Group::make([
            TextEntry::make('eligibility_checks')
                ->label('Eligibility Checks')
                ->getStateUsing(function (ShippingOrderLine $line): string {
                    return $this->formatEligibilityChecks($line);
                })
                ->html()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Format eligibility checks as HTML for display.
     */
    protected function formatEligibilityChecks(ShippingOrderLine $line): string
    {
        $voucher = $line->voucher;
        /** @var ShippingOrder $shippingOrder */
        $shippingOrder = $this->record;

        if ($voucher === null) {
            return '<div class="text-danger-600">❌ Voucher not found</div>';
        }

        $checks = $this->performEligibilityChecks($voucher, $shippingOrder);

        $html = '<div class="grid grid-cols-2 gap-2 text-sm mt-2">';
        foreach ($checks as $check) {
            $icon = $check['passed'] ? '✓' : '✗';
            $colorClass = $check['passed'] ? 'text-success-600' : 'text-danger-600';
            $html .= "<div class=\"{$colorClass}\">{$icon} {$check['label']}</div>";
        }
        $html .= '</div>';

        // If any checks failed, show the reason
        $failedChecks = array_filter($checks, fn ($c) => ! $c['passed']);
        if ($failedChecks !== []) {
            $html .= '<div class="mt-2 p-2 rounded bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-300 text-sm">';
            foreach ($failedChecks as $check) {
                if (isset($check['reason'])) {
                    $html .= '<div>'.e($check['reason']).'</div>';
                }
            }
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Perform all eligibility checks for a voucher.
     *
     * @return list<array{label: string, passed: bool, reason?: string}>
     */
    protected function performEligibilityChecks(Voucher $voucher, ShippingOrder $shippingOrder): array
    {
        $checks = [];

        // Check 1: Voucher not cancelled
        $notCancelled = $voucher->lifecycle_state !== VoucherLifecycleState::Cancelled;
        $checks[] = [
            'label' => 'Voucher not cancelled',
            'passed' => $notCancelled,
            'reason' => $notCancelled ? null : 'Voucher has been cancelled and cannot be fulfilled.',
        ];

        // Check 2: Voucher not already redeemed
        $notRedeemed = $voucher->lifecycle_state !== VoucherLifecycleState::Redeemed;
        $checks[] = [
            'label' => 'Voucher not redeemed',
            'passed' => $notRedeemed,
            'reason' => $notRedeemed ? null : 'Voucher has already been redeemed.',
        ];

        // Check 3: Voucher not locked by other processes
        $notLockedElsewhere = true;
        $lockReason = null;
        if ($voucher->lifecycle_state === VoucherLifecycleState::Locked) {
            // Check if it's locked for THIS SO (which is OK)
            $lockedForThisSo = $shippingOrder->lines()
                ->where('voucher_id', $voucher->id)
                ->exists();
            $notLockedElsewhere = $lockedForThisSo;
            if (! $lockedForThisSo) {
                $lockReason = 'Voucher is locked by another process (possibly another Shipping Order).';
            }
        }
        // Also check if voucher is in another active SO
        $inOtherSo = ShippingOrderLine::query()
            ->where('voucher_id', $voucher->id)
            ->where('shipping_order_id', '!=', $shippingOrder->id)
            ->whereHas('shippingOrder', function ($query) {
                $query->whereIn('status', [
                    ShippingOrderStatus::Draft->value,
                    ShippingOrderStatus::Planned->value,
                    ShippingOrderStatus::Picking->value,
                    ShippingOrderStatus::OnHold->value,
                ]);
            })
            ->first();
        if ($inOtherSo !== null) {
            $notLockedElsewhere = false;
            $lockReason = "Voucher is already assigned to Shipping Order {$inOtherSo->shipping_order_id}.";
        }
        $checks[] = [
            'label' => 'Not locked by other processes',
            'passed' => $notLockedElsewhere,
            'reason' => $lockReason,
        ];

        // Check 4: Voucher not suspended
        $notSuspended = ! $voucher->suspended;
        $checks[] = [
            'label' => 'Voucher not suspended',
            'passed' => $notSuspended,
            'reason' => $notSuspended ? null : 'Voucher is suspended. '.$voucher->getSuspensionReason(),
        ];

        // Check 5: Customer match (holder = SO customer)
        $customerMatch = $voucher->customer_id === $shippingOrder->customer_id;
        $checks[] = [
            'label' => 'Customer match (holder = SO customer)',
            'passed' => $customerMatch,
            'reason' => $customerMatch ? null : 'Voucher holder does not match Shipping Order customer.',
        ];

        // Additional checks from ShippingOrderService

        // Check 6: Not in pending transfer
        $noPendingTransfer = ! $voucher->hasPendingTransfer();
        $checks[] = [
            'label' => 'No pending transfer',
            'passed' => $noPendingTransfer,
            'reason' => $noPendingTransfer ? null : 'Voucher has a pending transfer. Complete or cancel the transfer first.',
        ];

        // Check 7: Not quarantined
        $notQuarantined = ! $voucher->isQuarantined();
        $checks[] = [
            'label' => 'Not quarantined',
            'passed' => $notQuarantined,
            'reason' => $notQuarantined ? null : 'Voucher requires attention: '.($voucher->getAttentionReason() ?? 'Unknown issue'),
        ];

        // Check 8: Valid allocation lineage
        $validAllocation = ! $voucher->hasLineageIssues();
        $checks[] = [
            'label' => 'Valid allocation lineage',
            'passed' => $validAllocation,
            'reason' => $validAllocation ? null : 'Voucher has allocation lineage issues.',
        ];

        return $checks;
    }

    /**
     * Check if a voucher is eligible for fulfillment.
     */
    protected function isVoucherEligible(ShippingOrderLine $line): bool
    {
        $voucher = $line->voucher;
        if ($voucher === null) {
            return false;
        }

        /** @var ShippingOrder $shippingOrder */
        $shippingOrder = $this->record;

        $checks = $this->performEligibilityChecks($voucher, $shippingOrder);

        foreach ($checks as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the eligibility status label.
     */
    protected function getEligibilityStatusLabel(ShippingOrderLine $line): string
    {
        return $this->isVoucherEligible($line) ? 'Eligible' : 'Ineligible';
    }

    /**
     * Get the eligibility status color.
     */
    protected function getEligibilityStatusColor(ShippingOrderLine $line): string
    {
        return $this->isVoucherEligible($line) ? 'success' : 'danger';
    }

    /**
     * Get the eligibility status icon.
     */
    protected function getEligibilityStatusIcon(ShippingOrderLine $line): string
    {
        return $this->isVoucherEligible($line) ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
    }

    /**
     * Count eligible vouchers in the Shipping Order.
     */
    protected function countEligibleVouchers(ShippingOrder $record): int
    {
        $record->load('lines.voucher');
        $count = 0;

        foreach ($record->lines as $line) {
            if ($this->isVoucherEligibleForRecord($line, $record)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count ineligible vouchers in the Shipping Order.
     */
    protected function countIneligibleVouchers(ShippingOrder $record): int
    {
        $record->load('lines.voucher');
        $count = 0;

        foreach ($record->lines as $line) {
            if (! $this->isVoucherEligibleForRecord($line, $record)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a voucher is eligible for a given shipping order record.
     * (Used when $this->record may not be set yet, e.g., in badge callbacks)
     */
    protected function isVoucherEligibleForRecord(ShippingOrderLine $line, ShippingOrder $record): bool
    {
        $voucher = $line->voucher;
        if ($voucher === null) {
            return false;
        }

        $checks = $this->performEligibilityChecks($voucher, $record);

        foreach ($checks as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Section 1: Customer & Destination
     * Customer name (link), destination address, contact info.
     */
    protected function getCustomerAndDestinationSection(): Section
    {
        return Section::make('Customer & Destination')
            ->description('Who is receiving this shipment and where')
            ->icon('heroicon-o-user')
            ->schema([
                Grid::make(2)
                    ->schema([
                        // Customer Information
                        Group::make([
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->url(fn (ShippingOrder $record): ?string => $record->customer
                                    ? CustomerResource::getUrl('view', ['record' => $record->customer])
                                    : null)
                                ->openUrlInNewTab()
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large)
                                ->icon('heroicon-o-user'),
                            TextEntry::make('customer.email')
                                ->label('Email')
                                ->icon('heroicon-o-envelope')
                                ->copyable()
                                ->copyMessage('Email copied')
                                ->default('Not specified'),
                            TextEntry::make('customer.phone')
                                ->label('Phone')
                                ->icon('heroicon-o-phone')
                                ->default('Not specified'),
                        ])->columnSpan(1),
                        // Destination Address
                        Group::make([
                            TextEntry::make('destination_address')
                                ->label('Destination Address')
                                ->icon('heroicon-o-map-pin')
                                ->default('Not specified')
                                ->html()
                                ->getStateUsing(function (ShippingOrder $record): string {
                                    if ($record->destination_address === null || $record->destination_address === '') {
                                        return '<span class="text-gray-400">Not specified</span>';
                                    }

                                    // Format multiline address for display
                                    return nl2br(e($record->destination_address));
                                }),
                            TextEntry::make('sourceWarehouse.name')
                                ->label('Source Warehouse')
                                ->icon('heroicon-o-building-storefront')
                                ->default('Not specified')
                                ->helperText('Where the shipment will be dispatched from'),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Section 2: Shipping Method
     * Carrier, method, incoterms, requested_ship_date.
     */
    protected function getShippingMethodSection(): Section
    {
        return Section::make('Shipping Method')
            ->description('How this shipment will be delivered')
            ->icon('heroicon-o-truck')
            ->schema([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('carrier')
                            ->label('Carrier')
                            ->icon('heroicon-o-building-office')
                            ->default('Not specified')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('shipping_method')
                            ->label('Shipping Method')
                            ->default('Not specified'),
                        TextEntry::make('incoterms')
                            ->label('Incoterms')
                            ->default('Not specified')
                            ->badge()
                            ->color('gray')
                            ->helperText(function (ShippingOrder $record): ?string {
                                if ($record->incoterms === null) {
                                    return null;
                                }

                                return match ($record->incoterms) {
                                    'EXW' => 'Ex Works - Buyer assumes all costs',
                                    'FCA' => 'Free Carrier - Seller delivers to carrier',
                                    'DDP' => 'Delivered Duty Paid - Seller pays all costs',
                                    'DAP' => 'Delivered at Place - Seller delivers to destination',
                                    'CPT' => 'Carriage Paid To - Seller pays freight to destination',
                                    'CIF' => 'Cost, Insurance, Freight - Seller pays to port',
                                    'FOB' => 'Free On Board - Seller delivers on vessel',
                                    default => null,
                                };
                            }),
                        TextEntry::make('requested_ship_date')
                            ->label('Requested Ship Date')
                            ->icon('heroicon-o-calendar')
                            ->date()
                            ->default('Not specified')
                            ->color(function (ShippingOrder $record): string {
                                if ($record->requested_ship_date === null) {
                                    return 'gray';
                                }
                                if ($record->requested_ship_date->isPast()) {
                                    return 'danger';
                                }
                                if ($record->requested_ship_date->isToday()) {
                                    return 'warning';
                                }

                                return 'success';
                            }),
                    ]),
                TextEntry::make('special_instructions')
                    ->label('Special Instructions')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->default('None')
                    ->columnSpanFull()
                    ->html()
                    ->getStateUsing(function (ShippingOrder $record): string {
                        if ($record->special_instructions === null || $record->special_instructions === '') {
                            return '<span class="text-gray-400 italic">No special instructions</span>';
                        }

                        return nl2br(e($record->special_instructions));
                    }),
            ]);
    }

    /**
     * Section 3: Packaging
     * Packaging preference with explanation.
     */
    protected function getPackagingSection(): Section
    {
        return Section::make('Packaging')
            ->description('How items should be packaged for shipping')
            ->icon('heroicon-o-cube')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('packaging_preference')
                            ->label('Packaging Preference')
                            ->formatStateUsing(fn (ShippingOrder $record): string => $record->getPackagingPreferenceLabel())
                            ->badge()
                            ->color(fn (ShippingOrder $record): string => $record->packaging_preference->color())
                            ->icon(fn (ShippingOrder $record): string => $record->packaging_preference->icon())
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('packaging_description')
                            ->label('Description')
                            ->getStateUsing(fn (ShippingOrder $record): string => $record->getPackagingPreferenceDescription())
                            ->html(),
                    ]),
                TextEntry::make('packaging_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => '⚠️ May delay shipment if original wooden case not available')
                    ->visible(fn (ShippingOrder $record): bool => $record->packaging_preference->mayDelayShipment())
                    ->color('warning')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Section 4: Voucher Summary
     * Count, list with voucher_id/wine/allocation, state badges.
     */
    protected function getVoucherSummarySection(): Section
    {
        return Section::make('Voucher Summary')
            ->description('Vouchers included in this shipping order')
            ->icon('heroicon-o-ticket')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('voucher_count')
                            ->label('Total Vouchers')
                            ->getStateUsing(fn (ShippingOrder $record): int => $record->lines()->count())
                            ->badge()
                            ->color('info')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('pending_lines')
                            ->label('Pending')
                            ->getStateUsing(fn (ShippingOrder $record): int => $record->lines()
                                ->where('status', ShippingOrderLineStatus::Pending)
                                ->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('validated_lines')
                            ->label('Validated')
                            ->getStateUsing(fn (ShippingOrder $record): int => $record->lines()
                                ->where('status', ShippingOrderLineStatus::Validated)
                                ->count())
                            ->badge()
                            ->color('success'),
                    ]),
                RepeatableEntry::make('lines')
                    ->label('Voucher Details')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('voucher.id')
                                    ->label('Voucher ID')
                                    ->url(fn (ShippingOrderLine $record): ?string => $record->voucher
                                        ? VoucherResource::getUrl('view', ['record' => $record->voucher])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->color('primary')
                                    ->copyable()
                                    ->copyMessage('Voucher ID copied')
                                    ->limit(8),
                                TextEntry::make('voucher.wineVariant.wineMaster.name')
                                    ->label('Wine')
                                    ->default('Unknown')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('voucher.format.name')
                                    ->label('Format')
                                    ->default('Standard'),
                                TextEntry::make('allocation.id')
                                    ->label('Allocation')
                                    ->limit(8)
                                    ->copyable()
                                    ->copyMessage('Allocation ID copied'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (ShippingOrderLine $record): string => $record->getStatusLabel())
                                    ->color(fn (ShippingOrderLine $record): string => $record->getStatusColor())
                                    ->icon(fn (ShippingOrderLine $record): string => $record->getStatusIcon()),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn (ShippingOrder $record): bool => $record->lines()->count() > 0),
                TextEntry::make('no_vouchers')
                    ->label('')
                    ->getStateUsing(fn (): string => 'No vouchers have been added to this shipping order yet.')
                    ->color('gray')
                    ->visible(fn (ShippingOrder $record): bool => $record->lines()->count() === 0),
            ]);
    }

    /**
     * Status banner at the top of the page.
     */
    protected function getStatusBanner(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('status_banner')
                    ->label('')
                    ->getStateUsing(function (ShippingOrder $record): string {
                        return match ($record->status) {
                            ShippingOrderStatus::Draft => 'This Shipping Order is in DRAFT status. It requires planning before execution.',
                            ShippingOrderStatus::Planned => 'This Shipping Order is PLANNED. Vouchers are locked and ready for picking.',
                            ShippingOrderStatus::Picking => 'This Shipping Order is in PICKING status. WMS is processing the order.',
                            ShippingOrderStatus::Shipped => 'This Shipping Order has been SHIPPED. Awaiting delivery confirmation.',
                            ShippingOrderStatus::Completed => 'This Shipping Order is COMPLETED. All vouchers have been redeemed.',
                            ShippingOrderStatus::Cancelled => 'This Shipping Order has been CANCELLED.',
                            ShippingOrderStatus::OnHold => 'This Shipping Order is ON HOLD. Review and resolve any issues.',
                        };
                    })
                    ->icon(fn (ShippingOrder $record): string => $record->status->icon())
                    ->iconColor(fn (ShippingOrder $record): string => $record->status->color())
                    ->weight(FontWeight::Bold)
                    ->color(fn (ShippingOrder $record): string => $record->status->color()),
            ])
            ->extraAttributes(fn (ShippingOrder $record): array => [
                'class' => match ($record->status->color()) {
                    'gray' => 'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-800',
                    'info' => 'bg-info-50 dark:bg-info-900/20 border-info-200 dark:border-info-800',
                    'warning' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800',
                    'success' => 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800',
                    'danger' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800',
                    default => 'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-800',
                },
            ])
            ->columnSpanFull();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSelectWarehouseAction(),
            $this->getPlanOrderAction(),
            Actions\EditAction::make()
                ->visible(fn (ShippingOrder $record): bool => $record->isDraft()),
            Actions\DeleteAction::make()
                ->visible(fn (ShippingOrder $record): bool => $record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Delete Shipping Order')
                ->modalDescription('Are you sure you want to delete this shipping order? This action cannot be undone.'),
        ];
    }

    /**
     * Action to select/change source warehouse.
     */
    protected function getSelectWarehouseAction(): Actions\Action
    {
        return Actions\Action::make('selectWarehouse')
            ->label(fn (ShippingOrder $record): string => $record->source_warehouse_id === null
                ? 'Select Warehouse'
                : 'Change Warehouse')
            ->icon('heroicon-o-building-storefront')
            ->color('gray')
            ->visible(fn (ShippingOrder $record): bool => $record->isDraft())
            ->form([
                Select::make('source_warehouse_id')
                    ->label('Source Warehouse')
                    ->options(function (): array {
                        return Location::query()
                            ->whereIn('location_type', [
                                LocationType::MainWarehouse,
                                LocationType::SatelliteWarehouse,
                            ])
                            ->where('status', \App\Enums\Inventory\LocationStatus::Active)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default(fn (ShippingOrder $record): ?string => $record->source_warehouse_id)
                    ->required()
                    ->searchable()
                    ->helperText('Select the warehouse from which this shipment will be dispatched.'),
            ])
            ->action(function (ShippingOrder $record, array $data): void {
                $record->source_warehouse_id = $data['source_warehouse_id'];
                $record->save();

                Notification::make()
                    ->title('Warehouse Updated')
                    ->body('Source warehouse has been updated. Check inventory availability.')
                    ->success()
                    ->send();
            })
            ->modalHeading('Select Source Warehouse')
            ->modalSubmitActionLabel('Save Warehouse');
    }

    /**
     * Action to plan the shipping order.
     */
    protected function getPlanOrderAction(): Actions\Action
    {
        return Actions\Action::make('planOrder')
            ->label('Plan Order')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->visible(fn (ShippingOrder $record): bool => $record->isDraft())
            ->disabled(fn (ShippingOrder $record): bool => ! $this->canPlanShippingOrder($record))
            ->requiresConfirmation()
            ->modalHeading('Plan Shipping Order')
            ->modalDescription(function (ShippingOrder $record): string {
                $voucherCount = $record->lines()->count();

                return "Are you sure you want to plan this Shipping Order?\n\n"
                    ."This will:\n"
                    ."• Lock {$voucherCount} voucher(s) for fulfillment\n"
                    ."• Reserve inventory at the selected warehouse\n"
                    ."• Prevent changes to the order without cancellation\n\n"
                    .'This action cannot be undone without cancelling the order.';
            })
            ->modalSubmitActionLabel('Plan Order')
            ->action(function (ShippingOrder $record): void {
                // Final validation checks
                if (! $this->canPlanShippingOrder($record)) {
                    Notification::make()
                        ->title('Cannot Plan Order')
                        ->body('This order does not meet all requirements for planning. Check voucher eligibility and inventory availability.')
                        ->danger()
                        ->send();

                    return;
                }

                // Check for any insufficient inventory and create exceptions
                $availability = $this->getInventoryAvailability($record);
                if (! $availability['all_available']) {
                    foreach ($availability['allocations'] as $allocationId => $data) {
                        if ($data['status'] === 'insufficient') {
                            ShippingOrderException::create([
                                'shipping_order_id' => $record->id,
                                'exception_type' => ShippingOrderExceptionType::SupplyInsufficient,
                                'description' => "Insufficient eligible inventory for allocation {$allocationId}. "
                                    ."Required: {$data['required_quantity']}, Available: {$data['available_quantity']}.",
                                'resolution_path' => 'Wait for inventory to become available, request internal transfer (Module B), or cancel Shipping Order.',
                                'status' => ShippingOrderExceptionStatus::Active,
                                'created_by' => Auth::id(),
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Cannot Plan Order')
                        ->body('Insufficient inventory for one or more allocations. Supply exceptions have been created.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    /** @var ShippingOrderService $shippingOrderService */
                    $shippingOrderService = app(ShippingOrderService::class);
                    $shippingOrderService->transitionTo($record, ShippingOrderStatus::Planned);

                    Notification::make()
                        ->title('Order Planned')
                        ->body('Shipping Order has been planned. Vouchers are now locked for fulfillment.')
                        ->success()
                        ->send();

                    // Redirect to refresh the page
                    $this->redirect(ShippingOrderResource::getUrl('view', ['record' => $record]));
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Planning Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
