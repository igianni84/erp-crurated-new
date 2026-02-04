<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
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
                Section::make('Order Overview')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('SO ID')
                                        ->copyable()
                                        ->copyMessage('SO ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (ShippingOrderStatus $state): string => $state->label())
                                        ->color(fn (ShippingOrderStatus $state): string => $state->color())
                                        ->icon(fn (ShippingOrderStatus $state): string => $state->icon())
                                        ->size(TextEntry\TextEntrySize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('customer.name')
                                        ->label('Customer')
                                        ->url(fn (ShippingOrder $record): ?string => $record->customer
                                            ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                                            : null)
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('customer.email')
                                        ->label('Email')
                                        ->icon('heroicon-o-envelope'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('sourceWarehouse.name')
                                        ->label('Source Warehouse')
                                        ->icon('heroicon-o-building-storefront')
                                        ->default('Not specified'),
                                    TextEntry::make('lines_count')
                                        ->label('Vouchers')
                                        ->getStateUsing(fn (ShippingOrder $record): int => $record->lines()->count())
                                        ->badge()
                                        ->color('info'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                    TextEntry::make('createdByUser.name')
                                        ->label('Created By')
                                        ->default('System'),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Shipping Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('carrier')
                                    ->label('Carrier')
                                    ->default('Not specified')
                                    ->icon('heroicon-o-truck'),
                                TextEntry::make('shipping_method')
                                    ->label('Shipping Method')
                                    ->default('Not specified'),
                                TextEntry::make('incoterms')
                                    ->label('Incoterms')
                                    ->default('Not specified')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('requested_ship_date')
                                    ->label('Requested Ship Date')
                                    ->date()
                                    ->default('Not specified')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),
                Section::make('Packaging & Instructions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('packaging_preference')
                                    ->label('Packaging Preference')
                                    ->formatStateUsing(fn (ShippingOrder $record): string => $record->getPackagingPreferenceLabel())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('packaging_description')
                                    ->label('Description')
                                    ->getStateUsing(fn (ShippingOrder $record): string => $record->getPackagingPreferenceDescription()),
                            ]),
                        TextEntry::make('special_instructions')
                            ->label('Special Instructions')
                            ->default('None')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

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
