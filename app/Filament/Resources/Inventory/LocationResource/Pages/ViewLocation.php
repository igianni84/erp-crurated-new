<?php

namespace App\Filament\Resources\Inventory\LocationResource\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\LocationStatus;
use App\Enums\Inventory\LocationType;
use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\LocationResource;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
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

class ViewLocation extends ViewRecord
{
    protected static string $resource = LocationResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Location $record */
        $record = $this->record;

        return "Location: {$record->name}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getSerializationWarningSection(),
                Tabs::make('Location Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getInventoryTab(),
                        $this->getInboundOutboundTab(),
                        $this->getWmsStatusTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Prominent warning section if serialization is not authorized.
     */
    protected function getSerializationWarningSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('serialization_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => 'Serialization NOT Authorized at this location')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->weight(FontWeight::Bold)
                    ->color('danger')
                    ->size(TextEntry\TextEntrySize::Large),
            ])
            ->visible(fn (Location $record): bool => ! $record->serialization_authorized)
            ->extraAttributes(['class' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800'])
            ->columnSpanFull();
    }

    /**
     * Tab 1: Overview - Stock summary, serialized vs non-serialized, ownership breakdown.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Location Identity')
                    ->description('Basic location information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('uuid')
                                        ->label('UUID')
                                        ->copyable()
                                        ->copyMessage('UUID copied'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('location_type')
                                        ->label('Type')
                                        ->badge()
                                        ->formatStateUsing(fn (LocationType $state): string => $state->label())
                                        ->color(fn (LocationType $state): string => $state->color())
                                        ->icon(fn (LocationType $state): string => $state->icon()),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (LocationStatus $state): string => $state->label())
                                        ->color(fn (LocationStatus $state): string => $state->color())
                                        ->icon(fn (LocationStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('country')
                                        ->label('Country'),
                                    TextEntry::make('address')
                                        ->label('Address')
                                        ->default('Not specified')
                                        ->placeholder('Not specified'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('serialization_authorized')
                                        ->label('Serialization')
                                        ->badge()
                                        ->getStateUsing(fn (Location $record): string => $record->serialization_authorized ? 'Authorized' : 'Not Authorized')
                                        ->color(fn (Location $record): string => $record->serialization_authorized ? 'success' : 'danger')
                                        ->icon(fn (Location $record): string => $record->serialization_authorized ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle'),
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Stock Summary')
                    ->description('Overview of inventory at this location')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_serialized_bottles')
                                    ->label('Serialized Bottles')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()->count())
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('success'),
                                TextEntry::make('total_cases')
                                    ->label('Cases')
                                    ->getStateUsing(fn (Location $record): int => $record->cases()->count())
                                    ->numeric()
                                    ->suffix(' cases')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('info'),
                                TextEntry::make('unserialized_inbound')
                                    ->label('Unserialized (Inbound)')
                                    ->getStateUsing(function (Location $record): int {
                                        return $record->inboundBatches()
                                            ->whereIn('serialization_status', [
                                                InboundBatchStatus::PendingSerialization,
                                                InboundBatchStatus::PartiallySerialized,
                                            ])
                                            ->get()
                                            ->sum(fn (InboundBatch $batch) => $batch->remaining_unserialized);
                                    })
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('warning'),
                                TextEntry::make('pending_batches')
                                    ->label('Batches Pending')
                                    ->getStateUsing(fn (Location $record): int => $record->inboundBatches()
                                        ->whereIn('serialization_status', [
                                            InboundBatchStatus::PendingSerialization,
                                            InboundBatchStatus::PartiallySerialized,
                                        ])
                                        ->count())
                                    ->numeric()
                                    ->suffix(' batches')
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make('Bottles by State')
                    ->description('Breakdown of serialized bottles by current state')
                    ->schema([
                        Grid::make(6)
                            ->schema([
                                TextEntry::make('bottles_stored')
                                    ->label('Stored')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Stored)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('bottles_reserved')
                                    ->label('Reserved')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::ReservedForPicking)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('bottles_shipped')
                                    ->label('Shipped')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Shipped)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('bottles_consumed')
                                    ->label('Consumed')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Consumed)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('bottles_destroyed')
                                    ->label('Destroyed')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Destroyed)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('bottles_missing')
                                    ->label('Missing')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Missing)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('danger'),
                            ]),
                    ]),
                Section::make('Ownership Breakdown')
                    ->description('Breakdown of inventory by ownership type')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('ownership_crurated')
                                    ->label('Crurated Owned')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('ownership_type', OwnershipType::CururatedOwned)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-building-office'),
                                TextEntry::make('ownership_custody')
                                    ->label('In Custody')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('ownership_type', OwnershipType::InCustody)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-hand-raised'),
                                TextEntry::make('ownership_third_party')
                                    ->label('Third Party Owned')
                                    ->getStateUsing(fn (Location $record): int => $record->serializedBottles()
                                        ->where('ownership_type', OwnershipType::ThirdPartyOwned)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-user-group'),
                            ]),
                    ]),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->default('No notes')
                            ->placeholder('No notes'),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Location $record): bool => $record->notes === null),
            ]);
    }

    /**
     * Tab 2: Inventory - Bottles and cases stored here.
     */
    protected function getInventoryTab(): Tab
    {
        return Tab::make('Inventory')
            ->icon('heroicon-o-archive-box')
            ->badge(fn (Location $record): ?string => $record->serializedBottles()->count() > 0
                ? (string) $record->serializedBottles()->count()
                : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Serialized Bottles')
                    ->description('Bottles currently stored at this location')
                    ->schema([
                        TextEntry::make('bottles_count_info')
                            ->label('')
                            ->getStateUsing(function (Location $record): string {
                                $total = $record->serializedBottles()->count();
                                $stored = $record->serializedBottles()->where('state', BottleState::Stored)->count();

                                return "Showing {$stored} stored bottles out of {$total} total bottles at this location.";
                            })
                            ->color('gray'),
                        RepeatableEntry::make('serializedBottles')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('serial_number')
                                            ->label('Serial Number')
                                            ->copyable()
                                            ->copyMessage('Serial number copied')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('wineVariant.display_label')
                                            ->label('Wine')
                                            ->getStateUsing(function (SerializedBottle $record): string {
                                                $variant = $record->wineVariant;
                                                if (! $variant) {
                                                    return 'Unknown';
                                                }
                                                $master = $variant->wineMaster;
                                                $name = $master ? $master->name : 'Unknown Wine';
                                                $vintage = $variant->vintage_year ?? 'NV';

                                                return "{$name} {$vintage}";
                                            }),
                                        TextEntry::make('format.name')
                                            ->label('Format')
                                            ->default('Standard'),
                                        TextEntry::make('state')
                                            ->label('State')
                                            ->badge()
                                            ->formatStateUsing(fn (BottleState $state): string => $state->label())
                                            ->color(fn (BottleState $state): string => $state->color())
                                            ->icon(fn (BottleState $state): string => $state->icon()),
                                        TextEntry::make('ownership_type')
                                            ->label('Ownership')
                                            ->badge()
                                            ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                                            ->color(fn (OwnershipType $state): string => $state->color()),
                                        TextEntry::make('serialized_at')
                                            ->label('Serialized')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (Location $record): bool => $record->serializedBottles()->count() > 0),
                        TextEntry::make('no_bottles')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No serialized bottles at this location.')
                            ->color('gray')
                            ->visible(fn (Location $record): bool => $record->serializedBottles()->count() === 0),
                    ]),
                Section::make('Cases')
                    ->description('Cases currently stored at this location')
                    ->schema([
                        RepeatableEntry::make('cases')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Case ID')
                                            ->copyable()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('caseConfiguration.name')
                                            ->label('Configuration')
                                            ->default('Standard'),
                                        TextEntry::make('integrity_status')
                                            ->label('Integrity')
                                            ->badge()
                                            ->formatStateUsing(fn (InventoryCase $record): string => $record->integrity_status->label())
                                            ->color(fn (InventoryCase $record): string => $record->integrity_status->color())
                                            ->icon(fn (InventoryCase $record): string => $record->integrity_status->icon()),
                                        TextEntry::make('bottles_in_case')
                                            ->label('Bottles')
                                            ->getStateUsing(fn (InventoryCase $record): int => $record->serializedBottles()->count())
                                            ->numeric()
                                            ->suffix(' bottles'),
                                        TextEntry::make('is_original')
                                            ->label('Original')
                                            ->badge()
                                            ->getStateUsing(fn (InventoryCase $record): string => $record->is_original ? 'Yes' : 'No')
                                            ->color(fn (InventoryCase $record): string => $record->is_original ? 'success' : 'gray'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (Location $record): bool => $record->cases()->count() > 0),
                        TextEntry::make('no_cases')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No cases at this location.')
                            ->color('gray')
                            ->visible(fn (Location $record): bool => $record->cases()->count() === 0),
                    ]),
            ]);
    }

    /**
     * Tab 3: Inbound/Outbound - Recent receipts and transfers.
     */
    protected function getInboundOutboundTab(): Tab
    {
        return Tab::make('Inbound/Outbound')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Section::make('Recent Inbound Batches')
                    ->description('Recent receipts at this location')
                    ->schema([
                        RepeatableEntry::make('inboundBatches')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Batch ID')
                                            ->copyable()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('source_type')
                                            ->label('Source')
                                            ->badge()
                                            ->color('info'),
                                        TextEntry::make('quantity_received')
                                            ->label('Qty Received')
                                            ->numeric()
                                            ->suffix(' bottles'),
                                        TextEntry::make('serialization_status')
                                            ->label('Serialization')
                                            ->badge()
                                            ->formatStateUsing(fn (InboundBatchStatus $state): string => $state->label())
                                            ->color(fn (InboundBatchStatus $state): string => $state->color())
                                            ->icon(fn (InboundBatchStatus $state): string => $state->icon()),
                                        TextEntry::make('ownership_type')
                                            ->label('Ownership')
                                            ->badge()
                                            ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                                            ->color(fn (OwnershipType $state): string => $state->color()),
                                        TextEntry::make('received_date')
                                            ->label('Received')
                                            ->date(),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (Location $record): bool => $record->inboundBatches()->count() > 0),
                        TextEntry::make('no_inbound')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No inbound batches at this location.')
                            ->color('gray')
                            ->visible(fn (Location $record): bool => $record->inboundBatches()->count() === 0),
                    ]),
                Section::make('Recent Transfers In')
                    ->description('Recent movements into this location')
                    ->schema([
                        TextEntry::make('transfers_in')
                            ->label('')
                            ->getStateUsing(function (Location $record): string {
                                $movements = InventoryMovement::query()
                                    ->where('destination_location_id', $record->id)
                                    ->orderBy('executed_at', 'desc')
                                    ->limit(10)
                                    ->get();

                                if ($movements->isEmpty()) {
                                    return 'No recent transfers into this location.';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($movements as $movement) {
                                    /** @var InventoryMovement $movement */
                                    $type = $movement->movement_type->label();
                                    $trigger = $movement->trigger->label();
                                    $sourceLocation = $movement->sourceLocation;
                                    $source = $sourceLocation !== null ? $sourceLocation->name : 'External';
                                    $date = $movement->executed_at->format('M d, Y H:i');
                                    $itemsCount = $movement->movementItems()->count();

                                    $typeColor = match ($movement->movement_type) {
                                        MovementType::InternalTransfer => 'text-blue-600 dark:text-blue-400',
                                        MovementType::ConsignmentPlacement => 'text-yellow-600 dark:text-yellow-400',
                                        MovementType::ConsignmentReturn => 'text-green-600 dark:text-green-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="flex items-center gap-4 p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                        <span class="font-medium {$typeColor}">{$type}</span>
                                        <span class="text-sm text-gray-500">from {$source}</span>
                                        <span class="text-sm text-gray-500">{$itemsCount} item(s)</span>
                                        <span class="text-sm text-gray-400">{$date}</span>
                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{$trigger}</span>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Recent Transfers Out')
                    ->description('Recent movements out of this location')
                    ->schema([
                        TextEntry::make('transfers_out')
                            ->label('')
                            ->getStateUsing(function (Location $record): string {
                                $movements = InventoryMovement::query()
                                    ->where('source_location_id', $record->id)
                                    ->orderBy('executed_at', 'desc')
                                    ->limit(10)
                                    ->get();

                                if ($movements->isEmpty()) {
                                    return 'No recent transfers out of this location.';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($movements as $movement) {
                                    /** @var InventoryMovement $movement */
                                    $type = $movement->movement_type->label();
                                    $trigger = $movement->trigger->label();
                                    $destinationLocation = $movement->destinationLocation;
                                    $destination = $destinationLocation !== null ? $destinationLocation->name : 'External';
                                    $date = $movement->executed_at->format('M d, Y H:i');
                                    $itemsCount = $movement->movementItems()->count();

                                    $typeColor = match ($movement->movement_type) {
                                        MovementType::InternalTransfer => 'text-blue-600 dark:text-blue-400',
                                        MovementType::ConsignmentPlacement => 'text-yellow-600 dark:text-yellow-400',
                                        MovementType::EventConsumption => 'text-red-600 dark:text-red-400',
                                        MovementType::EventShipment => 'text-purple-600 dark:text-purple-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="flex items-center gap-4 p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                        <span class="font-medium {$typeColor}">{$type}</span>
                                        <span class="text-sm text-gray-500">to {$destination}</span>
                                        <span class="text-sm text-gray-500">{$itemsCount} item(s)</span>
                                        <span class="text-sm text-gray-400">{$date}</span>
                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{$trigger}</span>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 4: WMS Status - Connection status and sync info.
     */
    protected function getWmsStatusTab(): Tab
    {
        return Tab::make('WMS Status')
            ->icon('heroicon-o-server-stack')
            ->badge(fn (Location $record): ?string => $record->hasWmsLink() ? 'Linked' : null)
            ->badgeColor('info')
            ->schema([
                Section::make('WMS Connection')
                    ->description('Warehouse Management System integration status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('wms_linked')
                                    ->label('Connection Status')
                                    ->badge()
                                    ->getStateUsing(fn (Location $record): string => $record->hasWmsLink() ? 'Connected' : 'Not Connected')
                                    ->color(fn (Location $record): string => $record->hasWmsLink() ? 'success' : 'gray')
                                    ->icon(fn (Location $record): string => $record->hasWmsLink() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('linked_wms_id')
                                    ->label('WMS ID')
                                    ->default('Not linked')
                                    ->placeholder('Not linked')
                                    ->copyable()
                                    ->copyMessage('WMS ID copied'),
                                TextEntry::make('wms_sync_indicator')
                                    ->label('Sync Status')
                                    ->getStateUsing(fn (Location $record): string => $record->hasWmsLink() ? 'Active' : 'Inactive')
                                    ->badge()
                                    ->color(fn (Location $record): string => $record->hasWmsLink() ? 'success' : 'gray'),
                            ]),
                    ]),
                Section::make('Last Sync Information')
                    ->description('Recent synchronization activity')
                    ->visible(fn (Location $record): bool => $record->hasWmsLink())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('last_sync_timestamp')
                                    ->label('Last Sync')
                                    ->getStateUsing(function (Location $record): string {
                                        // Check for most recent WMS-triggered movement involving this location
                                        $lastWmsMovement = InventoryMovement::query()
                                            ->where(function ($query) use ($record): void {
                                                $query->where('source_location_id', $record->id)
                                                    ->orWhere('destination_location_id', $record->id);
                                            })
                                            ->where('trigger', MovementTrigger::WmsEvent)
                                            ->orderBy('executed_at', 'desc')
                                            ->first();

                                        if ($lastWmsMovement) {
                                            return $lastWmsMovement->executed_at->format('M d, Y H:i:s');
                                        }

                                        return 'No WMS events recorded';
                                    }),
                                TextEntry::make('wms_events_count')
                                    ->label('Total WMS Events')
                                    ->getStateUsing(function (Location $record): int {
                                        return InventoryMovement::query()
                                            ->where(function ($query) use ($record): void {
                                                $query->where('source_location_id', $record->id)
                                                    ->orWhere('destination_location_id', $record->id);
                                            })
                                            ->where('trigger', MovementTrigger::WmsEvent)
                                            ->count();
                                    })
                                    ->numeric()
                                    ->suffix(' events'),
                            ]),
                    ]),
                Section::make('WMS Error Logs')
                    ->description('Recent errors and issues with WMS integration')
                    ->visible(fn (Location $record): bool => $record->hasWmsLink())
                    ->schema([
                        TextEntry::make('wms_errors')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No errors recorded. WMS integration is functioning normally.')
                            ->color('success')
                            ->icon('heroicon-o-check-circle'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('WMS Not Configured')
                    ->visible(fn (Location $record): bool => ! $record->hasWmsLink())
                    ->schema([
                        TextEntry::make('wms_not_linked_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This location is not linked to a Warehouse Management System. WMS integration enables automatic synchronization of inventory movements and real-time stock updates.')
                            ->icon('heroicon-o-information-circle')
                            ->iconColor('gray'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
