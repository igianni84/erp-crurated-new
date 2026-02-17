<?php

namespace App\Filament\Resources\Procurement;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\CreateBottlingInstruction;
use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\ListBottlingInstructions;
use App\Filament\Resources\Procurement\BottlingInstructionResource\Pages\ViewBottlingInstruction;
use App\Models\Procurement\BottlingInstruction;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BottlingInstructionResource extends Resource
{
    protected static ?string $model = BottlingInstruction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bottling Instructions';

    protected static ?string $modelLabel = 'Bottling Instruction';

    protected static ?string $pluralModelLabel = 'Bottling Instructions';

    protected static ?string $slug = 'procurement/bottling-instructions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Form schema will be implemented in wizard stories (US-029 to US-032)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Instruction ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Instruction ID copied')
                    ->limit(8)
                    ->tooltip(fn (BottlingInstruction $record): string => $record->id),

                TextColumn::make('product')
                    ->label('Wine + Vintage')
                    ->state(fn (BottlingInstruction $record): string => $record->getProductLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            // Search in liquid_products via wine_variants and wine_masters
                            $query->whereExists(function ($subquery) use ($search): void {
                                $subquery->selectRaw('1')
                                    ->from('liquid_products')
                                    ->join('wine_variants', 'liquid_products.wine_variant_id', '=', 'wine_variants.id')
                                    ->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                                    ->whereColumn('liquid_products.id', 'bottling_instructions.liquid_product_id')
                                    ->where('wine_masters.name', 'like', "%{$search}%");
                            });
                        });
                    })
                    ->wrap(),

                TextColumn::make('bottle_equivalents')
                    ->label('Bottle Equiv.')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('allowed_formats')
                    ->label('Allowed Formats')
                    ->state(fn (BottlingInstruction $record): string => $record->getAllowedFormatsLabel())
                    ->badge()
                    ->separator(', ')
                    ->color('gray')
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('bottling_deadline')
                    ->label('Bottling Deadline')
                    ->date()
                    ->sortable()
                    ->color(fn (BottlingInstruction $record): string => match ($record->getDeadlineUrgency()) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (BottlingInstruction $record): string => match ($record->getDeadlineUrgency()) {
                        'critical' => $record->isDeadlinePassed()
                            ? 'Passed '.abs($record->getDaysUntilDeadline()).' days ago'
                            : $record->getDaysUntilDeadline().' days remaining',
                        'warning' => $record->getDaysUntilDeadline().' days remaining',
                        default => '',
                    }),

                IconColumn::make('deadline_urgent')
                    ->label('Urgent')
                    ->boolean()
                    ->state(fn (BottlingInstruction $record): bool => $record->isDeadlineWithinDays(30) || $record->isDeadlinePassed())
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn (BottlingInstruction $record): ?string => $record->isDeadlinePassed()
                        ? 'Deadline has passed!'
                        : ($record->isDeadlineWithinDays(30) ? 'Deadline within 30 days' : null)),

                TextColumn::make('preference_status')
                    ->label('Preference Status')
                    ->badge()
                    ->formatStateUsing(fn (BottlingPreferenceStatus $state): string => $state->label())
                    ->color(fn (BottlingPreferenceStatus $state): string => $state->color())
                    ->icon(fn (BottlingPreferenceStatus $state): string => $state->icon())
                    ->sortable(),

                IconColumn::make('preference_pending')
                    ->label('Needs Prefs')
                    ->boolean()
                    ->state(fn (BottlingInstruction $record): bool => $record->preference_status === BottlingPreferenceStatus::Pending)
                    ->trueIcon('heroicon-o-bell-alert')
                    ->falseIcon('')
                    ->trueColor('warning')
                    ->tooltip(fn (BottlingInstruction $record): ?string => $record->preference_status === BottlingPreferenceStatus::Pending
                        ? 'Customer preferences pending'
                        : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (BottlingInstructionStatus $state): string => $state->label())
                    ->color(fn (BottlingInstructionStatus $state): string => $state->color())
                    ->icon(fn (BottlingInstructionStatus $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BottlingInstructionStatus::cases())
                        ->mapWithKeys(fn (BottlingInstructionStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default(fn (): array => [
                        BottlingInstructionStatus::Draft->value,
                        BottlingInstructionStatus::Active->value,
                    ])
                    ->multiple()
                    ->label('Status'),

                SelectFilter::make('preference_status')
                    ->options(collect(BottlingPreferenceStatus::cases())
                        ->mapWithKeys(fn (BottlingPreferenceStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->multiple()
                    ->label('Preference Status'),

                Filter::make('deadline_range')
                    ->schema([
                        DatePicker::make('deadline_from')
                            ->label('Deadline From'),
                        DatePicker::make('deadline_to')
                            ->label('Deadline To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['deadline_from'],
                                fn (Builder $query, $date): Builder => $query->where('bottling_deadline', '>=', $date)
                            )
                            ->when(
                                $data['deadline_to'],
                                fn (Builder $query, $date): Builder => $query->where('bottling_deadline', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['deadline_from'] ?? null) {
                            $indicators[] = Indicator::make('Deadline from '.Carbon::parse($data['deadline_from'])->format('M j, Y'))
                                ->removeField('deadline_from');
                        }

                        if ($data['deadline_to'] ?? null) {
                            $indicators[] = Indicator::make('Deadline to '.Carbon::parse($data['deadline_to'])->format('M j, Y'))
                                ->removeField('deadline_to');
                        }

                        return $indicators;
                    }),

                Filter::make('deadline_urgent')
                    ->label('Deadline < 30 days')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('bottling_deadline', '<=', now()->addDays(30))
                        ->where('bottling_deadline', '>=', now()))
                    ->toggle(),

                Filter::make('deadline_passed')
                    ->label('Deadline Passed')
                    ->query(fn (Builder $query): Builder => $query->where('bottling_deadline', '<', now()))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('bottling_deadline', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['liquidProduct.wineVariant.wineMaster', 'procurementIntent']));
    }

    public static function getRelations(): array
    {
        return [
            // Relations will be implemented in US-033 (detail tabs)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBottlingInstructions::route('/'),
            'create' => CreateBottlingInstruction::route('/create'),
            'view' => ViewBottlingInstruction::route('/{record}'),
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
