<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\ClubStatus;
use App\Filament\Resources\Customer\ClubResource\Pages\CreateClub;
use App\Filament\Resources\Customer\ClubResource\Pages\EditClub;
use App\Filament\Resources\Customer\ClubResource\Pages\ListClubs;
use App\Filament\Resources\Customer\ClubResource\Pages\ViewClub;
use App\Models\Customer\Club;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Clubs';

    protected static ?string $modelLabel = 'Club';

    protected static ?string $pluralModelLabel = 'Clubs';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Club Information')
                    ->schema([
                        TextInput::make('partner_name')
                            ->label('Partner Name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(ClubStatus::cases())->mapWithKeys(fn (ClubStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->default(ClubStatus::Active->value)
                            ->required()
                            ->native(false),
                        KeyValue::make('branding_metadata')
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
                TextColumn::make('partner_name')
                    ->label('Partner Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?ClubStatus $state): string => $state?->label() ?? '-')
                    ->color(fn (?ClubStatus $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?ClubStatus $state): ?string => $state?->icon())
                    ->sortable(),

                TextColumn::make('members_count')
                    ->label('Members')
                    ->state(fn (Club $record): int => $record->getActiveMembersCount())
                    ->badge()
                    ->color('info')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount(['activeCustomerAffiliations as active_members_count'])
                            ->orderBy('active_members_count', $direction);
                    }),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ClubStatus::cases())->mapWithKeys(fn (ClubStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to suspend this club?')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Suspended]))
                    ->visible(fn (Club $record): bool => $record->status === ClubStatus::Active),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to activate this club?')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Active]))
                    ->visible(fn (Club $record): bool => $record->status === ClubStatus::Suspended),
                Action::make('end')
                    ->label('End')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to end this club? This action indicates the club has concluded operations.')
                    ->action(fn (Club $record) => $record->update(['status' => ClubStatus::Ended]))
                    ->visible(fn (Club $record): bool => $record->status !== ClubStatus::Ended),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Club> $records */
                            $records->each(fn (Club $record) => $record->update(['status' => ClubStatus::Suspended]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Club> $records */
                            $records->each(fn (Club $record) => $record->update(['status' => ClubStatus::Active]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'view' => ViewClub::route('/{record}'),
            'edit' => EditClub::route('/{record}/edit'),
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
