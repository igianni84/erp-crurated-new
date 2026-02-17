<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Filament\Resources\Allocation\CaseEntitlementResource\Pages\ListCaseEntitlements;
use App\Filament\Resources\Allocation\CaseEntitlementResource\Pages\ViewCaseEntitlement;
use App\Models\Allocation\CaseEntitlement;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CaseEntitlementResource extends Resource
{
    protected static ?string $model = CaseEntitlement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Case Entitlements';

    protected static ?string $modelLabel = 'Case Entitlement';

    protected static ?string $pluralModelLabel = 'Case Entitlements';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // No form schema - case entitlements are created only via CaseEntitlementService
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Entitlement ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Entitlement ID copied'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (CaseEntitlement $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('sellableSku.sku_code')
                    ->label('Sellable SKU')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CaseEntitlementStatus $state): string => $state->label())
                    ->color(fn (CaseEntitlementStatus $state): string => $state->color())
                    ->icon(fn (CaseEntitlementStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('vouchers_count')
                    ->label('Vouchers')
                    ->counts('vouchers')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('broken_at')
                    ->label('Broken At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('N/A'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CaseEntitlementStatus::cases())
                        ->mapWithKeys(fn (CaseEntitlementStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                Filter::make('customer')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['customer_id'] ?? null,
                            fn (Builder $query, string $customerId): Builder => $query->where('customer_id', $customerId)
                        );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // No bulk actions - case entitlements require careful individual handling
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'sellableSku',
            ])->withCount('vouchers'));
    }

    public static function getRelations(): array
    {
        return [
            // No relation managers needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseEntitlements::route('/'),
            'view' => ViewCaseEntitlement::route('/{record}'),
        ];
    }

    /**
     * Case entitlements cannot be created from the admin panel.
     * They are created only via CaseEntitlementService when vouchers are grouped.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
