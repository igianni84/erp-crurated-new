<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Models\Allocation\Allocation;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\LiquidProduct;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreateProcurementIntent extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ProcurementIntentResource::class;

    /**
     * Get the form for creating a procurement intent.
     * Implements a multi-step wizard for intent creation.
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->components([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getWizardSubmitActions())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
    }

    /**
     * Get the wizard submit actions (Create as Draft).
     */
    protected function getWizardSubmitActions(): HtmlString
    {
        return new HtmlString(
            Blade::render(<<<'BLADE'
                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Create as Draft
                    </x-filament::button>
                </div>
            BLADE)
        );
    }

    /**
     * Get the wizard steps.
     *
     * @return array<\Filament\Schemas\Components\Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getProductStep(),
            $this->getSourceAndModelStep(),
            $this->getDeliveryStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Product Selection
     * Select product type and the specific product.
     */
    protected function getProductStep(): Step
    {
        return Step::make('Product')
            ->description('Select the product for this procurement intent')
            ->icon('heroicon-o-cube')
            ->schema([
                Section::make()
                    ->schema([
                        Placeholder::make('intent_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'📋 Procurement Intent represents a decision to source this wine. '
                                .'It is the starting point for Purchase Orders, Bottling Instructions, and Inbounds.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Section::make('Product Type')
                    ->description('Choose the type of product to source')
                    ->schema([
                        Radio::make('product_type')
                            ->label('What type of product are you sourcing?')
                            ->options([
                                'bottle_sku' => 'Bottle SKU (Already bottled wine)',
                                'liquid_product' => 'Liquid Product (Wine in barrel/tank, to be bottled)',
                            ])
                            ->descriptions([
                                'bottle_sku' => 'Select when the wine is already bottled with a specific format',
                                'liquid_product' => 'Select when the wine is still in liquid form and bottling decisions are pending',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                // Reset dependent fields
                                $set('wine_master_id', null);
                                $set('wine_variant_id', null);
                                $set('format_id', null);
                                $set('sellable_sku_id', null);
                                $set('liquid_product_id', null);
                            }),
                    ]),

                // Bottle SKU Selection (when product_type = bottle_sku)
                Section::make('Bottle SKU Selection')
                    ->description('Select the wine, vintage, and format')
                    ->schema([
                        Select::make('wine_master_id')
                            ->label('Wine')
                            ->placeholder('Search for a wine by name or producer...')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return WineMaster::query()
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('producer', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (WineMaster $wineMaster): array => [
                                        $wineMaster->id => self::formatWineMasterOption($wineMaster),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (int $value): ?string {
                                $wineMaster = WineMaster::find($value);

                                return $wineMaster !== null ? self::formatWineMasterOption($wineMaster) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('wine_variant_id', null);
                                $set('format_id', null);
                                $set('sellable_sku_id', null);
                            })
                            ->required()
                            ->helperText('Type at least 2 characters to search for wines by name or producer'),

                        Select::make('wine_variant_id')
                            ->label('Vintage')
                            ->placeholder('Select vintage year...')
                            ->options(function (Get $get): array {
                                $wineMasterId = $get('wine_master_id');

                                if ($wineMasterId === null) {
                                    return [];
                                }

                                /** @var array<int|string, string> $options */
                                $options = WineVariant::query()
                                    ->where('wine_master_id', $wineMasterId)
                                    ->orderByDesc('vintage_year')
                                    ->get()
                                    ->mapWithKeys(function (WineVariant $variant): array {
                                        $vintageYear = $variant->getAttribute('vintage_year');

                                        return [
                                            $variant->id => is_scalar($vintageYear)
                                                ? (string) $vintageYear
                                                : 'NV (Non-Vintage)',
                                        ];
                                    })
                                    ->toArray();

                                return $options;
                            })
                            ->required()
                            ->hidden(fn (Get $get): bool => $get('wine_master_id') === null)
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('format_id', null);
                                $set('sellable_sku_id', null);
                            })
                            ->helperText('Select the vintage year'),

                        Select::make('format_id')
                            ->label('Format')
                            ->placeholder('Select bottle format...')
                            ->options(function (): array {
                                return Format::query()
                                    ->orderBy('volume_ml')
                                    ->get()
                                    ->mapWithKeys(fn (Format $format): array => [
                                        $format->id => self::formatFormatOption($format),
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->hidden(fn (Get $get): bool => $get('wine_variant_id') === null)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                // Auto-select the SellableSku if one exists
                                $wineVariantId = $get('wine_variant_id');
                                $formatId = $get('format_id');

                                if ($wineVariantId !== null && $formatId !== null) {
                                    $sku = SellableSku::query()
                                        ->where('wine_variant_id', $wineVariantId)
                                        ->where('format_id', $formatId)
                                        ->first();

                                    if ($sku !== null) {
                                        $set('sellable_sku_id', $sku->id);
                                    } else {
                                        $set('sellable_sku_id', null);
                                    }
                                }
                            })
                            ->helperText('Standard bottle sizes: 750ml (standard), 375ml (half), 1500ml (magnum)'),
                    ])
                    ->columns(3)
                    ->hidden(fn (Get $get): bool => $get('product_type') !== 'bottle_sku'),

                // Liquid Product Selection (when product_type = liquid_product)
                Section::make('Liquid Product Selection')
                    ->description('Select the wine and vintage (liquid form)')
                    ->schema([
                        Select::make('liquid_wine_master_id')
                            ->label('Wine')
                            ->placeholder('Search for a wine by name or producer...')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return WineMaster::query()
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('producer', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (WineMaster $wineMaster): array => [
                                        $wineMaster->id => self::formatWineMasterOption($wineMaster),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (int $value): ?string {
                                $wineMaster = WineMaster::find($value);

                                return $wineMaster !== null ? self::formatWineMasterOption($wineMaster) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('liquid_wine_variant_id', null);
                                $set('liquid_product_id', null);
                            })
                            ->required()
                            ->helperText('Type at least 2 characters to search for wines by name or producer'),

                        Select::make('liquid_wine_variant_id')
                            ->label('Vintage')
                            ->placeholder('Select vintage year...')
                            ->options(function (Get $get): array {
                                $wineMasterId = $get('liquid_wine_master_id');

                                if ($wineMasterId === null) {
                                    return [];
                                }

                                /** @var array<int|string, string> $options */
                                $options = WineVariant::query()
                                    ->where('wine_master_id', $wineMasterId)
                                    ->orderByDesc('vintage_year')
                                    ->get()
                                    ->mapWithKeys(function (WineVariant $variant): array {
                                        $vintageYear = $variant->getAttribute('vintage_year');

                                        return [
                                            $variant->id => is_scalar($vintageYear)
                                                ? (string) $vintageYear
                                                : 'NV (Non-Vintage)',
                                        ];
                                    })
                                    ->toArray();

                                return $options;
                            })
                            ->required()
                            ->hidden(fn (Get $get): bool => $get('liquid_wine_master_id') === null)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                // Auto-select the LiquidProduct if one exists
                                $wineVariantId = $get('liquid_wine_variant_id');

                                if ($wineVariantId !== null) {
                                    $liquidProduct = LiquidProduct::query()
                                        ->where('wine_variant_id', $wineVariantId)
                                        ->first();

                                    if ($liquidProduct !== null) {
                                        $set('liquid_product_id', $liquidProduct->id);
                                    } else {
                                        $set('liquid_product_id', null);
                                    }
                                }
                            })
                            ->helperText('Select the vintage year'),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => $get('product_type') !== 'liquid_product'),

                // Product Preview and Allocations Count
                Section::make('Selected Product Preview')
                    ->schema([
                        Placeholder::make('product_preview')
                            ->label('Product')
                            ->content(function (Get $get): string {
                                $productType = $get('product_type');

                                if ($productType === 'bottle_sku') {
                                    $wineVariantId = $get('wine_variant_id');
                                    $formatId = $get('format_id');

                                    if ($wineVariantId === null || $formatId === null) {
                                        return 'Complete the selections above to see the product preview';
                                    }

                                    /** @var WineVariant|null $wineVariant */
                                    $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);
                                    /** @var Format|null $format */
                                    $format = Format::find($formatId);

                                    if ($wineVariant === null || $format === null) {
                                        return 'Invalid selection';
                                    }

                                    $wineMaster = $wineVariant->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                    $producerValue = $wineMaster !== null ? $wineMaster->getAttribute('producer') : null;
                                    $producer = is_string($producerValue) ? $producerValue : '';
                                    $vintageValue = $wineVariant->getAttribute('vintage_year');
                                    $vintage = is_scalar($vintageValue) ? (string) $vintageValue : 'NV';
                                    $formatLabel = self::formatFormatOption($format);

                                    $label = $wineName;
                                    if ($producer !== '') {
                                        $label .= " ({$producer})";
                                    }
                                    $label .= " {$vintage} - {$formatLabel}";

                                    return $label;
                                }

                                if ($productType === 'liquid_product') {
                                    $wineVariantId = $get('liquid_wine_variant_id');

                                    if ($wineVariantId === null) {
                                        return 'Complete the selections above to see the product preview';
                                    }

                                    /** @var WineVariant|null $wineVariant */
                                    $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);

                                    if ($wineVariant === null) {
                                        return 'Invalid selection';
                                    }

                                    $wineMaster = $wineVariant->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                    $producerValue = $wineMaster !== null ? $wineMaster->getAttribute('producer') : null;
                                    $producer = is_string($producerValue) ? $producerValue : '';
                                    $vintageValue = $wineVariant->getAttribute('vintage_year');
                                    $vintage = is_scalar($vintageValue) ? (string) $vintageValue : 'NV';

                                    $label = $wineName;
                                    if ($producer !== '') {
                                        $label .= " ({$producer})";
                                    }
                                    $label .= " {$vintage} (Liquid)";

                                    return $label;
                                }

                                return 'Select a product type above';
                            })
                            ->columnSpanFull(),

                        Placeholder::make('existing_allocations')
                            ->label('Existing Allocations')
                            ->content(function (Get $get): HtmlString {
                                $productType = $get('product_type');
                                $count = 0;

                                if ($productType === 'bottle_sku') {
                                    $wineVariantId = $get('wine_variant_id');
                                    $formatId = $get('format_id');

                                    if ($wineVariantId !== null && $formatId !== null) {
                                        $count = Allocation::query()
                                            ->where('wine_variant_id', $wineVariantId)
                                            ->where('format_id', $formatId)
                                            ->count();
                                    }
                                } elseif ($productType === 'liquid_product') {
                                    // For liquid products, count allocations with the same wine variant
                                    $wineVariantId = $get('liquid_wine_variant_id');

                                    if ($wineVariantId !== null) {
                                        $count = Allocation::query()
                                            ->where('wine_variant_id', $wineVariantId)
                                            ->count();
                                    }
                                }

                                if ($count === 0) {
                                    return new HtmlString(
                                        '<span class="text-gray-500">No existing allocations for this product</span>'
                                    );
                                }

                                return new HtmlString(
                                    "<span class=\"text-green-600 font-medium\">{$count} existing allocation(s)</span>"
                                );
                            })
                            ->columnSpanFull(),

                        // Warning if no matching SKU/LiquidProduct exists
                        Placeholder::make('no_product_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 text-sm">'
                                .'⚠️ No matching Sellable SKU found for this combination. One will be created if needed.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $productType = $get('product_type');

                                if ($productType === 'bottle_sku') {
                                    return $get('sellable_sku_id') !== null || $get('format_id') === null;
                                }

                                if ($productType === 'liquid_product') {
                                    return $get('liquid_product_id') !== null || $get('liquid_wine_variant_id') === null;
                                }

                                return true;
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('product_type') === null),

                // Hidden fields to store the actual product references
                Hidden::make('sellable_sku_id'),
                Hidden::make('liquid_product_id'),
            ]);
    }

    /**
     * Step 2: Source & Model
     * Define trigger type and sourcing model.
     */
    protected function getSourceAndModelStep(): Step
    {
        return Step::make('Source & Model')
            ->description('Define the trigger type and sourcing model')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make('Trigger Type')
                    ->description('What triggered this procurement intent?')
                    ->schema([
                        Select::make('trigger_type')
                            ->label('Trigger Type')
                            ->options(function (): array {
                                /** @var array<string, string> $options */
                                $options = collect(ProcurementTriggerType::cases())
                                    ->mapWithKeys(fn (ProcurementTriggerType $type): array => [
                                        $type->value => $type->label(),
                                    ])
                                    ->toArray();

                                return $options;
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('The reason for this procurement intent'),

                        Placeholder::make('trigger_guidance')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $triggerType = $get('trigger_type');

                                if ($triggerType === null) {
                                    return new HtmlString('<span class="text-gray-500">Select a trigger type to see guidance</span>');
                                }

                                $guidance = match ($triggerType) {
                                    'voucher_driven' => '<strong>Voucher Driven:</strong> This intent is linked to a sale. A voucher has been issued and wine needs to be sourced to fulfill it.',
                                    'allocation_driven' => '<strong>Allocation Driven:</strong> Pre-emptive procurement based on expected demand from an allocation. Wine is being sourced in advance.',
                                    'strategic' => '<strong>Strategic:</strong> Speculative procurement not tied to specific demand. Used for building inventory or opportunistic purchases.',
                                    'contractual' => '<strong>Contractual:</strong> Committed procurement based on a contractual obligation with a supplier.',
                                    default => 'Unknown trigger type',
                                };

                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    ."<p class=\"text-gray-700 dark:text-gray-300 text-sm\">{$guidance}</p>"
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Sourcing Model')
                    ->description('How will the product be sourced?')
                    ->schema([
                        Select::make('sourcing_model')
                            ->label('Sourcing Model')
                            ->options(function (): array {
                                /** @var array<string, string> $options */
                                $options = collect(SourcingModel::cases())
                                    ->mapWithKeys(fn (SourcingModel $model): array => [
                                        $model->value => $model->label(),
                                    ])
                                    ->toArray();

                                return $options;
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('The commercial arrangement for this sourcing'),

                        Placeholder::make('sourcing_guidance')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $sourcingModel = $get('sourcing_model');

                                if ($sourcingModel === null) {
                                    return new HtmlString('<span class="text-gray-500">Select a sourcing model to see guidance</span>');
                                }

                                $guidance = match ($sourcingModel) {
                                    'purchase' => '<strong>Purchase:</strong> Ownership transfers to us on delivery. We pay for the wine and own it outright.',
                                    'passive_consignment' => '<strong>Passive Consignment:</strong> We hold the wine but do not own it. Ownership remains with the supplier until sale.',
                                    'third_party_custody' => '<strong>Third Party Custody:</strong> Wine is held by a third party (e.g., producer, storage facility). No ownership transfer.',
                                    default => 'Unknown sourcing model',
                                };

                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    ."<p class=\"text-gray-700 dark:text-gray-300 text-sm\">{$guidance}</p>"
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Quantity')
                    ->description('How many bottles or bottle-equivalents?')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
                            ->suffix(function (Get $get): string {
                                return $get('product_type') === 'liquid_product'
                                    ? 'bottle-equivalents'
                                    : 'bottles';
                            })
                            ->helperText(function (Get $get): string {
                                return $get('product_type') === 'liquid_product'
                                    ? 'Number of bottle-equivalents (e.g., 600 for a barrel of 450L at 750ml each)'
                                    : 'Number of bottles to source';
                            }),
                    ]),
            ]);
    }

    /**
     * Step 3: Delivery
     * Define delivery preferences.
     */
    protected function getDeliveryStep(): Step
    {
        return Step::make('Delivery')
            ->description('Define delivery preferences')
            ->icon('heroicon-o-truck')
            ->schema([
                Section::make('Preferred Inbound Location')
                    ->description('Where should the wine be delivered?')
                    ->schema([
                        Select::make('preferred_inbound_location')
                            ->label('Preferred Location')
                            ->options([
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                            ])
                            ->placeholder('Select preferred location...')
                            ->helperText('The warehouse where the wine should be delivered'),

                        Placeholder::make('location_preview')
                            ->label('Serialization Constraints')
                            ->content(function (Get $get): HtmlString {
                                $location = $get('preferred_inbound_location');

                                if ($location === null) {
                                    return new HtmlString('<span class="text-gray-500">Select a location to see serialization constraints</span>');
                                }

                                $constraints = match ($location) {
                                    'main_warehouse' => 'Standard serialization process. All major markets supported.',
                                    'secondary_warehouse' => 'Standard serialization process. All major markets supported.',
                                    'bonded_warehouse' => 'Duty-free storage. Serialization upon exit only.',
                                    'third_party_storage' => 'External serialization required. Contact ops for routing.',
                                    default => 'No constraints defined',
                                };

                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    ."<p class=\"text-gray-700 dark:text-gray-300 text-sm\">{$constraints}</p>"
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Rationale')
                    ->description('Operational notes for context')
                    ->schema([
                        Textarea::make('rationale')
                            ->label('Rationale')
                            ->placeholder('Enter operational notes or context for this procurement intent...')
                            ->rows(4)
                            ->helperText('Optional but recommended. Helps provide context for future reference.'),
                    ]),
            ]);
    }

    /**
     * Step 4: Review
     * Review all data before creating.
     */
    protected function getReviewStep(): Step
    {
        return Step::make('Review')
            ->description('Review and create the procurement intent')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Draft status info
                Section::make()
                    ->schema([
                        Placeholder::make('draft_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'📋 Draft intents require approval before execution. '
                                .'After approval, you can create Purchase Orders, Bottling Instructions, and link Inbounds.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Product Summary
                Section::make('Product')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Placeholder::make('review_product_type')
                            ->label('Product Type')
                            ->content(fn (Get $get): string => match ($get('product_type')) {
                                'bottle_sku' => 'Bottle SKU',
                                'liquid_product' => 'Liquid Product',
                                default => 'Not selected',
                            }),

                        Placeholder::make('review_product')
                            ->label('Product')
                            ->content(function (Get $get): string {
                                $productType = $get('product_type');

                                if ($productType === 'bottle_sku') {
                                    $wineVariantId = $get('wine_variant_id');
                                    $formatId = $get('format_id');

                                    if ($wineVariantId === null || $formatId === null) {
                                        return 'Not selected';
                                    }

                                    /** @var WineVariant|null $wineVariant */
                                    $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);
                                    /** @var Format|null $format */
                                    $format = Format::find($formatId);

                                    if ($wineVariant === null || $format === null) {
                                        return 'Invalid selection';
                                    }

                                    $wineMaster = $wineVariant->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                    $vintageValue = $wineVariant->getAttribute('vintage_year');
                                    $vintage = is_scalar($vintageValue) ? (string) $vintageValue : 'NV';
                                    $formatLabel = self::formatFormatOption($format);

                                    return "{$wineName} {$vintage} - {$formatLabel}";
                                }

                                if ($productType === 'liquid_product') {
                                    $wineVariantId = $get('liquid_wine_variant_id');

                                    if ($wineVariantId === null) {
                                        return 'Not selected';
                                    }

                                    /** @var WineVariant|null $wineVariant */
                                    $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);

                                    if ($wineVariant === null) {
                                        return 'Invalid selection';
                                    }

                                    $wineMaster = $wineVariant->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                    $vintageValue = $wineVariant->getAttribute('vintage_year');
                                    $vintage = is_scalar($vintageValue) ? (string) $vintageValue : 'NV';

                                    return "{$wineName} {$vintage} (Liquid)";
                                }

                                return 'Not selected';
                            }),
                    ])
                    ->columns(2),

                // Source & Model Summary
                Section::make('Source & Model')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Placeholder::make('review_trigger_type')
                            ->label('Trigger Type')
                            ->content(function (Get $get): string {
                                $triggerType = $get('trigger_type');
                                if (! is_string($triggerType)) {
                                    return 'Not selected';
                                }
                                $enum = ProcurementTriggerType::tryFrom($triggerType);

                                return $enum !== null ? $enum->label() : $triggerType;
                            }),

                        Placeholder::make('review_sourcing_model')
                            ->label('Sourcing Model')
                            ->content(function (Get $get): string {
                                $sourcingModel = $get('sourcing_model');
                                if (! is_string($sourcingModel)) {
                                    return 'Not selected';
                                }
                                $enum = SourcingModel::tryFrom($sourcingModel);

                                return $enum !== null ? $enum->label() : $sourcingModel;
                            }),

                        Placeholder::make('review_quantity')
                            ->label('Quantity')
                            ->content(function (Get $get): string {
                                $quantityValue = $get('quantity');
                                $quantity = is_numeric($quantityValue) ? (int) $quantityValue : 0;
                                $unit = $get('product_type') === 'liquid_product' ? 'bottle-equivalents' : 'bottles';

                                return "{$quantity} {$unit}";
                            }),
                    ])
                    ->columns(3),

                // Delivery Summary
                Section::make('Delivery')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Placeholder::make('review_location')
                            ->label('Preferred Location')
                            ->content(function (Get $get): string {
                                $location = $get('preferred_inbound_location');

                                return match ($location) {
                                    'main_warehouse' => 'Main Warehouse',
                                    'secondary_warehouse' => 'Secondary Warehouse',
                                    'bonded_warehouse' => 'Bonded Warehouse',
                                    'third_party_storage' => 'Third Party Storage',
                                    default => 'Not specified',
                                };
                            }),

                        Placeholder::make('review_rationale')
                            ->label('Rationale')
                            ->content(function (Get $get): string {
                                $rationale = $get('rationale');

                                return is_string($rationale) && $rationale !== '' ? $rationale : 'Not specified';
                            }),
                    ])
                    ->columns(2),

                // Warnings
                Section::make()
                    ->schema([
                        Placeholder::make('no_allocation_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 text-sm">'
                                .'⚠️ No existing allocations found for this product. Consider creating an allocation first if this is demand-driven procurement.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $productType = $get('product_type');
                                $count = 0;

                                if ($productType === 'bottle_sku') {
                                    $wineVariantId = $get('wine_variant_id');
                                    $formatId = $get('format_id');

                                    if ($wineVariantId !== null && $formatId !== null) {
                                        $count = Allocation::query()
                                            ->where('wine_variant_id', $wineVariantId)
                                            ->where('format_id', $formatId)
                                            ->count();
                                    }
                                } elseif ($productType === 'liquid_product') {
                                    $wineVariantId = $get('liquid_wine_variant_id');

                                    if ($wineVariantId !== null) {
                                        $count = Allocation::query()
                                            ->where('wine_variant_id', $wineVariantId)
                                            ->count();
                                    }
                                }

                                return $count > 0;
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Format a WineMaster record for display in select options.
     */
    protected static function formatWineMasterOption(WineMaster $wineMaster): string
    {
        $label = $wineMaster->name;

        $producer = $wineMaster->getAttribute('producer');
        if (is_string($producer) && $producer !== '') {
            $label .= " ({$producer})";
        }

        $appellation = $wineMaster->getAttribute('appellation');
        if (is_string($appellation) && $appellation !== '') {
            $label .= " - {$appellation}";
        }

        return $label;
    }

    /**
     * Format a Format record for display in select options.
     */
    protected static function formatFormatOption(Format $format): string
    {
        $volumeMl = $format->volume_ml;
        $label = "{$volumeMl}ml";

        $name = $format->getAttribute('name');
        if (is_string($name) && $name !== '' && $name !== "{$volumeMl}ml") {
            $label .= " ({$name})";
        }

        if ($format->is_standard) {
            $label .= ' ★';
        }

        return $label;
    }

    /**
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $productType = $data['product_type'] ?? null;

        // Set product_reference_type and product_reference_id based on product type
        if ($productType === 'bottle_sku') {
            // Find or create the SellableSku
            $wineVariantId = $data['wine_variant_id'] ?? null;
            $formatId = $data['format_id'] ?? null;
            $sellableSkuId = $data['sellable_sku_id'] ?? null;

            if ($sellableSkuId !== null) {
                $data['product_reference_type'] = 'sellable_skus';
                $data['product_reference_id'] = $sellableSkuId;
            } elseif ($wineVariantId !== null && $formatId !== null) {
                // Find existing or create new SellableSku
                $sku = SellableSku::query()
                    ->where('wine_variant_id', $wineVariantId)
                    ->where('format_id', $formatId)
                    ->first();

                if ($sku === null) {
                    // We need a case configuration - get the default one
                    $defaultCaseConfig = CaseConfiguration::query()
                        ->where('is_default', true)
                        ->first();

                    if ($defaultCaseConfig === null) {
                        $defaultCaseConfig = CaseConfiguration::first();
                    }

                    if ($defaultCaseConfig !== null) {
                        $sku = SellableSku::create([
                            'wine_variant_id' => $wineVariantId,
                            'format_id' => $formatId,
                            'case_configuration_id' => $defaultCaseConfig->id,
                            'lifecycle_status' => 'draft',
                            'source' => 'generated',
                        ]);
                    }
                }

                if ($sku !== null) {
                    $data['product_reference_type'] = 'sellable_skus';
                    $data['product_reference_id'] = $sku->id;
                }
            }
        } elseif ($productType === 'liquid_product') {
            $wineVariantId = $data['liquid_wine_variant_id'] ?? null;
            $liquidProductId = $data['liquid_product_id'] ?? null;

            if ($liquidProductId !== null) {
                $data['product_reference_type'] = 'liquid_products';
                $data['product_reference_id'] = $liquidProductId;
            } elseif ($wineVariantId !== null) {
                // Find existing or create new LiquidProduct
                $liquidProduct = LiquidProduct::query()
                    ->where('wine_variant_id', $wineVariantId)
                    ->first();

                if ($liquidProduct === null) {
                    $liquidProduct = LiquidProduct::create([
                        'wine_variant_id' => $wineVariantId,
                        'serialization_required' => true,
                        'lifecycle_status' => 'draft',
                    ]);
                }

                $data['product_reference_type'] = 'liquid_products';
                $data['product_reference_id'] = $liquidProduct->id;
            }
        }

        // Set default status
        $data['status'] = ProcurementIntentStatus::Draft->value;

        // Clean up temporary fields
        unset(
            $data['product_type'],
            $data['wine_master_id'],
            $data['wine_variant_id'],
            $data['format_id'],
            $data['sellable_sku_id'],
            $data['liquid_wine_master_id'],
            $data['liquid_wine_variant_id'],
            $data['liquid_product_id']
        );

        return $data;
    }

    /**
     * After creating the procurement intent, show success notification.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Procurement Intent created as Draft')
            ->body('The procurement intent has been created in Draft status. It requires approval before execution.')
            ->send();
    }
}
