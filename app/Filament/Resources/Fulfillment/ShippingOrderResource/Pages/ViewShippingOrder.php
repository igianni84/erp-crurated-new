<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Fulfillment\ShippingOrderLineStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
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
                        // Future tabs will be added in subsequent stories:
                        // US-C025: Vouchers & Eligibility tab
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
