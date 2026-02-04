<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Filament\Resources\Customer\PartyResource\Pages;
use App\Models\Customer\Party;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Parties';

    protected static ?string $modelLabel = 'Party';

    protected static ?string $pluralModelLabel = 'Parties';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Party Information')
                    ->schema([
                        Forms\Components\TextInput::make('legal_name')
                            ->label('Legal Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('party_type')
                            ->label('Party Type')
                            ->options(collect(PartyType::cases())->mapWithKeys(fn (PartyType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(collect(PartyStatus::cases())->mapWithKeys(fn (PartyStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->default(PartyStatus::Active->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Legal Information')
                    ->schema([
                        Forms\Components\TextInput::make('tax_id')
                            ->label('Tax ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('jurisdiction')
                            ->label('Jurisdiction')
                            ->maxLength(255),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('legal_name')
                    ->label('Legal Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('party_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PartyType $state): string => $state->label())
                    ->color(fn (PartyType $state): string => $state->color())
                    ->icon(fn (PartyType $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.role')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (PartyRoleType $state): string => $state->label())
                    ->color(fn (PartyRoleType $state): string => $state->color())
                    ->icon(fn (PartyRoleType $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PartyStatus $state): string => $state->label())
                    ->color(fn (PartyStatus $state): string => $state->color())
                    ->icon(fn (PartyStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_id')
                    ->label('Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('vat_number')
                    ->label('VAT Number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('party_type')
                    ->label('Party Type')
                    ->options(collect(PartyType::cases())->mapWithKeys(fn (PartyType $type) => [
                        $type->value => $type->label(),
                    ])),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(collect(PartyRoleType::cases())->mapWithKeys(fn (PartyRoleType $role) => [
                        $role->value => $role->label(),
                    ]))
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'])) {
                            return $query->whereHas('roles', function (Builder $query) use ($data) {
                                $query->where('role', $data['value']);
                            });
                        }

                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PartyStatus::cases())->mapWithKeys(fn (PartyStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                Tables\Filters\SelectFilter::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->options(fn (): array => Party::query()
                        ->whereNotNull('jurisdiction')
                        ->distinct()
                        ->pluck('jurisdiction', 'jurisdiction')
                        ->toArray()),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Party> $records */
                            $records->each(fn (Party $record) => $record->update(['status' => PartyStatus::Active]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Party> $records */
                            $records->each(fn (Party $record) => $record->update(['status' => PartyStatus::Inactive]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('legal_name', 'asc');
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
            'index' => Pages\ListParties::route('/'),
            'create' => Pages\CreateParty::route('/create'),
            'view' => Pages\ViewParty::route('/{record}'),
            'edit' => Pages\EditParty::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('roles')
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
