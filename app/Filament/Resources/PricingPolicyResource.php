<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource\Pages\CreatePricingPolicy;
use App\Filament\Resources\PricingPolicyResource\Pages\EditPricingPolicy;
use App\Filament\Resources\PricingPolicyResource\Pages\ListPricingPolicies;
use App\Filament\Resources\PricingPolicyResource\Pages\ViewPricingPolicy;
use App\Models\Commercial\PricingPolicy;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PricingPolicyResource extends Resource
{
    protected static ?string $model = PricingPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pricing Policies';

    protected static ?string $modelLabel = 'Pricing Policy';

    protected static ?string $pluralModelLabel = 'Pricing Policies';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Policy Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('policy_type')
                            ->label('Policy Type')
                            ->options(collect(PricingPolicyType::cases())->mapWithKeys(fn (PricingPolicyType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Select::make('input_source')
                            ->label('Input Source')
                            ->options(collect(PricingPolicyInputSource::cases())->mapWithKeys(fn (PricingPolicyInputSource $source) => [
                                $source->value => $source->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Select::make('target_price_book_id')
                            ->label('Target Price Book')
                            ->relationship('targetPriceBook', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Select the Price Book where generated prices will be stored'),
                    ])
                    ->columns(2),
                Section::make('Execution Settings')
                    ->schema([
                        Select::make('execution_cadence')
                            ->label('Execution Cadence')
                            ->options(collect(ExecutionCadence::cases())->mapWithKeys(fn (ExecutionCadence $cadence) => [
                                $cadence->value => $cadence->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->options(collect(PricingPolicyStatus::cases())->mapWithKeys(fn (PricingPolicyStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(PricingPolicyStatus::Draft->value)
                            ->native(false)
                            ->disabled(fn (?PricingPolicy $record) => $record?->isActive() ?? false)
                            ->helperText(fn (?PricingPolicy $record) => $record?->isActive() ? 'Active policies cannot be modified directly.' : null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PricingPolicy $record): string => $record->getLogicDescription())
                    ->wrap(),
                TextColumn::make('policy_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PricingPolicyType $state): string => $state->label())
                    ->color(fn (PricingPolicyType $state): string => $state->color())
                    ->icon(fn (PricingPolicyType $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('input_source')
                    ->label('Input')
                    ->badge()
                    ->formatStateUsing(fn (PricingPolicyInputSource $state): string => $state->label())
                    ->color(fn (PricingPolicyInputSource $state): string => $state->color())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('targetPriceBook.name')
                    ->label('Target Price Book')
                    ->sortable()
                    ->placeholder('Not set')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PricingPolicyStatus $state): string => $state->label())
                    ->color(fn (PricingPolicyStatus $state): string => $state->color())
                    ->icon(fn (PricingPolicyStatus $state): string => $state->icon())
                    ->sortable(),
                TextColumn::make('last_executed_at')
                    ->label('Last Executed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->description(fn (PricingPolicy $record): ?string => static::getLastExecutionStatusDescription($record))
                    ->icon(fn (PricingPolicy $record): ?string => static::getLastExecutionIcon($record))
                    ->iconColor(fn (PricingPolicy $record): ?string => static::getLastExecutionIconColor($record)),
                TextColumn::make('execution_cadence')
                    ->label('Cadence')
                    ->badge()
                    ->formatStateUsing(fn (ExecutionCadence $state): string => $state->label())
                    ->color(fn (ExecutionCadence $state): string => $state->color())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PricingPolicyStatus::cases())->mapWithKeys(fn (PricingPolicyStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                SelectFilter::make('policy_type')
                    ->label('Policy Type')
                    ->options(collect(PricingPolicyType::cases())->mapWithKeys(fn (PricingPolicyType $type) => [
                        $type->value => $type->label(),
                    ])),
                SelectFilter::make('target_price_book_id')
                    ->label('Target Price Book')
                    ->relationship('targetPriceBook', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('has_failed_executions')
                    ->label('Failed Executions')
                    ->placeholder('All')
                    ->trueLabel('With failed executions')
                    ->falseLabel('Without failed executions')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('executions', function (Builder $q) {
                            $q->where('status', ExecutionStatus::Failed);
                        }),
                        false: fn (Builder $query) => $query->whereDoesntHave('executions', function (Builder $q) {
                            $q->where('status', ExecutionStatus::Failed);
                        }),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (PricingPolicy $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
            'index' => ListPricingPolicies::route('/'),
            'create' => CreatePricingPolicy::route('/create'),
            'view' => ViewPricingPolicy::route('/{record}'),
            'edit' => EditPricingPolicy::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Get the last execution status description.
     */
    protected static function getLastExecutionStatusDescription(PricingPolicy $record): ?string
    {
        $latestExecution = $record->latestExecution();

        if (! $latestExecution) {
            return null;
        }

        return $latestExecution->status->label();
    }

    /**
     * Get the icon for the last execution status.
     */
    protected static function getLastExecutionIcon(PricingPolicy $record): ?string
    {
        $latestExecution = $record->latestExecution();

        if (! $latestExecution) {
            return null;
        }

        return $latestExecution->status->icon();
    }

    /**
     * Get the icon color for the last execution status.
     */
    protected static function getLastExecutionIconColor(PricingPolicy $record): ?string
    {
        $latestExecution = $record->latestExecution();

        if (! $latestExecution) {
            return null;
        }

        return $latestExecution->status->color();
    }
}
