<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Filament\Resources\ChannelResource\Pages\CreateChannel;
use App\Filament\Resources\ChannelResource\Pages\EditChannel;
use App\Filament\Resources\ChannelResource\Pages\ListChannels;
use App\Filament\Resources\ChannelResource\Pages\ViewChannel;
use App\Models\Commercial\Channel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Channels';

    protected static ?string $modelLabel = 'Channel';

    protected static ?string $pluralModelLabel = 'Channels';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Channel Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('channel_type')
                            ->label('Channel Type')
                            ->options(collect(ChannelType::cases())->mapWithKeys(fn (ChannelType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        TextInput::make('default_currency')
                            ->label('Default Currency')
                            ->required()
                            ->maxLength(3)
                            ->placeholder('EUR'),
                        Select::make('status')
                            ->options(collect(ChannelStatus::cases())->mapWithKeys(fn (ChannelStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(ChannelStatus::Active->value)
                            ->native(false),
                    ])
                    ->columns(2),
                Section::make('Commercial Models')
                    ->description('Define which commercial models are allowed for this channel.')
                    ->schema([
                        CheckboxList::make('allowed_commercial_models')
                            ->label('Allowed Models')
                            ->options([
                                'voucher_based' => 'Voucher Based',
                                'sell_through' => 'Sell Through',
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ChannelType $state): string => $state->label())
                    ->color(fn (ChannelType $state): string => $state->color())
                    ->icon(fn (ChannelType $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('default_currency')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ChannelStatus $state): string => $state->label())
                    ->color(fn (ChannelStatus $state): string => $state->color())
                    ->icon(fn (ChannelStatus $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('allowed_commercial_models')
                    ->label('Allowed Models')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => collect($state ?? [])
                        ->map(fn (string $model): string => match ($model) {
                            'voucher_based' => 'Voucher Based',
                            'sell_through' => 'Sell Through',
                            default => $model,
                        })
                        ->implode(', '))
                    ->color('info')
                    ->separator(','),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel_type')
                    ->label('Channel Type')
                    ->options(collect(ChannelType::cases())->mapWithKeys(fn (ChannelType $type) => [
                        $type->value => $type->label(),
                    ])),
                SelectFilter::make('status')
                    ->options(collect(ChannelStatus::cases())->mapWithKeys(fn (ChannelStatus $status) => [
                        $status->value => $status->label(),
                    ])),
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
            ->defaultSort('name');
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
            'index' => ListChannels::route('/'),
            'create' => CreateChannel::route('/create'),
            'view' => ViewChannel::route('/{record}'),
            'edit' => EditChannel::route('/{record}/edit'),
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
