<?php

namespace App\Filament\Resources\Inventory;

use App\Enums\Inventory\InboundBatchStatus;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\CreateInboundBatch;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ListInboundBatches;
use App\Filament\Resources\Inventory\InboundBatchResource\Pages\ViewInboundBatch;
use App\Models\Allocation\Allocation;
use App\Models\Inventory\InboundBatch;
use App\Models\Inventory\Location;
use App\Models\Pim\WineVariant;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InboundBatchResource extends Resource
{
    protected static ?string $model = InboundBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Inbound Batches';

    protected static ?string $modelLabel = 'Inbound Batch';

    protected static ?string $pluralModelLabel = 'Inbound Batches';

    /**
     * Check if the current user can create inbound batches manually.
     * Only admins (admin or super_admin) can create batches manually.
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Warning Banner Section
            Section::make()
                ->schema([
                    Placeholder::make('warning_banner')
                        ->label('')
                        ->content('⚠️ MANUAL CREATION - This action bypasses normal WMS/procurement workflows. Manual batch creation is for exceptional cases only and requires full audit justification. All data entered will be permanently recorded.')
                        ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400 font-semibold']),
                ])
                ->extraAttributes(['class' => 'bg-danger-50 dark:bg-danger-950/30 border-danger-300 dark:border-danger-700']),

            // Mandatory Reason Section
            Section::make('Audit Justification')
                ->description('Required: Explain why this batch is being created manually instead of through normal workflows.')
                ->schema([
                    Textarea::make('manual_creation_reason')
                        ->label('Reason for Manual Creation')
                        ->placeholder('Explain why this batch is being created manually (e.g., WMS system failure, legacy data migration, correction of missing records, etc.)')
                        ->required()
                        ->minLength(20)
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Minimum 20 characters. Be specific about the circumstances requiring manual entry.')
                        ->columnSpanFull(),
                ])
                ->collapsible(false)
                ->extraAttributes(['class' => 'bg-warning-50 dark:bg-warning-950/30 border-warning-300 dark:border-warning-700']),

            // Source Information
            Section::make('Source Information')
                ->schema([
                    Select::make('source_type')
                        ->label('Source Type')
                        ->options([
                            'producer' => 'Producer',
                            'supplier' => 'Supplier',
                            'transfer' => 'Transfer',
                        ])
                        ->required()
                        ->native(false),

                    Select::make('product_reference_type')
                        ->label('Product Reference Type')
                        ->options([
                            WineVariant::class => 'Wine Variant',
                        ])
                        ->required()
                        ->native(false)
                        ->default(WineVariant::class)
                        ->live(),

                    Select::make('product_reference_id')
                        ->label('Product Reference')
                        ->options(function (): array {
                            return WineVariant::query()
                                ->with('wineMaster')
                                ->get()
                                ->mapWithKeys(function (WineVariant $variant): array {
                                    $wineMaster = $variant->wineMaster;
                                    $wineName = $wineMaster ? $wineMaster->name : 'Unknown Wine';
                                    $vintage = $variant->vintage_year ?? 'NV';

                                    return [$variant->id => "{$wineName} {$vintage}"];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Select the wine product for this inbound batch'),

                    Select::make('allocation_id')
                        ->label('Allocation (Lineage)')
                        ->options(function (): array {
                            return Allocation::query()
                                ->with(['wineVariant.wineMaster', 'format'])
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->mapWithKeys(function (Allocation $allocation): array {
                                    return [$allocation->id => $allocation->getBottleSkuLabel().' (ID: '.substr($allocation->id, 0, 8).'...)'];
                                })
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Required: The allocation that this batch belongs to. This will be propagated to all serialized bottles.'),
                ])
                ->columns(2),

            // Quantities Section
            Section::make('Quantities')
                ->schema([
                    TextInput::make('quantity_expected')
                        ->label('Quantity Expected')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(1)
                        ->helperText('The quantity that was expected to arrive'),

                    TextInput::make('quantity_received')
                        ->label('Quantity Received')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(1)
                        ->helperText('The actual quantity received (may differ from expected)'),

                    Select::make('packaging_type')
                        ->label('Packaging Type')
                        ->options([
                            'bottles' => 'Bottles',
                            'cases' => 'Cases',
                            'pallets' => 'Pallets',
                            'mixed' => 'Mixed',
                        ])
                        ->required()
                        ->native(false)
                        ->default('bottles'),
                ])
                ->columns(3),

            // Location & Ownership Section
            Section::make('Location & Ownership')
                ->schema([
                    Select::make('receiving_location_id')
                        ->label('Receiving Location')
                        ->relationship('receivingLocation', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('The physical location where this batch is received'),

                    Select::make('ownership_type')
                        ->label('Ownership Type')
                        ->options(collect(OwnershipType::cases())
                            ->mapWithKeys(fn (OwnershipType $type): array => [$type->value => $type->label()])
                            ->toArray())
                        ->required()
                        ->native(false)
                        ->default(OwnershipType::CururatedOwned->value),

                    DatePicker::make('received_date')
                        ->label('Received Date')
                        ->required()
                        ->default(now())
                        ->maxDate(now())
                        ->helperText('The date when this batch was physically received'),
                ])
                ->columns(3),

            // Serialization Status Section
            Section::make('Serialization')
                ->schema([
                    Select::make('serialization_status')
                        ->label('Serialization Status')
                        ->options(collect(InboundBatchStatus::cases())
                            ->mapWithKeys(fn (InboundBatchStatus $status): array => [$status->value => $status->label()])
                            ->toArray())
                        ->required()
                        ->native(false)
                        ->default(InboundBatchStatus::PendingSerialization->value)
                        ->helperText('Set to Pending Serialization for new batches that need to be serialized'),
                ])
                ->columns(1),

            // Additional Information Section
            Section::make('Additional Information')
                ->schema([
                    TextInput::make('wms_reference_id')
                        ->label('WMS Reference ID')
                        ->maxLength(255)
                        ->helperText('Optional: External WMS system reference (if applicable)'),

                    Textarea::make('condition_notes')
                        ->label('Condition Notes')
                        ->rows(3)
                        ->maxLength(2000)
                        ->helperText('Optional: Notes about the condition of the batch upon receipt'),
                ])
                ->columns(1)
                ->collapsible(),

            // Audit Confirmation Section
            Section::make('Confirmation')
                ->schema([
                    Checkbox::make('audit_confirmation')
                        ->label('I confirm that this manual batch creation is necessary and I have provided accurate justification')
                        ->required()
                        ->accepted()
                        ->helperText('This action will be logged for audit purposes'),
                ])
                ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-950/30']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Batch ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Batch ID copied')
                    ->weight('bold')
                    ->limit(8)
                    ->tooltip(fn (InboundBatch $record): string => $record->id),

                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'producer' => 'success',
                        'supplier' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('product_reference_type')
                    ->label('Product Reference')
                    ->formatStateUsing(function (InboundBatch $record): string {
                        $type = class_basename($record->product_reference_type);

                        return $type.' #'.substr((string) $record->product_reference_id, 0, 8);
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('product_reference_id', 'like', "%{$search}%");
                    })
                    ->sortable(),

                TextColumn::make('quantity_expected')
                    ->label('Expected')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('quantity_received')
                    ->label('Received')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color(fn (InboundBatch $record): string => $record->hasDiscrepancy() ? 'danger' : 'success'),

                TextColumn::make('packaging_type')
                    ->label('Packaging')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('receivingLocation.name')
                    ->label('Receiving Location')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-map-pin')
                    ->limit(20),

                TextColumn::make('received_date')
                    ->label('Received Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('serialization_status')
                    ->label('Serialization')
                    ->badge()
                    ->formatStateUsing(fn (InboundBatchStatus $state): string => $state->label())
                    ->color(fn (InboundBatchStatus $state): string => $state->color())
                    ->icon(fn (InboundBatchStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('ownership_type')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipType $state): string => $state->label())
                    ->color(fn (OwnershipType $state): string => $state->color())
                    ->icon(fn (OwnershipType $state): string => $state->icon())
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('has_discrepancy')
                    ->label('Discrepancy')
                    ->state(fn (InboundBatch $record): bool => $record->hasDiscrepancy())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('quantity_expected != quantity_received '.$direction);
                    }),

                IconColumn::make('pending_serialization_indicator')
                    ->label('Pending')
                    ->state(fn (InboundBatch $record): bool => $record->serialization_status->canStartSerialization() && $record->remaining_unserialized > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(serialization_status IN ('pending_serialization', 'partially_serialized')) ".$direction);
                    }),
            ])
            ->filters([
                SelectFilter::make('serialization_status')
                    ->options(collect(InboundBatchStatus::cases())
                        ->mapWithKeys(fn (InboundBatchStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Serialization Status'),

                SelectFilter::make('receiving_location_id')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->label('Receiving Location'),

                SelectFilter::make('ownership_type')
                    ->options(collect(OwnershipType::cases())
                        ->mapWithKeys(fn (OwnershipType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Ownership Type'),

                Filter::make('received_date_range')
                    ->schema([
                        DatePicker::make('received_from')
                            ->label('Received From'),
                        DatePicker::make('received_until')
                            ->label('Received Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_date', '>=', $date),
                            )
                            ->when(
                                $data['received_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['received_from'] ?? null) {
                            $indicators['received_from'] = 'From '.Carbon::parse($data['received_from'])->toFormattedDateString();
                        }

                        if ($data['received_until'] ?? null) {
                            $indicators['received_until'] = 'Until '.Carbon::parse($data['received_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                Filter::make('has_discrepancy')
                    ->label('Has Discrepancy')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('quantity_expected', '!=', 'quantity_received'))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('received_date', 'desc')
            ->recordClasses(function (InboundBatch $record): string {
                // Highlight rows with discrepancy in red
                if ($record->serialization_status === InboundBatchStatus::Discrepancy) {
                    return 'bg-danger-50 dark:bg-danger-950/20';
                }

                // Highlight rows pending serialization in yellow
                if ($record->serialization_status->canStartSerialization() && $record->remaining_unserialized > 0) {
                    return 'bg-warning-50 dark:bg-warning-950/20';
                }

                return '';
            });
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-B016
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInboundBatches::route('/'),
            'create' => CreateInboundBatch::route('/create'),
            'view' => ViewInboundBatch::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['receivingLocation'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
