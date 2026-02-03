<?php

namespace App\Filament\Resources;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource\Pages;
use App\Models\Commercial\DiscountRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DiscountRuleResource extends Resource
{
    protected static ?string $model = DiscountRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Discount Rules';

    protected static ?string $modelLabel = 'Discount Rule';

    protected static ?string $pluralModelLabel = 'Discount Rules';

    protected static ?string $navigationParentItem = 'Discounts & Rules';

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rule Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Summer Sale 15%, Volume Discount 6+')
                            ->columnSpanFull(),
                        Forms\Components\Radio::make('rule_type')
                            ->label('Rule Type')
                            ->options(collect(DiscountRuleType::cases())->mapWithKeys(fn (DiscountRuleType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->descriptions(collect(DiscountRuleType::cases())->mapWithKeys(fn (DiscountRuleType $type) => [
                                $type->value => $type->description().' (e.g., '.$type->example().')',
                            ]))
                            ->required()
                            ->live()
                            ->disabled(fn (?DiscountRule $record) => $record !== null && ! $record->canBeEdited())
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->options(collect(DiscountRuleStatus::cases())->mapWithKeys(fn (DiscountRuleStatus $status) => [
                                $status->value => $status->label(),
                            ]))
                            ->required()
                            ->default(DiscountRuleStatus::Active->value)
                            ->native(false)
                            ->disabled(fn (?DiscountRule $record) => $record !== null && ! $record->canBeEdited())
                            ->helperText(fn (?DiscountRule $record) => $record !== null && ! $record->canBeEdited()
                                ? 'Status cannot be changed while active Offers are using this rule.'
                                : null)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Dynamic Logic Builder Section
                Forms\Components\Section::make('Logic Configuration')
                    ->description('Configure the discount calculation logic based on the rule type selected above.')
                    ->schema([
                        // Percentage Discount - Simple value input
                        Forms\Components\TextInput::make('logic_definition.value')
                            ->label('Discount Percentage')
                            ->numeric()
                            ->required(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::Percentage->value)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->placeholder('e.g., 15')
                            ->helperText('Enter the percentage discount to apply (0-100)')
                            ->visible(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::Percentage->value)
                            ->live(onBlur: true),

                        // Fixed Amount Discount - Simple value input
                        Forms\Components\TextInput::make('logic_definition.value')
                            ->label('Discount Amount')
                            ->numeric()
                            ->required(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::FixedAmount->value)
                            ->prefix('€')
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('e.g., 10.00')
                            ->helperText('Enter the fixed amount to subtract from the price')
                            ->visible(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::FixedAmount->value)
                            ->live(onBlur: true),

                        // Tiered Discount - Multiple tiers based on price ranges
                        Forms\Components\Repeater::make('logic_definition.tiers')
                            ->label('Price Tiers')
                            ->schema([
                                Forms\Components\TextInput::make('min')
                                    ->label('Minimum Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->required(),
                                Forms\Components\TextInput::make('max')
                                    ->label('Maximum Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('Leave empty for unlimited')
                                    ->helperText('Leave empty for no upper limit'),
                                Forms\Components\TextInput::make('value')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.1)
                                    ->placeholder('e.g., 10')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Tier')
                            ->reorderable()
                            ->collapsible()
                            ->helperText('Define price ranges and the discount percentage applied to each range. Tiers are evaluated in order.')
                            ->visible(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::Tiered->value)
                            ->live(),

                        // Volume-Based Discount - Multiple thresholds based on quantity
                        Forms\Components\Repeater::make('logic_definition.thresholds')
                            ->label('Quantity Thresholds')
                            ->schema([
                                Forms\Components\TextInput::make('min_qty')
                                    ->label('Minimum Quantity')
                                    ->integer()
                                    ->minValue(1)
                                    ->placeholder('e.g., 6')
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->prefix('€')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('e.g., 10.00')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Threshold')
                            ->reorderable()
                            ->collapsible()
                            ->helperText('Define quantity thresholds and the fixed discount applied when the threshold is met. The highest matching threshold is used.')
                            ->visible(fn (Get $get): bool => $get('rule_type') === DiscountRuleType::VolumeBased->value)
                            ->live(),

                        // Placeholder message when no rule type selected
                        Forms\Components\Placeholder::make('select_type_message')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-gray-500 dark:text-gray-400">Select a rule type above to configure the discount logic.</span>
                                    </div>
                                </div>
                            '))
                            ->visible(fn (Get $get): bool => $get('rule_type') === null),
                    ])
                    ->visible(fn (Get $get): bool => $get('rule_type') !== null || true),

                // Preview Section
                Forms\Components\Section::make('Rule Preview')
                    ->description('See how your discount rule will be displayed and calculated.')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                return self::generateRulePreview($get);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('rule_type') !== null)
                    ->collapsed(),
            ]);
    }

    /**
     * Generate the plain-language preview for the rule.
     */
    public static function generateRulePreview(Get $get): HtmlString
    {
        $ruleType = $get('rule_type');
        if ($ruleType === null) {
            return new HtmlString('<div class="text-gray-500">Select a rule type to see the preview.</div>');
        }

        $type = DiscountRuleType::tryFrom($ruleType);
        if ($type === null) {
            return new HtmlString('<div class="text-gray-500">Invalid rule type.</div>');
        }

        $summary = '';
        $examples = [];

        switch ($type) {
            case DiscountRuleType::Percentage:
                $value = $get('logic_definition.value');
                if ($value !== null && $value !== '') {
                    $summary = '<strong>'.number_format((float) $value, 1).'% off</strong> the base price';
                    $examples = [
                        ['Base Price: €100.00', 'Discount: €'.number_format((float) $value, 2), 'Final: €'.number_format(100 - (float) $value, 2)],
                        ['Base Price: €250.00', 'Discount: €'.number_format(250 * (float) $value / 100, 2), 'Final: €'.number_format(250 - (250 * (float) $value / 100), 2)],
                    ];
                } else {
                    $summary = 'Percentage discount (value not configured yet)';
                }
                break;

            case DiscountRuleType::FixedAmount:
                $value = $get('logic_definition.value');
                if ($value !== null && $value !== '') {
                    $summary = '<strong>€'.number_format((float) $value, 2).' off</strong> the base price';
                    $examples = [
                        ['Base Price: €100.00', 'Discount: €'.number_format((float) $value, 2), 'Final: €'.number_format(max(0, 100 - (float) $value), 2)],
                        ['Base Price: €50.00', 'Discount: €'.number_format((float) $value, 2), 'Final: €'.number_format(max(0, 50 - (float) $value), 2)],
                    ];
                } else {
                    $summary = 'Fixed amount discount (value not configured yet)';
                }
                break;

            case DiscountRuleType::Tiered:
                $tiers = $get('logic_definition.tiers');
                if (is_array($tiers) && count($tiers) > 0) {
                    $validTiers = array_filter($tiers, function ($t): bool {
                        if (! is_array($t)) {
                            return false;
                        }
                        $val = $t['value'] ?? null;

                        return $val !== '' && $val !== null;
                    });
                    $summary = '<strong>'.count($validTiers).' tier(s)</strong> configured based on price ranges';
                    $examples = [];
                    foreach ($validTiers as $i => $tier) {
                        if (! is_array($tier)) {
                            continue;
                        }
                        $minVal = $tier['min'] ?? null;
                        $maxVal = $tier['max'] ?? null;
                        $min = ($minVal !== '' && $minVal !== null) ? (float) $minVal : 0;
                        $max = ($maxVal !== '' && $maxVal !== null) ? '€'.number_format((float) $maxVal, 2) : '∞';
                        $value = (float) ($tier['value'] ?? 0);
                        $examples[] = ['Tier '.($i + 1).': €'.number_format($min, 2).' - '.$max, 'Discount: '.$value.'%', ''];
                    }
                } else {
                    $summary = 'Tiered discount (no tiers configured yet)';
                }
                break;

            case DiscountRuleType::VolumeBased:
                $thresholds = $get('logic_definition.thresholds');
                if (is_array($thresholds) && count($thresholds) > 0) {
                    $validThresholds = array_filter($thresholds, function ($t): bool {
                        if (! is_array($t)) {
                            return false;
                        }
                        $minQty = $t['min_qty'] ?? null;
                        $val = $t['value'] ?? null;

                        return $minQty !== '' && $minQty !== null && $val !== '' && $val !== null;
                    });
                    $summary = '<strong>'.count($validThresholds).' threshold(s)</strong> configured based on quantity';
                    $examples = [];
                    foreach ($validThresholds as $i => $threshold) {
                        if (! is_array($threshold)) {
                            continue;
                        }
                        $minQty = (int) ($threshold['min_qty'] ?? 0);
                        $value = (float) ($threshold['value'] ?? 0);
                        $examples[] = ['Quantity >= '.$minQty, 'Discount: €'.number_format($value, 2), ''];
                    }
                } else {
                    $summary = 'Volume-based discount (no thresholds configured yet)';
                }
                break;
        }

        $html = '<div class="space-y-4">';

        // Summary
        $html .= '<div class="p-4 bg-'.$type->color().'-50 dark:bg-'.$type->color().'-900/20 rounded-lg border border-'.$type->color().'-200 dark:border-'.$type->color().'-800">';
        $html .= '<div class="flex items-center gap-3">';
        $html .= '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-'.$type->color().'-100 dark:bg-'.$type->color().'-800">';
        $html .= '<svg class="w-5 h-5 text-'.$type->color().'-600 dark:text-'.$type->color().'-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        $html .= '</span>';
        $html .= '<div>';
        $html .= '<div class="font-medium text-'.$type->color().'-800 dark:text-'.$type->color().'-200">'.$type->label().'</div>';
        $html .= '<div class="text-sm text-'.$type->color().'-600 dark:text-'.$type->color().'-400">'.$summary.'</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Examples table
        if (! empty($examples)) {
            $html .= '<div class="mt-4">';
            $html .= '<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Example Calculations</div>';
            $html .= '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
            $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
            $html .= '<tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">';
            foreach ($examples as $example) {
                $html .= '<tr>';
                foreach ($example as $cell) {
                    if ($cell !== '') {
                        $html .= '<td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">'.$cell.'</td>';
                    }
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (DiscountRuleType $state): string => $state->label())
                    ->color(fn (DiscountRuleType $state): string => $state->color())
                    ->icon(fn (DiscountRuleType $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Summary')
                    ->getStateUsing(fn (DiscountRule $record): string => $record->getSummary())
                    ->color('gray')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DiscountRuleStatus $state): string => $state->label())
                    ->color(fn (DiscountRuleStatus $state): string => $state->color())
                    ->icon(fn (DiscountRuleStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('offers_using_count')
                    ->label('Offers Using')
                    ->getStateUsing(fn (DiscountRule $record): int => $record->getOffersUsingCount())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('offerBenefits')
                            ->orderBy('offer_benefits_count', $direction);
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_type')
                    ->label('Rule Type')
                    ->options(collect(DiscountRuleType::cases())->mapWithKeys(fn (DiscountRuleType $type) => [
                        $type->value => $type->label(),
                    ])),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DiscountRuleStatus::cases())->mapWithKeys(fn (DiscountRuleStatus $status) => [
                        $status->value => $status->label(),
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (DiscountRule $record): bool => $record->canBeEdited()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Rules')
                        ->modalDescription('Are you sure you want to activate the selected discount rules?')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $activated = 0;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->isInactive()) {
                                    $record->status = DiscountRuleStatus::Active;
                                    $record->save();
                                    $activated++;
                                }
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("{$activated} rule(s) activated")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Rules')
                        ->modalDescription('Are you sure you want to deactivate the selected discount rules? Rules with active Offers will be skipped.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deactivated = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->canBeDeactivated()) {
                                    $record->status = DiscountRuleStatus::Inactive;
                                    $record->save();
                                    $deactivated++;
                                } elseif ($record->isActive()) {
                                    $skipped++;
                                }
                            }
                            $message = "{$deactivated} rule(s) deactivated";
                            if ($skipped > 0) {
                                $message .= ", {$skipped} skipped (have active Offers)";
                            }
                            \Filament\Notifications\Notification::make()
                                ->title($message)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action): void {
                            $hasReferencedRules = false;
                            foreach ($records as $record) {
                                /** @var DiscountRule $record */
                                if ($record->isReferencedByAnyOffer()) {
                                    $hasReferencedRules = true;
                                    break;
                                }
                            }
                            if ($hasReferencedRules) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot delete')
                                    ->body('Some rules are referenced by Offers and cannot be deleted.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
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
            'index' => Pages\ListDiscountRules::route('/'),
            'create' => Pages\CreateDiscountRule::route('/create'),
            'view' => Pages\ViewDiscountRule::route('/{record}'),
            'edit' => Pages\EditDiscountRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
