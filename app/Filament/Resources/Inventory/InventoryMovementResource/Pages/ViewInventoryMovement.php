<?php

namespace App\Filament\Resources\Inventory\InventoryMovementResource\Pages;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Filament\Resources\Inventory\CaseResource;
use App\Filament\Resources\Inventory\InventoryMovementResource;
use App\Filament\Resources\Inventory\SerializedBottleResource;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\MovementItem;
use App\Models\Inventory\SerializedBottle;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewInventoryMovement extends ViewRecord
{
    protected static string $resource = InventoryMovementResource::class;

    public function getTitle(): string
    {
        /** @var InventoryMovement $record */
        $record = $this->record;
        $shortId = substr($record->id, 0, 8);

        return "Movement {$shortId}...";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Immutability notice at top
                Section::make()
                    ->schema([
                        TextEntry::make('immutability_notice')
                            ->hiddenLabel()
                            ->state(new HtmlString(
                                '<div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">'.
                                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>'.
                                '<span>This movement record is <strong>immutable</strong>. Movements cannot be edited or deleted once created.</span>'.
                                '</div>'
                            ))
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800/50 rounded-lg']),

                // Movement Summary Section
                Section::make('Movement Summary')
                    ->description('Movement type, trigger, and locations')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Movement ID')
                            ->copyable()
                            ->copyMessage('Movement ID copied')
                            ->weight('bold')
                            ->icon('heroicon-o-identification'),

                        TextEntry::make('movement_type')
                            ->label('Movement Type')
                            ->badge()
                            ->formatStateUsing(fn (MovementType $state): string => $state->label())
                            ->color(fn (MovementType $state): string => $state->color())
                            ->icon(fn (MovementType $state): string => $state->icon()),

                        TextEntry::make('trigger')
                            ->label('Trigger')
                            ->badge()
                            ->formatStateUsing(fn (MovementTrigger $state): string => $state->label())
                            ->color(fn (MovementTrigger $state): string => $state->color())
                            ->icon(fn (MovementTrigger $state): string => $state->icon()),

                        TextEntry::make('custody_changed')
                            ->label('Custody Changed')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes - Custody Transferred' : 'No')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-arrow-path-rounded-square' : 'heroicon-o-minus'),
                    ])
                    ->columns(2),

                // Source and Destination Section
                Section::make('Locations')
                    ->description('Source and destination locations for this movement')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('sourceLocation.name')
                            ->label('Source Location')
                            ->icon('heroicon-o-arrow-right-start-on-rectangle')
                            ->placeholder('— (No source)')
                            ->url(function (InventoryMovement $record): ?string {
                                $location = $record->sourceLocation;
                                if ($location === null) {
                                    return null;
                                }

                                return route('filament.admin.resources.inventory.locations.view', ['record' => $location->id]);
                            })
                            ->color('primary'),

                        TextEntry::make('destinationLocation.name')
                            ->label('Destination Location')
                            ->icon('heroicon-o-arrow-right-end-on-rectangle')
                            ->placeholder('— (No destination)')
                            ->url(function (InventoryMovement $record): ?string {
                                $location = $record->destinationLocation;
                                if ($location === null) {
                                    return null;
                                }

                                return route('filament.admin.resources.inventory.locations.view', ['record' => $location->id]);
                            })
                            ->color('primary'),

                        TextEntry::make('location_summary')
                            ->label('Movement Direction')
                            ->hiddenLabel()
                            ->state(function (InventoryMovement $record): string {
                                $source = $record->sourceLocation;
                                $destination = $record->destinationLocation;

                                if ($source !== null && $destination !== null) {
                                    if ($source->id === $destination->id) {
                                        return "Status change at {$source->name} (same location)";
                                    }

                                    return "Transfer from {$source->name} to {$destination->name}";
                                }

                                if ($source !== null) {
                                    return "Outbound from {$source->name}";
                                }

                                if ($destination !== null) {
                                    return "Inbound to {$destination->name}";
                                }

                                return 'No location specified';
                            })
                            ->icon('heroicon-o-arrows-right-left')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Reason and WMS Section
                Section::make('Movement Details')
                    ->description('Reason and external references')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('No reason provided')
                            ->columnSpanFull()
                            ->markdown(),

                        TextEntry::make('wms_event_id')
                            ->label('WMS Event ID')
                            ->copyable()
                            ->copyMessage('WMS Event ID copied')
                            ->placeholder('— (Not WMS triggered)')
                            ->icon('heroicon-o-server')
                            ->visible(fn (InventoryMovement $record): bool => $record->wms_event_id !== null),

                        TextEntry::make('wms_notice')
                            ->label('WMS Status')
                            ->state('This movement was not triggered by a WMS event')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray')
                            ->visible(fn (InventoryMovement $record): bool => $record->wms_event_id === null),
                    ])
                    ->collapsible(),

                // Audit Information Section
                Section::make('Audit Information')
                    ->description('Execution details and audit trail')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('executed_at')
                            ->label('Executed At')
                            ->dateTime('F j, Y \a\t g:i:s A')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('executor.name')
                            ->label('Executed By')
                            ->icon('heroicon-o-user')
                            ->placeholder('System (Automatic)')
                            ->url(function (InventoryMovement $record): ?string {
                                $executor = $record->executor;
                                if ($executor === null) {
                                    return null;
                                }

                                // Link to user if route exists
                                return null; // User management routes may vary
                            }),

                        TextEntry::make('created_at')
                            ->label('Record Created')
                            ->dateTime('F j, Y \a\t g:i:s A')
                            ->icon('heroicon-o-document-plus'),

                        TextEntry::make('items_count')
                            ->label('Total Items')
                            ->state(fn (InventoryMovement $record): int => $record->items_count)
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-archive-box'),
                    ])
                    ->columns(2),

                // Movement Items Section - Full Detail List
                Section::make('Movement Items')
                    ->description('All items involved in this movement')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        TextEntry::make('items_summary')
                            ->hiddenLabel()
                            ->state(function (InventoryMovement $record): HtmlString {
                                $items = $record->movementItems()
                                    ->with(['serializedBottle.wineVariant.wineMaster', 'serializedBottle.format', 'case.caseConfiguration'])
                                    ->get();

                                if ($items->isEmpty()) {
                                    return new HtmlString(
                                        '<div class="text-gray-500 dark:text-gray-400 text-center py-4">'.
                                        '<svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>'.
                                        'No items recorded in this movement'.
                                        '</div>'
                                    );
                                }

                                $html = '<div class="space-y-3">';

                                $bottleCount = 0;
                                $caseCount = 0;

                                foreach ($items as $item) {
                                    /** @var MovementItem $item */
                                    $html .= '<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">';

                                    // Bottle information
                                    if ($item->hasBottle()) {
                                        $bottleCount++;
                                        /** @var SerializedBottle|null $bottle */
                                        $bottle = $item->serializedBottle;

                                        if ($bottle !== null) {
                                            $wineVariant = $bottle->wineVariant;
                                            $format = $bottle->format;

                                            $wineName = 'Unknown Wine';
                                            if ($wineVariant !== null) {
                                                $wineMaster = $wineVariant->wineMaster;
                                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                                $vintage = $wineVariant->vintage_year ?? 'NV';
                                                $wineName = "{$wineName} {$vintage}";
                                            }
                                            $formatName = $format !== null ? $format->name : 'Standard';

                                            $stateLabel = $bottle->state->label();
                                            $stateColor = $bottle->state->color();
                                            $bottleUrl = SerializedBottleResource::getUrl('view', ['record' => $bottle->id]);

                                            $html .= '<div class="flex items-start justify-between">';
                                            $html .= '<div class="flex-1">';
                                            $html .= '<div class="flex items-center gap-2 mb-1">';
                                            $html .= '<svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>';
                                            $html .= '<span class="font-semibold text-sm">Bottle</span>';
                                            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$stateColor.'-100 text-'.$stateColor.'-800 dark:bg-'.$stateColor.'-900/30 dark:text-'.$stateColor.'-400">'.$stateLabel.'</span>';
                                            $html .= '</div>';
                                            $html .= '<div class="ml-6 space-y-1 text-sm">';
                                            $html .= '<p><span class="text-gray-500">Serial:</span> <a href="'.$bottleUrl.'" class="text-primary-600 hover:underline font-mono">'.$bottle->serial_number.'</a></p>';
                                            $html .= '<p><span class="text-gray-500">Wine:</span> '.e($wineName).' ('.e($formatName).')</p>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '<a href="'.$bottleUrl.'" class="text-primary-600 hover:text-primary-700 text-sm">View →</a>';
                                            $html .= '</div>';
                                        } else {
                                            $html .= '<div class="flex items-center gap-2 text-gray-500">';
                                            $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>';
                                            $html .= '<span>Bottle (ID: '.substr($item->serialized_bottle_id ?? '', 0, 8).'...) - Record not found</span>';
                                            $html .= '</div>';
                                        }
                                    }

                                    // Case information
                                    if ($item->hasCase()) {
                                        $caseCount++;
                                        /** @var InventoryCase|null $case */
                                        $case = $item->case;

                                        if ($item->hasBottle()) {
                                            $html .= '<div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>';
                                        }

                                        if ($case !== null) {
                                            $config = $case->caseConfiguration;
                                            $configName = $config !== null ? $config->name : 'Unknown Configuration';
                                            $bottlesPerCase = $config !== null ? $config->bottles_per_case : '?';
                                            $integrityLabel = $case->integrity_status->label();
                                            $integrityColor = $case->integrity_status->color();
                                            $caseUrl = CaseResource::getUrl('view', ['record' => $case->id]);

                                            $html .= '<div class="flex items-start justify-between">';
                                            $html .= '<div class="flex-1">';
                                            $html .= '<div class="flex items-center gap-2 mb-1">';
                                            $html .= '<svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>';
                                            $html .= '<span class="font-semibold text-sm">Case</span>';
                                            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$integrityColor.'-100 text-'.$integrityColor.'-800 dark:bg-'.$integrityColor.'-900/30 dark:text-'.$integrityColor.'-400">'.$integrityLabel.'</span>';
                                            $html .= '</div>';
                                            $html .= '<div class="ml-6 space-y-1 text-sm">';
                                            $html .= '<p><span class="text-gray-500">Case ID:</span> <a href="'.$caseUrl.'" class="text-primary-600 hover:underline font-mono">'.substr($case->id, 0, 8).'...</a></p>';
                                            $html .= '<p><span class="text-gray-500">Configuration:</span> '.e($configName).' ('.$bottlesPerCase.' bottles)</p>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '<a href="'.$caseUrl.'" class="text-primary-600 hover:text-primary-700 text-sm">View →</a>';
                                            $html .= '</div>';
                                        } else {
                                            $html .= '<div class="flex items-center gap-2 text-gray-500">';
                                            $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>';
                                            $html .= '<span>Case (ID: '.substr($item->case_id ?? '', 0, 8).'...) - Record not found</span>';
                                            $html .= '</div>';
                                        }
                                    }

                                    // Item notes
                                    if ($item->notes !== null && $item->notes !== '') {
                                        $html .= '<div class="mt-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 p-2 rounded">';
                                        $html .= '<span class="font-medium">Notes:</span> '.e($item->notes);
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                // Summary footer
                                $html .= '<div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">';
                                $html .= '<strong>Summary:</strong> '.$bottleCount.' bottle(s), '.$caseCount.' case(s) involved in this movement';
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        // No actions - movements are immutable
        return [];
    }
}
