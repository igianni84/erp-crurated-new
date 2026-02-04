<?php

namespace App\Filament\Resources\Inventory\InventoryMovementResource\Pages;

use App\Enums\Inventory\MovementTrigger;
use App\Enums\Inventory\MovementType;
use App\Filament\Resources\Inventory\InventoryMovementResource;
use App\Models\Inventory\InventoryMovement;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

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
                Section::make('Movement Summary')
                    ->description('Read-only movement record. Movements are immutable.')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Movement ID')
                            ->copyable()
                            ->weight('bold'),

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

                        TextEntry::make('sourceLocation.name')
                            ->label('Source Location')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('—'),

                        TextEntry::make('destinationLocation.name')
                            ->label('Destination Location')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('—'),

                        TextEntry::make('custody_changed')
                            ->label('Custody Changed')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('wms_event_id')
                            ->label('WMS Event ID')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('executed_at')
                            ->label('Executed At')
                            ->dateTime(),

                        TextEntry::make('executor.name')
                            ->label('Executed By')
                            ->icon('heroicon-o-user')
                            ->placeholder('System'),

                        TextEntry::make('items_count')
                            ->label('Items Count')
                            ->state(fn (InventoryMovement $record): int => $record->items_count)
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(2),

                // Full detail view will be implemented in US-B034
                Section::make('Movement Items')
                    ->description('Detailed items list will be implemented in the Movement Detail story.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('items_preview')
                            ->label('Items Preview')
                            ->state(function (InventoryMovement $record): string {
                                $items = $record->movementItems()->with(['serializedBottle', 'case'])->limit(5)->get();
                                if ($items->isEmpty()) {
                                    return 'No items in this movement.';
                                }

                                $preview = [];
                                foreach ($items as $item) {
                                    if ($item->serialized_bottle_id !== null) {
                                        $bottle = $item->serializedBottle;
                                        $serial = $bottle !== null ? substr($bottle->serial_number, 0, 15).'...' : 'Unknown';
                                        $preview[] = "Bottle: {$serial}";
                                    }
                                    if ($item->case_id !== null) {
                                        $case = $item->case;
                                        $caseId = $case !== null ? substr($case->id, 0, 8).'...' : 'Unknown';
                                        $preview[] = "Case: {$caseId}";
                                    }
                                }

                                $total = $record->items_count;
                                if ($total > 5) {
                                    $preview[] = '... and '.($total - 5).' more items';
                                }

                                return implode("\n", $preview);
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
