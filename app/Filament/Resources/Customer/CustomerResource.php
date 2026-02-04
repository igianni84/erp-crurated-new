<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Filament\Resources\Customer\CustomerResource\Pages;
use App\Models\Customer\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('customer_type')
                            ->label('Customer Type')
                            ->options(collect(CustomerType::cases())->mapWithKeys(fn (CustomerType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->default(CustomerType::B2C->value)
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(collect(CustomerStatus::cases())->mapWithKeys(fn (CustomerStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->default(CustomerStatus::Prospect->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Customer Name')
                    ->state(fn (Customer $record): string => $record->getName())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhereHas('party', function (Builder $partyQuery) use ($search): void {
                                    $partyQuery->where('legal_name', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('name', $direction);
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?CustomerType $state): string => $state?->label() ?? '-')
                    ->color(fn (?CustomerType $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?CustomerType $state): ?string => $state?->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('membership_tier')
                    ->label('Membership')
                    ->badge()
                    ->state(fn (Customer $record): string => 'N/A')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?CustomerStatus $state): string => $state?->label() ?? '-')
                    ->color(fn (?CustomerStatus $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?CustomerStatus $state): ?string => $state?->icon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('accounts_count')
                    ->label('Accounts')
                    ->counts('accounts')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('has_active_blocks')
                    ->label('Blocks')
                    ->state(fn (Customer $record): bool => false)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('party.tax_id')
                    ->label('Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_type')
                    ->label('Customer Type')
                    ->options(collect(CustomerType::cases())->mapWithKeys(fn (CustomerType $type) => [
                        $type->value => $type->label(),
                    ])),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(CustomerStatus::cases())->mapWithKeys(fn (CustomerStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                Tables\Filters\SelectFilter::make('membership_tier')
                    ->label('Membership Tier')
                    ->options([
                        'legacy' => 'Legacy',
                        'member' => 'Member',
                        'invitation_only' => 'Invitation Only',
                    ]),

                Tables\Filters\TernaryFilter::make('has_blocks')
                    ->label('Has Active Blocks')
                    ->placeholder('All')
                    ->trueLabel('With Blocks')
                    ->falseLabel('Without Blocks'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to suspend this customer?')
                    ->action(fn (Customer $record) => $record->update(['status' => CustomerStatus::Suspended]))
                    ->visible(fn (Customer $record): bool => $record->status === CustomerStatus::Active),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to activate this customer?')
                    ->action(fn (Customer $record) => $record->update(['status' => CustomerStatus::Active]))
                    ->visible(fn (Customer $record): bool => $record->status === CustomerStatus::Suspended || $record->status === CustomerStatus::Prospect),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Customer> $records */
                            $records->each(fn (Customer $record) => $record->update(['status' => CustomerStatus::Suspended]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Customer> $records */
                            $records->each(fn (Customer $record) => $record->update(['status' => CustomerStatus::Active]));
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['party', 'accounts'])
            ->withCount('accounts')
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
