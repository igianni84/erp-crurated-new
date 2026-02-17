<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\DiscrepancyResolution;
use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource;
use App\Models\AuditLog;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\SerializationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class ViewInboundBatch extends ViewRecord
{
    protected static string $resource = InboundBatchResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var InboundBatch $record */
        $record = $this->record;

        return "Inbound Batch: {$record->display_label}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                $this->getDiscrepancyWarningSection(),
                $this->getSerializationNotAuthorizedSection(),
                Tabs::make('Inbound Batch Details')
                    ->tabs([
                        $this->getSummaryTab(),
                        $this->getQuantitiesTab(),
                        $this->getDiscrepancyResolutionTab(),
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
                    ->size(TextSize::Large),
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
                    ->size(TextSize::Large),
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
                                    ->size(TextSize::Large),
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
                                    ->size(TextSize::Large),
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
                                    ->size(TextSize::Large)
                                    ->color('info'),
                                TextEntry::make('quantity_received')
                                    ->label('Received')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color(fn (InboundBatch $record): string => $record->hasDiscrepancy() ? 'danger' : 'success'),
                                TextEntry::make('serialized_count')
                                    ->label('Serialized')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color('success'),
                                TextEntry::make('remaining_unserialized')
                                    ->label('Remaining')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
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
                                    ->size(TextSize::Large),
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
     * Tab: Discrepancy Resolution - visible only when quantity_expected != quantity_received.
     * Shows side-by-side comparison and resolution history.
     */
    protected function getDiscrepancyResolutionTab(): Tab
    {
        return Tab::make('Discrepancy Resolution')
            ->icon('heroicon-o-exclamation-triangle')
            ->badge(fn (InboundBatch $record): ?string => $record->hasDiscrepancy() ? 'Action Required' : null)
            ->badgeColor('danger')
            ->visible(fn (InboundBatch $record): bool => $record->hasDiscrepancy())
            ->schema([
                Section::make('Discrepancy Overview')
                    ->description('Comparison between expected and received quantities')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Side-by-side comparison
                                Section::make('Expected Quantity')
                                    ->description('What was expected per documentation')
                                    ->schema([
                                        TextEntry::make('quantity_expected')
                                            ->label('Expected')
                                            ->numeric()
                                            ->suffix(' bottles')
                                            ->weight(FontWeight::Bold)
                                            ->size(TextSize::Large)
                                            ->color('info'),
                                        TextEntry::make('expected_source')
                                            ->label('Source')
                                            ->getStateUsing(fn (InboundBatch $record): string => $record->wms_reference_id
                                                ? "WMS Reference: {$record->wms_reference_id}"
                                                : 'Manual entry / Documentation')
                                            ->icon('heroicon-o-document-text')
                                            ->color('gray'),
                                    ])
                                    ->columnSpan(1),
                                Section::make('Received Quantity')
                                    ->description('Actual quantity received at location')
                                    ->schema([
                                        TextEntry::make('quantity_received')
                                            ->label('Received')
                                            ->numeric()
                                            ->suffix(' bottles')
                                            ->weight(FontWeight::Bold)
                                            ->size(TextSize::Large)
                                            ->color(fn (InboundBatch $record): string => $record->hasDiscrepancy() ? 'danger' : 'success'),
                                        TextEntry::make('received_info')
                                            ->label('Reported by')
                                            ->getStateUsing(fn (InboundBatch $record): string => $record->wms_reference_id
                                                ? 'WMS (Warehouse Management System)'
                                                : 'Manual count / ERP Operator')
                                            ->icon('heroicon-o-clipboard-document-check')
                                            ->color('gray'),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ]),
                Section::make('Discrepancy Analysis')
                    ->description('Details of the quantity difference')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('discrepancy_type')
                                    ->label('Discrepancy Type')
                                    ->getStateUsing(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta > 0) {
                                            return 'OVERAGE';
                                        }
                                        if ($delta < 0) {
                                            return 'SHORTAGE';
                                        }

                                        return 'NONE';
                                    })
                                    ->badge()
                                    ->color(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta > 0) {
                                            return 'warning';
                                        }
                                        if ($delta < 0) {
                                            return 'danger';
                                        }

                                        return 'success';
                                    })
                                    ->icon(function (InboundBatch $record): string {
                                        $delta = $record->quantity_delta;
                                        if ($delta > 0) {
                                            return 'heroicon-o-plus-circle';
                                        }
                                        if ($delta < 0) {
                                            return 'heroicon-o-minus-circle';
                                        }

                                        return 'heroicon-o-check-circle';
                                    })
                                    ->size(TextSize::Large),
                                TextEntry::make('discrepancy_amount')
                                    ->label('Difference')
                                    ->getStateUsing(fn (InboundBatch $record): string => abs($record->quantity_delta).' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color('danger'),
                                TextEntry::make('serialization_blocked')
                                    ->label('Serialization Status')
                                    ->getStateUsing(fn (InboundBatch $record): string => $record->serialization_status === InboundBatchStatus::Discrepancy
                                        ? 'BLOCKED - Resolution Required'
                                        : 'Available')
                                    ->badge()
                                    ->color(fn (InboundBatch $record): string => $record->serialization_status === InboundBatchStatus::Discrepancy
                                        ? 'danger'
                                        : 'success')
                                    ->icon(fn (InboundBatch $record): string => $record->serialization_status === InboundBatchStatus::Discrepancy
                                        ? 'heroicon-o-lock-closed'
                                        : 'heroicon-o-lock-open'),
                            ]),
                        TextEntry::make('discrepancy_explanation')
                            ->label('What This Means')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $delta = $record->quantity_delta;
                                $abs = abs($delta);
                                if ($delta > 0) {
                                    return "The warehouse received {$abs} more bottles than expected. This overage needs to be documented and reconciled. Possible causes: supplier sent extra, documentation error, or previous shortage correction.";
                                }
                                if ($delta < 0) {
                                    return "The warehouse received {$abs} fewer bottles than expected. This shortage needs to be documented and investigated. Possible causes: damaged in transit, missing from shipment, counting error, or theft.";
                                }

                                return 'No discrepancy detected.';
                            })
                            ->icon('heroicon-o-information-circle')
                            ->color('gray')
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50 dark:bg-danger-900/10'])
                    ->visible(fn (InboundBatch $record): bool => $record->serialization_status === InboundBatchStatus::Discrepancy),
                Section::make('Resolution History')
                    ->description('Immutable record of all discrepancy resolutions for this batch')
                    ->schema([
                        TextEntry::make('resolution_history')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $resolutions = $record->discrepancyResolutions()
                                    ->with(['creator', 'resolver'])
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                if ($resolutions->isEmpty()) {
                                    if ($record->serialization_status === InboundBatchStatus::Discrepancy) {
                                        return '<div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                                            <div class="flex items-center gap-2 text-warning-700 dark:text-warning-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                <span class="font-semibold">No resolutions recorded yet. Use the "Resolve Discrepancy" action to document the resolution.</span>
                                            </div>
                                        </div>';
                                    }

                                    return '<div class="text-gray-500">No resolution history available.</div>';
                                }

                                $html = '<div class="space-y-4">';
                                foreach ($resolutions as $resolution) {
                                    /** @var InventoryException $resolution */
                                    $type = str_replace('discrepancy_', '', $resolution->exception_type);
                                    $typeLabel = ucfirst($type);
                                    $typeColor = match ($type) {
                                        'shortage' => 'danger',
                                        'overage' => 'warning',
                                        'damage' => 'danger',
                                        default => 'gray',
                                    };
                                    $colorClass = match ($typeColor) {
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 border-danger-200 dark:border-danger-800',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 border-warning-200 dark:border-warning-800',
                                        default => 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                                    };
                                    $badgeClass = match ($typeColor) {
                                        'danger' => 'bg-danger-500 text-white',
                                        'warning' => 'bg-warning-500 text-white',
                                        default => 'bg-gray-500 text-white',
                                    };

                                    $creator = $resolution->creator;
                                    $creatorName = $creator ? $creator->name : 'System';
                                    $createdAt = $resolution->created_at->format('M d, Y H:i:s');
                                    $reason = e($resolution->reason);

                                    $resolutionText = '';
                                    if ($resolution->isResolved()) {
                                        $resolver = $resolution->resolver;
                                        $resolverName = $resolver ? $resolver->name : 'System';
                                        $resolvedAt = $resolution->resolved_at->format('M d, Y H:i:s');
                                        $resolutionNote = e($resolution->resolution ?? 'No resolution notes');
                                        $resolutionText = <<<HTML
                                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                            <div class="flex items-center gap-2 text-success-600 dark:text-success-400 mb-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span class="font-semibold">Resolved</span>
                                            </div>
                                            <div class="text-sm text-gray-600 dark:text-gray-300">{$resolutionNote}</div>
                                            <div class="text-xs text-gray-400 mt-1">Resolved by {$resolverName} on {$resolvedAt}</div>
                                        </div>
                                        HTML;
                                    }

                                    $html .= <<<HTML
                                    <div class="p-4 rounded-lg border {$colorClass}">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <span class="px-2 py-1 text-xs font-semibold rounded {$badgeClass}">{$typeLabel}</span>
                                                <span class="text-sm text-gray-500">Exception #{$resolution->id}</span>
                                            </div>
                                            <div class="text-sm text-gray-500">{$createdAt}</div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Reason:</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">{$reason}</div>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-2">Reported by {$creatorName}</div>
                                        {$resolutionText}
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Original Values Preserved')
                    ->description('These values are immutable and serve as the audit baseline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('original_expected')
                                    ->label('Original Expected Quantity')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->quantity_expected)
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->icon('heroicon-o-lock-closed')
                                    ->color('gray'),
                                TextEntry::make('original_received')
                                    ->label('Original Received Quantity')
                                    ->getStateUsing(fn (InboundBatch $record): int => $record->quantity_received)
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->icon('heroicon-o-lock-closed')
                                    ->color('gray'),
                            ]),
                        TextEntry::make('immutability_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Original values are never overwritten. Discrepancy resolutions are recorded as separate immutable correction events (InventoryException records) to maintain full audit trail.')
                            ->icon('heroicon-o-shield-check')
                            ->color('success'),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
                                    ->size(TextSize::Large),
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
                                    $formattedDate = Carbon::parse($date)->format('M d, Y');

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
                                    ->size(TextSize::Large)
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
                            ->size(TextSize::Large)
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
     * Tab 6: Audit - Immutable audit log timeline for compliance.
     *
     * Events logged:
     * - creation (batch_created)
     * - quantity_update (batch_quantity_update)
     * - discrepancy_flagged (batch_discrepancy_flagged)
     * - discrepancy_resolved (batch_discrepancy_resolved)
     * - serialization_started (batch_serialization_started)
     * - serialization_completed (batch_serialization_completed)
     *
     * @see US-B057
     */
    protected function getAuditLogTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-clipboard-document-list')
            ->badge(function (InboundBatch $record): ?string {
                $count = $record->auditLogs()->count();

                return $count > 0 ? (string) $count : null;
            })
            ->badgeColor('gray')
            ->schema([
                Section::make('WMS Reference')
                    ->description('Warehouse Management System integration')
                    ->schema([
                        TextEntry::make('wms_reference_display')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                if (! $record->hasWmsReference()) {
                                    return 'This batch is not linked to a WMS. All events are from ERP operations.';
                                }

                                return "WMS Reference: {$record->wms_reference_id}. WMS events are synchronized automatically and included in the audit log below.";
                            })
                            ->icon(fn (InboundBatch $record): string => $record->hasWmsReference()
                                ? 'heroicon-o-server-stack'
                                : 'heroicon-o-information-circle')
                            ->color(fn (InboundBatch $record): string => $record->hasWmsReference() ? 'info' : 'gray'),
                    ])
                    ->collapsible(),
                Section::make('Audit Log')
                    ->description('Immutable record of all changes to this inbound batch')
                    ->schema([
                        TextEntry::make('audit_immutability_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes for compliance purposes.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('success')
                            ->color('gray'),
                        TextEntry::make('audit_timeline')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $auditLogs = $record->auditLogs()
                                    ->with('user')
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                if ($auditLogs->isEmpty()) {
                                    return '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                                        <p class="text-gray-500">No audit logs recorded for this batch.</p>
                                    </div>';
                                }

                                $html = '<div class="space-y-3">';

                                foreach ($auditLogs as $log) {
                                    /** @var AuditLog $log */
                                    $eventLabel = $log->getEventLabel();
                                    $eventColor = $log->getEventColor();

                                    $user = $log->user;
                                    $userName = $user !== null ? e($user->name) : 'System';
                                    $timestamp = $log->created_at->format('M d, Y H:i:s');

                                    $oldValues = $log->old_values ?? [];
                                    $newValues = $log->new_values ?? [];

                                    $colorClass = match ($eventColor) {
                                        'success' => 'border-success-200 dark:border-success-800 bg-success-50/50 dark:bg-success-900/10',
                                        'info' => 'border-info-200 dark:border-info-800 bg-info-50/50 dark:bg-info-900/10',
                                        'warning' => 'border-warning-200 dark:border-warning-800 bg-warning-50/50 dark:bg-warning-900/10',
                                        'danger' => 'border-danger-200 dark:border-danger-800 bg-danger-50/50 dark:bg-danger-900/10',
                                        default => 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50',
                                    };

                                    $badgeClass = match ($eventColor) {
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300',
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-700 dark:text-info-300',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-300',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                                    };

                                    // Build changes display
                                    $changesHtml = '';
                                    if (! empty($oldValues) || ! empty($newValues)) {
                                        $changesHtml = '<div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 text-sm">';
                                        $changesHtml .= '<div class="font-medium text-gray-500 mb-1">Changes:</div>';
                                        $changesHtml .= '<div class="grid grid-cols-2 gap-2">';

                                        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
                                        foreach ($allKeys as $key) {
                                            /** @var mixed $oldValRaw */
                                            $oldValRaw = $oldValues[$key] ?? null;
                                            /** @var mixed $newValRaw */
                                            $newValRaw = $newValues[$key] ?? null;

                                            // Format values for display (handle arrays, objects, etc.)
                                            $oldValStr = $this->formatAuditValue($oldValRaw);
                                            $newValStr = $this->formatAuditValue($newValRaw);

                                            $changesHtml .= "<div class='col-span-2 flex items-center gap-2'>";
                                            $changesHtml .= "<span class='text-gray-400'>".e($key).':</span>';
                                            $changesHtml .= "<span class='text-danger-500 line-through'>{$oldValStr}</span>";
                                            $changesHtml .= "<span class='text-gray-400'>→</span>";
                                            $changesHtml .= "<span class='text-success-500'>{$newValStr}</span>";
                                            $changesHtml .= '</div>';
                                        }

                                        $changesHtml .= '</div></div>';
                                    }

                                    $html .= <<<HTML
                                    <div class="p-4 rounded-lg border {$colorClass}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 text-sm font-medium rounded {$badgeClass}">{$eventLabel}</span>
                                            </div>
                                            <span class="text-sm text-gray-500">{$timestamp}</span>
                                        </div>
                                        <div class="text-sm">
                                            <span class="text-gray-500">By:</span>
                                            <span class="ml-1">{$userName}</span>
                                        </div>
                                        {$changesHtml}
                                    </div>
                                    HTML;
                                }

                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Discrepancy Resolution Events')
                    ->description('Correction events for this batch (see Discrepancy Resolution tab for details)')
                    ->visible(fn (InboundBatch $record): bool => $record->discrepancyResolutions()->count() > 0)
                    ->schema([
                        TextEntry::make('discrepancy_count')
                            ->label('')
                            ->getStateUsing(function (InboundBatch $record): string {
                                $count = $record->discrepancyResolutions()->count();

                                return "{$count} discrepancy resolution event(s) recorded. See the Discrepancy Resolution tab for full details.";
                            })
                            ->icon('heroicon-o-document-check')
                            ->color('info'),
                    ]),
            ]);
    }

    /**
     * Format a value from audit log for display.
     */
    protected function formatAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '<em>null</em>';
        }

        if (is_array($value)) {
            return e(json_encode($value) ?: '[]');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return e((string) $value);
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
     *
     * Pre-checks (hard blockers):
     * - location.serialization_authorized = true
     * - batch.serialization_status != discrepancy
     *
     * @see US-B020
     */
    protected function getStartSerializationAction(): Action
    {
        return Action::make('startSerialization')
            ->label('Start Serialization')
            ->icon('heroicon-o-qr-code')
            ->color('success')
            ->visible(fn (InboundBatch $record): bool => $record->canStartSerialization())
            ->requiresConfirmation()
            ->modalHeading('Start Serialization')
            ->modalDescription(function (InboundBatch $record): string {
                $remaining = $record->remaining_unserialized;
                $location = $record->receivingLocation;
                $locationName = $location !== null ? $location->name : 'Unknown';
                $allocationId = $record->allocation_id ?? 'None';

                return "You are about to serialize bottles from this inbound batch at location '{$locationName}'. {$remaining} bottles are available for serialization. All serialized bottles will inherit allocation lineage #{$allocationId}.";
            })
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->schema([
                Section::make()
                    ->schema([
                        Placeholder::make('serialization_warning')
                            ->label('')
                            ->content(fn (): Htmlable => new HtmlString(
                                '<div class="p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-6 h-6 text-warning-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-warning-700 dark:text-warning-300">Warning: This action creates permanent records</p>
                                            <ul class="mt-2 text-sm text-warning-600 dark:text-warning-400 list-disc list-inside space-y-1">
                                                <li>Each bottle will receive a <strong>unique serial number</strong> that cannot be changed</li>
                                                <li>Allocation lineage will be <strong>permanently assigned</strong> and is immutable</li>
                                                <li>NFT provenance minting will be <strong>automatically queued</strong></li>
                                                <li>This operation <strong>cannot be undone</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>'
                            )),
                    ])
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->label('Quantity to Serialize')
                    ->helperText(fn (InboundBatch $record): string => "Maximum: {$record->remaining_unserialized} bottles")
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (InboundBatch $record): int => $record->remaining_unserialized)
                    ->default(fn (InboundBatch $record): int => $record->remaining_unserialized)
                    ->suffix('bottles'),
                Checkbox::make('confirm_serialization')
                    ->label('I understand that serialization creates permanent, immutable records and cannot be undone')
                    ->required()
                    ->accepted(),
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
                } catch (InvalidArgumentException $e) {
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
     * Creates an immutable InventoryException record (correction event).
     * Original values are never overwritten - delta record approach.
     */
    protected function getResolveDiscrepancyAction(): Action
    {
        return Action::make('resolveDiscrepancy')
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

                return "Expected: {$expected} bottles, Received: {$received} bottles. This is a {$type} of ".abs($delta).' bottles. An immutable correction event will be created to preserve the full audit trail.';
            })
            ->schema([
                Section::make('Quantity Comparison')
                    ->description('Original values will be preserved')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('expected_display')
                                    ->label('Expected Quantity')
                                    ->content(fn (InboundBatch $record): string => "{$record->quantity_expected} bottles"),
                                Placeholder::make('received_display')
                                    ->label('Received Quantity')
                                    ->content(fn (InboundBatch $record): string => "{$record->quantity_received} bottles"),
                            ]),
                    ]),
                Select::make('resolution_type')
                    ->label('Resolution Reason')
                    ->options([
                        DiscrepancyResolution::Shortage->value => DiscrepancyResolution::Shortage->label().' - Accept lower quantity as final',
                        DiscrepancyResolution::Overage->value => DiscrepancyResolution::Overage->label().' - Accept higher quantity as final',
                        DiscrepancyResolution::Damage->value => DiscrepancyResolution::Damage->label().' - Bottles damaged in transit',
                        DiscrepancyResolution::Other->value => DiscrepancyResolution::Other->label().' - See notes for details',
                    ])
                    ->required()
                    ->native(false)
                    ->helperText('Select the reason that best describes this discrepancy'),
                Textarea::make('reason')
                    ->label('Detailed Explanation')
                    ->helperText('Provide a detailed explanation of the discrepancy and how it was investigated')
                    ->required()
                    ->rows(4)
                    ->placeholder('Describe the discrepancy investigation, findings, and justification for the resolution...'),
                TextInput::make('document_reference')
                    ->label('Evidence / Document Reference')
                    ->helperText('Reference to supporting documents (e.g., delivery note, photos, incident report)')
                    ->placeholder('e.g., DN-2024-001, Photo evidence in SharePoint'),
                Checkbox::make('confirm_audit')
                    ->label('I confirm this resolution will be recorded as an immutable audit record')
                    ->required()
                    ->accepted(),
            ])
            ->action(function (InboundBatch $record, array $data): void {
                $user = auth()->user();

                if (! $user) {
                    Notification::make()
                        ->title('Authentication required')
                        ->danger()
                        ->send();

                    return;
                }

                // Create immutable correction event (InventoryException)
                $exceptionType = 'discrepancy_'.$data['resolution_type'];
                $reason = $data['reason'];
                if (! empty($data['document_reference'])) {
                    $reason .= "\n\nEvidence Reference: ".$data['document_reference'];
                }

                // Add context about the discrepancy
                $delta = $record->quantity_delta;
                $discrepancyContext = $delta > 0
                    ? "Overage of {$delta} bottles (Expected: {$record->quantity_expected}, Received: {$record->quantity_received})"
                    : 'Shortage of '.abs($delta)." bottles (Expected: {$record->quantity_expected}, Received: {$record->quantity_received})";
                $reason = "Discrepancy: {$discrepancyContext}\n\n{$reason}";

                // Create the immutable InventoryException record
                $exception = InventoryException::create([
                    'exception_type' => $exceptionType,
                    'inbound_batch_id' => $record->id,
                    'reason' => $reason,
                    'resolution' => 'Discrepancy acknowledged and resolved. Serialization unblocked.',
                    'resolved_at' => now(),
                    'resolved_by' => $user->id,
                    'created_by' => $user->id,
                ]);

                // Update the batch status to allow serialization
                // NOTE: Original quantity values are NOT modified - they are preserved for audit
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
                ]);

                Notification::make()
                    ->title('Discrepancy Resolved')
                    ->body("Correction event #{$exception->id} created. Status updated from {$previousStatus->label()} to {$newStatus->label()}. Serialization is now available.")
                    ->success()
                    ->send();

                $this->refreshFormData(['serialization_status']);
            });
    }
}
