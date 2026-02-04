<?php

namespace App\Filament\Resources\Inventory\CaseResource\Pages;

use App\Filament\Resources\Inventory\CaseResource;
use App\Models\Inventory\InventoryCase;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCase extends ViewRecord
{
    protected static string $resource = CaseResource::class;

    public function getTitle(): string
    {
        /** @var InventoryCase $record */
        $record = $this->record;

        return "Case: {$record->display_label}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        // Basic view - full detail tabs implemented in US-B031
        return $infolist
            ->schema([
                Section::make('Case Information')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Case ID')
                            ->copyable(),

                        TextEntry::make('integrity_status')
                            ->label('Integrity Status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state->label())
                            ->color(fn ($state): string => $state->color())
                            ->icon(fn ($state): string => $state->icon()),

                        TextEntry::make('is_original')
                            ->label('Original Producer Case')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-badge' : 'heroicon-o-minus-circle'),

                        TextEntry::make('is_breakable')
                            ->label('Breakable')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-scissors' : 'heroicon-o-lock-closed'),

                        TextEntry::make('currentLocation.name')
                            ->label('Current Location')
                            ->icon('heroicon-o-map-pin'),

                        TextEntry::make('bottle_count')
                            ->label('Bottles in Case')
                            ->getStateUsing(fn (InventoryCase $record): int => $record->bottle_count)
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        // Actions will be implemented in US-B031 and US-B032 (Break Case action)
        return [];
    }
}
