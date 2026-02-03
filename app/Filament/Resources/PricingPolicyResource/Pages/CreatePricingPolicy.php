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
            $this->getLogicStep(),
            $this->getScopeAndTargetStep(),
            $this->getExecutionStep(),
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
     * Step 3: Logic Definition
     * Defines the calculation logic based on policy type.
     */
    protected function getLogicStep(): Wizard\Step
    {
        return Wizard\Step::make('Logic')
            ->description('Define the calculation logic')
            ->icon('heroicon-o-calculator')
            ->schema([
                // Info section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('logic_info')
                            ->label('')
                            ->content(function (Get $get): string {
                                $type = $get('policy_type');
                                if ($type === null) {
                                    return 'Please select a policy type in Step 1.';
                                }

                                $policyType = PricingPolicyType::from($type);

                                return match ($policyType) {
                                    PricingPolicyType::CostPlusMargin => 'Define the margin to add to product cost. You can set a fixed percentage or configure tiered margins for different categories.',
                                    PricingPolicyType::ReferencePriceBook => 'Define how to adjust prices from the reference Price Book. You can apply a percentage adjustment or tiered adjustments.',
                                    PricingPolicyType::IndexBased => 'Define how to calculate prices from the external index. Configure multipliers and adjustments.',
                                    PricingPolicyType::FixedAdjustment => 'The adjustment was already configured in Step 2. Here you can add optional rounding rules.',
                                    PricingPolicyType::Rounding => 'The rounding rule was already configured in Step 2. No additional logic is needed.',
                                };
                            })
                            ->columnSpanFull(),
                    ]),

                // Cost + Margin Logic
                Forms\Components\Section::make('Margin Configuration')
                    ->description('Define how margin is calculated')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::CostPlusMargin->value)
                    ->schema([
                        Forms\Components\Radio::make('margin_type')
                            ->label('Margin Type')
                            ->options([
                                'percentage' => 'Percentage Margin',
                                'fixed_amount' => 'Fixed Amount Markup',
                            ])
                            ->descriptions([
                                'percentage' => 'Add a percentage of the cost (e.g., cost × 1.30 for 30% margin)',
                                'fixed_amount' => 'Add a fixed amount to the cost (e.g., cost + €10)',
                            ])
                            ->default('percentage')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('margin_percentage')
                            ->label('Margin Percentage')
                            ->numeric()
                            ->required()
                            ->default(25)
                            ->minValue(0)
                            ->maxValue(500)
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => ($get('margin_type') ?? 'percentage') === 'percentage')
                            ->helperText('The percentage to add to cost (e.g., 30 means selling at cost + 30%)')
                            ->live(),

                        Forms\Components\TextInput::make('markup_fixed_amount')
                            ->label('Fixed Markup Amount')
                            ->numeric()
                            ->required()
                            ->default(10)
                            ->minValue(0)
                            ->suffix('€')
                            ->visible(fn (Get $get): bool => ($get('margin_type') ?? 'percentage') === 'fixed_amount')
                            ->helperText('Fixed amount to add to each product cost')
                            ->live(),

                        // Tiered margins toggle
                        Forms\Components\Toggle::make('use_tiered_margins')
                            ->label('Use Tiered Margins')
                            ->helperText('Apply different margins based on product category or price range')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        // Tiered margins configuration
                        Forms\Components\Repeater::make('tiered_margins')
                            ->label('Tier Configuration')
                            ->visible(fn (Get $get): bool => $get('use_tiered_margins') === true)
                            ->schema([
                                Forms\Components\Select::make('tier_type')
                                    ->label('Tier Based On')
                                    ->options([
                                        'category' => 'Product Category',
                                        'price_range' => 'Price Range',
                                    ])
                                    ->default('category')
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('tier_category')
                                    ->label('Category Name')
                                    ->visible(fn (Get $get): bool => ($get('tier_type') ?? 'category') === 'category')
                                    ->placeholder('e.g., Premium Wines'),

                                Forms\Components\TextInput::make('tier_min_price')
                                    ->label('Min Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => ($get('tier_type') ?? 'category') === 'price_range'),

                                Forms\Components\TextInput::make('tier_max_price')
                                    ->label('Max Price')
                                    ->numeric()
                                    ->prefix('€')
                                    ->visible(fn (Get $get): bool => ($get('tier_type') ?? 'category') === 'price_range'),

                                Forms\Components\TextInput::make('tier_margin')
                                    ->label('Margin %')
                                    ->numeric()
                                    ->required()
                                    ->suffix('%')
                                    ->default(25),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Tier')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Reference Price Book Logic
                Forms\Components\Section::make('Adjustment Configuration')
                    ->description('Define how to adjust reference prices')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::ReferencePriceBook->value)
                    ->schema([
                        Forms\Components\Radio::make('ref_adjustment_type')
                            ->label('Adjustment Type')
                            ->options([
                                'percentage' => 'Percentage Adjustment',
                                'fixed_amount' => 'Fixed Amount Adjustment',
                            ])
                            ->descriptions([
                                'percentage' => 'Adjust by a percentage (e.g., -15% for wholesale)',
                                'fixed_amount' => 'Adjust by a fixed amount (e.g., -€5 per unit)',
                            ])
                            ->default('percentage')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('ref_adjustment_value')
                            ->label(fn (Get $get): string => ($get('ref_adjustment_type') ?? 'percentage') === 'fixed_amount'
                                ? 'Adjustment Amount'
                                : 'Adjustment Percentage')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix(fn (Get $get): string => ($get('ref_adjustment_type') ?? 'percentage') === 'fixed_amount' ? '€' : '%')
                            ->helperText('Use negative values for discounts, positive for markups')
                            ->live(),

                        // Tiered adjustments toggle
                        Forms\Components\Toggle::make('use_tiered_adjustments')
                            ->label('Use Tiered Adjustments')
                            ->helperText('Apply different adjustments based on product category')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('tiered_adjustments')
                            ->label('Tier Configuration')
                            ->visible(fn (Get $get): bool => $get('use_tiered_adjustments') === true)
                            ->schema([
                                Forms\Components\TextInput::make('tier_category')
                                    ->label('Category Name')
                                    ->placeholder('e.g., Premium Wines'),

                                Forms\Components\TextInput::make('tier_adjustment')
                                    ->label('Adjustment %')
                                    ->numeric()
                                    ->required()
                                    ->suffix('%')
                                    ->default(0)
                                    ->helperText('Negative for discount'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Add Tier')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Index-Based Logic
                Forms\Components\Section::make('Index Multiplier')
                    ->description('Define how to calculate prices from the index')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::IndexBased->value)
                    ->schema([
                        Forms\Components\TextInput::make('index_multiplier')
                            ->label('Index Multiplier')
                            ->numeric()
                            ->required()
                            ->default(1.0)
                            ->minValue(0.1)
                            ->maxValue(10)
                            ->step(0.01)
                            ->helperText('Multiply the index value by this factor (e.g., 1.1 for 10% above market)')
                            ->live(),

                        Forms\Components\TextInput::make('index_fixed_adjustment')
                            ->label('Fixed Adjustment')
                            ->numeric()
                            ->default(0)
                            ->prefix('€')
                            ->helperText('Add/subtract a fixed amount after multiplier (optional)')
                            ->live(),

                        Forms\Components\Placeholder::make('index_example')
                            ->label('Calculation Example')
                            ->content(function (Get $get): HtmlString {
                                $multiplier = (float) ($get('index_multiplier') ?? 1.0);
                                $adjustment = (float) ($get('index_fixed_adjustment') ?? 0);
                                $indexType = $get('index_type') ?? 'emp';

                                $exampleIndex = $indexType === 'emp' ? 100 : 85; // Example EMP or converted price
                                $result = ($exampleIndex * $multiplier) + $adjustment;

                                $formula = "Index Value × {$multiplier}";
                                if ($adjustment !== 0.0) {
                                    $formula .= $adjustment >= 0 ? " + €{$adjustment}" : ' - €'.abs($adjustment);
                                }

                                return new HtmlString(
                                    "<div class=\"bg-blue-50 p-3 rounded-lg border border-blue-200\">
                                        <p class=\"font-medium text-blue-800\">Formula: {$formula}</p>
                                        <p class=\"text-blue-700 mt-1\">Example: €{$exampleIndex} × {$multiplier}".($adjustment !== 0.0 ? ($adjustment >= 0 ? " + €{$adjustment}" : ' - €'.abs($adjustment)) : '').' = €'.number_format($result, 2).'</p>
                                    </div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Fixed Adjustment - just show what was configured in Step 2
                Forms\Components\Section::make('Adjustment Summary')
                    ->description('Review the adjustment configured in Step 2')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::FixedAdjustment->value)
                    ->schema([
                        Forms\Components\Placeholder::make('adjustment_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $type = $get('adjustment_type') ?? 'percentage';
                                $value = (float) ($get('adjustment_value') ?? 0);

                                $direction = $value >= 0 ? 'increase' : 'decrease';
                                $absValue = abs($value);

                                if ($type === 'fixed_amount') {
                                    $description = $value >= 0 ? "+€{$absValue}" : "-€{$absValue}";
                                } else {
                                    $description = $value >= 0 ? "+{$absValue}%" : "-{$absValue}%";
                                }

                                $colorClass = $value >= 0 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border {$colorClass}\">
                                        <p class=\"text-lg font-semibold\">{$description}</p>
                                        <p class=\"mt-1\">This adjustment will {$direction} all prices by ".($type === 'fixed_amount' ? '€'.$absValue : $absValue.'%').'.</p>
                                    </div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Rounding - just show what was configured in Step 2
                Forms\Components\Section::make('Rounding Summary')
                    ->description('Review the rounding rule configured in Step 2')
                    ->visible(fn (Get $get): bool => $get('policy_type') === PricingPolicyType::Rounding->value)
                    ->schema([
                        Forms\Components\Placeholder::make('rounding_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $rule = $get('rounding_rule') ?? '.99';
                                $direction = $get('rounding_direction') ?? 'nearest';

                                $ruleLabel = match ($rule) {
                                    '.99' => 'End in .99',
                                    '.95' => 'End in .95',
                                    '.90' => 'End in .90',
                                    '.00' => 'Whole numbers',
                                    'nearest_5' => 'Nearest €5',
                                    'nearest_10' => 'Nearest €10',
                                    default => $rule,
                                };

                                $directionLabel = match ($direction) {
                                    'up' => 'always round up',
                                    'down' => 'always round down',
                                    default => 'round to nearest',
                                };

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border bg-gray-50 border-gray-200\">
                                        <p class=\"text-lg font-semibold\">{$ruleLabel}</p>
                                        <p class=\"mt-1 text-gray-600\">Direction: {$directionLabel}</p>
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Optional Rounding for non-rounding policies
                Forms\Components\Section::make('Final Rounding (Optional)')
                    ->description('Apply rounding to the calculated price')
                    ->visible(fn (Get $get): bool => ! in_array($get('policy_type'), [
                        PricingPolicyType::Rounding->value,
                        PricingPolicyType::FixedAdjustment->value,
                    ], true))
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('apply_rounding')
                            ->label('Apply Rounding')
                            ->helperText('Round the final calculated price')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('final_rounding_rule')
                            ->label('Rounding Pattern')
                            ->options([
                                '.99' => 'End in .99',
                                '.95' => 'End in .95',
                                '.90' => 'End in .90',
                                '.00' => 'Whole numbers',
                                'nearest_5' => 'Nearest €5',
                                'nearest_10' => 'Nearest €10',
                            ])
                            ->default('.99')
                            ->visible(fn (Get $get): bool => $get('apply_rounding') === true),

                        Forms\Components\Select::make('final_rounding_direction')
                            ->label('Direction')
                            ->options([
                                'nearest' => 'Nearest',
                                'up' => 'Always up',
                                'down' => 'Always down',
                            ])
                            ->default('nearest')
                            ->visible(fn (Get $get): bool => $get('apply_rounding') === true),
                    ])
                    ->columns(2),

                // Formula Preview
                Forms\Components\Section::make('Formula Preview')
                    ->description('Plain-language summary of your pricing logic')
                    ->schema([
                        Forms\Components\Placeholder::make('formula_preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                return self::generateFormulaPreview($get);
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Generate a plain-language formula preview based on form values.
     */
    protected static function generateFormulaPreview(Get $get): HtmlString
    {
        $policyTypeValue = $get('policy_type');
        if ($policyTypeValue === null) {
            return new HtmlString('<p class="text-gray-500">Please complete previous steps to see the formula preview.</p>');
        }

        $policyType = PricingPolicyType::from($policyTypeValue);
        $formula = '';
        $example = '';
        $exampleInput = 100;

        switch ($policyType) {
            case PricingPolicyType::CostPlusMargin:
                $marginType = $get('margin_type') ?? 'percentage';
                $useTiered = $get('use_tiered_margins') ?? false;

                if ($useTiered) {
                    $formula = 'Cost + Tiered Margin (varies by category/range)';
                    $example = 'Example varies by tier configuration';
                } elseif ($marginType === 'percentage') {
                    $margin = (float) ($get('margin_percentage') ?? 25);
                    $formula = "Cost + {$margin}% margin";
                    $result = $exampleInput * (1 + $margin / 100);
                    $example = "€{$exampleInput} × 1.".str_pad((string) (int) $margin, 2, '0', STR_PAD_LEFT).' = €'.number_format($result, 2);
                } else {
                    $amount = (float) ($get('markup_fixed_amount') ?? 10);
                    $formula = "Cost + €{$amount} markup";
                    $result = $exampleInput + $amount;
                    $example = "€{$exampleInput} + €{$amount} = €".number_format($result, 2);
                }
                break;

            case PricingPolicyType::ReferencePriceBook:
                $adjType = $get('ref_adjustment_type') ?? 'percentage';
                $useTiered = $get('use_tiered_adjustments') ?? false;

                if ($useTiered) {
                    $formula = 'Reference Price + Tiered Adjustment (varies by category)';
                    $example = 'Example varies by tier configuration';
                } elseif ($adjType === 'percentage') {
                    $adj = (float) ($get('ref_adjustment_value') ?? 0);
                    $sign = $adj >= 0 ? '+' : '';
                    $formula = "Reference Price {$sign}{$adj}%";
                    $result = $exampleInput * (1 + $adj / 100);
                    $example = "€{$exampleInput} × ".(1 + $adj / 100).' = €'.number_format($result, 2);
                } else {
                    $adj = (float) ($get('ref_adjustment_value') ?? 0);
                    $sign = $adj >= 0 ? '+' : '';
                    $formula = "Reference Price {$sign}€".abs($adj);
                    $result = $exampleInput + $adj;
                    $example = "€{$exampleInput} {$sign}€".abs($adj).' = €'.number_format($result, 2);
                }
                break;

            case PricingPolicyType::IndexBased:
                $multiplier = (float) ($get('index_multiplier') ?? 1.0);
                $adjustment = (float) ($get('index_fixed_adjustment') ?? 0);
                $indexType = $get('index_type') ?? 'emp';
                $indexLabel = $indexType === 'emp' ? 'EMP' : 'Converted Price';

                $formula = "{$indexLabel} × {$multiplier}";
                if ($adjustment !== 0.0) {
                    $formula .= $adjustment >= 0 ? " + €{$adjustment}" : ' - €'.abs($adjustment);
                }
                $result = ($exampleInput * $multiplier) + $adjustment;
                $example = "€{$exampleInput} × {$multiplier}".($adjustment !== 0.0 ? ($adjustment >= 0 ? " + €{$adjustment}" : ' - €'.abs($adjustment)) : '').' = €'.number_format($result, 2);
                break;

            case PricingPolicyType::FixedAdjustment:
                $adjType = $get('adjustment_type') ?? 'percentage';
                $adj = (float) ($get('adjustment_value') ?? 0);

                if ($adjType === 'percentage') {
                    $sign = $adj >= 0 ? '+' : '';
                    $formula = "Current Price {$sign}{$adj}%";
                    $result = $exampleInput * (1 + $adj / 100);
                } else {
                    $sign = $adj >= 0 ? '+' : '';
                    $formula = "Current Price {$sign}€".abs($adj);
                    $result = $exampleInput + $adj;
                }
                $example = "€{$exampleInput} → €".number_format($result, 2);
                break;

            case PricingPolicyType::Rounding:
                $rule = $get('rounding_rule') ?? '.99';
                $direction = $get('rounding_direction') ?? 'nearest';

                $ruleLabel = match ($rule) {
                    '.99' => '.99',
                    '.95' => '.95',
                    '.90' => '.90',
                    '.00' => 'whole',
                    'nearest_5' => 'nearest €5',
                    'nearest_10' => 'nearest €10',
                    default => $rule,
                };
                $formula = "Round to {$ruleLabel}";

                $exampleInput = 64.37;
                $rounded = self::previewRounding($exampleInput, $rule, $direction);
                $example = '€'.number_format($exampleInput, 2).' → €'.number_format($rounded, 2);
                break;
        }

        // Add rounding if enabled (for non-rounding policies)
        if ($policyType !== PricingPolicyType::Rounding && $policyType !== PricingPolicyType::FixedAdjustment) {
            $applyRounding = $get('apply_rounding') ?? false;
            if ($applyRounding) {
                $roundingRule = $get('final_rounding_rule') ?? '.99';
                $formula .= ", rounded to {$roundingRule}";
            }
        }

        return new HtmlString(
            '<div class="p-4 rounded-lg border bg-gradient-to-r from-indigo-50 to-purple-50 border-indigo-200">
                <p class="text-lg font-semibold text-indigo-900">'.$formula.'</p>
                <p class="mt-2 text-indigo-700">Example: '.$example.'</p>
            </div>'
        );
    }

    /**
     * Step 4: Scope & Target
     * Defines the target Price Book and scope of the Pricing Policy.
     */
    protected function getScopeAndTargetStep(): Wizard\Step
    {
        return Wizard\Step::make('Scope & Target')
            ->description('Define target Price Book and SKU scope')
            ->icon('heroicon-o-funnel')
            ->schema([
                // Info section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('scope_info')
                            ->label('')
                            ->content('Pricing Policies generate prices into a target Price Book. Define which Price Book to target and which SKUs should be affected.')
                            ->columnSpanFull(),
                    ]),

                // Target Price Book section
                Forms\Components\Section::make('Target Price Book')
                    ->description('Select the Price Book where prices will be generated')
                    ->schema([
                        Forms\Components\Select::make('target_price_book_id')
                            ->label('Target Price Book')
                            ->options(function (): array {
                                return \App\Models\Commercial\PriceBook::query()
                                    ->whereIn('status', [
                                        \App\Enums\Commercial\PriceBookStatus::Draft,
                                        \App\Enums\Commercial\PriceBookStatus::Active,
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
                            ->live()
                            ->helperText('Select the Price Book where generated prices will be written'),

                        Forms\Components\Placeholder::make('target_price_book_preview')
                            ->label('Target Price Book Details')
                            ->content(function (Get $get): HtmlString {
                                $id = $get('target_price_book_id');
                                if (! $id) {
                                    return new HtmlString('<p class="text-gray-500">Select a Price Book to see its details.</p>');
                                }

                                $pb = \App\Models\Commercial\PriceBook::withCount('entries')->with('channel')->find($id);
                                if (! $pb) {
                                    return new HtmlString('<p class="text-red-500">Price Book not found.</p>');
                                }

                                $statusColor = match ($pb->status) {
                                    \App\Enums\Commercial\PriceBookStatus::Active => 'green',
                                    \App\Enums\Commercial\PriceBookStatus::Draft => 'yellow',
                                    default => 'gray',
                                };

                                $channel = $pb->channel;
                                $channelName = $channel !== null ? $channel->name : 'All Channels';

                                $warning = '';
                                if ($pb->isActive()) {
                                    $warning = '<div class="mt-3 p-2 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
                                        <strong>Note:</strong> This Price Book is already active. Generated prices will be marked as policy_generated but will require re-approval if the Price Book needs to be modified.
                                    </div>';
                                }

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
                                                <p class=\"text-sm font-medium\">Channel</p>
                                                <p class=\"text-sm text-gray-600\">{$channelName}</p>
                                            </div>
                                            <div>
                                                <p class=\"text-sm font-medium\">Status</p>
                                                <p class=\"text-sm\"><span class=\"px-2 py-1 rounded text-xs bg-{$statusColor}-100 text-{$statusColor}-800\">{$pb->status->label()}</span></p>
                                            </div>
                                            <div>
                                                <p class=\"text-sm font-medium\">Current Entries</p>
                                                <p class=\"text-sm text-gray-600\">{$pb->entries_count} SKUs</p>
                                            </div>
                                        </div>
                                        {$warning}
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Scope Definition section
                Forms\Components\Section::make('Scope Definition')
                    ->description('Define which SKUs this policy will affect')
                    ->schema([
                        Forms\Components\Radio::make('scope_type')
                            ->label('Scope Type')
                            ->options([
                                'all' => \App\Enums\Commercial\PolicyScopeType::All->label(),
                                'category' => \App\Enums\Commercial\PolicyScopeType::Category->label(),
                                'product' => \App\Enums\Commercial\PolicyScopeType::Product->label(),
                                'sku' => \App\Enums\Commercial\PolicyScopeType::Sku->label(),
                            ])
                            ->descriptions([
                                'all' => \App\Enums\Commercial\PolicyScopeType::All->description(),
                                'category' => \App\Enums\Commercial\PolicyScopeType::Category->description(),
                                'product' => \App\Enums\Commercial\PolicyScopeType::Product->description(),
                                'sku' => \App\Enums\Commercial\PolicyScopeType::Sku->description(),
                            ])
                            ->default('all')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // Category selection
                        Forms\Components\TextInput::make('scope_category')
                            ->label('Category')
                            ->placeholder('e.g., Premium Wines, Bordeaux, Champagne')
                            ->helperText('Enter the category name to filter by')
                            ->visible(fn (Get $get): bool => $get('scope_type') === 'category')
                            ->required(fn (Get $get): bool => $get('scope_type') === 'category'),

                        // Product selection
                        Forms\Components\TextInput::make('scope_product')
                            ->label('Product')
                            ->placeholder('e.g., Château Margaux 2015')
                            ->helperText('Enter the product name to filter by (all formats)')
                            ->visible(fn (Get $get): bool => $get('scope_type') === 'product')
                            ->required(fn (Get $get): bool => $get('scope_type') === 'product'),

                        // SKU selection
                        Forms\Components\Select::make('scope_skus')
                            ->label('Specific SKUs')
                            ->multiple()
                            ->options(function (): array {
                                return \App\Models\Pim\SellableSku::query()
                                    ->where('lifecycle_status', 'active')
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->orderBy('sku_code')
                                    ->limit(500)
                                    ->get()
                                    ->mapWithKeys(function ($sku): array {
                                        $wineVariant = $sku->wineVariant;
                                        $wineMaster = $wineVariant?->wineMaster;
                                        $wine = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                        $vintage = $wineVariant !== null ? (string) $wineVariant->vintage_year : '';
                                        $format = $sku->format !== null ? (string) $sku->format->volume_ml : '';
                                        $label = "{$sku->sku_code} - {$wine} {$vintage} ({$format}ml)";

                                        return [$sku->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Select specific SKUs to include in the scope')
                            ->visible(fn (Get $get): bool => $get('scope_type') === 'sku')
                            ->required(fn (Get $get): bool => $get('scope_type') === 'sku'),
                    ])
                    ->columns(1),

                // Market/Channel Filters section
                Forms\Components\Section::make('Market & Channel Filters')
                    ->description('Optionally restrict the scope to specific markets or channels')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('scope_markets')
                            ->label('Markets')
                            ->multiple()
                            ->options(function (): array {
                                // Get unique markets from EMP records
                                $markets = \App\Models\Commercial\EstimatedMarketPrice::query()
                                    ->distinct()
                                    ->pluck('market')
                                    ->sort()
                                    ->mapWithKeys(fn ($m): array => [$m => $m])
                                    ->toArray();

                                // Add common markets if EMP data is empty
                                if (empty($markets)) {
                                    $markets = [
                                        'IT' => 'Italy',
                                        'UK' => 'United Kingdom',
                                        'US' => 'United States',
                                        'FR' => 'France',
                                        'DE' => 'Germany',
                                        'HK' => 'Hong Kong',
                                        'SG' => 'Singapore',
                                    ];
                                }

                                return $markets;
                            })
                            ->searchable()
                            ->helperText('Leave empty to apply to all markets'),

                        Forms\Components\Select::make('scope_channels')
                            ->label('Channels')
                            ->multiple()
                            ->options(function (): array {
                                return \App\Models\Commercial\Channel::query()
                                    ->where('status', \App\Enums\Commercial\ChannelStatus::Active)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->helperText('Leave empty to apply to all channels'),
                    ])
                    ->columns(2),

                // Scope Preview section
                Forms\Components\Section::make('Scope Preview')
                    ->description('Preview of the SKUs that will be affected')
                    ->schema([
                        Forms\Components\Placeholder::make('scope_preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $scopeType = $get('scope_type') ?? 'all';

                                // Calculate SKU count based on scope
                                $query = \App\Models\Pim\SellableSku::query()
                                    ->where('lifecycle_status', 'active');

                                $scopeDescription = '';

                                switch ($scopeType) {
                                    case 'category':
                                        $category = $get('scope_category');
                                        if ($category) {
                                            $scopeDescription = "Category: {$category}";
                                            // For demo, we'll show all active SKUs - in production, this would filter by category
                                        } else {
                                            return new HtmlString('<p class="text-amber-600">Please enter a category name to see the preview.</p>');
                                        }
                                        break;

                                    case 'product':
                                        $product = $get('scope_product');
                                        if ($product) {
                                            $scopeDescription = "Product: {$product}";
                                            // Filter by wine master name (approximate matching)
                                            $query->whereHas('wineVariant.wineMaster', function ($q) use ($product): void {
                                                $q->where('name', 'like', "%{$product}%");
                                            });
                                        } else {
                                            return new HtmlString('<p class="text-amber-600">Please enter a product name to see the preview.</p>');
                                        }
                                        break;

                                    case 'sku':
                                        $skuIds = $get('scope_skus');
                                        if (! empty($skuIds)) {
                                            $scopeDescription = 'Specific SKUs selected';
                                            $query->whereIn('id', $skuIds);
                                        } else {
                                            return new HtmlString('<p class="text-amber-600">Please select at least one SKU to see the preview.</p>');
                                        }
                                        break;

                                    default:
                                        $scopeDescription = 'All commercially available SKUs';
                                }

                                $totalCount = $query->count();

                                // Build market/channel restriction text
                                $restrictions = [];
                                $markets = $get('scope_markets');
                                if (! empty($markets)) {
                                    $marketCount = count($markets);
                                    $restrictions[] = "{$marketCount} market(s)";
                                }
                                $channels = $get('scope_channels');
                                if (! empty($channels)) {
                                    $channelCount = count($channels);
                                    $restrictions[] = "{$channelCount} channel(s)";
                                }

                                $restrictionText = ! empty($restrictions)
                                    ? '<p class="text-sm text-gray-600 mt-2">Additional filters: '.implode(', ', $restrictions).'</p>'
                                    : '';

                                // Warning for SKUs without allocation (placeholder - would need allocation data)
                                $allocationWarning = '';
                                if ($totalCount > 0) {
                                    // In production, this would check actual allocation data
                                    $allocationWarning = '<div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                                        <strong>Note:</strong> Policies only apply to SKUs with active commercial allocations. SKUs without allocations will be skipped during execution.
                                    </div>';
                                }

                                $colorClass = $totalCount > 0 ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200';
                                $countColor = $totalCount > 0 ? 'text-green-800' : 'text-gray-600';

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border {$colorClass}\">
                                        <div class=\"flex items-center justify-between\">
                                            <div>
                                                <p class=\"font-medium\">{$scopeDescription}</p>
                                                {$restrictionText}
                                            </div>
                                            <div class=\"text-right\">
                                                <p class=\"text-2xl font-bold {$countColor}\">{$totalCount}</p>
                                                <p class=\"text-sm text-gray-500\">SKUs affected</p>
                                            </div>
                                        </div>
                                        {$allocationWarning}
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 5: Execution
     * Defines the execution cadence of the Pricing Policy.
     */
    protected function getExecutionStep(): Wizard\Step
    {
        return Wizard\Step::make('Execution')
            ->description('Define when and how the policy executes')
            ->icon('heroicon-o-play')
            ->schema([
                // Important note about policy execution
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('execution_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 rounded-lg border bg-blue-50 border-blue-200">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <p class="font-semibold text-blue-900">Important: Policies generate draft prices</p>
                                            <p class="text-blue-800 mt-1">Pricing Policies generate prices into the target Price Book, but they <strong>never activate the Price Book directly</strong>. After policy execution, you must review and approve the generated prices before they take effect.</p>
                                        </div>
                                    </div>
                                </div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Execution Cadence section
                Forms\Components\Section::make('Execution Cadence')
                    ->description('Choose when this policy should generate prices')
                    ->schema([
                        Forms\Components\Radio::make('execution_cadence')
                            ->label('')
                            ->options([
                                ExecutionCadence::Manual->value => ExecutionCadence::Manual->label(),
                                ExecutionCadence::Scheduled->value => ExecutionCadence::Scheduled->label(),
                                ExecutionCadence::EventTriggered->value => ExecutionCadence::EventTriggered->label(),
                            ])
                            ->descriptions([
                                ExecutionCadence::Manual->value => 'Execute on-demand only. You will manually trigger the policy when needed.',
                                ExecutionCadence::Scheduled->value => 'Execute automatically on a schedule. Great for regular price updates.',
                                ExecutionCadence::EventTriggered->value => 'Execute automatically when specific events occur. Ideal for reactive pricing.',
                            ])
                            ->default(ExecutionCadence::Manual->value)
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // Dynamic info based on selected cadence
                        Forms\Components\Placeholder::make('cadence_details')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $cadence = $get('execution_cadence');

                                if ($cadence === null) {
                                    return new HtmlString('<p class="text-gray-500">Select an execution cadence to see more details.</p>');
                                }

                                $executionCadence = ExecutionCadence::from($cadence);

                                $details = match ($executionCadence) {
                                    ExecutionCadence::Manual => [
                                        'icon' => $executionCadence->icon(),
                                        'color' => 'gray',
                                        'title' => 'Manual Execution',
                                        'description' => 'The policy will only execute when you explicitly trigger it from the Policy Detail page.',
                                        'use_cases' => [
                                            'One-time price adjustments',
                                            'Ad-hoc price reviews',
                                            'Testing new pricing strategies',
                                        ],
                                    ],
                                    ExecutionCadence::Scheduled => [
                                        'icon' => $executionCadence->icon(),
                                        'color' => 'blue',
                                        'title' => 'Scheduled Execution',
                                        'description' => 'The policy will execute automatically according to the schedule you define.',
                                        'use_cases' => [
                                            'Regular cost-based price updates',
                                            'Weekly/monthly price adjustments',
                                            'Automated price synchronization',
                                        ],
                                    ],
                                    ExecutionCadence::EventTriggered => [
                                        'icon' => $executionCadence->icon(),
                                        'color' => 'amber',
                                        'title' => 'Event-Triggered Execution',
                                        'description' => 'The policy will execute automatically when specific events occur in the system.',
                                        'use_cases' => [
                                            'React to cost changes',
                                            'Adjust to market price updates',
                                            'Respond to currency fluctuations',
                                        ],
                                    ],
                                };

                                /** @var 'gray'|'blue'|'amber' $color */
                                $color = $details['color'];
                                $colorClass = match ($color) {
                                    'gray' => 'bg-gray-50 border-gray-200',
                                    'blue' => 'bg-blue-50 border-blue-200',
                                    'amber' => 'bg-amber-50 border-amber-200',
                                };

                                $useCasesHtml = '<ul class="list-disc list-inside text-sm mt-2 space-y-1">';
                                foreach ($details['use_cases'] as $useCase) {
                                    $useCasesHtml .= "<li>{$useCase}</li>";
                                }
                                $useCasesHtml .= '</ul>';

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border {$colorClass}\">
                                        <h4 class=\"font-semibold text-lg mb-2\">{$details['title']}</h4>
                                        <p class=\"mb-3\">{$details['description']}</p>
                                        <div class=\"text-sm\">
                                            <p class=\"font-medium\">Best for:</p>
                                            {$useCasesHtml}
                                        </div>
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Scheduled Options section
                Forms\Components\Section::make('Schedule Configuration')
                    ->description('Define when the policy should execute')
                    ->visible(fn (Get $get): bool => $get('execution_cadence') === ExecutionCadence::Scheduled->value)
                    ->schema([
                        Forms\Components\Select::make('schedule_frequency')
                            ->label('Frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->default('daily')
                            ->required()
                            ->live()
                            ->helperText('How often should the policy execute?'),

                        Forms\Components\Select::make('schedule_day_of_week')
                            ->label('Day of Week')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->default('monday')
                            ->visible(fn (Get $get): bool => $get('schedule_frequency') === 'weekly')
                            ->helperText('Which day of the week?'),

                        Forms\Components\Select::make('schedule_day_of_month')
                            ->label('Day of Month')
                            ->options(
                                collect(range(1, 28))->mapWithKeys(fn ($day): array => [
                                    (string) $day => $day === 1 ? '1st' : ($day === 2 ? '2nd' : ($day === 3 ? '3rd' : "{$day}th")),
                                ])->toArray()
                            )
                            ->default('1')
                            ->visible(fn (Get $get): bool => $get('schedule_frequency') === 'monthly')
                            ->helperText('Which day of the month? (1-28 to ensure execution in all months)'),

                        Forms\Components\TimePicker::make('schedule_time')
                            ->label('Time of Day')
                            ->default('06:00')
                            ->seconds(false)
                            ->required()
                            ->helperText('At what time should the policy execute? (Server timezone)'),

                        Forms\Components\Placeholder::make('schedule_preview')
                            ->label('Schedule Preview')
                            ->content(function (Get $get): HtmlString {
                                $frequency = $get('schedule_frequency') ?? 'daily';
                                $time = $get('schedule_time') ?? '06:00';
                                $dayOfWeek = $get('schedule_day_of_week') ?? 'monday';
                                $dayOfMonth = $get('schedule_day_of_month') ?? '1';

                                $schedule = match ($frequency) {
                                    'daily' => "Every day at {$time}",
                                    'weekly' => 'Every '.ucfirst($dayOfWeek)." at {$time}",
                                    'monthly' => "On day {$dayOfMonth} of each month at {$time}",
                                    default => "At {$time}",
                                };

                                return new HtmlString(
                                    "<div class=\"p-3 rounded-lg border bg-green-50 border-green-200\">
                                        <p class=\"font-medium text-green-800\">{$schedule}</p>
                                        <p class=\"text-sm text-green-700 mt-1\">The policy will automatically execute at this schedule when activated.</p>
                                    </div>"
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Event Triggers section
                Forms\Components\Section::make('Event Triggers')
                    ->description('Select which events should trigger policy execution')
                    ->visible(fn (Get $get): bool => $get('execution_cadence') === ExecutionCadence::EventTriggered->value)
                    ->schema([
                        Forms\Components\CheckboxList::make('event_triggers')
                            ->label('')
                            ->options([
                                'cost_change' => 'Cost Change',
                                'emp_update' => 'EMP Update',
                                'fx_change' => 'FX Rate Change',
                            ])
                            ->descriptions([
                                'cost_change' => 'Execute when product cost is updated (for Cost + Margin policies)',
                                'emp_update' => 'Execute when Estimated Market Price (EMP) is updated',
                                'fx_change' => 'Execute when currency exchange rates change significantly',
                            ])
                            ->default(['cost_change'])
                            ->columns(1)
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('event_trigger_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $triggers = $get('event_triggers') ?? [];
                                $policyType = $get('policy_type');

                                $warnings = [];

                                if (in_array('cost_change', $triggers, true) && $policyType !== PricingPolicyType::CostPlusMargin->value) {
                                    $warnings[] = 'Cost Change trigger is most relevant for Cost + Margin policies.';
                                }

                                if (in_array('emp_update', $triggers, true) && $policyType !== PricingPolicyType::IndexBased->value) {
                                    $warnings[] = 'EMP Update trigger is most relevant for Index-Based policies.';
                                }

                                if (in_array('fx_change', $triggers, true) && $policyType !== PricingPolicyType::IndexBased->value) {
                                    $warnings[] = 'FX Change trigger is most relevant for Index-Based policies with FX rates.';
                                }

                                $warningHtml = '';
                                if (! empty($warnings)) {
                                    $warningHtml = '<div class="p-3 rounded-lg border bg-amber-50 border-amber-200 mt-3">
                                        <p class="font-medium text-amber-800">Suggestions:</p>
                                        <ul class="list-disc list-inside text-sm text-amber-700 mt-1">';
                                    foreach ($warnings as $warning) {
                                        $warningHtml .= "<li>{$warning}</li>";
                                    }
                                    $warningHtml .= '</ul></div>';
                                }

                                $triggerCount = count($triggers);
                                $countText = $triggerCount > 0
                                    ? "<p class=\"text-sm text-gray-600\">{$triggerCount} trigger(s) selected. The policy will execute when any of these events occur.</p>"
                                    : '<p class="text-sm text-amber-600">Please select at least one trigger event.</p>';

                                return new HtmlString($countText.$warningHtml);
                            })
                            ->columnSpanFull(),
                    ]),

                // Manual Mode Info section
                Forms\Components\Section::make('Manual Execution')
                    ->description('How manual execution works')
                    ->visible(fn (Get $get): bool => $get('execution_cadence') === ExecutionCadence::Manual->value)
                    ->schema([
                        Forms\Components\Placeholder::make('manual_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex-shrink-0">1</span>
                                        <div>
                                            <p class="font-medium">Activate the Policy</p>
                                            <p class="text-sm text-gray-600">After creation, activate the policy to enable execution.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex-shrink-0">2</span>
                                        <div>
                                            <p class="font-medium">Run Dry Run (Optional)</p>
                                            <p class="text-sm text-gray-600">Preview generated prices without writing to the Price Book.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex-shrink-0">3</span>
                                        <div>
                                            <p class="font-medium">Execute the Policy</p>
                                            <p class="text-sm text-gray-600">Click "Execute Now" to generate prices into the target Price Book.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 text-sm font-medium flex-shrink-0">4</span>
                                        <div>
                                            <p class="font-medium">Review & Approve</p>
                                            <p class="text-sm text-gray-600">Review generated prices in the Price Book and activate if satisfied.</p>
                                        </div>
                                    </div>
                                </div>'
                            ))
                            ->columnSpanFull(),
                    ]),
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

        // Set execution cadence from Step 5 (defaults to manual if not set)
        if (! isset($data['execution_cadence'])) {
            $data['execution_cadence'] = ExecutionCadence::Manual->value;
        }

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

        // Build logic_definition from Step 2 and Step 3 inputs
        $logicDefinition = [];

        switch ($policyType) {
            case PricingPolicyType::CostPlusMargin:
                // Step 2: Input source
                $logicDefinition['cost_source'] = $data['cost_source'] ?? 'product_catalog';

                // Step 3: Margin logic
                $marginType = $data['margin_type'] ?? 'percentage';
                $logicDefinition['margin_type'] = $marginType;

                if ($marginType === 'percentage') {
                    $logicDefinition['margin_percentage'] = (float) ($data['margin_percentage'] ?? 25);
                } else {
                    $logicDefinition['markup_value'] = (float) ($data['markup_fixed_amount'] ?? 10);
                }

                // Tiered margins
                if (! empty($data['use_tiered_margins']) && ! empty($data['tiered_margins'])) {
                    $logicDefinition['tiered_logic'] = $data['tiered_margins'];
                }
                break;

            case PricingPolicyType::ReferencePriceBook:
                // Step 2: Source price book
                $logicDefinition['source_price_book_id'] = $data['source_price_book_id'] ?? null;

                // Step 3: Adjustment logic
                $adjType = $data['ref_adjustment_type'] ?? 'percentage';
                $logicDefinition['adjustment_type'] = $adjType;
                $logicDefinition['adjustment_value'] = (float) ($data['ref_adjustment_value'] ?? 0);

                // For compatibility with getLogicDescription()
                if ($adjType === 'percentage') {
                    $logicDefinition['markup_value'] = (float) ($data['ref_adjustment_value'] ?? 0);
                }

                // Tiered adjustments
                if (! empty($data['use_tiered_adjustments']) && ! empty($data['tiered_adjustments'])) {
                    $logicDefinition['tiered_logic'] = $data['tiered_adjustments'];
                }
                break;

            case PricingPolicyType::IndexBased:
                // Step 2: Index configuration
                $logicDefinition['index_type'] = $data['index_type'] ?? 'emp';
                if (($data['index_type'] ?? 'emp') === 'emp') {
                    $logicDefinition['emp_market'] = $data['emp_market'] ?? 'default';
                    $logicDefinition['emp_confidence_threshold'] = $data['emp_confidence_threshold'] ?? 'any';
                } else {
                    $logicDefinition['source_currency'] = $data['source_currency'] ?? 'EUR';
                    $logicDefinition['target_currency'] = $data['target_currency'] ?? 'USD';
                    $logicDefinition['fx_rate_buffer'] = (float) ($data['fx_rate_buffer'] ?? 0);
                }

                // Step 3: Multiplier and adjustment
                $logicDefinition['index_multiplier'] = (float) ($data['index_multiplier'] ?? 1.0);
                $logicDefinition['index_fixed_adjustment'] = (float) ($data['index_fixed_adjustment'] ?? 0);
                break;

            case PricingPolicyType::FixedAdjustment:
                // Step 2: Adjustment configuration
                $logicDefinition['adjustment_type'] = $data['adjustment_type'] ?? 'percentage';
                $logicDefinition['adjustment_value'] = (float) ($data['adjustment_value'] ?? 0);

                // For compatibility with getLogicDescription()
                if (($data['adjustment_type'] ?? 'percentage') === 'percentage') {
                    $logicDefinition['markup_value'] = (float) ($data['adjustment_value'] ?? 0);
                }
                break;

            case PricingPolicyType::Rounding:
                // Step 2: Rounding configuration
                $logicDefinition['rounding_rule'] = $data['rounding_rule'] ?? '.99';
                $logicDefinition['rounding_direction'] = $data['rounding_direction'] ?? 'nearest';
                break;
        }

        // Add optional final rounding for non-rounding policies
        if ($policyType !== PricingPolicyType::Rounding && $policyType !== PricingPolicyType::FixedAdjustment) {
            if (! empty($data['apply_rounding'])) {
                $logicDefinition['apply_rounding'] = true;
                $logicDefinition['final_rounding_rule'] = $data['final_rounding_rule'] ?? '.99';
                $logicDefinition['final_rounding_direction'] = $data['final_rounding_direction'] ?? 'nearest';
                // Also set rounding_rule for compatibility with getLogicDescription()
                $logicDefinition['rounding_rule'] = $data['final_rounding_rule'] ?? '.99';
            }
        }

        // Add Step 5: Execution configuration to logic_definition
        $executionCadence = ExecutionCadence::from($data['execution_cadence']);
        if ($executionCadence === ExecutionCadence::Scheduled) {
            $logicDefinition['schedule'] = [
                'frequency' => $data['schedule_frequency'] ?? 'daily',
                'day_of_week' => $data['schedule_day_of_week'] ?? null,
                'day_of_month' => $data['schedule_day_of_month'] ?? null,
                'time' => $data['schedule_time'] ?? '06:00',
            ];
        } elseif ($executionCadence === ExecutionCadence::EventTriggered) {
            $logicDefinition['event_triggers'] = $data['event_triggers'] ?? ['cost_change'];
        }

        $data['logic_definition'] = $logicDefinition;

        // Store scope data in session for afterCreate hook
        $scopeType = $data['scope_type'] ?? 'all';
        $scopeReference = match ($scopeType) {
            'category' => $data['scope_category'] ?? null,
            'product' => $data['scope_product'] ?? null,
            'sku' => is_array($data['scope_skus'] ?? null) ? implode(',', $data['scope_skus']) : null,
            default => null,
        };

        session([
            'pricing_policy_scope_data' => [
                'scope_type' => $scopeType,
                'scope_reference' => $scopeReference,
                'markets' => $data['scope_markets'] ?? null,
                'channels' => $data['scope_channels'] ?? null,
            ],
        ]);

        // Clean up temporary form fields that aren't in the model
        unset(
            // Step 2 fields
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
            $data['rounding_direction'],
            // Step 3 fields
            $data['margin_type'],
            $data['margin_percentage'],
            $data['markup_fixed_amount'],
            $data['use_tiered_margins'],
            $data['tiered_margins'],
            $data['ref_adjustment_type'],
            $data['ref_adjustment_value'],
            $data['use_tiered_adjustments'],
            $data['tiered_adjustments'],
            $data['index_multiplier'],
            $data['index_fixed_adjustment'],
            $data['apply_rounding'],
            $data['final_rounding_rule'],
            $data['final_rounding_direction'],
            // Step 4 fields
            $data['scope_type'],
            $data['scope_category'],
            $data['scope_product'],
            $data['scope_skus'],
            $data['scope_markets'],
            $data['scope_channels'],
            // Step 5 fields
            $data['schedule_frequency'],
            $data['schedule_day_of_week'],
            $data['schedule_day_of_month'],
            $data['schedule_time'],
            $data['event_triggers']
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

        // Create the PricingPolicyScope from session data
        $scopeData = session('pricing_policy_scope_data');
        if ($scopeData) {
            \App\Models\Commercial\PricingPolicyScope::create([
                'pricing_policy_id' => $pricingPolicy->id,
                'scope_type' => $scopeData['scope_type'],
                'scope_reference' => $scopeData['scope_reference'],
                'markets' => ! empty($scopeData['markets']) ? $scopeData['markets'] : null,
                'channels' => ! empty($scopeData['channels']) ? $scopeData['channels'] : null,
            ]);

            // Clean up session
            session()->forget('pricing_policy_scope_data');
        }

        Notification::make()
            ->success()
            ->title('Pricing Policy created')
            ->body("The pricing policy \"{$pricingPolicy->name}\" has been created as Draft. Configure execution settings, then activate when ready.")
            ->send();
    }
}
