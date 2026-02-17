<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Filament\Resources\Customer\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\Customer\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\Customer\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\Customer\CustomerResource\Pages\ViewCustomer;
use App\Models\Customer\Customer;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Customer Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('customer_type')
                            ->label('Customer Type')
                            ->options(collect(CustomerType::cases())->mapWithKeys(fn (CustomerType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->default(CustomerType::B2C->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
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
                TextColumn::make('display_name')
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

                TextColumn::make('customer_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?CustomerType $state): string => $state?->label() ?? '-')
                    ->color(fn (?CustomerType $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?CustomerType $state): ?string => $state?->icon())
                    ->sortable(),

                TextColumn::make('membership_tier')
                    ->label('Membership')
                    ->badge()
                    ->state(fn (Customer $record): string => 'N/A')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?CustomerStatus $state): string => $state?->label() ?? '-')
                    ->color(fn (?CustomerStatus $state): string => $state?->color() ?? 'gray')
                    ->icon(fn (?CustomerStatus $state): ?string => $state?->icon())
                    ->sortable(),

                TextColumn::make('accounts_count')
                    ->label('Accounts')
                    ->counts('accounts')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                IconColumn::make('has_active_blocks')
                    ->label('Blocks')
                    ->state(fn (Customer $record): bool => false)
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('party.tax_id')
                    ->label('Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label('Customer Type')
                    ->options(collect(CustomerType::cases())->mapWithKeys(fn (CustomerType $type) => [
                        $type->value => $type->label(),
                    ])),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(CustomerStatus::cases())->mapWithKeys(fn (CustomerStatus $status) => [
                        $status->value => $status->label(),
                    ])),

                SelectFilter::make('membership_tier')
                    ->label('Membership Tier')
                    ->options([
                        'legacy' => 'Legacy',
                        'member' => 'Member',
                        'invitation_only' => 'Invitation Only',
                    ]),

                TernaryFilter::make('has_blocks')
                    ->label('Has Active Blocks')
                    ->placeholder('All')
                    ->trueLabel('With Blocks')
                    ->falseLabel('Without Blocks'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to suspend this customer?')
                    ->action(fn (Customer $record) => $record->update(['status' => CustomerStatus::Suspended]))
                    ->visible(fn (Customer $record): bool => $record->status === CustomerStatus::Active
                        && Gate::allows('suspend', $record)),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to activate this customer?')
                    ->action(fn (Customer $record) => $record->update(['status' => CustomerStatus::Active]))
                    ->visible(fn (Customer $record): bool => ($record->status === CustomerStatus::Suspended || $record->status === CustomerStatus::Prospect)
                        && Gate::allows('activate', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Customer> $records */
                            $records->each(fn (Customer $record) => $record->update(['status' => CustomerStatus::Suspended]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Customer> $records */
                            $records->each(fn (Customer $record) => $record->update(['status' => CustomerStatus::Active]));
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'party.legal_name', 'party.tax_id'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['party']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Customer $record */
        return $record->getName();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Customer $record */
        return [
            'Email' => $record->email ?? 'N/A',
            'Type' => $record->customer_type->label(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['party', 'accounts'])
            ->withCount('accounts')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
