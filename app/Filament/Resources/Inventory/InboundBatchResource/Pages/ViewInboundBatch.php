<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource;
use App\Models\AuditLog;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\SerializationService;
use Filament\Actions;
use Filament\Forms;
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

class ViewInboundBatch extends ViewRecord
{
    protected static string $resource = InboundBatchResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var InboundBatch $record */
        $record = $this->record;

        return "Inbound Batch: {$record->display_label}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getDiscrepancyWarningSection(),
                $this->getSerializationNotAuthorizedSection(),
                Tabs::make('Inbound Batch Details')
                    ->tabs([
                        $this->getSummaryTab(),
                        $this->getQuantitiesTab(),
                        $this->getSerializationTab(),
                        $this->getLinkedPhysicalObjectsTab(),
                        $this->getAuditLogTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Warning section for discrepancy status.
     */
    protected function getDiscrepancyWarningSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('discrepancy_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => 'This batch has a DISCREPANCY between expected and received quantities. Serialization is blocked until discrepancy is resolved.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->weight(FontWeight::Bold)
                    ->color('danger')
                    ->size(TextEntry\TextEntrySize::Large),
            ])
            ->visible(fn (InboundBatch $record): bool => $record->serialization_status === InboundBatchStatus::Discrepancy)
            ->extraAttributes(['class' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800'])
            ->columnSpanFull();
    }

    /**
     * Warning section if serialization is not authorized at the receiving location.
     */
    protected function getSerializationNotAuthorizedSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('serialization_not_authorized')
                    ->label('')
                    ->getStateUsing(fn (): string => 'Serialization NOT Authorized at this batch\'s receiving location.')
                    ->icon('heroicon-o-no-symbol')
                    ->iconColor('warning')
                    ->weight(FontWeight::Bold)
                    ->color('warning')
                    ->size(TextEntry\TextEntrySize::Large),
            ])
            ->visible(function (InboundBatch $record): bool {
                $location = $record->receivingLocation;

                return $location !== null && ! $location->serialization_authorized;
            })
            ->extraAttributes(['class' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800'])
            ->columnSpanFull();
    }

    /**
     * Tab 1: Summary - source, sourcing context, allocation lineage, ownership.
     */
    protected function getSummaryTab(): Tab
    {
        return Tab::make('Summary')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Batch Identity')
                    ->description('Basic batch information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Batch ID')
                                        ->copyable()
                                        ->copyMessage('Batch ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('uuid')
                                        ->label('UUID')
                                        ->copyable()
                                        ->copyMessage('UUID copied'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('source_type')
                                        ->label('Source Type')
                                        ->badge()
                                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                        ->color(fn (string $state): string => match ($state) {
                                            'producer' => 'success',
                                            'supplier' => 'info',
                                            'transfer' => 'warning',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('received_date')
                                        ->label('Received Date')
                                        ->date()
                                        ->icon('heroicon-o-calendar'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('packaging_type')
                                        ->label('Packaging Type')
                                        ->badge()
                                        ->color('gray'),
                                    TextEntry::make('wms_reference_id')
                                        ->label('WMS Reference')
                                        ->copyable()
                                        ->default('Not linked')
                                        ->placeholder('Not linked'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('serialization_status')
                                        ->label('Serialization Status')
                                        ->badge()
                                        ->formatStateUsing(fn (InboundBatchStatus $state): string => $state->label())
                                        ->color(fn (InboundBatchStatus $state): string => $state->color())
                                        ->icon(fn (InboundBatchStatus $state): string => $state->icon()),
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Sourcing Context')
                    ->description('Procurement and allocation references')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('procurement_intent_id')
                                    ->label('Procurement Intent')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        if ($record->procurement_intent_id) {
                                            return "Intent #{$record->procurement_intent_id}";
                                        }

                                        return 'No linked procurement intent';
                                    })
                                    ->icon(fn (InboundBatch $record): string => $record->procurement_intent_id
                                        ? 'heroicon-o-link'
                                        : 'heroicon-o-minus')
                                    ->color(fn (InboundBatch $record): string => $record->procurement_intent_id
                                        ? 'info'
                                        : 'gray'),
                                TextEntry::make('product_reference_display')
                                    ->label('Product Reference')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $type = class_basename($record->product_reference_type);

                                        return "{$type} #{$record->product_reference_id}";
                                    })
                                    ->icon('heroicon-o-cube')
                                    ->copyable()
                                    ->copyMessage('Product reference copied'),
                                TextEntry::make('condition_notes')
                                    ->label('Condition Notes')
                                    ->default('No condition notes')
                                    ->placeholder('No condition notes'),
                            ]),
                    ]),
                Section::make('Allocation Lineage')
                    ->description('Immutable allocation reference for all bottles serialized from this batch')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('allocation_lineage')
                                    ->label('Allocation')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        if ($record->allocation_id) {
                                            $allocation = $record->allocation;
                                            if ($allocation) {
                                                return "Allocation #{$allocation->id}";
                                            }

                                            return "Allocation #{$record->allocation_id}";
                                        }

