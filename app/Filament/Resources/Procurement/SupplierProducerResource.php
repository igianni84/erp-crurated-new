<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Filament\Resources\Procurement\SupplierProducerResource\Pages;
use App\Models\Customer\Party;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supplier & Producer Resource
 *
 * A filtered, read-focused view of parties that have Supplier or Producer roles.
 * Provides quick access to supplier/producer config in the Procurement navigation group.
 */
class SupplierProducerResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Suppliers & Producers';

    protected static ?string $modelLabel = 'Supplier / Producer';

    protected static ?string $pluralModelLabel = 'Suppliers & Producers';

    protected static ?string $slug = 'procurement/suppliers-producers';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('legal_name')
                    ->label('Party Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('roles.role')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (PartyRoleType $state): string => $state->label())
                    ->color(fn (PartyRoleType $state): string => $state->color())
                    ->icon(fn (PartyRoleType $state): string => $state->icon()),

                Tables\Columns\IconColumn::make('has_config')
                    ->label('Config')
                    ->boolean()
                    ->state(fn (Party $record): bool => $record->hasSupplierConfig())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Party $record): string => $record->hasSupplierConfig()
                        ? 'Supplier config exists'
                        : 'No supplier config'),

                Tables\Columns\TextColumn::make('supplierConfig.default_bottling_deadline_days')
                    ->label('Default Deadline')
                    ->placeholder('Not set')
                    ->suffix(' days')
                    ->sortable()
                    ->tooltip('Default bottling deadline in days'),

                Tables\Columns\TextColumn::make('party_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PartyType $state): string => $state->label())
                    ->color(fn (PartyType $state): string => $state->color())
                    ->icon(fn (PartyType $state): string => $state->icon())
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PartyStatus $state): string => $state->label())
                    ->color(fn (PartyStatus $state): string => $state->color())
                    ->icon(fn (PartyStatus $state): string => $state->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        PartyRoleType::Supplier->value => PartyRoleType::Supplier->label(),
                        PartyRoleType::Producer->value => PartyRoleType::Producer->label(),
                    ])
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

                Tables\Filters\TernaryFilter::make('has_config')
                    ->label('Has Config')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('supplierConfig'),
                        false: fn (Builder $query) => $query->whereDoesntHave('supplierConfig'),
                    ),

                Tables\Filters\SelectFilter::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->options(fn (): array => Party::query()
                        ->whereHas('roles', fn (Builder $query) => $query->whereIn('role', [
                            PartyRoleType::Supplier->value,
                            PartyRoleType::Producer->value,
                        ]))
                        ->whereNotNull('jurisdiction')
                        ->distinct()
                        ->pluck('jurisdiction', 'jurisdiction')
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('view_party')
                    ->label('View Party')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Party $record): string => route('filament.admin.resources.customers.parties.view', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->color('gray'),

                Tables\Actions\Action::make('manage_config')
                    ->label(fn (Party $record): string => $record->hasSupplierConfig()
                        ? 'Edit Config'
                        : 'Create Config')
                    ->icon(fn (Party $record): string => $record->hasSupplierConfig()
                        ? 'heroicon-o-pencil-square'
                        : 'heroicon-o-plus-circle')
                    ->url(fn (Party $record): string => route('filament.admin.resources.customers.parties.edit-supplier-config', ['record' => $record]))
                    ->openUrlInNewTab(false)
                    ->color(fn (Party $record): string => $record->hasSupplierConfig()
                        ? 'primary'
                        : 'success'),
            ])
            ->bulkActions([
                // No bulk actions for this read-focused view
            ])
            ->defaultSort('legal_name', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['roles', 'supplierConfig'])
                ->whereHas('roles', fn (Builder $q) => $q->whereIn('role', [
                    PartyRoleType::Supplier->value,
                    PartyRoleType::Producer->value,
                ])));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierProducers::route('/'),
        ];
    }

    /**
     * This resource is read-only and uses the Party model but doesn't allow creation.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
