<?php

namespace App\Filament\Resources\BundleResource\RelationManagers;

use App\Models\Commercial\Bundle;
use App\Models\Commercial\BundleComponent;
use App\Models\Pim\SellableSku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Bundle Components';

    protected static ?string $modelLabel = 'Component';

    protected static ?string $pluralModelLabel = 'Components';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('sellable_sku_id')
                    ->label('Sellable SKU')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        // Get existing component SKU IDs for this bundle
                        $existingSkuIds = $this->getOwnerRecord() instanceof Bundle
                            ? $this->getOwnerRecord()->components()->pluck('sellable_sku_id')->toArray()
                            : [];

                        return SellableSku::query()
                            ->where('lifecycle_status', 'active')
                            ->whereNotIn('id', $existingSkuIds)
                            ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                            ->get()
                            ->mapWithKeys(function (SellableSku $sku) {
                                $wineVariant = $sku->wineVariant;
                                $wineName = 'Unknown';
                                $vintage = '';
                                if ($wineVariant !== null) {
                                    $wineMaster = $wineVariant->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                    $vintage = (string) $wineVariant->vintage_year;
                                }
                                $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '';
                                $caseConfig = $sku->caseConfiguration;
                                $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.' btl' : '';

                                return [
                                    $sku->id => $sku->sku_code.' - '.$wineName.' '.$vintage.' ('.$format.', '.$packaging.')',
                                ];
                            })
                            ->toArray();
                    })
                    ->helperText('Only active SKUs not already in this bundle are shown')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(100)
                    ->step(1)
                    ->helperText('Number of this SKU in the bundle'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('sellableSku.sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('wine_name')
                    ->label('Wine')
                    ->getStateUsing(function (BundleComponent $record): string {
                        $sku = $record->sellableSku;
                        if ($sku === null) {
                            return 'Unknown';
                        }
                        $wineVariant = $sku->wineVariant;
                        if ($wineVariant === null) {
                            return 'Unknown';
                        }
                        $wineMaster = $wineVariant->wineMaster;
                        if ($wineMaster === null) {
                            return 'Unknown';
                        }
                        $vintage = $wineVariant->vintage_year;

                        return $wineMaster->name.' '.$vintage;
                    })
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('sellableSku.wineVariant.wineMaster', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->getStateUsing(function (BundleComponent $record): string {
                        $format = $record->sellableSku?->format;

                        return $format !== null ? $format->volume_ml.'ml' : 'N/A';
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('packaging')
                    ->label('Packaging')
                    ->getStateUsing(function (BundleComponent $record): string {
                        $caseConfig = $record->sellableSku?->caseConfiguration;
                        if ($caseConfig === null) {
                            return 'N/A';
                        }

                        return $caseConfig->bottles_per_case.' btl/'.$caseConfig->case_type;
                    }),
                Tables\Columns\TextInputColumn::make('quantity')
                    ->label('Qty')
                    ->rules(['required', 'integer', 'min:1', 'max:100'])
                    ->disabled(function (): bool {
                        $record = $this->getOwnerRecord();

                        return $record instanceof Bundle && ! $record->isEditable();
                    })
                    ->afterStateUpdated(function (BundleComponent $record, $state): void {
                        $record->quantity = (int) $state;
                        $record->save();

                        Notification::make()
                            ->title('Quantity Updated')
                            ->success()
                            ->send();
                    }),
                Tables\Columns\IconColumn::make('sku_active')
                    ->label('SKU Status')
                    ->getStateUsing(fn (BundleComponent $record): bool => $record->hasActiveSku())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (BundleComponent $record): string => $record->hasActiveSku() ? 'SKU is active' : 'SKU is not active - may affect bundle activation'),
                Tables\Columns\IconColumn::make('has_allocation')
                    ->label('Allocation')
                    ->getStateUsing(fn (BundleComponent $record): bool => $record->hasAllocation())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (BundleComponent $record): string => $record->hasAllocation() ? 'Has active allocation' : 'No active allocation'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('sku_active')
                    ->label('SKU Status')
                    ->queries(
                        true: fn ($query) => $query->whereHas('sellableSku', fn ($q) => $q->where('lifecycle_status', 'active')),
                        false: fn ($query) => $query->whereHas('sellableSku', fn ($q) => $q->where('lifecycle_status', '!=', 'active')),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(function (): bool {
                        $record = $this->getOwnerRecord();

                        return $record instanceof Bundle && $record->isEditable();
                    })
                    ->modalHeading('Add Component')
                    ->modalDescription('Add a Sellable SKU to this bundle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function (): bool {
                        $record = $this->getOwnerRecord();

                        return $record instanceof Bundle && $record->isEditable();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function (): bool {
                        $record = $this->getOwnerRecord();

                        return $record instanceof Bundle && $record->isEditable();
                    })
                    ->before(function (BundleComponent $record): void {
                        $bundle = $record->bundle;
                        if ($bundle !== null && $bundle->components()->count() <= 1) {
                            Notification::make()
                                ->title('Cannot Delete')
                                ->body('A bundle must have at least one component. Delete the bundle instead.')
                                ->warning()
                                ->send();

                            $this->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function (): bool {
                            $record = $this->getOwnerRecord();

                            return $record instanceof Bundle && $record->isEditable();
                        })
                        ->before(function ($records): void {
                            $bundle = $this->getOwnerRecord();
                            if ($bundle instanceof Bundle) {
                                $totalComponents = $bundle->components()->count();
                                $deleteCount = $records->count();

                                if ($deleteCount >= $totalComponents) {
                                    Notification::make()
                                        ->title('Cannot Delete All')
                                        ->body('A bundle must have at least one component remaining.')
                                        ->warning()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No Components')
            ->emptyStateDescription(function (): string {
                $record = $this->getOwnerRecord();
                $isEditable = $record instanceof Bundle && $record->isEditable();

                return $isEditable
                    ? 'Add Sellable SKUs to this bundle to create a commercial package.'
                    : 'This bundle has no components.';
            })
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First Component')
                    ->visible(function (): bool {
                        $record = $this->getOwnerRecord();

                        return $record instanceof Bundle && $record->isEditable();
                    }),
            ])
            ->defaultSort('created_at', 'asc');
    }

    /**
     * Stop the current action.
     */
    protected function halt(): void
    {
        throw new \Filament\Support\Exceptions\Halt;
    }
}