                                        return 'No allocation lineage';
                                    })
                                    ->badge()
                                    ->color(fn (InboundBatch $record): string => $record->hasAllocationLineage() ? 'success' : 'danger')
                                    ->icon(fn (InboundBatch $record): string => $record->hasAllocationLineage()
                                        ? 'heroicon-o-check-badge'
                                        : 'heroicon-o-exclamation-triangle')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('allocation_note')
                                    ->label('')
                                    ->getStateUsing(fn (): string => 'All serialized bottles from this batch inherit this allocation lineage. This reference is IMMUTABLE after serialization.')
                                    ->icon('heroicon-o-information-circle')
                                    ->iconColor('info')
                                    ->color('gray'),
                            ]),
                    ])
                    ->extraAttributes(fn (InboundBatch $record): array => $record->hasAllocationLineage()
                        ? ['class' => 'bg-success-50 dark:bg-success-900/10']
                        : ['class' => 'bg-danger-50 dark:bg-danger-900/10']),
                Section::make('Ownership')
                    ->description('Ownership classification for bottles from this batch')
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
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        return match ($record->ownership_type) {
                                            OwnershipType::CururatedOwned => 'Crurated owns this inventory. Bottles can be consumed for events.',
                                            OwnershipType::InCustody => 'Inventory is held in custody. Bottles cannot be consumed for events without special permission.',
                                            OwnershipType::ThirdPartyOwned => 'Inventory is owned by a third party. Special handling required.',
                                        };
                                    })
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make('Location')
                    ->description('Where this batch was received')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('receivingLocation.name')
                                    ->label('Receiving Location')
                                    ->icon('heroicon-o-map-pin')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('receivingLocation.location_type')
                                    ->label('Location Type')
                                    ->badge()
                                    ->formatStateUsing(fn (InboundBatch $record): string => $record->receivingLocation?->location_type?->label() ?? 'Unknown')
                                    ->color(fn (InboundBatch $record): string => $record->receivingLocation?->location_type?->color() ?? 'gray'),
                                TextEntry::make('receivingLocation.serialization_authorized')
                                    ->label('Serialization')
                                    ->badge()
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $location = $record->receivingLocation;

                                        return $location !== null && $location->serialization_authorized
                                            ? 'Authorized'
                                            : 'Not Authorized';
                                    })
                                    ->color(function (InboundBatch $record): string {
                                        $location = $record->receivingLocation;

                                        return $location !== null && $location->serialization_authorized
                                            ? 'success'
                                            : 'danger';
                                    })
                                    ->icon(function (InboundBatch $record): string {
                                        $location = $record->receivingLocation;

                                        return $location !== null && $location->serialization_authorized
                                            ? 'heroicon-o-check-badge'
                                            : 'heroicon-o-x-circle';
                                    }),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Quantities - expected vs received, remaining unserialized, delta.
     */
    protected function getQuantitiesTab(): Tab
    {
        return Tab::make('Quantities')
            ->icon('heroicon-o-calculator')
            ->badge(fn (InboundBatch $record): ?string => $record->hasDiscrepancy() ? 'Discrepancy' : null)
            ->badgeColor('danger')
            ->schema([
                Section::make('Quantity Overview')
                    ->description('Expected, received, and serialized quantities')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('quantity_expected')
                                    ->label('Expected')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('info'),
                                TextEntry::make('quantity_received')
                                    ->label('Received')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color(fn (InboundBatch $record): string => $record->hasDiscrepancy() ? 'danger' : 'success'),
                                TextEntry::make('serialized_count')
                                    ->label('Serialized')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('success'),
                                TextEntry::make('remaining_unserialized')
                                    ->label('Remaining')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color(fn (InboundBatch $record): string => $record->remaining_unserialized > 0 ? 'warning' : 'success'),
                            ]),
                    ]),
                Section::make('Delta Analysis')
                    ->description('Difference between expected and received quantities')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('quantity_delta')
                                    ->label('Quantity Delta')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta === 0) {
                                            return '0 (Exact match)';
                                        }
                                        if ($delta > 0) {
                                            return "+{$delta} (Overage)";
                                        }

                                        return "{$delta} (Shortage)";
                                    })
                                    ->badge()
                                    ->color(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta === 0) {
                                            return 'success';
                                        }

                                        return 'danger';
                                    })
                                    ->icon(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta === 0) {
                                            return 'heroicon-o-check-circle';
                                        }
                                        if ($delta > 0) {
                                            return 'heroicon-o-arrow-trending-up';
                                        }

                                        return 'heroicon-o-arrow-trending-down';
                                    })
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('delta_explanation')
                                    ->label('Explanation')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta === 0) {
                                            return 'The received quantity exactly matches the expected quantity. No discrepancy exists.';
                                        }
                                        if ($delta > 0) {
                                            return "Received {$delta} more bottles than expected. This may indicate an overage that needs investigation or reconciliation.";
                                        }
                                        $shortage = abs($delta);

                                        return "Received {$shortage} fewer bottles than expected. This shortage should be investigated and documented.";
                                    })
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray'),
                            ]),
                    ])
                    ->visible(fn (InboundBatch $record): bool => true),
                Section::make('Serialization Progress')
                    ->description('Progress towards full serialization')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('serialization_progress')
                                    ->label('Progress')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $serialized = $record->serialized_count;
                                        $received = $record->quantity_received;
                                        if ($received === 0) {
                                            return 'N/A (No bottles received)';
                                        }
                                        $percentage = round(($serialized / $received) * 100, 1);

                                        return "{$serialized} / {$received} ({$percentage}%)";
                                    })
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('serialization_status_detail')
                                    ->label('Status')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        return match ($record->serialization_status) {
                                            InboundBatchStatus::PendingSerialization => 'No bottles have been serialized yet. Ready to start serialization.',
                                            InboundBatchStatus::PartiallySerialized => 'Some bottles have been serialized. Continue serialization to complete.',
                                            InboundBatchStatus::FullySerialized => 'All received bottles have been serialized. Batch is complete.',
                                            InboundBatchStatus::Discrepancy => 'Discrepancy detected. Serialization is blocked until discrepancy is resolved.',
                                        };
                                    })
                                    ->icon(fn (InboundBatch $record): string => $record->serialization_status->icon())
                                    ->color(fn (InboundBatch $record): string => $record->serialization_status->color()),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Serialization - eligibility, history, serialized bottles list.
     */
    protected function getSerializationTab(): Tab
    {
        return Tab::make('Serialization')
            ->icon('heroicon-o-qr-code')
            ->badge(fn (InboundBatch $record): ?string => $record->canStartSerialization() ? 'Eligible' : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Serialization Eligibility')
                    ->description('Check if this batch is eligible for serialization')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('serialization_eligible')
                                    ->label('Eligible for Serialization')
                                    ->getStateUsing(fn (InboundBatch $record): string => $record->canStartSerialization() ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn (InboundBatch $record): string => $record->canStartSerialization() ? 'success' : 'danger')
                                    ->icon(fn (InboundBatch $record): string => $record->canStartSerialization()
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-x-circle')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('eligibility_reason')
                                    ->label('Eligibility Details')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $reasons = [];

                                        // Check serialization status
                                        if (! $record->serialization_status->canStartSerialization()) {
                                            $reasons[] = "Status '{$record->serialization_status->label()}' does not allow serialization";
                                        }

                                        // Check location authorization
                                        $location = $record->receivingLocation;
                                        if (! $location) {
                                            $reasons[] = 'No receiving location configured';
                                        } elseif (! $location->serialization_authorized) {
                                            $reasons[] = "Location '{$location->name}' is not authorized for serialization";
                                        }

                                        // Check remaining quantity
                                        if ($record->remaining_unserialized <= 0) {
                                            $reasons[] = 'No remaining bottles to serialize';
                                        }

                                        if (empty($reasons)) {
                                            return 'All requirements met. This batch can be serialized.';
                                        }

                                        return 'Blocked: '.implode('; ', $reasons);
                                    })
                                    ->icon(fn (InboundBatch $record): string => $record->canStartSerialization()
                                        ? 'heroicon-o-check-badge'
                                        : 'heroicon-o-exclamation-triangle')
                                    ->color(fn (InboundBatch $record): string => $record->canStartSerialization() ? 'success' : 'danger'),
                            ]),
                    ]),
                Section::make('Serialization History')
                    ->description('Timeline of serialization events for this batch')
                    ->schema([
                        TextEntry::make('serialization_events')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $bottles = $record->serializedBottles()
                                    ->orderBy('serialized_at', 'desc')
                                    ->limit(20)
                                    ->get();

                                if ($bottles->isEmpty()) {
                                    return 'No serialization events recorded yet.';
                                }

                                // Group by date
                                $grouped = $bottles->groupBy(function ($bottle) {
                                    /** @var SerializedBottle $bottle */
                                    return $bottle->serialized_at->format('Y-m-d');
                                });

                                $html = '<div class="space-y-4">';
                                foreach ($grouped as $date => $dateBottles) {
                                    $count = $dateBottles->count();
                                    $formattedDate = \Carbon\Carbon::parse($date)->format('M d, Y');

                                    // Get the first bottle's serialized_by for the user info
                                    /** @var SerializedBottle $firstBottle */
                                    $firstBottle = $dateBottles->first();
                                    $user = $firstBottle->serializedByUser;
                                    $userName = $user ? $user->name : 'System';

                                    $html .= <<<HTML
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg font-semibold text-success-600 dark:text-success-400">{$count}</span>
                                                <span class="text-gray-600 dark:text-gray-300">bottles serialized</span>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {$formattedDate} by {$userName}
                                            </div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                $totalSerialized = $record->serialized_count;
                                if ($totalSerialized > 20) {
                                    $html .= '<p class="text-sm text-gray-500 mt-2">Showing most recent 20 bottles. Total serialized: '.$totalSerialized.'</p>';
                                }

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Serialized Bottles')
                    ->description('List of bottles serialized from this batch')
                    ->schema([
                        TextEntry::make('bottles_list_info')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $total = $record->serialized_count;

                                return "Total: {$total} serialized bottles from this batch.";
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
                                        TextEntry::make('state')
                                            ->label('State')
                                            ->badge()
                                            ->formatStateUsing(fn (BottleState $state): string => $state->label())
                                            ->color(fn (BottleState $state): string => $state->color())
                                            ->icon(fn (BottleState $state): string => $state->icon()),
                                        TextEntry::make('currentLocation.name')
                                            ->label('Location')
                                            ->icon('heroicon-o-map-pin'),
                                        TextEntry::make('nft_status')
                                            ->label('NFT')
                                            ->getStateUsing(fn (SerializedBottle $record): string => $record->hasNft() ? 'Minted' : 'Pending')
                                            ->badge()
                                            ->color(fn (SerializedBottle $record): string => $record->hasNft() ? 'success' : 'warning'),
                                        TextEntry::make('serialized_at')
                                            ->label('Serialized')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (InboundBatch $record): bool => $record->serialized_count > 0),
                        TextEntry::make('no_bottles')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No bottles have been serialized from this batch yet.')
                            ->color('gray')
                            ->icon('heroicon-o-information-circle')
                            ->visible(fn (InboundBatch $record): bool => $record->serialized_count === 0),
                    ]),
            ]);
    }

    /**
     * Tab 4: Linked Physical Objects - bottles and cases created from this batch.
     */
    protected function getLinkedPhysicalObjectsTab(): Tab
    {
        return Tab::make('Linked Objects')
            ->icon('heroicon-o-cube')
            ->badge(fn (InboundBatch $record): ?string => ($record->serialized_count + $record->cases()->count()) > 0
                ? (string) ($record->serialized_count + $record->cases()->count())
                : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Serialized Bottles')
                    ->description('All bottles created from this inbound batch')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('bottles_total')
                                    ->label('Total Bottles')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->serialized_count)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('success'),
                                TextEntry::make('bottles_stored')
                                    ->label('Stored')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Stored)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('bottles_shipped')
                                    ->label('Shipped')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->serializedBottles()
                                        ->where('state', BottleState::Shipped)
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('bottles_other')
                                    ->label('Other States')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->serializedBottles()
                                        ->whereNotIn('state', [BottleState::Stored, BottleState::Shipped])
                                        ->count())
                                    ->numeric()
                                    ->badge()
                                    ->color('gray'),
                            ]),
                        RepeatableEntry::make('serializedBottles')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('serial_number')
                                            ->label('Serial')
                                            ->copyable()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('state')
                                            ->label('State')
                                            ->badge()
                                            ->formatStateUsing(fn (BottleState $state): string => $state->label())
                                            ->color(fn (BottleState $state): string => $state->color()),
                                        TextEntry::make('currentLocation.name')
                                            ->label('Location'),
                                        TextEntry::make('case_id')
                                            ->label('In Case')
                                            ->getStateUsing(fn (SerializedBottle $record): string => $record->isInCase()
                                                ? 'Yes'
                                                : 'No')
                                            ->badge()
                                            ->color(fn (SerializedBottle $record): string => $record->isInCase() ? 'info' : 'gray'),
                                        TextEntry::make('allocation.id')
                                            ->label('Allocation')
                                            ->getStateUsing(fn (SerializedBottle $record): string => "#{$record->allocation_id}")
                                            ->badge()
                                            ->color('success'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (InboundBatch $record): bool => $record->serialized_count > 0),
                        TextEntry::make('no_linked_bottles')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No serialized bottles linked to this batch.')
                            ->color('gray')
                            ->visible(fn (InboundBatch $record): bool => $record->serialized_count === 0),
                    ]),
                Section::make('Cases')
                    ->description('Cases created from this inbound batch')
                    ->schema([
                        TextEntry::make('cases_total')
                            ->label('Total Cases')
                            ->getStateUsing(fn (InboundBatch $record): int => $record->cases()->count())
                            ->numeric()
                            ->weight(FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large)
                            ->color('info'),
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
                                        TextEntry::make('bottle_count')
                                            ->label('Bottles')
                                            ->getStateUsing(fn (InventoryCase $record): int => $record->serializedBottles()->count())
                                            ->numeric()
                                            ->suffix(' bottles'),
                                        TextEntry::make('currentLocation.name')
                                            ->label('Location'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (InboundBatch $record): bool => $record->cases()->count() > 0),
                        TextEntry::make('no_cases')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No cases linked to this batch.')
                            ->color('gray')
                            ->visible(fn (InboundBatch $record): bool => $record->cases()->count() === 0),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit Log - WMS events, operator actions, discrepancy resolutions.
     */
    protected function getAuditLogTab(): Tab
    {
        return Tab::make('Audit Log')
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                Section::make('WMS Events')
                    ->description('Events received from the Warehouse Management System')
                    ->schema([
                        TextEntry::make('wms_events')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                if (! $record->hasWmsReference()) {
                                    return 'No WMS events recorded. This batch is not linked to a WMS.';
                                }

                                return "WMS Reference: {$record->wms_reference_id}. WMS events are synchronized automatically.";
                            })
                            ->icon(fn (InboundBatch $record): string => $record->hasWmsReference()
                                ? 'heroicon-o-server-stack'
                                : 'heroicon-o-minus')
                            ->color(fn (InboundBatch $record): string => $record->hasWmsReference() ? 'info' : 'gray'),
                    ]),
                Section::make('Operator Actions')
                    ->description('Actions performed by operators on this batch')
                    ->schema([
                        TextEntry::make('audit_log_entries')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $logs = $record->auditLogs()
                                    ->orderBy('created_at', 'desc')
                                    ->limit(20)
                                    ->get();

                                if ($logs->isEmpty()) {
                                    return 'No audit log entries recorded.';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($logs as $log) {
                                    /** @var AuditLog $log */
                                    $event = $log->getEventLabel();
                                    $icon = $log->getEventIcon();
                                    $color = $log->getEventColor();
                                    $user = $log->user;
                                    $userName = $user ? $user->name : 'System';
                                    $date = $log->created_at->format('M d, Y H:i:s');

                                    // Build changes summary
                                    $changes = '';
                                    /** @var array<string, mixed>|null $newValues */
                                    $newValues = $log->getAttribute('new_values');
                                    if ($newValues !== null && count($newValues) > 0) {
                                        $changedFields = array_keys($newValues);
                                        $changes = implode(', ', array_slice($changedFields, 0, 3));
                                        $fieldCount = count($changedFields);
                                        if ($fieldCount > 3) {
                                            $changes .= ' +'.($fieldCount - 3).' more';
                                        }
                                    }

                                    $colorClass = match ($color) {
                                        'success' => 'text-success-600 dark:text-success-400',
                                        'danger' => 'text-danger-600 dark:text-danger-400',
                                        'warning' => 'text-warning-600 dark:text-warning-400',
                                        'info' => 'text-info-600 dark:text-info-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                        <span class="font-medium {$colorClass}">{$event}</span>
                                        <span class="text-sm text-gray-500">by {$userName}</span>
                                        <span class="text-sm text-gray-400">{$date}</span>
                                        <span class="text-xs text-gray-400">{$changes}</span>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Discrepancy Resolutions')
                    ->description('History of discrepancy resolutions for this batch')
                    ->visible(fn (InboundBatch $record): bool => $record->hasDiscrepancy() || $record->serialization_status === InboundBatchStatus::Discrepancy)
                    ->schema([
                        TextEntry::make('discrepancy_info')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                if ($record->serialization_status === InboundBatchStatus::Discrepancy) {
                                    $delta = $record->quantity_delta;
                                    $discrepancyType = $delta > 0 ? 'overage' : 'shortage';

                                    return "Active discrepancy: {$discrepancyType} of ".abs($delta).' bottles. Resolution required before serialization can proceed.';
                                }

                                if ($record->hasDiscrepancy()) {
                                    return 'There is a quantity difference between expected and received. This may have been resolved or is pending resolution.';
                                }

                                return 'No discrepancy detected for this batch.';
                            })
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color(fn (InboundBatch $record): string => $record->serialization_status === InboundBatchStatus::Discrepancy
                                ? 'danger'
                                : ($record->hasDiscrepancy() ? 'warning' : 'success')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getStartSerializationAction(),
            $this->getResolveDiscrepancyAction(),
        ];
    }

    /**
     * Action: Start Serialization (if eligible).
     */
    protected function getStartSerializationAction(): Actions\Action
    {
        return Actions\Action::make('startSerialization')
            ->label('Start Serialization')
            ->icon('heroicon-o-qr-code')
            ->color('success')
            ->visible(fn (InboundBatch $record): bool => $record->canStartSerialization())
            ->requiresConfirmation()
            ->modalHeading('Start Serialization')
            ->modalDescription(function (InboundBatch $record): string {
                $remaining = $record->remaining_unserialized;

                return "You are about to serialize bottles from this inbound batch. {$remaining} bottles are available for serialization.";
            })
            ->form([
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity to Serialize')
                    ->helperText(fn (InboundBatch $record): string => "Maximum: {$record->remaining_unserialized} bottles")
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (InboundBatch $record): int => $record->remaining_unserialized)
                    ->default(fn (InboundBatch $record): int => $record->remaining_unserialized),
            ])
            ->action(function (InboundBatch $record, array $data): void {
                $quantity = (int) $data['quantity'];
                $user = auth()->user();

                if (! $user) {
                    Notification::make()
                        ->title('Authentication required')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $serializationService = app(SerializationService::class);
                    $bottles = $serializationService->serializeBatch($record, $quantity, $user);

                    Notification::make()
                        ->title('Serialization Complete')
                        ->body("{$bottles->count()} bottles have been serialized successfully.")
                        ->success()
                        ->send();

                    // Refresh the record to show updated data
                    $this->refreshFormData(['serialization_status', 'remaining_unserialized']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Serialization Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Action: Resolve Discrepancy.
     */
    protected function getResolveDiscrepancyAction(): Actions\Action
    {
        return Actions\Action::make('resolveDiscrepancy')
            ->label('Resolve Discrepancy')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (InboundBatch $record): bool => $record->serialization_status === InboundBatchStatus::Discrepancy
                || $record->hasDiscrepancy())
            ->requiresConfirmation()
            ->modalHeading('Resolve Discrepancy')
            ->modalDescription(function (InboundBatch $record): string {
                $expected = $record->quantity_expected;
                $received = $record->quantity_received;
                $delta = $record->quantity_delta;
                $type = $delta > 0 ? 'overage' : 'shortage';

                return "Expected: {$expected} bottles, Received: {$received} bottles. This is a {$type} of ".abs($delta).' bottles.';
            })
            ->form([
                Forms\Components\Select::make('resolution_type')
                    ->label('Resolution Type')
                    ->options([
                        'shortage' => 'Shortage - Accept lower quantity',
                        'overage' => 'Overage - Accept higher quantity',
                        'damage' => 'Damage - Bottles damaged in transit',
                        'other' => 'Other - See notes',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('resolution_notes')
                    ->label('Resolution Notes')
                    ->helperText('Provide details about the discrepancy and resolution')
                    ->required()
                    ->rows(3),
                Forms\Components\TextInput::make('document_reference')
                    ->label('Document Reference (optional)')
                    ->helperText('Reference to supporting documents (e.g., delivery note, photos)'),
            ])
            ->action(function (InboundBatch $record, array $data): void {
                // Update the batch status to allow serialization
                $previousStatus = $record->serialization_status;

                // Determine new status based on serialization progress
                $serializedCount = $record->serialized_count;
                $receivedQuantity = $record->quantity_received;

                if ($serializedCount === 0) {
                    $newStatus = InboundBatchStatus::PendingSerialization;
                } elseif ($serializedCount >= $receivedQuantity) {
                    $newStatus = InboundBatchStatus::FullySerialized;
                } else {
                    $newStatus = InboundBatchStatus::PartiallySerialized;
                }

                $record->update([
                    'serialization_status' => $newStatus,
                    'condition_notes' => ($record->condition_notes ? $record->condition_notes."\n\n" : '')
                        ."[DISCREPANCY RESOLVED]\n"
                        ."Type: {$data['resolution_type']}\n"
                        ."Notes: {$data['resolution_notes']}\n"
                        .($data['document_reference'] ? "Document: {$data['document_reference']}\n" : '')
                        .'Resolved at: '.now()->format('Y-m-d H:i:s'),
                ]);

                Notification::make()
                    ->title('Discrepancy Resolved')
                    ->body("Status updated from {$previousStatus->label()} to {$newStatus->label()}. Serialization is now available.")
                    ->success()
                    ->send();

                $this->refreshFormData(['serialization_status', 'condition_notes']);
            });
    }
}
