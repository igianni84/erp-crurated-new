<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource\Pages;
use App\Models\Commercial\PricingPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PricingPolicyResource extends Resource
{
    protected static ?string $model = PricingPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pricing Policies';

    protected static ?string $modelLabel = 'Pricing Policy';

    protected static ?string $pluralModelLabel = 'Pricing Policies';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Policy Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('policy_type')
                            ->label('Policy Type')
                            ->options(collect(PricingPolicyType::cases())->mapWithKeys(fn (PricingPolicyType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('input_source')
                            ->label('Input Source')
                            ->options(collect(\App\Enums\Commercial\PricingPolicyInputSource::cases())->mapWithKeys(fn (\App\Enums\Commercial\PricingPolicyInputSource $source) => [
                                $source->value => $source->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('target_price_book_id')
                            ->label('Target Price Book')
                            ->relationship('targetPriceBook', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Select the Price Book where generated prices will be stored'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Execution Settings')
                    ->schema([
                        Forms\Components\Select::make('execution_cadence')
                            ->label('Execution Cadence')
                            ->options(collect(\App\Enums\Commercial\ExecutionCadence::cases())->mapWithKeys(fn (\App\Enums\Commercial\ExecutionCadence $cadence) => [
                                $cadence->value => $cadence->label(),
                            ]))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PricingPolicy $record): string => $record->getLogicDescription())
                    ->wrap(),
                Tables\Columns\TextColumn::make('policy_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (PricingPolicyType $state): string => $state->label())
                    ->color(fn (PricingPolicyType $state): string => $state->color())
                    ->icon(fn (PricingPolicyType $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('input_source')
                    ->label('Input')
                    ->badge()
                    ->formatStateUsing(fn (\App\Enums\Commercial\PricingPolicyInputSource $state): string => $state->label())
                    ->color(fn (\App\Enums\Commercial\PricingPolicyInputSource $state): string => $state->color())
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('targetPriceBook.name')
                    ->label('Target Price Book')
                    ->sortable()
                    ->placeholder('Not set')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PricingPolicyStatus $state): string => $state->label())
                    ->color(fn (PricingPolicyStatus $state): string => $state->color())
                    ->icon(fn (PricingPolicyStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_executed_at')
                    ->label('Last Executed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->description(fn (PricingPolicy $record): ?string => static::getLastExecutionStatusDescription($record))
                    ->icon(fn (PricingPolicy $record): ?string => static::getLastExecutionIcon($record))
                    ->iconColor(fn (PricingPolicy $record): ?string => static::getLastExecutionIconColor($record)),
                Tables\Columns\TextColumn::make('execution_cadence')
                    ->label('Cadence')
                    ->badge()
                    ->formatStateUsing(fn (\App\Enums\Commercial\ExecutionCadence $state): string => $state->label())
                    ->color(fn (\App\Enums\Commercial\ExecutionCadence $state): string => $state->color())
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PricingPolicyStatus::cases())->mapWithKeys(fn (PricingPolicyStatus $status) => [
                        $status->value => $status->label(),
                    ])),
                Tables\Filters\SelectFilter::make('policy_type')
                    ->label('Policy Type')
                    ->options(collect(PricingPolicyType::cases())->mapWithKeys(fn (PricingPolicyType $type) => [
                        $type->value => $type->label(),
                    ])),
                Tables\Filters\SelectFilter::make('target_price_book_id')
                    ->label('Target Price Book')
                    ->relationship('targetPriceBook', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('has_failed_executions')
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
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PricingPolicy $record): bool => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index' => Pages\ListPricingPolicies::route('/'),
            'create' => Pages\CreatePricingPolicy::route('/create'),
            'view' => Pages\ViewPricingPolicy::route('/{record}'),
            'edit' => Pages\EditPricingPolicy::route('/{record}/edit'),
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
