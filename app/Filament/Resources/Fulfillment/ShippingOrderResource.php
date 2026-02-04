<?php

namespace App\Filament\Resources\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShippingOrderResource extends Resource
{
    protected static ?string $model = ShippingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Shipping Orders';

    protected static ?string $modelLabel = 'Shipping Order';

    protected static ?string $pluralModelLabel = 'Shipping Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form will be implemented in US-C018-C022 (creation wizard)
                // For now, provide basic fields for view/edit
                Forms\Components\Section::make('Customer & Destination')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Select::make('source_warehouse_id')
                            ->label('Source Warehouse')
                            ->relationship('sourceWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Shipping Method')
                    ->schema([
                        Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->maxLength(255)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\TextInput::make('shipping_method')
                            ->label('Shipping Method')
                            ->maxLength(255)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Select::make('incoterms')
                            ->label('Incoterms')
                            ->options([
                                'EXW' => 'EXW - Ex Works',
                                'FCA' => 'FCA - Free Carrier',
                                'DDP' => 'DDP - Delivered Duty Paid',
                                'DAP' => 'DAP - Delivered at Place',
                            ])
                            ->native(false)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\DatePicker::make('requested_ship_date')
                            ->label('Requested Ship Date')
                            ->minDate(now())
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Packaging & Instructions')
                    ->schema([
                        Forms\Components\Select::make('packaging_preference')
                            ->label('Packaging Preference')
                            ->options(fn (): array => collect(\App\Enums\Fulfillment\PackagingPreference::cases())
                                ->mapWithKeys(fn (\App\Enums\Fulfillment\PackagingPreference $pref): array => [$pref->value => $pref->label()])
                                ->toArray())
                            ->native(false)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

                        Forms\Components\Textarea::make('special_instructions')
                            ->label('Special Instructions')
                            ->rows(3)
                            ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('SO ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SO ID copied')
                    ->limit(8)
                    ->tooltip(fn (ShippingOrder $record): string => $record->id),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ShippingOrder $record): ?string => $record->customer
                        ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('sourceWarehouse.name')
                    ->label('Source Warehouse')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Vouchers')
                    ->counts('lines')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ShippingOrderStatus $state): string => $state->label())
                    ->color(fn (ShippingOrderStatus $state): string => $state->color())
                    ->icon(fn (ShippingOrderStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('carrier')
                    ->label('Carrier')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_ship_date')
                    ->label('Requested Ship Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ShippingOrderStatus::cases())
                        ->mapWithKeys(fn (ShippingOrderStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->default(fn (): array => [
                        ShippingOrderStatus::Draft->value,
                        ShippingOrderStatus::Planned->value,
                        ShippingOrderStatus::Picking->value,
                        ShippingOrderStatus::Shipped->value,
                        ShippingOrderStatus::OnHold->value,
                    ])
                    ->label('Status'),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Customer'),

                Tables\Filters\SelectFilter::make('source_warehouse_id')
                    ->relationship('sourceWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Source Warehouse'),

                Tables\Filters\Filter::make('requested_ship_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('requested_from')
                            ->label('Requested From'),
                        Forms\Components\DatePicker::make('requested_until')
                            ->label('Requested Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['requested_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_ship_date', '>=', $date),
                            )
                            ->when(
                                $data['requested_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_ship_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ShippingOrder $record): bool => $record->isDraft()),
            ])
            ->bulkActions([
                // Limited bulk actions as per US-C023
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => false), // Disabled for safety
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['customer', 'sourceWarehouse'])
                ->withCount('lines'));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in later US stories
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingOrders::route('/'),
            'create' => Pages\CreateShippingOrder::route('/create'),
            'view' => Pages\ViewShippingOrder::route('/{record}'),
            'edit' => Pages\EditShippingOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    /**
     * Check if the current user can create shipping orders.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Check if a record can be deleted.
     * Only draft orders can be deleted.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var ShippingOrder $record */
        return $record->isDraft();
    }

    /**
     * Check if a record can be edited.
     * Only draft orders can be edited.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var ShippingOrder $record */
        return $record->isDraft();
    }
}
