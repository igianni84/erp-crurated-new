<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Filament\Resources\ChannelResource\Pages;
use App\Models\Commercial\Channel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Channels';

    protected static ?string $modelLabel = 'Channel';

    protected static ?string $pluralModelLabel = 'Channels';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Channel Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('channel_type')
                            ->label('Channel Type')
                            ->options(collect(ChannelType::cases())->mapWithKeys(fn (ChannelType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('default_currency')
                            ->label('Default Currency')
                            ->required()
                            ->maxLength(3)
                            ->placeholder('EUR'),
                        Forms\Components\Select::make('status')
                            ->options(collect(ChannelStatus::cases())->mapWithKeys(fn (ChannelStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(ChannelStatus::Active->value)
                            ->native(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Commercial Models')
                    ->description('Define which commercial models are allowed for this channel.')
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_commercial_models')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ChannelType $state): string => $state->label())
                    ->color(fn (ChannelType $state): string => $state->color())
                    ->icon(fn (ChannelType $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_currency')
                    ->label('Currency')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ChannelStatus $state): string => $state->label())
                    ->color(fn (ChannelStatus $state): string => $state->color())
                    ->icon(fn (ChannelStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('allowed_commercial_models')
                    ->label('Allowed Models')
                    ->badge()
                    ->formatStateUsing(fn (array $state): string => collect($state)
                        ->map(fn (string $model): string => match ($model) {
                            'voucher_based' => 'Voucher Based',
                            'sell_through' => 'Sell Through',
                            default => $model,
                        })
                        ->implode(', '))
                    ->color('info')
                    ->separator(','),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel_type')
                    ->label('Channel Type')
                    ->options(collect(ChannelType::cases())->mapWithKeys(fn (ChannelType $type) => [
                        $type->value => $type->label(),
                    ])),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ChannelStatus::cases())->mapWithKeys(fn (ChannelStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'view' => Pages\ViewChannel::route('/{record}'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
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
