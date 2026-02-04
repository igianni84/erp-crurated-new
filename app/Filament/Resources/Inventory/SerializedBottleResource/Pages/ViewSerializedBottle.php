<?php

namespace App\Filament\Resources\Inventory\SerializedBottleResource\Pages;

use App\Filament\Resources\Inventory\SerializedBottleResource;
use App\Models\Inventory\SerializedBottle;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSerializedBottle extends ViewRecord
{
    protected static string $resource = SerializedBottleResource::class;

    public function getTitle(): string
    {
        /** @var SerializedBottle $record */
        $record = $this->record;

        return "Bottle: {$record->serial_number}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        // Basic view - full implementation in US-B026
        return $infolist
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('serial_number')
                            ->label('Serial Number')
                            ->weight('bold')
                            ->copyable()
                            ->icon('heroicon-o-qr-code'),

                        TextEntry::make('wine_variant_display')
                            ->label('Wine')
                            ->state(function (SerializedBottle $record): string {
                                $wineVariant = $record->wineVariant;
                                if ($wineVariant === null) {
                                    return 'Unknown Wine';
                                }
                                $wineMaster = $wineVariant->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                $vintage = $wineVariant->vintage_year ?? 'NV';

                                return "{$wineName} {$vintage}";
                            }),

                        TextEntry::make('format.name')
                            ->label('Format')
                            ->placeholder('Standard'),

                        TextEntry::make('allocation_id')
                            ->label('Allocation Lineage')
                            ->state(function (SerializedBottle $record): string {
                                $allocation = $record->allocation;
                                if ($allocation === null) {
                                    return 'N/A';
                                }

                                return $allocation->getBottleSkuLabel();
                            })
                            ->helperText(fn (SerializedBottle $record): string => "ID: {$record->allocation_id}")
                            ->weight('bold')
                            ->color('primary'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        TextEntry::make('state')
                            ->label('State')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color())
                            ->icon(fn ($state) => $state->icon()),

                        TextEntry::make('ownership_type')
                            ->label('Ownership')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color())
                            ->icon(fn ($state) => $state->icon()),

                        TextEntry::make('currentLocation.name')
                            ->label('Current Location')
                            ->icon('heroicon-o-map-pin'),

                        TextEntry::make('custody_holder')
                            ->label('Custody Holder')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Serialization')
                    ->schema([
                        TextEntry::make('serialized_at')
                            ->label('Serialized At')
                            ->dateTime(),

                        TextEntry::make('serializedByUser.name')
                            ->label('Serialized By')
                            ->placeholder('System'),

                        TextEntry::make('inboundBatch.id')
                            ->label('Inbound Batch')
                            ->state(fn (SerializedBottle $record): string => 'Batch #'.substr($record->inbound_batch_id, 0, 8).'...')
                            ->url(fn (SerializedBottle $record): string => route('filament.admin.resources.inventory.inbound-batches.view', ['record' => $record->inbound_batch_id]))
                            ->color('primary'),
                    ])
                    ->columns(3),

                Section::make('NFT Provenance')
                    ->schema([
                        TextEntry::make('nft_reference')
                            ->label('NFT Reference')
                            ->placeholder('Pending minting...')
                            ->copyable()
                            ->icon(fn (SerializedBottle $record): string => $record->hasNft() ? 'heroicon-o-check-badge' : 'heroicon-o-clock'),

                        TextEntry::make('nft_minted_at')
                            ->label('Minted At')
                            ->dateTime()
                            ->placeholder('Not yet minted'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        // Actions will be implemented in US-B027, US-B028, US-B029
        return [];
    }
}
