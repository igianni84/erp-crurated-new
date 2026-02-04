<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\ClubStatus;
use App\Filament\Resources\Customer\ClubResource\Pages;
use App\Models\Customer\Club;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Clubs';

    protected static ?string $modelLabel = 'Club';

    protected static ?string $pluralModelLabel = 'Clubs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Club Information')
                    ->schema([
                        Forms\Components\TextInput::make('partner_name')
                            ->label('Partner Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(collect(ClubStatus::cases())->mapWithKeys(fn (ClubStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->default(ClubStatus::Active->value)
                            ->required()
                            ->native(false),
                        Forms\Components\KeyValue::make('branding_metadata')
                            ->label('Branding Metadata')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partner_name')
                    ->label('Partner Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?ClubStatus $state): string => $state?->label() ?? '-')
                    ->color(fn (?ClubStatus $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?ClubStatus $state): ?string => $state?->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->state(fn (Club $record): int => $record->getActiveMembersCount())
                    ->badge()
                    ->color('info')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount(['activeCustomerAffiliations as active_members_count'])
                            ->orderBy('active_members_count', $direction);
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ClubStatus::cases())->mapWithKeys(fn (ClubStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to suspend this club?')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Suspended]))
                    ->visible(fn (Club $record): bool => $record->status === ClubStatus::Active),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to activate this club?')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Active]))
                    ->visible(fn (Club $record): bool => $record->status === ClubStatus::Suspended),
                Tables\Actions\Action::make('end')
                    ->label('End')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to end this club? This action indicates the club has concluded operations.')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Ended]))
                    ->visible(fn (Club $record): bool => $record->status !== ClubStatus::Ended),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Club> $records */
                            $records->each(fn (Club $record) => $record->update(['status' => ClubStatus::Suspended]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Club> $records */
                            $records->each(fn (Club $record) => $record->update(['status' => ClubStatus::Active]));
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListClubs::route('/'),
            'create' => Pages\CreateClub::route('/create'),
            'view' => Pages\ViewClub::route('/{record}'),
            'edit' => Pages\EditClub::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
