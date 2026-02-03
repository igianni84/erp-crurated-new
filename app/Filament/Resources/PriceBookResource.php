<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\PriceBookStatus;
use App\Filament\Resources\PriceBookResource\Pages;
use App\Models\Commercial\PriceBook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PriceBookResource extends Resource
{
    protected static ?string $model = PriceBook::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Price Books';

    protected static ?string $modelLabel = 'Price Book';

    protected static ?string $pluralModelLabel = 'Price Books';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Price Book Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('market')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., IT, DE, US'),
                        Forms\Components\Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for channel-agnostic price book'),
                        Forms\Components\TextInput::make('currency')
                            ->required()
                            ->maxLength(3)
                            ->placeholder('EUR'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Validity Period')
                    ->schema([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->after('valid_from'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(PriceBookStatus::cases())->mapWithKeys(fn (PriceBookStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(PriceBookStatus::Draft->value)
                            ->native(false)
                            ->disabled(fn (?PriceBook $record) => $record?->isActive() ?? false)
                            ->helperText(fn (?PriceBook $record) => $record?->isActive() ? 'Active price books cannot be modified directly.' : null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'Expiring soon' : null)
                    ->icon(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('warning'),
                Tables\Columns\TextColumn::make('market')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->placeholder('All Channels')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('currency')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_to')
                    ->label('Valid To')
                    ->date()
                    ->sortable()
                    ->placeholder('Indefinite')
                    ->color(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'warning' : null)
                    ->weight(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'bold' : null),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PriceBookStatus $state): string => $state->label())
                    ->color(fn (PriceBookStatus $state): string => $state->color())
                    ->icon(fn (PriceBookStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('entries_count')
                    ->label('Entries')
                    ->counts('entries')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                    ->icon(fn (int $state): ?string => $state === 0 ? 'heroicon-o-exclamation-circle' : null)
                    ->tooltip(fn (int $state): ?string => $state === 0 ? 'No prices defined - add prices to this Price Book' : null),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PriceBookStatus::cases())->mapWithKeys(fn (PriceBookStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                Tables\Filters\SelectFilter::make('channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('market')
                    ->options(fn () => PriceBook::query()
                        ->distinct()
                        ->pluck('market', 'market')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => PriceBook::query()
                        ->distinct()
                        ->pluck('currency', 'currency')
                        ->toArray()),
                Tables\Filters\TernaryFilter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->placeholder('All')
                    ->trueLabel('Expiring within 30 days')
                    ->falseLabel('Not expiring soon')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('valid_to')
                            ->where('valid_to', '<=', now()->addDays(30))
                            ->where('valid_to', '>=', now()),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('valid_to')
                                ->orWhere('valid_to', '>', now()->addDays(30));
                        }),
                    ),
                Tables\Filters\TernaryFilter::make('missing_prices')
                    ->label('Missing Prices')
                    ->placeholder('All')
                    ->trueLabel('With missing prices')
                    ->falseLabel('With prices')
                    ->queries(
                        true: fn (Builder $query) => $query->whereDoesntHave('entries'),
                        false: fn (Builder $query) => $query->whereHas('entries'),
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PriceBook $record): bool => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceBooks::route('/'),
            'create' => Pages\CreatePriceBook::route('/create'),
            'view' => Pages\ViewPriceBook::route('/{record}'),
            'edit' => Pages\EditPriceBook::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
