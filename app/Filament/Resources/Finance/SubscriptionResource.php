<?php

namespace App\Filament\Resources\Finance;

use App\Enums\Finance\BillingCycle;
use App\Enums\Finance\SubscriptionPlanType;
use App\Enums\Finance\SubscriptionStatus;
use App\Filament\Resources\Finance\SubscriptionResource\Pages\CreateSubscription;
use App\Filament\Resources\Finance\SubscriptionResource\Pages\EditSubscription;
use App\Filament\Resources\Finance\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\Resources\Finance\SubscriptionResource\Pages\ViewSubscription;
use App\Models\Finance\Subscription;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?string $modelLabel = 'Subscription';

    protected static ?string $pluralModelLabel = 'Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Subscription $record): bool => $record !== null),

                        TextInput::make('plan_name')
                            ->label('Plan Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('plan_type')
                            ->label('Plan Type')
                            ->options(collect(SubscriptionPlanType::cases())
                                ->mapWithKeys(fn (SubscriptionPlanType $e) => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Subscription $record): bool => $record !== null),

                        Select::make('billing_cycle')
                            ->label('Billing Cycle')
                            ->options(collect(BillingCycle::cases())
                                ->mapWithKeys(fn (BillingCycle $e) => [$e->value => $e->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Subscription $record): bool => $record !== null),
                    ]),

                Section::make('Financial')
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->prefix('EUR'),

                        TextInput::make('currency')
                            ->label('Currency')
                            ->default('EUR')
                            ->disabled(fn (?Subscription $record): bool => $record !== null),
                    ]),

                Section::make('Dates')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('started_at')
                            ->label('Started At')
                            ->required()
                            ->disabled(fn (?Subscription $record): bool => $record !== null),

                        DatePicker::make('next_billing_date')
                            ->label('Next Billing Date')
                            ->required(),
                    ]),

                Section::make('Cancellation')
                    ->columns(1)
                    ->visible(fn (?Subscription $record): bool => $record !== null && $record->isCancelled())
                    ->schema([
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->maxLength(2000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Subscription ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Subscription ID copied')
                    ->limit(8)
                    ->tooltip(fn (Subscription $record): string => $record->id),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->url(fn (Subscription $record): ?string => $record->customer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('plan_name')
                    ->label('Plan Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionPlanType $state): string => $state->label())
                    ->color(fn (SubscriptionPlanType $state): string => $state->color())
                    ->icon(fn (SubscriptionPlanType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->badge()
                    ->formatStateUsing(fn (BillingCycle $state): string => $state->label())
                    ->color(fn (BillingCycle $state): string => $state->color())
                    ->icon(fn (BillingCycle $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn (Subscription $record): string => $record->currency)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->icon(fn (SubscriptionStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('next_billing_date')
                    ->label('Next Billing')
                    ->date()
                    ->sortable()
                    ->color(fn (Subscription $record): ?string => $record->isOverdueForBilling() ? 'danger' : null)
                    ->description(fn (Subscription $record): ?string => $record->isOverdueForBilling()
                        ? 'Overdue'
                        : ($record->isDueForBilling() ? 'Due today' : null)),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('stripe_subscription_id')
                    ->label('Stripe ID')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->placeholder('Not linked'),
            ])
            ->filters([
                SelectFilter::make('plan_type')
                    ->options(collect(SubscriptionPlanType::cases())
                        ->mapWithKeys(fn (SubscriptionPlanType $type) => [$type->value => $type->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Plan Type'),

                SelectFilter::make('status')
                    ->options(collect(SubscriptionStatus::cases())
                        ->mapWithKeys(fn (SubscriptionStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('billing_cycle')
                    ->options(collect(BillingCycle::cases())
                        ->mapWithKeys(fn (BillingCycle $cycle) => [$cycle->value => $cycle->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Billing Cycle'),

                TernaryFilter::make('overdue_billing')
                    ->label('Billing Status')
                    ->placeholder('All subscriptions')
                    ->trueLabel('Overdue for billing')
                    ->falseLabel('Not overdue')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->where('status', SubscriptionStatus::Active)
                            ->where('next_billing_date', '<', now()->startOfDay()),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->where('status', '!=', SubscriptionStatus::Active)
                                ->orWhere('next_billing_date', '>=', now()->startOfDay());
                        }),
                    ),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Subscription $record): bool => ! $record->isCancelled() && ! $record->isTerminal()),
            ])
            ->toolbarActions([
                BulkAction::make('export_csv')
                    ->label('Export to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records): StreamedResponse {
                        return response()->streamDownload(function () use ($records): void {
                            $handle = fopen('php://output', 'w');
                            if ($handle !== false) {
                                fputcsv($handle, [
                                    'Subscription ID',
                                    'Customer',
                                    'Plan Name',
                                    'Plan Type',
                                    'Billing Cycle',
                                    'Amount',
                                    'Currency',
                                    'Status',
                                    'Started At',
                                    'Next Billing Date',
                                    'Stripe Subscription ID',
                                ]);
                                foreach ($records as $record) {
                                    /** @var Subscription $record */
                                    fputcsv($handle, [
                                        $record->id,
                                        $record->customer !== null ? $record->customer->name : 'N/A',
                                        $record->plan_name,
                                        $record->plan_type->label(),
                                        $record->billing_cycle->label(),
                                        $record->amount,
                                        $record->currency,
                                        $record->status->label(),
                                        $record->started_at->format('Y-m-d'),
                                        $record->next_billing_date->format('Y-m-d'),
                                        $record->stripe_subscription_id,
                                    ]);
                                }
                                fclose($handle);
                            }
                        }, 'subscriptions-'.now()->format('Y-m-d').'.csv');
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
            ]));
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['stripe_subscription_id', 'plan_name', 'customer.name', 'customer.email'];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\Subscription> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Subscription $record */
        return $record->plan_name ?? 'Subscription #'.substr($record->id, 0, 8);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Subscription $record */
        return [
            'Customer' => $record->customer !== null ? ($record->customer->name ?? 'N/A') : 'N/A',
            'Type' => $record->plan_type->label(),
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-E084
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'view' => ViewSubscription::route('/{record}'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Finance\Subscription> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
