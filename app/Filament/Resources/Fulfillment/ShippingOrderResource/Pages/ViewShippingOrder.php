<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Allocation\Voucher;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use App\Services\Fulfillment\ShippingOrderService;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

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
                        // Future tabs will be added in subsequent stories:
                        // US-C026: Planning tab
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
            Actions\EditAction::make()
                ->visible(fn (ShippingOrder $record): bool => $record->isDraft()),
            Actions\DeleteAction::make()
                ->visible(fn (ShippingOrder $record): bool => $record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Delete Shipping Order')
                ->modalDescription('Are you sure you want to delete this shipping order? This action cannot be undone.'),
        ];
    }
}
