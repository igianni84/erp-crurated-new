<?php

namespace App\Filament\Resources\Fulfillment\ShipmentResource\Pages;

use App\Enums\Fulfillment\ShipmentStatus;
use App\Filament\Resources\Fulfillment\ShipmentResource;
use App\Models\Fulfillment\Shipment;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Status Banner
                Section::make()
                    ->schema([
                        TextEntry::make('status')
                            ->label('')
                            ->badge()
                            ->size(TextEntry\TextEntrySize::Large)
                            ->formatStateUsing(fn (ShipmentStatus $state): string => $state->label())
                            ->color(fn (ShipmentStatus $state): string => $state->color())
                            ->icon(fn (ShipmentStatus $state): string => $state->icon()),
                        TextEntry::make('id')
                            ->label('Shipment ID')
                            ->copyable()
                            ->copyMessage('Shipment ID copied'),
                        TextEntry::make('shipped_at')
                            ->label('Shipped At')
                            ->dateTime()
                            ->placeholder('Not yet shipped'),
                    ])
                    ->columns(3)
                    ->extraAttributes(fn (Shipment $record): array => [
                        'class' => match ($record->status) {
                            ShipmentStatus::Preparing => 'bg-warning-50 dark:bg-warning-950 border-warning-200',
                            ShipmentStatus::Shipped, ShipmentStatus::InTransit => 'bg-info-50 dark:bg-info-950 border-info-200',
                            ShipmentStatus::Delivered => 'bg-success-50 dark:bg-success-950 border-success-200',
                            ShipmentStatus::Failed => 'bg-danger-50 dark:bg-danger-950 border-danger-200',
                        },
                    ]),

                // Section 1 - Shipping Order
                Section::make('Shipping Order')
                    ->description('The source Shipping Order for this shipment')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('shippingOrder.id')
                                    ->label('SO ID')
                                    ->url(fn (Shipment $record): ?string => $record->shippingOrder
                                        ? route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->shippingOrder])
                                        : null)
                                    ->color('primary')
                                    ->copyable()
                                    ->copyMessage('SO ID copied'),
                                TextEntry::make('shippingOrder.customer.name')
                                    ->label('Customer')
                                    ->url(fn (Shipment $record): ?string => $record->shippingOrder?->customer
                                        ? route('filament.admin.resources.customers.view', ['record' => $record->shippingOrder->customer])
                                        : null)
                                    ->color('primary')
                                    ->placeholder('-'),
                                TextEntry::make('shippingOrder.status')
                                    ->label('SO Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '-')
                                    ->color(fn ($state): string => $state?->color() ?? 'gray'),
                            ]),
                        TextEntry::make('destination_address')
                            ->label('Destination Address')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(nl2br(e($state ?? '-')))),
                    ]),

                // Section 2 - Carrier & Tracking
                Section::make('Carrier & Tracking')
                    ->description('Shipping carrier information and tracking details')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('carrier')
                                    ->label('Carrier')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->copyable()
                                    ->copyMessage('Tracking number copied')
                                    ->placeholder('-'),
                                TextEntry::make('tracking_url')
                                    ->label('Track Package')
                                    ->getStateUsing(fn (Shipment $record): ?string => $record->tracking_number ? $this->getTrackingUrl($record) : null)
                                    ->url(fn (Shipment $record): ?string => $record->tracking_number ? $this->getTrackingUrl($record) : null)
                                    ->openUrlInNewTab()
                                    ->formatStateUsing(fn (?string $state): string => $state ? 'Track Package' : '-')
                                    ->color(fn (?string $state): ?string => $state ? 'primary' : null)
                                    ->icon(fn (?string $state): ?string => $state ? 'heroicon-o-arrow-top-right-on-square' : null)
                                    ->placeholder('-'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('originWarehouse.name')
                                    ->label('Origin Warehouse')
                                    ->placeholder('-'),
                                TextEntry::make('weight')
                                    ->label('Weight')
                                    ->formatStateUsing(fn (?string $state): string => $state ? "{$state} kg" : '-')
                                    ->placeholder('-'),
                                TextEntry::make('delivered_at')
                                    ->label('Delivered At')
                                    ->dateTime()
                                    ->placeholder('Not yet delivered'),
                            ]),
                    ]),

                // Section 3 - Shipped Items
                Section::make('Shipped Items')
                    ->description(fn (Shipment $record): string => "Total: {$record->getBottleCount()} bottle(s)")
                    ->icon('heroicon-o-cube')
                    ->schema([
                        TextEntry::make('shipped_bottle_serials')
                            ->label('')
                            ->getStateUsing(function (Shipment $record): HtmlString {
                                $serials = $record->shipped_bottle_serials ?? [];
                                if (empty($serials)) {
                                    return new HtmlString('<p class="text-gray-500 italic">No bottles in this shipment</p>');
                                }

                                $html = '<div class="overflow-x-auto"><table class="w-full text-sm border-collapse">';
                                $html .= '<thead><tr class="border-b">';
                                $html .= '<th class="text-left py-2 px-3 font-medium">#</th>';
                                $html .= '<th class="text-left py-2 px-3 font-medium">Serial Number</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($serials as $index => $serial) {
                                    $rowClass = $index % 2 === 0 ? 'bg-gray-50 dark:bg-gray-900' : '';
                                    $html .= "<tr class=\"border-b {$rowClass}\">";
                                    $html .= '<td class="py-2 px-3">'.($index + 1).'</td>';
                                    $html .= '<td class="py-2 px-3 font-mono">'.e($serial).'</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Shipment $record): bool => $record->getBottleCount() > 20),

                // Section 4 - Audit
                Section::make('Audit Trail')
                    ->description('Events timeline for this shipment')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('audit_timeline')
                            ->label('')
                            ->getStateUsing(function (Shipment $record): HtmlString {
                                $auditLogs = $record->auditLogs()->orderBy('created_at', 'desc')->get();

                                if ($auditLogs->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 italic">No audit events recorded</p>');
                                }

                                $html = '<div class="space-y-4">';

                                foreach ($auditLogs as $log) {
                                    $timestamp = $log->created_at->format('M d, Y H:i:s');
                                    $user = $log->user !== null ? $log->user->name : 'System';

                                    $html .= '<div class="flex items-start gap-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-900">';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<div class="flex items-center gap-2">';
                                    $html .= '<span class="font-medium">'.e($log->event).'</span>';
                                    $html .= '<span class="text-xs text-gray-500">'.$timestamp.'</span>';
                                    $html .= '</div>';
                                    $html .= '<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">'.e($log->description ?? '-').'</p>';
                                    $html .= '<p class="text-xs text-gray-500 mt-1">By: '.e($user).'</p>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Notes Section
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(nl2br(e($state ?? 'No notes'))))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (Shipment $record): bool => $record->notes !== null),

                // Timestamps
                Section::make('Record Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        // Shipments are read-only after shipped status
        return [];
    }

    /**
     * Get tracking URL for the carrier.
     */
    private function getTrackingUrl(Shipment $record): ?string
    {
        if (! $record->tracking_number) {
            return null;
        }

        $trackingNumber = urlencode($record->tracking_number);

        return match (strtoupper($record->carrier ?? '')) {
            'DHL' => "https://www.dhl.com/en/express/tracking.html?AWB={$trackingNumber}",
            'FEDEX' => "https://www.fedex.com/fedextrack/?trknbr={$trackingNumber}",
            'UPS' => "https://www.ups.com/track?tracknum={$trackingNumber}",
            'USPS' => "https://tools.usps.com/go/TrackConfirmAction?tLabels={$trackingNumber}",
            'TNT' => "https://www.tnt.com/express/en_us/site/tracking.html?searchType=con&cons={$trackingNumber}",
            default => null,
        };
    }
}
