<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreatePricingPolicy extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PricingPolicyResource::class;

    /**
     * Get the form for creating a pricing policy.
     * Implements a multi-step wizard for pricing policy creation.
     */
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getWizardSubmitAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
    }

    /**
     * Get the wizard submit action.
     */
    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <x-filament::button
                    type="submit"
                    size="sm"
                >
                    Create as Draft
                </x-filament::button>
            BLADE)
        );
    }

    /**
     * Get the wizard steps.
     *
     * @return array<Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getTypeStep(),
            $this->getInputsStep(),
        ];
    }

    /**
     * Step 1: Type Selection
     * Defines the name and policy type of the pricing policy.
     */
    protected function getTypeStep(): Wizard\Step
    {
        return Wizard\Step::make('Type')
            ->description('Select the type of Pricing Policy')
            ->icon('heroicon-o-calculator')
            ->schema([
                // Info section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('policy_info')
                            ->label('')
                            ->content('Pricing Policies automate the generation of prices for your Price Books. Each policy type uses a different calculation method to determine prices.')
                            ->columnSpanFull(),
                    ]),

                // Name section
                Forms\Components\Section::make('Policy Identity')
                    ->description('Give your policy a clear, descriptive name')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., B2C Cost + 30% Margin Policy')
                            ->helperText('Use a descriptive name that indicates the policy type and purpose'),
                    ]),

                // Policy type selection
                Forms\Components\Section::make('Policy Type')
                    ->description('Choose how this policy will calculate prices')
                    ->schema([
                        Forms\Components\Radio::make('policy_type')
                            ->label('')
                            ->options([
                                PricingPolicyType::CostPlusMargin->value => PricingPolicyType::CostPlusMargin->label(),
                                PricingPolicyType::ReferencePriceBook->value => PricingPolicyType::ReferencePriceBook->label(),
                                PricingPolicyType::IndexBased->value => 'External Index (EMP/FX)',
                                PricingPolicyType::FixedAdjustment->value => PricingPolicyType::FixedAdjustment->label(),
                                PricingPolicyType::Rounding->value => 'Rounding/Normalization',
                            ])
                            ->descriptions([
                                PricingPolicyType::CostPlusMargin->value => 'Calculate prices as cost plus a percentage margin. Best for maintaining consistent profit margins across products.',
                                PricingPolicyType::ReferencePriceBook->value => 'Use another Price Book as a reference and apply adjustments. Useful for creating channel-specific pricing from a master price book.',
                                PricingPolicyType::IndexBased->value => 'Calculate prices based on external market indexes like Estimated Market Price (EMP) or currency exchange rates (FX).',
                                PricingPolicyType::FixedAdjustment->value => 'Apply a fixed percentage or amount adjustment to existing prices. Good for temporary promotions or regional adjustments.',
                                PricingPolicyType::Rounding->value => 'Normalize prices to specific patterns (e.g., .99, .95, nearest 5). Apply as a final step to other policies.',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // Dynamic info based on selected type
                        Forms\Components\Placeholder::make('type_details')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $type = $get('policy_type');

                                if ($type === null) {
                                    return new HtmlString('<p class="text-gray-500">Select a policy type to see more details.</p>');
                                }

                                $policyType = PricingPolicyType::from($type);

                                $details = match ($policyType) {
                                    PricingPolicyType::CostPlusMargin => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'green',
                                        'title' => 'Cost + Margin Policy',
                                        'description' => 'This policy calculates prices by adding a margin to the product cost.',
                                        'inputs' => 'Cost data from your product catalog',
                                        'example' => 'If cost is €50 and margin is 30%, the price will be €65',
                                        'best_for' => 'Standard retail pricing with consistent profit margins',
                                    ],
                                    PricingPolicyType::ReferencePriceBook => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'blue',
                                        'title' => 'Reference Price Book Policy',
                                        'description' => 'This policy uses prices from another Price Book as a starting point and applies adjustments.',
                                        'inputs' => 'An existing Price Book with established prices',
                                        'example' => 'B2B prices = B2C prices - 15% discount',
                                        'best_for' => 'Creating derivative pricing (e.g., wholesale from retail)',
                                    ],
                                    PricingPolicyType::IndexBased => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'amber',
                                        'title' => 'External Index Policy',
                                        'description' => 'This policy calculates prices based on external market data like Estimated Market Price (EMP) or currency exchange rates.',
                                        'inputs' => 'EMP values, FX rates, or other external indexes',
                                        'example' => 'Price = EMP × 1.1 (10% above market)',
                                        'best_for' => 'Market-aware pricing that tracks competition',
                                    ],
                                    PricingPolicyType::FixedAdjustment => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'purple',
                                        'title' => 'Fixed Adjustment Policy',
                                        'description' => 'This policy applies a fixed percentage or amount adjustment to prices.',
                                        'inputs' => 'Existing prices from the target Price Book',
                                        'example' => '+5% price increase or -€10 per unit',
                                        'best_for' => 'Price adjustments, seasonal changes, regional pricing',
                                    ],
                                    PricingPolicyType::Rounding => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'gray',
                                        'title' => 'Rounding/Normalization Policy',
                                        'description' => 'This policy normalizes prices to psychological price points.',
                                        'inputs' => 'Existing prices from the target Price Book',
                                        'example' => '€64.37 → €64.99 (rounded to .99)',
                                        'best_for' => 'Final price formatting for customer-facing prices',
                                    ],
                                };

                                $colorClass = match ($details['color']) {
                                    'green' => 'bg-green-50 border-green-200',
                                    'blue' => 'bg-blue-50 border-blue-200',
                                    'amber' => 'bg-amber-50 border-amber-200',
                                    'purple' => 'bg-purple-50 border-purple-200',
                                    'gray' => 'bg-gray-50 border-gray-200',
                                };

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border {$colorClass}\">".
                                    "<h4 class=\"font-semibold text-lg mb-2\">{$details['title']}</h4>".
                                    "<p class=\"mb-3\">{$details['description']}</p>".
                                    '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'.
                                    '<div>'.
                                    '<p class="font-medium">Input Source:</p>'.
                                    "<p class=\"text-gray-600\">{$details['inputs']}</p>".
                                    '</div>'.
                                    '<div>'.
                                    '<p class="font-medium">Example:</p>'.
                                    "<p class=\"text-gray-600\">{$details['example']}</p>".
                                    '</div>'.
                                    '</div>'.
                                    '<div class="mt-3 text-sm">'.
                                    '<p class="font-medium">Best for:</p>'.
                                    "<p class=\"text-gray-600\">{$details['best_for']}</p>".
                                    '</div>'.
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 2: Inputs Definition
     * Defines the inputs based on policy type.
     */
    protected function getInputsStep(): Wizard\Step
    {
        return Wizard\Step::make('Inputs')
            ->description('Define the input sources for your policy')
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->schema([
                // Info section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('inputs_info')
                            ->label('')
                            ->content(function (Get $get): string {
                                $type = $get('policy_type');
                                if ($type === null) {
                                    return 'Please select a policy type in the previous step.';
                                }

                                $policyType = PricingPolicyType::from($type);

                                return match ($policyType) {
                                    PricingPolicyType::CostPlusMargin => 'Cost + Margin policies calculate prices based on product cost. Configure the cost source below.',
                                    PricingPolicyType::ReferencePriceBook => 'Reference Price Book policies use prices from another Price Book as a starting point. Select the source Price Book below.',
                                    PricingPolicyType::IndexBased => 'External Index policies use market indexes like EMP (Estimated Market Price) or FX rates. Configure the index type and currency conversion below.',
                                    PricingPolicyType::FixedAdjustment => 'Fixed Adjustment policies apply a fixed change to existing prices. Configure the adjustment type and value below.',
                                    PricingPolicyType::Rounding => 'Rounding policies normalize prices to psychological price points. Select the rounding pattern below.',
                                };
                            })
                            ->columnSpanFull(),
                    ]),

                // Cost + Margin inputs
                Forms\Components\Section::make('Cost Source')
                    ->description('Define where cost data comes from')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::CostPlusMargin->value)
                    ->schema([
                        Forms\Components\Select::make('cost_source')
                            ->label('Cost Source')
                            ->options([
                                'product_catalog' => 'Product Catalog (Sellable SKU cost)',
                                'bottle_sku_cost' => 'Bottle SKU Cost (landed cost)',
                                'manual' => 'Manual Entry (per SKU)',
                            ])
                            ->default('product_catalog')
                            ->required()
                            ->helperText('Select where to retrieve cost data for margin calculations')
                            ->live(),

                        Forms\Components\Placeholder::make('cost_source_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $source = $get('cost_source');

                                $info = match ($source) {
                                    'bottle_sku_cost' => '<div class="text-sm bg-blue-50 p-3 rounded-lg border border-blue-200">
                                        <p class="font-medium text-blue-800">Bottle SKU Cost</p>
                                        <p class="text-blue-700">Uses the landed cost from the underlying Bottle SKU. Best for when product costs are tracked at the inventory level.</p>
                                    </div>',
                                    'manual' => '<div class="text-sm bg-amber-50 p-3 rounded-lg border border-amber-200">
                                        <p class="font-medium text-amber-800">Manual Entry</p>
                                        <p class="text-amber-700">Costs will be entered manually per SKU in the scope definition. Use when costs vary by context or are not tracked in the system.</p>
                                    </div>',
                                    default => '<div class="text-sm bg-green-50 p-3 rounded-lg border border-green-200">
                                        <p class="font-medium text-green-800">Product Catalog</p>
                                        <p class="text-green-700">Uses the cost field from the Sellable SKU record. This is the default and most common source.</p>
                                    </div>',
                                };

                                return new HtmlString($info);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Reference Price Book inputs
                Forms\Components\Section::make('Source Price Book')
                    ->description('Select the Price Book to use as a reference')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::ReferencePriceBook->value)
                    ->schema([
                        Forms\Components\Select::make('source_price_book_id')
                            ->label('Reference Price Book')
                            ->options(function (): array {
                                return \App\Models\Commercial\PriceBook::query()
                                    ->whereIn('status', [
                                        \App\Enums\Commercial\PriceBookStatus::Active,
                                        \App\Enums\Commercial\PriceBookStatus::Draft,
                                    ])
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($pb): array => [
                                        $pb->id => "{$pb->name} ({$pb->market} - {$pb->currency}) [{$pb->status->label()}]",
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select an existing Price Book to use as the base for price calculations')
                            ->live(),

                        Forms\Components\Placeholder::make('source_preview')
                            ->label('Source Price Book Details')
                            ->content(function (Get $get): HtmlString {
                                $id = $get('source_price_book_id');
                                if (! $id) {
                                    return new HtmlString('<p class="text-gray-500">Select a Price Book to see its details.</p>');
                                }

                                $pb = \App\Models\Commercial\PriceBook::withCount('entries')->find($id);
                                if (! $pb) {
                                    return new HtmlString('<p class="text-red-500">Price Book not found.</p>');
                                }

                                $statusColor = match ($pb->status) {
                                    \App\Enums\Commercial\PriceBookStatus::Active => 'green',
                                    \App\Enums\Commercial\PriceBookStatus::Draft => 'yellow',
                                    default => 'gray',
                                };

                                return new HtmlString(
                                    "<div class=\"bg-gray-50 p-4 rounded-lg border\">
                                        <div class=\"grid grid-cols-2 gap-4\">
                                            <div>
                                                <p class=\"text-sm font-medium\">Market</p>
                                                <p class=\"text-sm text-gray-600\">{$pb->market}</p>
                                            </div>
                                            <div>
                                                <p class=\"text-sm font-medium\">Currency</p>
                                                <p class=\"text-sm text-gray-600\">{$pb->currency}</p>
                                            </div>
                                            <div>
                                                <p class=\"text-sm font-medium\">Status</p>
                                                <p class=\"text-sm\"><span class=\"px-2 py-1 rounded text-xs bg-{$statusColor}-100 text-{$statusColor}-800\">{$pb->status->label()}</span></p>
                                            </div>
                                            <div>
                                                <p class=\"text-sm font-medium\">Price Entries</p>
                                                <p class=\"text-sm text-gray-600\">{$pb->entries_count} SKUs</p>
                                            </div>
                                        </div>
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // External Index inputs
                Forms\Components\Section::make('Index Configuration')
                    ->description('Configure the external index source')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::IndexBased->value)
                    ->schema([
                        Forms\Components\Radio::make('index_type')
                            ->label('Index Type')
                            ->options([
                                'emp' => 'Estimated Market Price (EMP)',
                                'fx_rate' => 'Currency Exchange Rate (FX)',
                            ])
                            ->descriptions([
                                'emp' => 'Use EMP values as the price reference. Prices will track market estimates.',
                                'fx_rate' => 'Convert prices from another currency using exchange rates.',
                            ])
                            ->default('emp')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // EMP-specific options
                        Forms\Components\Select::make('emp_market')
                            ->label('EMP Market')
                            ->options(function (): array {
                                $markets = \App\Models\Commercial\EstimatedMarketPrice::query()
                                    ->distinct()
                                    ->pluck('market')
                                    ->sort()
                                    ->mapWithKeys(fn ($m): array => [$m => $m])
                                    ->toArray();

                                return ! empty($markets) ? $markets : ['default' => 'Default Market'];
                            })
                            ->default('default')
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('index_type') === 'emp')
                            ->helperText('Select the market for EMP values'),

                        Forms\Components\Select::make('emp_confidence_threshold')
                            ->label('Minimum Confidence Level')
                            ->options([
                                'any' => 'Any confidence level',
                                'low' => 'Low or better',
                                'medium' => 'Medium or better',
                                'high' => 'High only',
                            ])
                            ->default('any')
                            ->visible(fn (Get $get): bool => $get('index_type') === 'emp')
                            ->helperText('Only use EMP values at or above this confidence threshold'),

                        // FX-specific options
                        Forms\Components\Select::make('source_currency')
                            ->label('Source Currency')
                            ->options([
                                'EUR' => 'EUR (Euro)',
                                'USD' => 'USD (US Dollar)',
                                'GBP' => 'GBP (British Pound)',
                                'CHF' => 'CHF (Swiss Franc)',
                                'HKD' => 'HKD (Hong Kong Dollar)',
                            ])
                            ->default('EUR')
                            ->visible(fn (Get $get): bool => $get('index_type') === 'fx_rate')
                            ->helperText('Currency to convert from'),

                        Forms\Components\Select::make('target_currency')
                            ->label('Target Currency')
                            ->options([
                                'EUR' => 'EUR (Euro)',
                                'USD' => 'USD (US Dollar)',
                                'GBP' => 'GBP (British Pound)',
                                'CHF' => 'CHF (Swiss Franc)',
                                'HKD' => 'HKD (Hong Kong Dollar)',
                            ])
                            ->default('USD')
                            ->visible(fn (Get $get): bool => $get('index_type') === 'fx_rate')
                            ->helperText('Currency to convert to'),

                        Forms\Components\TextInput::make('fx_rate_buffer')
                            ->label('Rate Buffer (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(50)
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => $get('index_type') === 'fx_rate')
                            ->helperText('Add a buffer to the exchange rate (e.g., 2% for hedging)'),

                        Forms\Components\Placeholder::make('fx_info')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('index_type') === 'fx_rate')
                            ->content(new HtmlString(
                                '<div class="text-sm bg-amber-50 p-3 rounded-lg border border-amber-200">
                                    <p class="font-medium text-amber-800">Currency Conversion Note</p>
                                    <p class="text-amber-700">FX rates are fetched from the system\'s configured exchange rate provider. Ensure rates are kept up-to-date for accurate pricing.</p>
                                </div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Fixed Adjustment inputs
                Forms\Components\Section::make('Adjustment Configuration')
                    ->description('Define the adjustment to apply')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::FixedAdjustment->value)
                    ->schema([
                        Forms\Components\Radio::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->options([
                                'percentage' => 'Percentage Adjustment',
                                'fixed_amount' => 'Fixed Amount Adjustment',
                            ])
                            ->descriptions([
                                'percentage' => 'Apply a percentage increase or decrease (e.g., +5%, -10%)',
                                'fixed_amount' => 'Apply a fixed amount increase or decrease (e.g., +€10, -€5)',
                            ])
                            ->default('percentage')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('adjustment_value')
                            ->label(fn (Get $get): string => $get('adjustment_type') === 'fixed_amount'
                                ? 'Amount'
                                : 'Percentage')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix(fn (Get $get): string => $get('adjustment_type') === 'fixed_amount' ? '' : '%')
                            ->helperText(fn (Get $get): string => $get('adjustment_type') === 'fixed_amount'
                                ? 'Use positive values for increase, negative for decrease (e.g., 10 or -5)'
                                : 'Use positive values for increase, negative for decrease (e.g., 5 for +5%, -10 for -10%)'),

                        Forms\Components\Placeholder::make('adjustment_preview')
                            ->label('Preview')
                            ->content(function (Get $get): HtmlString {
                                $type = $get('adjustment_type');
                                $value = (float) ($get('adjustment_value') ?? 0);

                                if ($value === 0.0) {
                                    return new HtmlString('<p class="text-gray-500">Enter a value to see the preview.</p>');
                                }

                                $direction = $value >= 0 ? 'increase' : 'decrease';
                                $absValue = abs($value);
                                $basePrice = 100;

                                if ($type === 'fixed_amount') {
                                    $newPrice = $basePrice + $value;
                                    $symbol = $value >= 0 ? '+' : '';
                                    $preview = "€{$basePrice} {$symbol}€{$value} = €{$newPrice}";
                                } else {
                                    $newPrice = $basePrice * (1 + $value / 100);
                                    $symbol = $value >= 0 ? '+' : '';
                                    $preview = "€{$basePrice} × (1 {$symbol} {$value}%) = €".number_format($newPrice, 2);
                                }

                                $colorClass = $value >= 0 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';

                                return new HtmlString(
                                    "<div class=\"p-3 rounded-lg border {$colorClass}\">
                                        <p class=\"font-medium\">Example (base price €100):</p>
                                        <p>{$preview}</p>
                                        <p class=\"text-sm mt-1\">This will {$direction} prices by ".($type === 'fixed_amount' ? '€'.$absValue : $absValue.'%').'</p>
                                    </div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Rounding inputs
                Forms\Components\Section::make('Rounding Rule')
                    ->description('Select the price rounding pattern')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::Rounding->value)
                    ->schema([
                        Forms\Components\Radio::make('rounding_rule')
                            ->label('Rounding Pattern')
                            ->options([
                                '.99' => 'End in .99 (e.g., €64.37 → €64.99)',
                                '.95' => 'End in .95 (e.g., €64.37 → €64.95)',
                                '.90' => 'End in .90 (e.g., €64.37 → €64.90)',
                                '.00' => 'Round to whole (e.g., €64.37 → €64.00 or €65.00)',
                                'nearest_5' => 'Nearest 5 (e.g., €64.37 → €65.00)',
                                'nearest_10' => 'Nearest 10 (e.g., €64.37 → €60.00)',
                            ])
                            ->descriptions([
                                '.99' => 'Classic psychological pricing - prices end in .99',
                                '.95' => 'Slightly higher ending - prices end in .95',
                                '.90' => 'Round ending - prices end in .90',
                                '.00' => 'Whole numbers only - no cents',
                                'nearest_5' => 'Round to the nearest €5 increment',
                                'nearest_10' => 'Round to the nearest €10 increment',
                            ])
                            ->default('.99')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('rounding_direction')
                            ->label('Rounding Direction')
                            ->options([
                                'nearest' => 'Nearest (up or down)',
                                'up' => 'Always round up (ceil)',
                                'down' => 'Always round down (floor)',
                            ])
                            ->default('nearest')
                            ->helperText('How to handle the main number when applying the pattern'),

                        Forms\Components\Placeholder::make('rounding_preview')
                            ->label('Examples')
                            ->content(function (Get $get): HtmlString {
                                $rule = $get('rounding_rule') ?? '.99';
                                $direction = $get('rounding_direction') ?? 'nearest';

                                $examples = [
                                    ['original' => 64.37, 'context' => 'mid-range'],
                                    ['original' => 99.12, 'context' => 'near boundary'],
                                    ['original' => 142.67, 'context' => 'higher price'],
                                ];

                                $rows = '';
                                foreach ($examples as $ex) {
                                    $original = $ex['original'];
                                    $rounded = self::previewRounding($original, $rule, $direction);
                                    $rows .= '<tr><td class="py-1">€'.number_format($original, 2).'</td><td class="py-1 px-4">→</td><td class="py-1 font-medium">€'.number_format($rounded, 2).'</td></tr>';
                                }

                                return new HtmlString(
                                    '<div class="bg-gray-50 p-3 rounded-lg border">
                                        <table class="text-sm">
                                            <thead><tr><th class="text-left py-1">Original</th><th></th><th class="text-left py-1">Rounded</th></tr></thead>
                                            <tbody>'.$rows.'</tbody>
                                        </table>
                                    </div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * Preview rounding for a given value and rule.
     */
    protected static function previewRounding(float $value, string $rule, string $direction): float
    {
        $intPart = (int) floor($value);
        $decPart = $value - $intPart;

        return match ($rule) {
            '.99' => match ($direction) {
                'up' => $intPart + 0.99 + ($decPart > 0.99 ? 1 : 0),
                'down' => $intPart + 0.99 - ($decPart < 0.99 ? 1 : 0),
                default => ($decPart >= 0.495) ? $intPart + 0.99 : $intPart - 0.01,
            },
            '.95' => match ($direction) {
                'up' => $intPart + 0.95 + ($decPart > 0.95 ? 1 : 0),
                'down' => $intPart + 0.95 - ($decPart < 0.95 ? 1 : 0),
                default => ($decPart >= 0.475) ? $intPart + 0.95 : $intPart - 0.05,
            },
            '.90' => match ($direction) {
                'up' => $intPart + 0.90 + ($decPart > 0.90 ? 1 : 0),
                'down' => $intPart + 0.90 - ($decPart < 0.90 ? 1 : 0),
                default => ($decPart >= 0.45) ? $intPart + 0.90 : $intPart - 0.10,
            },
            '.00' => match ($direction) {
                'up' => ceil($value),
                'down' => floor($value),
                default => round($value),
            },
            'nearest_5' => match ($direction) {
                'up' => ceil($value / 5) * 5,
                'down' => floor($value / 5) * 5,
                default => round($value / 5) * 5,
            },
            'nearest_10' => match ($direction) {
                'up' => ceil($value / 10) * 10,
                'down' => floor($value / 10) * 10,
                default => round($value / 10) * 10,
            },
            default => $value,
        };
    }

    /**
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set status to draft
        $data['status'] = PricingPolicyStatus::Draft->value;

        // Set default execution cadence to manual (will be configurable in Step 5)
        $data['execution_cadence'] = ExecutionCadence::Manual->value;

        // Set input source based on policy type and step 2 selections
        $policyType = PricingPolicyType::from($data['policy_type']);
        $data['input_source'] = match ($policyType) {
            PricingPolicyType::CostPlusMargin => PricingPolicyInputSource::Cost->value,
            PricingPolicyType::ReferencePriceBook => PricingPolicyInputSource::PriceBook->value,
            PricingPolicyType::IndexBased => ($data['index_type'] ?? 'emp') === 'fx_rate'
                ? PricingPolicyInputSource::ExternalIndex->value
                : PricingPolicyInputSource::Emp->value,
            PricingPolicyType::FixedAdjustment => PricingPolicyInputSource::PriceBook->value,
            PricingPolicyType::Rounding => PricingPolicyInputSource::PriceBook->value,
        };

        // Build logic_definition from Step 2 inputs
        $logicDefinition = [];

        switch ($policyType) {
            case PricingPolicyType::CostPlusMargin:
                $logicDefinition['cost_source'] = $data['cost_source'] ?? 'product_catalog';
                break;

            case PricingPolicyType::ReferencePriceBook:
                $logicDefinition['source_price_book_id'] = $data['source_price_book_id'] ?? null;
                break;

            case PricingPolicyType::IndexBased:
                $logicDefinition['index_type'] = $data['index_type'] ?? 'emp';
                if (($data['index_type'] ?? 'emp') === 'emp') {
                    $logicDefinition['emp_market'] = $data['emp_market'] ?? 'default';
                    $logicDefinition['emp_confidence_threshold'] = $data['emp_confidence_threshold'] ?? 'any';
                } else {
                    $logicDefinition['source_currency'] = $data['source_currency'] ?? 'EUR';
                    $logicDefinition['target_currency'] = $data['target_currency'] ?? 'USD';
                    $logicDefinition['fx_rate_buffer'] = (float) ($data['fx_rate_buffer'] ?? 0);
                }
                break;

            case PricingPolicyType::FixedAdjustment:
                $logicDefinition['adjustment_type'] = $data['adjustment_type'] ?? 'percentage';
                $logicDefinition['adjustment_value'] = (float) ($data['adjustment_value'] ?? 0);
                break;

            case PricingPolicyType::Rounding:
                $logicDefinition['rounding_rule'] = $data['rounding_rule'] ?? '.99';
                $logicDefinition['rounding_direction'] = $data['rounding_direction'] ?? 'nearest';
                break;
        }

        $data['logic_definition'] = $logicDefinition;

        // Clean up temporary form fields that aren't in the model
        unset(
            $data['cost_source'],
            $data['source_price_book_id'],
            $data['index_type'],
            $data['emp_market'],
            $data['emp_confidence_threshold'],
            $data['source_currency'],
            $data['target_currency'],
            $data['fx_rate_buffer'],
            $data['adjustment_type'],
            $data['adjustment_value'],
            $data['rounding_rule'],
            $data['rounding_direction']
        );

        return $data;
    }

    /**
     * After creating the pricing policy.
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Commercial\PricingPolicy $pricingPolicy */
        $pricingPolicy = $this->record;

        Notification::make()
            ->success()
            ->title('Pricing Policy created')
            ->body("The pricing policy \"{$pricingPolicy->name}\" has been created as Draft. Configure logic and scope, then activate when ready.")
            ->send();
    }
}
