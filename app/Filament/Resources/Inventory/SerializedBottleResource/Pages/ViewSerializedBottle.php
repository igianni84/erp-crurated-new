<?php

namespace App\Filament\Resources\Inventory\SerializedBottleResource\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\SerializedBottleResource;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\MovementItem;
use App\Models\Inventory\SerializedBottle;
use App\Models\Pim\Format;
use App\Models\Pim\WineVariant;
use App\Services\Inventory\MovementService;
use App\Services\Inventory\SerializationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ViewSerializedBottle extends ViewRecord
{
    protected static string $resource = SerializedBottleResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var SerializedBottle $record */
        $record = $this->record;

        return "Bottle: {$record->serial_number}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getImmutabilityNoticeSection(),
                Tabs::make('Bottle Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getLocationCustodyTab(),
                        $this->getProvenanceTab(),
                        $this->getMovementsTab(),
                        $this->getFulfillmentStatusTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Notice section highlighting that bottle records are immutable.
     */
    protected function getImmutabilityNoticeSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('immutability_notice')
                    ->label('')
                    ->getStateUsing(fn (): string => 'This bottle record is IMMUTABLE. Serial number and allocation lineage cannot be modified. All changes are recorded as movements.')
                    ->icon('heroicon-o-lock-closed')
                    ->iconColor('info')
                    ->color('gray'),
                TextEntry::make('mis_serialization_notice')
                    ->label('')
                    ->visible(fn (SerializedBottle $record): bool => $record->isMisSerialized())
                    ->getStateUsing(function (SerializedBottle $record): string {
                        $linkedBottle = $record->linkedBottle;
                        $linkedSerial = $linkedBottle !== null ? $linkedBottle->serial_number : 'Unknown';

                        return "⚠️ MIS-SERIALIZED: This record has been flagged as mis-serialized and is LOCKED. A corrective record ({$linkedSerial}) has been created with the correct data.";
                    })
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->color('danger'),
                TextEntry::make('correction_origin_notice')
                    ->label('')
                    ->visible(fn (SerializedBottle $record): bool => $record->hasCorrectionReference() && ! $record->isMisSerialized())
                    ->getStateUsing(function (SerializedBottle $record): string {
                        $linkedBottle = $record->linkedBottle;
                        $linkedSerial = $linkedBottle !== null ? $linkedBottle->serial_number : 'Unknown';

                        return "ℹ️ CORRECTIVE RECORD: This bottle was created to correct a mis-serialization error in bottle {$linkedSerial}.";
                    })
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('info')
                    ->color('info'),
            ])
            ->columnSpanFull();
    }

    /**
     * Tab 1: Overview - identity, physical attributes, allocation lineage.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-identification')
            ->schema([
                Section::make('Bottle Identity')
                    ->description('Unique identification and wine information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('serial_number')
                                        ->label('Serial Number')
                                        ->copyable()
                                        ->copyMessage('Serial number copied')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large)
                                        ->icon('heroicon-o-qr-code'),
                                    TextEntry::make('id')
                                        ->label('Internal ID')
                                        ->copyable()
                                        ->copyMessage('ID copied')
                                        ->color('gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('wine_display')
                                        ->label('Wine')
                                        ->getStateUsing(function (SerializedBottle $record): string {
                                            $wineVariant = $record->wineVariant;
                                            if ($wineVariant === null) {
                                                return 'Unknown Wine';
                                            }
                                            $wineMaster = $wineVariant->wineMaster;
                                            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                            $vintage = $wineVariant->vintage_year ?? 'NV';

                                            return "{$wineName} {$vintage}";
                                        })
                                        ->weight(FontWeight::Bold)
                                        ->icon('heroicon-o-beaker'),
                                    TextEntry::make('format.name')
                                        ->label('Format')
                                        ->placeholder('Standard')
                                        ->icon('heroicon-o-cube'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('state')
                                        ->label('Current State')
                                        ->badge()
                                        ->formatStateUsing(fn (BottleState $state): string => $state->label())
                                        ->color(fn (BottleState $state): string => $state->color())
                                        ->icon(fn (BottleState $state): string => $state->icon())
                                        ->size(TextEntry\TextEntrySize::Large),
                                    TextEntry::make('ownership_type')
                                        ->label('Ownership')
                                        ->badge()
                                        ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                                        ->color(fn (OwnershipType $state): string => $state->color())
                                        ->icon(fn (OwnershipType $state): string => $state->icon()),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Allocation Lineage')
                    ->description('Immutable allocation reference - cannot be changed after serialization')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('allocation_lineage_display')
                                    ->label('Allocation')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        $allocation = $record->allocation;
                                        if ($allocation === null) {
                                            return 'N/A';
                                        }

                                        return $allocation->getBottleSkuLabel();
                                    })
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-link')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('allocation_id')
                                    ->label('Allocation ID')
                                    ->copyable()
                                    ->copyMessage('Allocation ID copied')
                                    ->icon('heroicon-o-lock-closed')
                                    ->helperText('This ID is immutable and was assigned at serialization'),
                            ]),
                        TextEntry::make('allocation_immutability_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'The allocation lineage was permanently assigned when this bottle was serialized. It links this bottle to its commercial allocation and cannot be modified. Any attempt to substitute bottles across allocations is blocked system-wide.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('success')
                            ->color('gray'),
                    ])
                    ->extraAttributes(['class' => 'bg-primary-50 dark:bg-primary-900/10']),
                Section::make('Physical Attributes')
                    ->description('Physical characteristics and container information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('format.capacity_ml')
                                    ->label('Capacity')
                                    ->suffix(' ml')
                                    ->placeholder('Standard (750ml)'),
                                TextEntry::make('case_status')
                                    ->label('In Case')
                                    ->getStateUsing(fn (SerializedBottle $record): string => $record->isInCase() ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn (SerializedBottle $record): string => $record->isInCase() ? 'info' : 'gray')
                                    ->icon(fn (SerializedBottle $record): string => $record->isInCase()
                                        ? 'heroicon-o-archive-box'
                                        : 'heroicon-o-minus'),
                                TextEntry::make('case_id_display')
                                    ->label('Case ID')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        if (! $record->isInCase()) {
                                            return 'Not in a case';
                                        }

                                        return "#{$record->case_id}";
                                    })
                                    ->url(function (SerializedBottle $record): ?string {
                                        if (! $record->isInCase()) {
                                            return null;
                                        }

                                        return route('filament.admin.resources.inventory.cases.view', ['record' => $record->case_id]);
                                    })
                                    ->color(fn (SerializedBottle $record): string => $record->isInCase() ? 'primary' : 'gray'),
                                TextEntry::make('physical_presence')
                                    ->label('Physical Status')
                                    ->getStateUsing(fn (SerializedBottle $record): string => $record->isPhysicallyPresent()
                                        ? 'Physically Present'
                                        : 'Not Present')
                                    ->badge()
                                    ->color(fn (SerializedBottle $record): string => $record->isPhysicallyPresent()
                                        ? 'success'
                                        : 'gray')
                                    ->icon(fn (SerializedBottle $record): string => $record->isPhysicallyPresent()
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-x-circle'),
                            ]),
                    ])
                    ->collapsible(),
                Section::make('Serialization Information')
                    ->description('When and how this bottle was serialized')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('serialized_at')
                                    ->label('Serialized At')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('serializedByUser.name')
                                    ->label('Serialized By')
                                    ->placeholder('System')
                                    ->icon('heroicon-o-user'),
                                TextEntry::make('inbound_batch_link')
                                    ->label('Source Batch')
                                    ->getStateUsing(fn (SerializedBottle $record): string => 'Batch #'.substr($record->inbound_batch_id, 0, 8).'...')
                                    ->url(fn (SerializedBottle $record): string => route('filament.admin.resources.inventory.inbound-batches.view', ['record' => $record->inbound_batch_id]))
                                    ->color('primary')
                                    ->icon('heroicon-o-inbox-arrow-down'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 2: Location & Custody - current location, custody holder, ownership type.
     */
    protected function getLocationCustodyTab(): Tab
    {
        return Tab::make('Location & Custody')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Section::make('Current Location')
                    ->description('Where this bottle is physically stored')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('currentLocation.name')
                                    ->label('Location Name')
                                    ->icon('heroicon-o-map-pin')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('currentLocation.location_type')
                                    ->label('Location Type')
                                    ->badge()
                                    ->formatStateUsing(fn (SerializedBottle $record): string => $record->currentLocation?->location_type?->label() ?? 'Unknown')
                                    ->color(fn (SerializedBottle $record): string => $record->currentLocation?->location_type?->color() ?? 'gray'),
                                TextEntry::make('currentLocation.country')
                                    ->label('Country')
                                    ->icon('heroicon-o-globe-alt')
                                    ->placeholder('Not specified'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('currentLocation.address')
                                    ->label('Address')
                                    ->placeholder('Not specified')
                                    ->columnSpan(1),
                                TextEntry::make('current_location_id')
                                    ->label('Location ID')
                                    ->copyable()
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make('Custody Information')
                    ->description('Who has physical custody of this bottle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('custody_holder')
                                    ->label('Custody Holder')
                                    ->placeholder('Crurated (default)')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-user-circle'),
                                TextEntry::make('custody_status')
                                    ->label('Custody Status')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        if ($record->custody_holder === null || $record->custody_holder === '') {
                                            return 'In Crurated custody';
                                        }

                                        return "In custody of: {$record->custody_holder}";
                                    })
                                    ->badge()
                                    ->color(function (SerializedBottle $record): string {
                                        if ($record->custody_holder === null || $record->custody_holder === '') {
                                            return 'success';
                                        }

                                        return 'warning';
                                    }),
                            ]),
                    ]),
                Section::make('Ownership')
                    ->description('Legal ownership classification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ownership_type')
                                    ->label('Ownership Type')
                                    ->badge()
                                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                                    ->color(fn (OwnershipType $state): string => $state->color())
                                    ->icon(fn (OwnershipType $state): string => $state->icon())
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('ownership_info')
                                    ->label('')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        return match ($record->ownership_type) {
                                            OwnershipType::CururatedOwned => 'Crurated owns this bottle. It can be consumed for events.',
                                            OwnershipType::InCustody => 'Bottle is held in custody. Cannot be consumed for events without special permission.',
                                            OwnershipType::ThirdPartyOwned => 'Bottle is owned by a third party. Special handling required.',
                                        };
                                    })
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray'),
                            ]),
                        TextEntry::make('can_consume')
                            ->label('Event Consumption')
                            ->getStateUsing(fn (SerializedBottle $record): string => $record->canConsumeForEvents()
                                ? 'Eligible for event consumption'
                                : 'Not eligible for event consumption')
                            ->badge()
                            ->color(fn (SerializedBottle $record): string => $record->canConsumeForEvents() ? 'success' : 'danger')
                            ->icon(fn (SerializedBottle $record): string => $record->canConsumeForEvents()
                                ? 'heroicon-o-check-circle'
                                : 'heroicon-o-x-circle'),
                    ]),
            ]);
    }

    /**
     * Tab 3: Provenance - inbound event link, all movements, NFT reference/link.
     */
    protected function getProvenanceTab(): Tab
    {
        return Tab::make('Provenance')
            ->icon('heroicon-o-document-check')
            ->schema([
                Section::make('Origin')
                    ->description('How this bottle entered the inventory system')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('inbound_batch_info')
                                    ->label('Inbound Batch')
                                    ->getStateUsing(fn (SerializedBottle $record): string => 'Batch #'.substr($record->inbound_batch_id, 0, 8).'...')
                                    ->url(fn (SerializedBottle $record): string => route('filament.admin.resources.inventory.inbound-batches.view', ['record' => $record->inbound_batch_id]))
                                    ->color('primary')
                                    ->icon('heroicon-o-inbox-arrow-down')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('inboundBatch.source_type')
                                    ->label('Source Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->color(fn (string $state): string => match ($state) {
                                        'producer' => 'success',
                                        'supplier' => 'info',
                                        'transfer' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('inboundBatch.received_date')
                                    ->label('Received Date')
                                    ->date()
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),
                Section::make('NFT Provenance')
                    ->description('Blockchain-based provenance verification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('nft_reference')
                                    ->label('NFT Reference')
                                    ->getStateUsing(fn (SerializedBottle $record): string => $record->hasNft()
                                        ? $record->nft_reference ?? 'Unknown'
                                        : 'Pending minting...')
                                    ->copyable(fn (SerializedBottle $record): bool => $record->hasNft())
                                    ->copyMessage('NFT reference copied')
                                    ->badge()
                                    ->color(fn (SerializedBottle $record): string => $record->hasNft() ? 'success' : 'warning')
                                    ->icon(fn (SerializedBottle $record): string => $record->hasNft()
                                        ? 'heroicon-o-check-badge'
                                        : 'heroicon-o-clock')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('nft_minted_at')
                                    ->label('Minted At')
                                    ->dateTime()
                                    ->placeholder('Not yet minted')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        TextEntry::make('blockchain_explorer')
                            ->label('Blockchain Explorer')
                            ->getStateUsing(function (SerializedBottle $record): string {
                                if (! $record->hasNft()) {
                                    return 'NFT not yet minted. Blockchain link will be available after minting.';
                                }

                                return 'View on blockchain explorer';
                            })
                            ->url(function (SerializedBottle $record): ?string {
                                if (! $record->hasNft() || $record->nft_reference === null) {
                                    return null;
                                }

                                // Placeholder blockchain explorer URL
                                // In production, this would be configured per blockchain network
                                return "https://explorer.example.com/token/{$record->nft_reference}";
                            })
                            ->openUrlInNewTab()
                            ->icon(fn (SerializedBottle $record): string => $record->hasNft()
                                ? 'heroicon-o-arrow-top-right-on-square'
                                : 'heroicon-o-clock')
                            ->color(fn (SerializedBottle $record): string => $record->hasNft() ? 'primary' : 'gray'),
                    ])
                    ->extraAttributes(fn (SerializedBottle $record): array => $record->hasNft()
                        ? ['class' => 'bg-success-50 dark:bg-success-900/10']
                        : ['class' => 'bg-warning-50 dark:bg-warning-900/10']),
                Section::make('Provenance Timeline')
                    ->description('Complete history of this bottle\'s journey')
                    ->schema([
                        TextEntry::make('provenance_timeline')
                            ->label('')
                            ->getStateUsing(function (SerializedBottle $record): string {
                                $timeline = [];

                                // 1. Origin event (serialization)
                                $serializedAt = $record->serialized_at->format('M d, Y H:i');
                                $serializedBy = $record->serializedByUser;
                                $serializedByName = $serializedBy !== null ? $serializedBy->name : 'System';
                                $inboundBatch = $record->inboundBatch;
                                $sourceType = $inboundBatch !== null ? ucfirst($inboundBatch->source_type) : 'Unknown';

                                $timeline[] = [
                                    'icon' => 'check-badge',
                                    'color' => 'success',
                                    'title' => 'Serialized',
                                    'description' => "Bottle serialized from {$sourceType} inbound batch",
                                    'date' => $serializedAt,
                                    'user' => $serializedByName,
                                ];

                                // 2. Get movements via MovementItem relationship
                                $movementItems = MovementItem::where('serialized_bottle_id', $record->id)
                                    ->with(['inventoryMovement.sourceLocation', 'inventoryMovement.destinationLocation', 'inventoryMovement.executor'])
                                    ->orderBy('created_at', 'asc')
                                    ->get();

                                foreach ($movementItems as $item) {
                                    /** @var MovementItem $item */
                                    $movement = $item->inventoryMovement;
                                    if ($movement === null) {
                                        continue;
                                    }

                                    $icon = match ($movement->movement_type) {
                                        MovementType::InternalTransfer => 'arrows-right-left',
                                        MovementType::ConsignmentPlacement => 'arrow-up-tray',
                                        MovementType::ConsignmentReturn => 'arrow-down-tray',
                                        MovementType::EventShipment => 'truck',
                                        MovementType::EventConsumption => 'fire',
                                    };

                                    $color = match ($movement->movement_type) {
                                        MovementType::InternalTransfer => 'info',
                                        MovementType::ConsignmentPlacement => 'warning',
                                        MovementType::ConsignmentReturn => 'success',
                                        MovementType::EventShipment => 'primary',
                                        MovementType::EventConsumption => 'danger',
                                    };

                                    $source = $movement->sourceLocation;
                                    $dest = $movement->destinationLocation;
                                    $sourceName = $source !== null ? $source->name : 'Unknown';
                                    $destName = $dest !== null ? $dest->name : 'Unknown';

                                    $description = match ($movement->movement_type) {
                                        MovementType::InternalTransfer => "Transferred from {$sourceName} to {$destName}",
                                        MovementType::ConsignmentPlacement => "Placed in consignment at {$destName}",
                                        MovementType::ConsignmentReturn => "Returned from consignment at {$sourceName}",
                                        MovementType::EventShipment => "Shipped for event to {$destName}",
                                        MovementType::EventConsumption => 'Consumed at event',
                                    };

                                    $executor = $movement->executor;
                                    $executorName = $executor !== null ? $executor->name : 'System';

                                    $timeline[] = [
                                        'icon' => $icon,
                                        'color' => $color,
                                        'title' => $movement->movement_type->label(),
                                        'description' => $description,
                                        'date' => $movement->executed_at->format('M d, Y H:i'),
                                        'user' => $executorName,
                                    ];
                                }

                                // 3. NFT minting event (if applicable)
                                if ($record->hasNft() && $record->nft_minted_at !== null) {
                                    $timeline[] = [
                                        'icon' => 'shield-check',
                                        'color' => 'success',
                                        'title' => 'NFT Minted',
                                        'description' => 'Provenance NFT minted on blockchain',
                                        'date' => $record->nft_minted_at->format('M d, Y H:i'),
                                        'user' => 'System',
                                    ];
                                }

                                // Build HTML timeline (always has at least the serialization event)
                                $html = '<div class="space-y-4">';
                                foreach ($timeline as $event) {
                                    /** @var string $eventColor */
                                    $eventColor = $event['color'];
                                    $colorClass = match ($eventColor) {
                                        'success' => 'bg-success-100 dark:bg-success-900/30 border-success-200 dark:border-success-800 text-success-700 dark:text-success-300',
                                        'info' => 'bg-info-100 dark:bg-info-900/30 border-info-200 dark:border-info-800 text-info-700 dark:text-info-300',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 border-warning-200 dark:border-warning-800 text-warning-700 dark:text-warning-300',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 border-danger-200 dark:border-danger-800 text-danger-700 dark:text-danger-300',
                                        'primary' => 'bg-primary-100 dark:bg-primary-900/30 border-primary-200 dark:border-primary-800 text-primary-700 dark:text-primary-300',
                                        default => 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300',
                                    };

                                    $title = e($event['title']);
                                    $description = e($event['description']);
                                    $date = e($event['date']);
                                    $user = e($event['user']);

                                    $html .= <<<HTML
                                    <div class="flex items-start gap-4 p-4 rounded-lg border {$colorClass}">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-gray-900">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-semibold">{$title}</span>
                                                <span class="text-sm opacity-75">{$date}</span>
                                            </div>
                                            <p class="mt-1 text-sm opacity-90">{$description}</p>
                                            <p class="mt-1 text-xs opacity-75">By: {$user}</p>
                                        </div>
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
     * Tab 4: Movements - full movement history (read-only ledger).
     */
    protected function getMovementsTab(): Tab
    {
        return Tab::make('Movements')
            ->icon('heroicon-o-arrow-path')
            ->badge(function (SerializedBottle $record): ?string {
                $count = MovementItem::where('serialized_bottle_id', $record->id)->count();

                return $count > 0 ? (string) $count : null;
            })
            ->badgeColor('info')
            ->schema([
                Section::make('Movement History')
                    ->description('Complete, immutable record of all movements for this bottle')
                    ->schema([
                        TextEntry::make('movement_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Movements are append-only records. They cannot be modified or deleted. This ensures complete audit trail integrity.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('success')
                            ->color('gray'),
                        TextEntry::make('movement_history')
                            ->label('')
                            ->getStateUsing(function (SerializedBottle $record): string {
                                $movementItems = MovementItem::where('serialized_bottle_id', $record->id)
                                    ->with(['inventoryMovement.sourceLocation', 'inventoryMovement.destinationLocation', 'inventoryMovement.executor'])
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                if ($movementItems->isEmpty()) {
                                    return '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                                        <p class="text-gray-500">No movements recorded for this bottle.</p>
                                        <p class="text-sm text-gray-400 mt-1">The bottle has remained in its original location since serialization.</p>
                                    </div>';
                                }

                                $html = '<div class="space-y-3">';

                                foreach ($movementItems as $item) {
                                    /** @var MovementItem $item */
                                    $movement = $item->inventoryMovement;
                                    if ($movement === null) {
                                        continue;
                                    }

                                    $typeLabel = $movement->movement_type->label();
                                    $typeColor = $movement->movement_type->color();
                                    $typeIcon = $movement->movement_type->icon();

                                    $triggerLabel = $movement->trigger->label();
                                    $triggerColor = match ($movement->trigger) {
                                        MovementTrigger::WmsEvent => 'info',
                                        MovementTrigger::ErpOperator => 'success',
                                        MovementTrigger::SystemAutomatic => 'gray',
                                    };

                                    $source = $movement->sourceLocation;
                                    $dest = $movement->destinationLocation;
                                    $sourceName = $source !== null ? e($source->name) : '—';
                                    $destName = $dest !== null ? e($dest->name) : '—';

                                    $executor = $movement->executor;
                                    $executorName = $executor !== null ? e($executor->name) : 'System';
                                    $executedAt = $movement->executed_at->format('M d, Y H:i:s');

                                    $reason = $movement->reason !== null ? e($movement->reason) : '—';
                                    $wmsEventId = $movement->wms_event_id !== null ? e($movement->wms_event_id) : '—';
                                    $custodyChanged = $movement->custody_changed ? 'Yes' : 'No';
                                    $movementId = substr($movement->id, 0, 8).'...';

                                    $colorClass = match ($typeColor) {
                                        'success' => 'border-success-200 dark:border-success-800',
                                        'info' => 'border-info-200 dark:border-info-800',
                                        'warning' => 'border-warning-200 dark:border-warning-800',
                                        'danger' => 'border-danger-200 dark:border-danger-800',
                                        default => 'border-gray-200 dark:border-gray-700',
                                    };

                                    $badgeClass = match ($typeColor) {
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300',
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-700 dark:text-info-300',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-300',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                                    };

                                    $triggerBadgeClass = match ($triggerColor) {
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-600 dark:text-info-400',
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-600 dark:text-success-400',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="p-4 bg-white dark:bg-gray-900 rounded-lg border-l-4 {$colorClass} shadow-sm">
                                        <div class="flex items-start justify-between mb-3">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 text-sm font-medium rounded {$badgeClass}">{$typeLabel}</span>
                                                <span class="px-2 py-1 text-xs rounded {$triggerBadgeClass}">{$triggerLabel}</span>
                                            </div>
                                            <span class="text-sm text-gray-500">{$executedAt}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">From:</span>
                                                <span class="font-medium ml-1">{$sourceName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">To:</span>
                                                <span class="font-medium ml-1">{$destName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Executed by:</span>
                                                <span class="ml-1">{$executorName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Custody Changed:</span>
                                                <span class="ml-1">{$custodyChanged}</span>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 text-sm">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <span class="text-gray-500">Reason:</span>
                                                    <span class="ml-1">{$reason}</span>
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    Movement #{$movementId}
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-gray-500">WMS Event:</span>
                                                <span class="ml-1 text-xs font-mono">{$wmsEventId}</span>
                                            </div>
                                        </div>
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
     * Tab 5: Fulfillment Status - reserved/shipped (fed from Module C), NO customer identity shown.
     */
    protected function getFulfillmentStatusTab(): Tab
    {
        return Tab::make('Fulfillment Status')
            ->icon('heroicon-o-truck')
            ->schema([
                Section::make('Fulfillment Information')
                    ->description('Read-only fulfillment status from Module C (no customer identity shown)')
                    ->schema([
                        TextEntry::make('privacy_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Customer identity is not displayed in this view for privacy compliance. Contact Module C for customer-specific information if required.')
                            ->icon('heroicon-o-shield-exclamation')
                            ->iconColor('warning')
                            ->color('gray'),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('fulfillment_availability')
                                    ->label('Availability')
                                    ->getStateUsing(fn (SerializedBottle $record): string => $record->isAvailableForFulfillment()
                                        ? 'Available for Fulfillment'
                                        : 'Not Available')
                                    ->badge()
                                    ->color(fn (SerializedBottle $record): string => $record->isAvailableForFulfillment()
                                        ? 'success'
                                        : 'danger')
                                    ->icon(fn (SerializedBottle $record): string => $record->isAvailableForFulfillment()
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-x-circle')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('state')
                                    ->label('Current State')
                                    ->badge()
                                    ->formatStateUsing(fn (BottleState $state): string => $state->label())
                                    ->color(fn (BottleState $state): string => $state->color())
                                    ->icon(fn (BottleState $state): string => $state->icon()),
                                TextEntry::make('terminal_state')
                                    ->label('Terminal State')
                                    ->getStateUsing(fn (SerializedBottle $record): string => $record->isInTerminalState()
                                        ? 'Yes (final state)'
                                        : 'No')
                                    ->badge()
                                    ->color(fn (SerializedBottle $record): string => $record->isInTerminalState()
                                        ? 'gray'
                                        : 'info'),
                            ]),
                    ]),
                Section::make('State Details')
                    ->description('Explanation of current fulfillment state')
                    ->schema([
                        TextEntry::make('state_explanation')
                            ->label('')
                            ->getStateUsing(function (SerializedBottle $record): string {
                                return match ($record->state) {
                                    BottleState::Stored => 'This bottle is stored in inventory and available for fulfillment. It can be reserved for picking when a voucher is redeemed.',
                                    BottleState::ReservedForPicking => 'This bottle has been reserved for an order and is awaiting picking. It is not available for other fulfillment requests.',
                                    BottleState::Shipped => 'This bottle has been shipped to fulfill an order. It is no longer in physical inventory.',
                                    BottleState::Consumed => 'This bottle was consumed (e.g., at an event). It is no longer available for fulfillment.',
                                    BottleState::Destroyed => 'This bottle was destroyed (damage, leakage, etc.). It is no longer available for fulfillment.',
                                    BottleState::Missing => 'This bottle is marked as missing. It cannot be used for fulfillment until located.',
                                    BottleState::MisSerialized => 'This bottle was flagged as mis-serialized. A corrective record has been created. This record is locked for audit purposes.',
                                };
                            })
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
                Section::make('Fulfillment Constraints')
                    ->description('Factors affecting fulfillment eligibility')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('allocation_constraint')
                                    ->label('Allocation Lineage')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        $allocation = $record->allocation;
                                        if ($allocation === null) {
                                            return 'No allocation - fulfillment may be restricted';
                                        }

                                        return $allocation->getBottleSkuLabel();
                                    })
                                    ->helperText('Bottles can only fulfill vouchers from the same allocation lineage')
                                    ->icon('heroicon-o-link')
                                    ->color('primary'),
                                TextEntry::make('ownership_constraint')
                                    ->label('Ownership Type')
                                    ->getStateUsing(function (SerializedBottle $record): string {
                                        return match ($record->ownership_type) {
                                            OwnershipType::CururatedOwned => 'Crurated Owned - Standard fulfillment',
                                            OwnershipType::InCustody => 'In Custody - May have restrictions',
                                            OwnershipType::ThirdPartyOwned => 'Third Party - Special handling required',
                                        };
                                    })
                                    ->icon('heroicon-o-building-office')
                                    ->color('gray'),
                            ]),
                        TextEntry::make('substitution_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Cross-allocation substitution is blocked system-wide. This bottle can only fulfill vouchers that match its allocation lineage.')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->iconColor('warning')
                            ->color('warning'),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getMarkAsDamagedAction(),
            $this->getMarkAsMissingAction(),
            $this->getFlagAsMisSerializedAction(),
        ];
    }

    /**
     * Action to mark a bottle as damaged/destroyed.
     *
     * US-B027: Mark Bottle as Damaged/Destroyed
     *
     * - Requires confirmation of physical destruction
     * - Captures reason (breakage/leakage/contamination)
     * - Optional evidence field
     * - Changes state to DESTROYED
     * - Creates audit trail via movement record
     * - Bottle remains visible for audit (never deleted)
     * - Destroyed bottles cannot be selected by Module C
     */
    protected function getMarkAsDamagedAction(): Actions\Action
    {
        return Actions\Action::make('markAsDamaged')
            ->label('Mark as Damaged')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Mark Bottle as Damaged/Destroyed')
            ->modalDescription(fn (SerializedBottle $record): string => "You are about to mark bottle {$record->serial_number} as destroyed. This action is IRREVERSIBLE.")
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->visible(fn (SerializedBottle $record): bool => ! $record->isInTerminalState())
            ->form([
                Forms\Components\Section::make('Destruction Confirmation')
                    ->description('This action permanently changes the bottle state to DESTROYED')
                    ->schema([
                        Forms\Components\Placeholder::make('warning')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-danger-700 dark:text-danger-300">Irreversible Action</h4>
                                            <ul class="mt-2 text-sm text-danger-600 dark:text-danger-400 list-disc list-inside space-y-1">
                                                <li>The bottle will be marked as <strong>DESTROYED</strong></li>
                                                <li>It will no longer be available for fulfillment</li>
                                                <li>Physical inventory count will be reduced</li>
                                                <li>The bottle record will remain visible for audit purposes</li>
                                                <li>Provenance and history will be preserved</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ')),
                        Forms\Components\Checkbox::make('confirm_destruction')
                            ->label('I confirm that this bottle has been physically destroyed or is no longer usable')
                            ->required()
                            ->accepted()
                            ->validationMessages([
                                'accepted' => 'You must confirm the physical destruction to proceed.',
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50/50 dark:bg-danger-900/10']),
                Forms\Components\Section::make('Destruction Details')
                    ->description('Provide details about the damage/destruction')
                    ->schema([
                        Forms\Components\Select::make('reason')
                            ->label('Reason for Destruction')
                            ->options([
                                'breakage' => 'Breakage - Physical damage to the bottle',
                                'leakage' => 'Leakage - Cork failure or seal compromise',
                                'contamination' => 'Contamination - Wine quality issues',
                                'other' => 'Other - Please specify in evidence',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Select the primary reason for destruction'),
                        Forms\Components\Textarea::make('evidence')
                            ->label('Evidence / Additional Notes')
                            ->placeholder('Describe the damage, circumstances, or any relevant details...')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Optional: Provide any evidence or additional context'),
                    ]),
            ])
            ->action(function (array $data, SerializedBottle $record): void {
                try {
                    /** @var MovementService $movementService */
                    $movementService = app(MovementService::class);

                    // Get the reason label
                    $reasonLabels = [
                        'breakage' => 'Breakage',
                        'leakage' => 'Leakage',
                        'contamination' => 'Contamination',
                        'other' => 'Other',
                    ];
                    /** @var string $reasonKey */
                    $reasonKey = $data['reason'];
                    $reason = $reasonLabels[$reasonKey] ?? 'Unknown';
                    /** @var string|null $evidence */
                    $evidence = $data['evidence'] ?? null;

                    // Record the destruction via MovementService
                    $movementService->recordDestruction(
                        bottle: $record,
                        reason: $reason,
                        executor: auth()->user(),
                        evidence: $evidence
                    );

                    // Create an inventory exception for audit trail
                    InventoryException::create([
                        'exception_type' => 'bottle_destroyed',
                        'serialized_bottle_id' => $record->id,
                        'reason' => "Bottle destroyed: {$reason}".($evidence !== null && $evidence !== '' ? ". Evidence: {$evidence}" : ''),
                        'created_by' => auth()->id(),
                        'resolution' => 'Bottle marked as destroyed',
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Bottle Marked as Destroyed')
                        ->body("Bottle {$record->serial_number} has been marked as destroyed. The record remains visible for audit purposes.")
                        ->persistent()
                        ->send();

                    // Refresh the record to show updated state
                    $this->refreshFormData(['state']);

                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Action to mark a bottle as missing.
     *
     * US-B028: Mark Bottle as Missing
     *
     * - Captures reason for marking as missing
     * - Captures last known custody holder
     * - Captures agreement reference (for consignment)
     * - Changes state to MISSING
     * - Inventory reduced (bottle locked from fulfillment)
     * - Creates audit trail via movement record and exception
     * - Missing bottles remain visible forever
     * - Used in loss & compliance reporting
     */
    protected function getMarkAsMissingAction(): Actions\Action
    {
        return Actions\Action::make('markAsMissing')
            ->label('Mark as Missing')
            ->icon('heroicon-o-question-mark-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Mark Bottle as Missing')
            ->modalDescription(fn (SerializedBottle $record): string => "You are about to mark bottle {$record->serial_number} as MISSING. This action indicates the bottle cannot be located.")
            ->modalIcon('heroicon-o-question-mark-circle')
            ->modalIconColor('warning')
            ->visible(fn (SerializedBottle $record): bool => ! $record->isInTerminalState())
            ->form([
                Forms\Components\Section::make('Missing Bottle Confirmation')
                    ->description('Mark this bottle as missing when it cannot be located')
                    ->schema([
                        Forms\Components\Placeholder::make('warning')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-warning-600 dark:text-warning-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-warning-700 dark:text-warning-300">Marking as Missing</h4>
                                            <ul class="mt-2 text-sm text-warning-600 dark:text-warning-400 list-disc list-inside space-y-1">
                                                <li>The bottle will be marked as <strong>MISSING</strong></li>
                                                <li>It will be locked from fulfillment operations</li>
                                                <li>Physical inventory count will be reduced</li>
                                                <li>The record will remain visible for compliance reporting</li>
                                                <li>Used for loss tracking and audit purposes</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ')),
                        Forms\Components\Checkbox::make('confirm_missing')
                            ->label('I confirm that this bottle cannot be located and should be marked as missing')
                            ->required()
                            ->accepted()
                            ->validationMessages([
                                'accepted' => 'You must confirm the bottle is missing to proceed.',
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-warning-50/50 dark:bg-warning-900/10']),
                Forms\Components\Section::make('Missing Details')
                    ->description('Provide details about the missing bottle')
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->placeholder('Describe why this bottle is being marked as missing...')
                            ->rows(2)
                            ->required()
                            ->maxLength(500)
                            ->helperText('Explain the circumstances (e.g., consignment lost, inventory discrepancy, etc.)'),
                        Forms\Components\TextInput::make('last_known_custody')
                            ->label('Last Known Custody')
                            ->placeholder('e.g., Consignee name, warehouse section, etc.')
                            ->maxLength(255)
                            ->helperText('Who or where was the bottle last known to be?'),
                        Forms\Components\TextInput::make('agreement_reference')
                            ->label('Agreement Reference')
                            ->placeholder('e.g., Consignment agreement number')
                            ->maxLength(255)
                            ->helperText('Relevant agreement reference (for consignment situations)'),
                    ]),
            ])
            ->action(function (array $data, SerializedBottle $record): void {
                try {
                    /** @var MovementService $movementService */
                    $movementService = app(MovementService::class);

                    /** @var string $reason */
                    $reason = $data['reason'];
                    /** @var string|null $lastKnownCustody */
                    $lastKnownCustody = $data['last_known_custody'] ?? null;
                    /** @var string|null $agreementReference */
                    $agreementReference = $data['agreement_reference'] ?? null;

                    // Record the missing status via MovementService
                    $movementService->recordMissing(
                        bottle: $record,
                        reason: $reason,
                        executor: auth()->user(),
                        lastKnownCustody: $lastKnownCustody,
                        agreementReference: $agreementReference
                    );

                    // Build reason text for exception
                    $exceptionReason = "Bottle marked as missing: {$reason}";
                    if ($lastKnownCustody !== null && $lastKnownCustody !== '') {
                        $exceptionReason .= ". Last known custody: {$lastKnownCustody}";
                    }
                    if ($agreementReference !== null && $agreementReference !== '') {
                        $exceptionReason .= ". Agreement reference: {$agreementReference}";
                    }

                    // Create an inventory exception for audit trail and compliance reporting
                    InventoryException::create([
                        'exception_type' => 'bottle_missing',
                        'serialized_bottle_id' => $record->id,
                        'reason' => $exceptionReason,
                        'created_by' => auth()->id(),
                        // Not resolved - missing bottles stay open until found or written off
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Bottle Marked as Missing')
                        ->body("Bottle {$record->serial_number} has been marked as missing. The record remains visible for compliance and loss reporting.")
                        ->persistent()
                        ->send();

                    // Refresh the record to show updated state
                    $this->refreshFormData(['state']);

                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Action to flag a bottle as mis-serialized and create a corrective record.
     *
     * US-B029: Mis-serialization correction flow (admin-only)
     *
     * - Admin-only action
     * - Original bottle record flagged as MIS_SERIALIZED
     * - Original record locked (no further changes)
     * - Corrective record created with correct data
     * - Both records linked via correction_reference
     * - Original error remains visible
     * - Provenance integrity preserved (additive corrections)
     * - Full audit trail
     */
    protected function getFlagAsMisSerializedAction(): Actions\Action
    {
        return Actions\Action::make('flagAsMisSerialized')
            ->label('Flag as Mis-serialized')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Flag Bottle as Mis-serialized')
            ->modalDescription(fn (SerializedBottle $record): string => "You are about to flag bottle {$record->serial_number} as mis-serialized. This will lock the original record and create a new corrective record with the correct data.")
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->visible(function (SerializedBottle $record): bool {
                // Admin-only action
                /** @var \App\Models\User|null $user */
                $user = auth()->user();
                if ($user === null || ! $user->isAdmin()) {
                    return false;
                }

                // Cannot flag if already in terminal state
                return $record->canFlagAsMisSerialized();
            })
            ->form([
                Forms\Components\Section::make('Mis-serialization Confirmation')
                    ->description('This action is for correcting bottles serialized with incorrect data')
                    ->schema([
                        Forms\Components\Placeholder::make('warning')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-6 h-6 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-danger-700 dark:text-danger-300">Admin-Only Correction Flow</h4>
                                            <ul class="mt-2 text-sm text-danger-600 dark:text-danger-400 list-disc list-inside space-y-1">
                                                <li>The <strong>original bottle record</strong> will be flagged as <strong>MIS_SERIALIZED</strong></li>
                                                <li>The original record will be <strong>locked</strong> (no further changes allowed)</li>
                                                <li>A <strong>new corrective record</strong> will be created with the correct data you provide</li>
                                                <li>Both records will be <strong>linked via correction_reference</strong></li>
                                                <li>The original error <strong>remains visible</strong> for audit purposes</li>
                                                <li><strong>Provenance integrity is preserved</strong> (additive correction, not overwrite)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ')),
                        Forms\Components\Checkbox::make('confirm_mis_serialization')
                            ->label('I confirm this bottle was serialized with incorrect data and requires correction')
                            ->required()
                            ->accepted()
                            ->validationMessages([
                                'accepted' => 'You must confirm the mis-serialization to proceed.',
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50/50 dark:bg-danger-900/10']),
                Forms\Components\Section::make('Correction Reason')
                    ->description('Document why this bottle was mis-serialized')
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label('Mis-serialization Reason')
                            ->placeholder('Explain what was incorrect about the original serialization (e.g., wrong wine variant, wrong format, etc.)...')
                            ->rows(3)
                            ->required()
                            ->minLength(20)
                            ->maxLength(1000)
                            ->helperText('Minimum 20 characters. This will be recorded in the audit trail.'),
                    ]),
                Forms\Components\Section::make('Corrective Record Data')
                    ->description('Provide the CORRECT data for the new bottle record')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('correct_wine_variant_id')
                                    ->label('Correct Wine Variant')
                                    ->options(fn () => WineVariant::query()
                                        ->with('wineMaster')
                                        ->get()
                                        ->mapWithKeys(fn (WineVariant $variant): array => [
                                            $variant->id => ($variant->wineMaster !== null ? $variant->wineMaster->name : 'Unknown Wine').' '.$variant->vintage_year,
                                        ]))
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->helperText('Select the correct wine variant for this bottle'),
                                Forms\Components\Select::make('correct_format_id')
                                    ->label('Correct Format')
                                    ->options(fn () => Format::query()
                                        ->get()
                                        ->mapWithKeys(fn (Format $format): array => [
                                            $format->id => $format->name.' ('.($format->capacity_ml ?? '750').'ml)',
                                        ]))
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->helperText('Select the correct format for this bottle'),
                            ]),
                        Forms\Components\Placeholder::make('note')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-3 bg-info-50 dark:bg-info-900/20 rounded-lg border border-info-200 dark:border-info-800 text-info-700 dark:text-info-300 text-sm">
                                    <strong>Note:</strong> The corrective record will inherit the allocation lineage, inbound batch, ownership type, and location from the original record. Only the wine variant and format can be corrected.
                                </div>
                            ')),
                    ]),
            ])
            ->action(function (array $data, SerializedBottle $record): void {
                try {
                    DB::transaction(function () use ($data, $record): void {
                        /** @var string $reason */
                        $reason = $data['reason'];
                        /** @var string $correctWineVariantId */
                        $correctWineVariantId = $data['correct_wine_variant_id'];
                        /** @var string $correctFormatId */
                        $correctFormatId = $data['correct_format_id'];

                        // 1. Generate a new serial number for the corrective record
                        /** @var SerializationService $serializationService */
                        $serializationService = app(SerializationService::class);
                        $newSerialNumber = $serializationService->generateSerialNumber();

                        // 2. Create the corrective record with correct data
                        $correctiveBottle = SerializedBottle::create([
                            'serial_number' => $newSerialNumber,
                            'wine_variant_id' => $correctWineVariantId,
                            'format_id' => $correctFormatId,
                            'allocation_id' => $record->allocation_id, // Inherited - immutable
                            'inbound_batch_id' => $record->inbound_batch_id, // Inherited
                            'current_location_id' => $record->current_location_id, // Inherited
                            'case_id' => $record->case_id, // Inherited
                            'ownership_type' => $record->ownership_type, // Inherited
                            'custody_holder' => $record->custody_holder, // Inherited
                            'state' => BottleState::Stored, // New corrective bottle is stored
                            'serialized_at' => now(),
                            'serialized_by' => auth()->id(),
                            'correction_reference' => $record->id, // Link to original
                        ]);

                        // 3. Flag the original record as MIS_SERIALIZED and link to corrective
                        // We need to bypass the immutability guard for this specific update
                        // Use a direct DB update since the state field is not immutable
                        $record->state = BottleState::MisSerialized;
                        $record->correction_reference = $correctiveBottle->id;
                        $record->saveQuietly(); // Skip events to avoid issues

                        // 4. Create inventory exception for audit trail
                        InventoryException::create([
                            'exception_type' => 'mis_serialization_correction',
                            'serialized_bottle_id' => $record->id,
                            'reason' => "Mis-serialization flagged: {$reason}. Original serial: {$record->serial_number}. Corrective record created with serial: {$newSerialNumber}",
                            'created_by' => auth()->id(),
                            'resolution' => "Corrective record created: {$newSerialNumber} (ID: {$correctiveBottle->id})",
                            'resolved_at' => now(),
                            'resolved_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Mis-serialization Correction Completed')
                            ->body("Original bottle {$record->serial_number} has been flagged as mis-serialized. Corrective record created with serial number: {$newSerialNumber}")
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view_corrective')
                                    ->label('View Corrective Record')
                                    ->url(SerializedBottleResource::getUrl('view', ['record' => $correctiveBottle->id]))
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    });

                    // Refresh the page to show updated state
                    /** @var SerializedBottle $currentRecord */
                    $currentRecord = $this->record;
                    $this->redirect(SerializedBottleResource::getUrl('view', ['record' => $currentRecord->id]));

                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body('An unexpected error occurred: '.$e->getMessage())
                        ->send();
                }
            });
    }
}
