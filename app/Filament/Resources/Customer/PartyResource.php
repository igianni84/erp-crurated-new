<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Filament\Resources\Customer\PartyResource\Pages\CreateParty;
use App\Filament\Resources\Customer\PartyResource\Pages\EditParty;
use App\Filament\Resources\Customer\PartyResource\Pages\EditSupplierConfig;
use App\Filament\Resources\Customer\PartyResource\Pages\ListParties;
use App\Filament\Resources\Customer\PartyResource\Pages\ViewParty;
use App\Models\Customer\Party;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
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

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Parties';

    protected static ?string $modelLabel = 'Party';

    protected static ?string $pluralModelLabel = 'Parties';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Party Information')
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Legal Name')
                            ->required()
                            ->maxLength(255),
                        Select::make('party_type')
                            ->label('Party Type')
                            ->options(collect(PartyType::cases())->mapWithKeys(fn (PartyType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(PartyStatus::cases())->mapWithKeys(fn (PartyStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->default(PartyStatus::Active->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(3),
                Section::make('Legal Information')
                    ->schema([
                        TextInput::make('tax_id')
                            ->label('Tax ID')
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->maxLength(255),
                        TextInput::make('jurisdiction')
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
                TextColumn::make('legal_name')
                    ->label('Legal Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('party_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PartyType $state): string => $state->label())
                    ->color(fn (PartyType $state): string => $state->color())
                    ->icon(fn (PartyType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('roles.role')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (PartyRoleType $state): string => $state->label())
                    ->color(fn (PartyRoleType $state): string => $state->color())
                    ->icon(fn (PartyRoleType $state): string => $state->icon()),

                TextColumn::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PartyStatus $state): string => $state->label())
                    ->color(fn (PartyStatus $state): string => $state->color())
                    ->icon(fn (PartyStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('tax_id')
                    ->label('Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vat_number')
                    ->label('VAT Number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('party_type')
                    ->label('Party Type')
                    ->options(collect(PartyType::cases())->mapWithKeys(fn (PartyType $type) => [
                        $type->value => $type->label(),
                    ])),

                SelectFilter::make('role')
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

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(PartyStatus::cases())->mapWithKeys(fn (PartyStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                SelectFilter::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->options(fn (): array => Party::query()
                        ->whereNotNull('jurisdiction')
                        ->distinct()
                        ->pluck('jurisdiction', 'jurisdiction')
                        ->toArray()),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Party> $records */
                            $records->each(fn (Party $record) => $record->update(['status' => PartyStatus::Active]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Party> $records */
                            $records->each(fn (Party $record) => $record->update(['status' => PartyStatus::Inactive]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'view' => ViewParty::route('/{record}'),
            'edit' => EditParty::route('/{record}/edit'),
            'edit-supplier-config' => EditSupplierConfig::route('/{record}/edit-supplier-config'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('roles')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
