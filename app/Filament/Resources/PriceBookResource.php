<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\PriceBookStatus;
use App\Filament\Resources\PriceBookResource\Pages\CreatePriceBook;
use App\Filament\Resources\PriceBookResource\Pages\EditPriceBook;
use App\Filament\Resources\PriceBookResource\Pages\ListPriceBooks;
use App\Filament\Resources\PriceBookResource\Pages\ViewPriceBook;
use App\Filament\Resources\PriceBookResource\RelationManagers\EntriesRelationManager;
use App\Models\Commercial\PriceBook;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PriceBookResource extends Resource
{
    protected static ?string $model = PriceBook::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Price Books';

    protected static ?string $modelLabel = 'Price Book';

    protected static ?string $pluralModelLabel = 'Price Books';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Book Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('market')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., IT, DE, US'),
                        Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for channel-agnostic price book'),
                        TextInput::make('currency')
                            ->required()
                            ->maxLength(3)
                            ->placeholder('EUR'),
                    ])
                    ->columns(2),
                Section::make('Validity Period')
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false),
                        DatePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->after('valid_from'),
                    ])
                    ->columns(2),
                Section::make('Status')
                    ->schema([
                        Select::make('status')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'Expiring soon' : null)
                    ->icon(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('warning'),
                TextColumn::make('market')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->sortable()
                    ->placeholder('All Channels')
                    ->toggleable(),
                TextColumn::make('currency')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Valid To')
                    ->date()
                    ->sortable()
                    ->placeholder('Indefinite')
                    ->color(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'warning' : null)
                    ->weight(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'bold' : null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PriceBookStatus $state): string => $state->label())
                    ->color(fn (PriceBookStatus $state): string => $state->color())
                    ->icon(fn (PriceBookStatus $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->label('Entries')
                    ->counts('entries')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                    ->icon(fn (int $state): ?string => $state === 0 ? 'heroicon-o-exclamation-circle' : null)
                    ->tooltip(fn (int $state): ?string => $state === 0 ? 'No prices defined - add prices to this Price Book' : null),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PriceBookStatus::cases())->mapWithKeys(fn (PriceBookStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                SelectFilter::make('channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('market')
                    ->options(fn () => PriceBook::query()
                        ->distinct()
                        ->pluck('market', 'market')
                        ->toArray()),
                SelectFilter::make('currency')
                    ->options(fn () => PriceBook::query()
                        ->distinct()
                        ->pluck('currency', 'currency')
                        ->toArray()),
                TernaryFilter::make('expiring_soon')
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
                TernaryFilter::make('missing_prices')
                    ->label('Missing Prices')
                    ->placeholder('All')
                    ->trueLabel('With missing prices')
                    ->falseLabel('With prices')
                    ->queries(
                        true: fn (Builder $query) => $query->whereDoesntHave('entries'),
                        false: fn (Builder $query) => $query->whereHas('entries'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (PriceBook $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'market'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['channel']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PriceBook $record */
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PriceBook $record */
        return [
            'Market' => $record->market ?? '-',
            'Channel' => $record->channel !== null ? $record->channel->name : 'All Channels',
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceBooks::route('/'),
            'create' => CreatePriceBook::route('/create'),
            'view' => ViewPriceBook::route('/{record}'),
            'edit' => EditPriceBook::route('/{record}/edit'),
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
