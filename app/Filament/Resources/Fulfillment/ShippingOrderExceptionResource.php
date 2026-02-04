<?php

namespace App\Filament\Resources\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource\Pages;
use App\Models\Fulfillment\ShippingOrderException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShippingOrderExceptionResource extends Resource
{
    protected static ?string $model = ShippingOrderException::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Fulfillment';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Exceptions & Holds';

    protected static ?string $modelLabel = 'Exception';

    protected static ?string $pluralModelLabel = 'Exceptions & Holds';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exception Information')
                    ->schema([
                        Forms\Components\Select::make('shipping_order_id')
                            ->label('Shipping Order')
                            ->relationship('shippingOrder', 'id')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        Forms\Components\Select::make('shipping_order_line_id')
                            ->label('Shipping Order Line')
                            ->relationship('shippingOrderLine', 'id')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->placeholder('Order-level exception'),

                        Forms\Components\Select::make('exception_type')
                            ->label('Exception Type')
                            ->options(collect(ShippingOrderExceptionType::cases())
                                ->mapWithKeys(fn (ShippingOrderExceptionType $type) => [$type->value => $type->label()])
                                ->toArray())
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(collect(ShippingOrderExceptionStatus::cases())
                                ->mapWithKeys(fn (ShippingOrderExceptionStatus $status) => [$status->value => $status->label()])
                                ->toArray())
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('resolution_path')
                            ->label('Resolution Path')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Resolution')
                    ->schema([
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->disabled(),

                        Forms\Components\Select::make('resolved_by')
                            ->label('Resolved By')
                            ->relationship('resolvedByUser', 'name')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn (?ShippingOrderException $record): bool => $record?->isResolved() ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Exception ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Exception ID copied')
                    ->limit(8)
                    ->tooltip(fn (ShippingOrderException $record): string => $record->id),

                Tables\Columns\TextColumn::make('shippingOrder.id')
                    ->label('SO ID')
                    ->searchable()
                    ->sortable()
                    ->limit(8)
                    ->url(fn (ShippingOrderException $record): ?string => $record->shippingOrder
                        ? route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->shippingOrder])
                        : null)
                    ->color('primary')
                    ->tooltip(fn (ShippingOrderException $record): string => $record->shipping_order_id),

                Tables\Columns\TextColumn::make('exception_type')
                    ->label('Exception Type')
                    ->badge()
                    ->formatStateUsing(fn (ShippingOrderExceptionType $state): string => $state->label())
                    ->color(fn (ShippingOrderExceptionType $state): string => $state->color())
                    ->icon(fn (ShippingOrderExceptionType $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn (ShippingOrderException $record): string => $record->description)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ShippingOrderExceptionStatus $state): string => $state->label())
                    ->color(fn (ShippingOrderExceptionStatus $state): string => $state->color())
                    ->icon(fn (ShippingOrderExceptionStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdByUser.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('resolvedByUser.name')
                    ->label('Resolved By')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exception_type')
                    ->options(collect(ShippingOrderExceptionType::cases())
                        ->mapWithKeys(fn (ShippingOrderExceptionType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Exception Type'),

                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ShippingOrderExceptionStatus::cases())
                        ->mapWithKeys(fn (ShippingOrderExceptionStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status')
                    ->default([ShippingOrderExceptionStatus::Active->value]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Created from '.$data['created_from'];
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Created until '.$data['created_until'];
                        }

                        return $indicators;
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Export CSV
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): \Symfony\Component\HttpFoundation\StreamedResponse {
                            return response()->streamDownload(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                if ($handle === false) {
                                    return;
                                }

                                // CSV header
                                fputcsv($handle, [
                                    'Exception ID',
                                    'SO ID',
                                    'Line ID',
                                    'Exception Type',
                                    'Description',
                                    'Resolution Path',
                                    'Status',
                                    'Created At',
                                    'Created By',
                                    'Resolved At',
                                    'Resolved By',
                                ]);

                                // CSV rows
                                foreach ($records as $record) {
                                    /** @var ShippingOrderException $record */
                                    $createdByName = $record->createdByUser !== null ? $record->createdByUser->name : '-';
                                    $resolvedByName = $record->resolvedByUser !== null ? $record->resolvedByUser->name : '-';

                                    fputcsv($handle, [
                                        $record->id,
                                        $record->shipping_order_id,
                                        $record->shipping_order_line_id ?? '-',
                                        $record->exception_type->label(),
                                        $record->description,
                                        $record->resolution_path ?? '-',
                                        $record->status->label(),
                                        $record->created_at?->format('Y-m-d H:i:s'),
                                        $createdByName,
                                        $record->resolved_at?->format('Y-m-d H:i:s') ?? '-',
                                        $resolvedByName,
                                    ]);
                                }

                                fclose($handle);
                            }, 'exceptions-'.now()->format('Y-m-d-His').'.csv');
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search by exception ID or SO ID...')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['shippingOrder', 'shippingOrderLine', 'createdByUser', 'resolvedByUser']))
            ->striped()
            ->recordClasses(fn (ShippingOrderException $record): string => $record->isActive() ? 'bg-danger-50 dark:bg-danger-950/20' : '');
    }

    /**
     * Get the global search result details.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        /** @var ShippingOrderException $record */
        return [
            'SO ID' => $record->shipping_order_id,
            'Type' => $record->exception_type->label(),
            'Status' => $record->status->label(),
        ];
    }

    /**
     * Get the globally searchable attributes.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'shipping_order_id', 'description'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingOrderExceptions::route('/'),
            'view' => Pages\ViewShippingOrderException::route('/{record}'),
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
     * Exceptions cannot be created directly from the UI - they are created by the system.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Exceptions cannot be edited directly from the UI.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /**
     * Exceptions cannot be deleted.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /**
     * Get the navigation badge showing active exception count.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = ShippingOrderException::where('status', ShippingOrderExceptionStatus::Active)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Get the navigation badge color.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        $count = ShippingOrderException::where('status', ShippingOrderExceptionStatus::Active)->count();

        return $count > 0 ? 'danger' : null;
    }
}
