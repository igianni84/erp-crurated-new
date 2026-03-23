<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Filament\Resources\Allocation\AllocationResource\Pages\CreateAllocation;
use App\Filament\Resources\Allocation\AllocationResource\Pages\EditAllocation;
use App\Filament\Resources\Allocation\AllocationResource\Pages\ListAllocations;
use App\Filament\Resources\Allocation\AllocationResource\Pages\ViewAllocation;
use App\Models\Allocation\Allocation;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AllocationResource extends Resource
{
    protected static ?string $model = Allocation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string|\UnitEnum|null $navigationGroup = 'Allocations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Allocations';

    protected static ?string $modelLabel = 'Allocation';

    protected static ?string $pluralModelLabel = 'Allocations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Wine & Format')
                    ->columns(2)
                    ->schema([
                        Select::make('wine_variant_id')
                            ->label('Wine Variant')
                            ->relationship('wineVariant', 'id')
                            ->getOptionLabelFromRecordUsing(function (WineVariant $record): string {
                                $wineMaster = $record->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                $vintage = $record->vintage_year ?? 'NV';

                                return "{$wineName} {$vintage}";
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),
                    ]),

                Section::make('Supply Details')
                    ->columns(2)
                    ->schema([
                        Select::make('source_type')
                            ->label('Source Type')
                            ->options(collect(AllocationSourceType::cases())
                                ->mapWithKeys(fn (AllocationSourceType $e): array => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        Select::make('supply_form')
                            ->label('Supply Form')
                            ->options(collect(AllocationSupplyForm::cases())
                                ->mapWithKeys(fn (AllocationSupplyForm $e): array => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        TextInput::make('total_quantity')
                            ->label('Total Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        Toggle::make('serialization_required')
                            ->label('Serialization Required')
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),
                    ]),

                Section::make('Availability')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('expected_availability_start')
                            ->label('Expected Availability Start')
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        DatePicker::make('expected_availability_end')
                            ->label('Expected Availability End')
                            ->native(false)
                            ->disabled(fn (?Allocation $record): bool => $record !== null && ! $record->isDraft()),

                        Select::make('status')
                            ->label('Status')
                            ->options(collect(AllocationStatus::cases())
                                ->mapWithKeys(fn (AllocationStatus $e): array => [$e->value => $e->label()])
                                ->toArray())
                            ->default(AllocationStatus::Draft->value)
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Allocation ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Allocation ID copied'),

                TextColumn::make('bottle_sku')
                    ->label('Bottle SKU')
                    ->state(fn (Allocation $record): string => $record->getBottleSkuLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineVariant', function (Builder $query) use ($search): void {
                            $query->whereHas('wineMaster', function (Builder $query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('producer', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            WineMaster::query()
                                ->select('name')
                                ->join('wine_variants', 'wine_masters.id', '=', 'wine_variants.wine_master_id')
                                ->whereColumn('wine_variants.id', 'allocations.wine_variant_id')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->wrap(),

                TextColumn::make('supply_form')
                    ->label('Supply Form')
                    ->badge()
                    ->formatStateUsing(fn (AllocationSupplyForm $state): string => $state->label())
                    ->color(fn (AllocationSupplyForm $state): string => $state->color())
                    ->icon(fn (AllocationSupplyForm $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('source_type')
                    ->label('Source Type')
                    ->badge()
                    ->formatStateUsing(fn (AllocationSourceType $state): string => $state->label())
                    ->color(fn (AllocationSourceType $state): string => $state->color())
                    ->icon(fn (AllocationSourceType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AllocationStatus $state): string => $state->label())
                    ->color(fn (AllocationStatus $state): string => $state->color())
                    ->icon(fn (AllocationStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('sold_quantity')
                    ->label('Sold Qty')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('remaining_quantity')
                    ->label('Remaining Qty')
                    ->state(fn (Allocation $record): int => $record->remaining_quantity)
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(total_quantity - sold_quantity) '.$direction);
                    })
                    ->alignEnd()
                    ->color(fn (Allocation $record): string => $record->isNearExhaustion() ? 'danger' : 'success')
                    ->weight(fn (Allocation $record): string => $record->isNearExhaustion() ? 'bold' : 'normal')
                    ->icon(fn (Allocation $record): ?string => $record->isNearExhaustion() ? 'heroicon-o-exclamation-triangle' : null),

                TextColumn::make('availability_window')
                    ->label('Availability')
                    ->state(fn (Allocation $record): string => $record->getAvailabilityWindowLabel())
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('expected_availability_start', $direction);
                    }),

                TextColumn::make('constraint_summary')
                    ->label('Constraints')
                    ->state(function (Allocation $record): string {
                        $constraint = $record->constraint;

                        return $constraint !== null ? $constraint->getSummary() : 'Not set';
                    })
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AllocationStatus::cases())
                        ->mapWithKeys(fn (AllocationStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        AllocationStatus::Draft->value,
                        AllocationStatus::Active->value,
                        AllocationStatus::Exhausted->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('source_type')
                    ->options(collect(AllocationSourceType::cases())
                        ->mapWithKeys(fn (AllocationSourceType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Source Type'),

                SelectFilter::make('supply_form')
                    ->options(collect(AllocationSupplyForm::cases())
                        ->mapWithKeys(fn (AllocationSupplyForm $form) => [$form->value => $form->label()])
                        ->toArray())
                    ->label('Supply Form'),

                Filter::make('wine_variant')
                    ->schema([
                        Select::make('wine_variant_id')
                            ->label('Bottle SKU (Wine Variant)')
                            ->relationship('wineVariant', 'id')
                            ->getOptionLabelFromRecordUsing(function (WineVariant $record): string {
                                $wineMaster = $record->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                $vintage = $record->vintage_year ?? 'NV';

                                return "{$wineName} {$vintage}";
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['wine_variant_id'] ?? null,
                            fn (Builder $query, string $wineVariantId): Builder => $query->where('wine_variant_id', $wineVariantId)
                        );
                    }),

                Filter::make('near_exhaustion')
                    ->label('Near Exhaustion')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(total_quantity - sold_quantity) < (total_quantity * 0.10)'))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['wineVariant.wineMaster', 'format', 'constraint']));
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'wineVariant.wineMaster.name'];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Allocation\Allocation> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['wineVariant.wineMaster', 'format']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Allocation $record */
        return 'Allocation #'.substr($record->id, 0, 8);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Allocation $record */
        return [
            'Wine' => $record->getBottleSkuLabel(),
            'Status' => $record->status->label(),
            'Remaining' => $record->remaining_quantity.'/'.$record->total_quantity,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getRelations(): array
    {
        return [
            AllocationResource\RelationManagers\VouchersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllocations::route('/'),
            'create' => CreateAllocation::route('/create'),
            'view' => ViewAllocation::route('/{record}'),
            'edit' => EditAllocation::route('/{record}/edit'),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Allocation\Allocation> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
