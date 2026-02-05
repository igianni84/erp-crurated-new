<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Enums\Commercial\BundlePricingLogic;
use App\Enums\Commercial\BundleStatus;
use App\Filament\Resources\BundleResource;
use App\Models\Commercial\Bundle;
use App\Models\Commercial\BundleComponent;
use App\Models\Pim\SellableSku;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CreateBundle extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = BundleResource::class;

    /**
     * Whether to activate the bundle after creation.
     */
    public bool $activateAfterCreate = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make($this->getSteps())
                    ->submitAction(new HtmlString(
                        '<div class="flex gap-2">'.
                        '<x-filament::button type="submit" wire:click="$set(\'activateAfterCreate\', false)" color="primary">'.
                        'Create as Draft'.
                        '</x-filament::button>'.
                        '<x-filament::button type="submit" wire:click="$set(\'activateAfterCreate\', true)" color="success">'.
                        'Create and Activate'.
                        '</x-filament::button>'.
                        '</div>'
                    ))
                    ->persistStepInQueryString()
                    ->skippable(false),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getBundleInfoStep(),
            $this->getComponentsStep(),
            $this->getPricingStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Bundle Information
     */
    protected function getBundleInfoStep(): Wizard\Step
    {
        return Wizard\Step::make('Bundle Information')
            ->icon('heroicon-o-gift')
            ->description('Define basic bundle details')
            ->schema([
                Forms\Components\Section::make('Bundle Identity')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Bundle Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Premium Wine Collection, Holiday Gift Set')
                            ->helperText('A descriptive name for this bundle'),
                        Forms\Components\TextInput::make('bundle_sku')
                            ->label('Bundle SKU')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., BDL-PREMIUM-001 (leave empty to auto-generate)')
                            ->helperText('Unique identifier. Leave empty to auto-generate based on name.'),
                    ])
                    ->columns(2),

                Forms\Components\Placeholder::make('bundle_info')
                    ->content(new HtmlString(
                        '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">'.
                        '<div class="flex items-start gap-3">'.
                        '<div class="flex-shrink-0">'.
                        '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">'.
                        '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>'.
                        '</svg>'.
                        '</div>'.
                        '<div>'.
                        '<h4 class="font-medium text-blue-800 dark:text-blue-200">About Commercial Bundles</h4>'.
                        '<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">'.
                        'Bundles are commercial groupings of Sellable SKUs sold together at a special price. '.
                        'Each bundle generates a composite SKU in the PIM for inventory and order tracking.'.
                        '</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    )),
            ]);
    }

    /**
     * Step 2: Components Selection
     */
    protected function getComponentsStep(): Wizard\Step
    {
        return Wizard\Step::make('Components')
            ->icon('heroicon-o-cube')
            ->description('Select SKUs to include in the bundle')
            ->schema([
                Forms\Components\Placeholder::make('components_info')
                    ->content(new HtmlString(
                        '<div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700 mb-4">'.
                        '<div class="flex items-start gap-3">'.
                        '<div class="flex-shrink-0">'.
                        '<svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">'.
                        '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>'.
                        '</svg>'.
                        '</div>'.
                        '<div>'.
                        '<h4 class="font-medium text-amber-800 dark:text-amber-200">Component Selection</h4>'.
                        '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">'.
                        'Select one or more Sellable SKUs to include in this bundle. '.
                        'Each SKU can only appear once - use the quantity field for multiples. '.
                        'Only active SKUs with allocations are shown.'.
                        '</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    )),

                Forms\Components\Repeater::make('bundle_components')
                    ->label('Bundle Components')
                    ->schema([
                        Forms\Components\Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function (): array {
                                return SellableSku::query()
                                    ->where('lifecycle_status', 'active')
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->get()
                                    ->mapWithKeys(function (SellableSku $sku) {
                                        $wineVariant = $sku->wineVariant;
                                        $wineName = 'Unknown';
                                        $vintage = '';
                                        if ($wineVariant !== null) {
                                            $wineMaster = $wineVariant->wineMaster;
                                            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                            $vintage = (string) $wineVariant->vintage_year;
                                        }
                                        $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '';
                                        $caseConfig = $sku->caseConfiguration;
                                        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.' btl' : '';

                                        return [
                                            $sku->id => $sku->sku_code.' - '.$wineName.' '.$vintage.' ('.$format.', '.$packaging.')',
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if ($state === null) {
                                    $set('sku_preview', null);

                                    return;
                                }

                                $sku = SellableSku::with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])->find($state);
                                if ($sku !== null) {
                                    $wineVariant = $sku->wineVariant;
                                    $wineName = 'Unknown';
                                    $vintage = '';
                                    if ($wineVariant !== null) {
                                        $wineMaster = $wineVariant->wineMaster;
                                        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                        $vintage = (string) $wineVariant->vintage_year;
                                    }
                                    $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : 'N/A';
                                    $caseConfig = $sku->caseConfiguration;
                                    $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.' bottles/'.$caseConfig->case_type : 'N/A';

                                    $set('sku_preview', "{$wineName} {$vintage}\n{$format} - {$packaging}");
                                }
                            })
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->step(1)
                            ->columnSpan(1),
                        Forms\Components\Placeholder::make('sku_preview')
                            ->label('SKU Details')
                            ->content(function (Get $get): string {
                                $preview = $get('sku_preview');

                                return is_string($preview) ? $preview : 'Select a SKU to see details';
                            })
                            ->columnSpan(2),
                    ])
                    ->columns(5)
                    ->minItems(1)
                    ->maxItems(20)
                    ->defaultItems(1)
                    ->itemLabel(function (array $state): string {
                        if (! isset($state['sellable_sku_id'])) {
                            return 'New Component';
                        }
                        $sku = SellableSku::find($state['sellable_sku_id']);

                        return 'Component: '.($sku !== null ? $sku->sku_code : 'Unknown');
                    })
                    ->collapsible()
                    ->cloneable()
                    ->reorderable()
                    ->addActionLabel('Add Component')
                    ->helperText('Add at least one component to the bundle'),

                Forms\Components\Placeholder::make('components_summary')
                    ->label('Components Summary')
                    ->content(function (Get $get): HtmlString {
                        $components = $get('bundle_components') ?? [];
                        $totalQuantity = 0;
                        $componentCount = 0;

                        foreach ($components as $component) {
                            if (is_array($component) && isset($component['sellable_sku_id']) && $component['sellable_sku_id'] !== '') {
                                $componentCount++;
                                $totalQuantity += (int) ($component['quantity'] ?? 1);
                            }
                        }

                        if ($componentCount === 0) {
                            return new HtmlString(
                                '<div class="text-gray-500 dark:text-gray-400 text-sm">'.
                                'No components selected yet'.
                                '</div>'
                            );
                        }

                        return new HtmlString(
                            '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'.
                            '<div class="grid grid-cols-2 gap-4">'.
                            '<div>'.
                            '<span class="text-sm text-gray-500 dark:text-gray-400">Unique SKUs:</span>'.
                            '<span class="ml-2 font-medium">'.$componentCount.'</span>'.
                            '</div>'.
                            '<div>'.
                            '<span class="text-sm text-gray-500 dark:text-gray-400">Total Quantity:</span>'.
                            '<span class="ml-2 font-medium">'.$totalQuantity.' items</span>'.
                            '</div>'.
                            '</div>'.
                            '</div>'
                        );
                    })
                    ->live(),
            ]);
    }

    /**
     * Step 3: Pricing Configuration
     */
    protected function getPricingStep(): Wizard\Step
    {
        return Wizard\Step::make('Pricing')
            ->icon('heroicon-o-currency-euro')
            ->description('Define bundle pricing strategy')
            ->schema([
                Forms\Components\Section::make('Pricing Logic')
                    ->schema([
                        Forms\Components\Radio::make('pricing_logic')
                            ->label('Pricing Strategy')
                            ->options([
                                BundlePricingLogic::SumComponents->value => BundlePricingLogic::SumComponents->label(),
                                BundlePricingLogic::FixedPrice->value => BundlePricingLogic::FixedPrice->label(),
                                BundlePricingLogic::PercentageOffSum->value => BundlePricingLogic::PercentageOffSum->label(),
                            ])
                            ->descriptions([
                                BundlePricingLogic::SumComponents->value => BundlePricingLogic::SumComponents->description(),
                                BundlePricingLogic::FixedPrice->value => BundlePricingLogic::FixedPrice->description(),
                                BundlePricingLogic::PercentageOffSum->value => BundlePricingLogic::PercentageOffSum->description(),
                            ])
                            ->required()
                            ->default(BundlePricingLogic::SumComponents->value)
                            ->live()
                            ->columns(1),
                    ]),

                Forms\Components\Section::make('Pricing Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('fixed_price')
                            ->label('Fixed Price')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->placeholder('e.g., 150.00')
                            ->visible(fn (Get $get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value)
                            ->required(fn (Get $get): bool => $get('pricing_logic') === BundlePricingLogic::FixedPrice->value)
                            ->helperText('The fixed selling price for this bundle'),
                        Forms\Components\TextInput::make('percentage_off')
                            ->label('Percentage Off')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0.1)
                            ->maxValue(99.9)
                            ->step(0.1)
                            ->placeholder('e.g., 10')
                            ->visible(fn (Get $get): bool => $get('pricing_logic') === BundlePricingLogic::PercentageOffSum->value)
                            ->required(fn (Get $get): bool => $get('pricing_logic') === BundlePricingLogic::PercentageOffSum->value)
                            ->helperText('Discount percentage applied to the sum of component prices'),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('pricing_logic'), [
                        BundlePricingLogic::FixedPrice->value,
                        BundlePricingLogic::PercentageOffSum->value,
                    ])),

                Forms\Components\Placeholder::make('pricing_preview')
                    ->label('Pricing Preview')
                    ->content(function (Get $get): HtmlString {
                        $pricingLogic = $get('pricing_logic');

                        if ($pricingLogic === null) {
                            return new HtmlString(
                                '<div class="text-gray-500 dark:text-gray-400">'.
                                'Select a pricing strategy to see the preview'.
                                '</div>'
                            );
                        }

                        $logic = BundlePricingLogic::tryFrom($pricingLogic);
                        if ($logic === null) {
                            return new HtmlString(
                                '<div class="text-gray-500 dark:text-gray-400">'.
                                'Invalid pricing strategy'.
                                '</div>'
                            );
                        }

                        $pricingDetails = match ($logic) {
                            BundlePricingLogic::SumComponents => [
                                'color' => 'blue',
                                'icon' => 'heroicon-o-calculator',
                                'title' => 'Automatic Pricing',
                                'description' => 'Bundle price = Sum of all component prices from the active Price Book',
                                'example' => 'e.g., 3 SKUs at €50, €60, €70 = Bundle price €180',
                            ],
                            BundlePricingLogic::FixedPrice => [
                                'color' => 'green',
                                'icon' => 'heroicon-o-banknotes',
                                'title' => 'Fixed Price',
                                'description' => $get('fixed_price') !== null
                                    ? 'Bundle price = € '.number_format((float) $get('fixed_price'), 2).' (regardless of component prices)'
                                    : 'Bundle price = Fixed amount (set below)',
                                'example' => 'Ideal for gift sets or promotional bundles with a set price point',
                            ],
                            BundlePricingLogic::PercentageOffSum => [
                                'color' => 'amber',
                                'icon' => 'heroicon-o-receipt-percent',
                                'title' => 'Percentage Discount',
                                'description' => $get('percentage_off') !== null
                                    ? 'Bundle price = Sum of components - '.number_format((float) $get('percentage_off'), 0).'%'
                                    : 'Bundle price = Sum of components - X%',
                                'example' => 'e.g., Sum €200 - 15% = Bundle price €170',
                            ],
                        };

                        return new HtmlString(
                            '<div class="p-4 bg-'.$pricingDetails['color'].'-50 dark:bg-'.$pricingDetails['color'].'-900/20 rounded-lg border border-'.$pricingDetails['color'].'-200 dark:border-'.$pricingDetails['color'].'-700">'.
                            '<div class="flex items-start gap-3">'.
                            '<div class="p-2 bg-'.$pricingDetails['color'].'-100 dark:bg-'.$pricingDetails['color'].'-800 rounded">'.
                            '<svg class="w-5 h-5 text-'.$pricingDetails['color'].'-600 dark:text-'.$pricingDetails['color'].'-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">'.
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>'.
                            '</svg>'.
                            '</div>'.
                            '<div>'.
                            '<h4 class="font-medium text-'.$pricingDetails['color'].'-800 dark:text-'.$pricingDetails['color'].'-200">'.$pricingDetails['title'].'</h4>'.
                            '<p class="mt-1 text-sm text-'.$pricingDetails['color'].'-700 dark:text-'.$pricingDetails['color'].'-300">'.$pricingDetails['description'].'</p>'.
                            '<p class="mt-2 text-xs text-'.$pricingDetails['color'].'-600 dark:text-'.$pricingDetails['color'].'-400 italic">'.$pricingDetails['example'].'</p>'.
                            '</div>'.
                            '</div>'.
                            '</div>'
                        );
                    })
                    ->live(),
            ]);
    }

    /**
     * Step 4: Review & Create
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review')
            ->icon('heroicon-o-clipboard-document-check')
            ->description('Review and create the bundle')
            ->schema([
                Forms\Components\Placeholder::make('review_header')
                    ->content(new HtmlString(
                        '<div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg border border-green-200 dark:border-green-700 mb-4">'.
                        '<div class="flex items-center gap-3">'.
                        '<div class="p-2 bg-green-100 dark:bg-green-800 rounded-full">'.
                        '<svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="currentColor" viewBox="0 0 20 20">'.
                        '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'.
                        '</svg>'.
                        '</div>'.
                        '<div>'.
                        '<h3 class="font-semibold text-green-800 dark:text-green-200">Ready to Create Bundle</h3>'.
                        '<p class="text-sm text-green-700 dark:text-green-300">Review the details below before creating</p>'.
                        '</div>'.
                        '</div>'.
                        '</div>'
                    )),

                Forms\Components\Section::make('Bundle Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('bundle_summary')
                            ->content(function (Get $get): HtmlString {
                                $name = $get('name') ?? 'Unnamed Bundle';
                                $bundleSku = $get('bundle_sku') ?: '[Auto-generated]';

                                return new HtmlString(
                                    '<div class="grid grid-cols-2 gap-4">'.
                                    '<div>'.
                                    '<span class="text-sm text-gray-500 dark:text-gray-400">Bundle Name:</span>'.
                                    '<div class="font-medium text-lg">'.e($name).'</div>'.
                                    '</div>'.
                                    '<div>'.
                                    '<span class="text-sm text-gray-500 dark:text-gray-400">Bundle SKU:</span>'.
                                    '<div class="font-mono text-gray-700 dark:text-gray-300">'.e($bundleSku).'</div>'.
                                    '</div>'.
                                    '</div>'
                                );
                            })
                            ->live(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Components')
                    ->schema([
                        Forms\Components\Placeholder::make('components_review')
                            ->content(function (Get $get): HtmlString {
                                /** @var array<int, mixed> $components */
                                $components = $get('bundle_components') ?? [];
                                $validComponents = array_filter($components, function ($c): bool {
                                    return is_array($c) && isset($c['sellable_sku_id']) && $c['sellable_sku_id'] !== '';
                                });

                                if (empty($validComponents)) {
                                    return new HtmlString(
                                        '<div class="text-red-600 dark:text-red-400 font-medium">'.
                                        '⚠️ No components selected - please go back and add components'.
                                        '</div>'
                                    );
                                }

                                $rows = [];
                                $totalQuantity = 0;
                                foreach ($validComponents as $component) {
                                    /** @var array{sellable_sku_id: string, quantity?: int} $component */
                                    $sku = SellableSku::with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])->find($component['sellable_sku_id']);
                                    if ($sku === null) {
                                        continue;
                                    }

                                    $quantity = (int) ($component['quantity'] ?? 1);
                                    $totalQuantity += $quantity;

                                    $wineVariant = $sku->wineVariant;
                                    $wineName = 'Unknown';
                                    $vintage = '';
                                    if ($wineVariant !== null) {
                                        $wineMaster = $wineVariant->wineMaster;
                                        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                        $vintage = (string) $wineVariant->vintage_year;
                                    }
                                    $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : 'N/A';

                                    $rows[] = '<tr class="border-b dark:border-gray-700">'.
                                        '<td class="py-2 font-mono text-sm">'.$sku->sku_code.'</td>'.
                                        '<td class="py-2">'.$wineName.' '.$vintage.'</td>'.
                                        '<td class="py-2 text-center">'.$format.'</td>'.
                                        '<td class="py-2 text-center font-medium">'.$quantity.'</td>'.
                                        '</tr>';
                                }

                                return new HtmlString(
                                    '<table class="w-full">'.
                                    '<thead class="text-left text-sm text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">'.
                                    '<tr>'.
                                    '<th class="py-2">SKU Code</th>'.
                                    '<th class="py-2">Wine</th>'.
                                    '<th class="py-2 text-center">Format</th>'.
                                    '<th class="py-2 text-center">Qty</th>'.
                                    '</tr>'.
                                    '</thead>'.
                                    '<tbody>'.implode('', $rows).'</tbody>'.
                                    '<tfoot class="border-t-2 dark:border-gray-600">'.
                                    '<tr>'.
                                    '<td colspan="3" class="py-2 font-medium text-right">Total:</td>'.
                                    '<td class="py-2 text-center font-bold text-lg">'.$totalQuantity.'</td>'.
                                    '</tr>'.
                                    '</tfoot>'.
                                    '</table>'
                                );
                            })
                            ->live(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Placeholder::make('pricing_review')
                            ->content(function (Get $get): HtmlString {
                                $pricingLogic = $get('pricing_logic');
                                $logic = BundlePricingLogic::tryFrom($pricingLogic ?? '');

                                if ($logic === null) {
                                    return new HtmlString(
                                        '<div class="text-red-600 dark:text-red-400">'.
                                        '⚠️ No pricing strategy selected'.
                                        '</div>'
                                    );
                                }

                                $priceDisplay = match ($logic) {
                                    BundlePricingLogic::SumComponents => 'Automatic (sum of component prices)',
                                    BundlePricingLogic::FixedPrice => $get('fixed_price') !== null
                                        ? '€ '.number_format((float) $get('fixed_price'), 2).' (fixed)'
                                        : 'Not set',
                                    BundlePricingLogic::PercentageOffSum => $get('percentage_off') !== null
                                        ? number_format((float) $get('percentage_off'), 0).'% off sum of components'
                                        : 'Not set',
                                };

                                return new HtmlString(
                                    '<div class="flex items-center gap-4">'.
                                    '<div class="flex items-center gap-2">'.
                                    '<span class="px-2 py-1 rounded text-sm font-medium bg-'.$logic->color().'-100 dark:bg-'.$logic->color().'-800 text-'.$logic->color().'-700 dark:text-'.$logic->color().'-200">'.
                                    $logic->label().
                                    '</span>'.
                                    '</div>'.
                                    '<div class="text-lg font-semibold">'.$priceDisplay.'</div>'.
                                    '</div>'
                                );
                            })
                            ->live(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Status & Next Steps')
                    ->schema([
                        Forms\Components\Placeholder::make('status_info')
                            ->content(new HtmlString(
                                '<div class="space-y-3">'.
                                '<div class="flex items-center gap-2">'.
                                '<span class="px-2 py-1 rounded text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">'.
                                'Draft'.
                                '</span>'.
                                '<span class="text-sm text-gray-500 dark:text-gray-400">Bundle will be created in Draft status</span>'.
                                '</div>'.
                                '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'.
                                '<h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">After creation you can:</h5>'.
                                '<ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">'.
                                '<li>Add, remove, or modify components</li>'.
                                '<li>Change pricing configuration</li>'.
                                '<li>Activate when ready (requires at least one component)</li>'.
                                '</ul>'.
                                '</div>'.
                                '<div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">'.
                                '<h5 class="font-medium text-green-700 dark:text-green-300 mb-1">Create and Activate</h5>'.
                                '<p class="text-sm text-green-600 dark:text-green-400">'.
                                'Choose "Create and Activate" to immediately activate the bundle. '.
                                'Activated bundles will generate a composite SKU in PIM.'.
                                '</p>'.
                                '</div>'.
                                '</div>'
                            )),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default status
        $data['status'] = BundleStatus::Draft->value;

        // Auto-generate bundle_sku if not provided
        if (empty($data['bundle_sku'])) {
            $baseSku = 'BDL-'.Str::upper(Str::slug(Str::limit($data['name'] ?? 'BUNDLE', 20, ''), '-'));
            $suffix = Str::random(4);
            $data['bundle_sku'] = $baseSku.'-'.$suffix;
        }

        // Store components in session for afterCreate
        if (isset($data['bundle_components'])) {
            session(['bundle_components' => $data['bundle_components']]);
            unset($data['bundle_components']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Bundle $bundle */
        $bundle = $this->record;

        // Create components from session
        $components = session('bundle_components', []);
        session()->forget('bundle_components');

        foreach ($components as $component) {
            if (is_array($component) && isset($component['sellable_sku_id']) && $component['sellable_sku_id'] !== '') {
                BundleComponent::create([
                    'bundle_id' => $bundle->id,
                    'sellable_sku_id' => $component['sellable_sku_id'],
                    'quantity' => (int) ($component['quantity'] ?? 1),
                ]);
            }
        }

        // Handle activation if requested
        if ($this->activateAfterCreate) {
            $bundle->refresh();

            if ($bundle->canBeActivated()) {
                $bundle->status = BundleStatus::Active;
                $bundle->save();

                Notification::make()
                    ->title('Bundle Created and Activated')
                    ->body('The bundle "'.$bundle->name.'" has been created and activated.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Bundle Created')
                    ->body('The bundle was created but could not be activated. Ensure it has at least one component.')
                    ->warning()
                    ->send();
            }
        } else {
            Notification::make()
                ->title('Bundle Created')
                ->body('The bundle "'.$bundle->name.'" has been created as a draft.')
                ->success()
                ->send();
        }
    }
}
