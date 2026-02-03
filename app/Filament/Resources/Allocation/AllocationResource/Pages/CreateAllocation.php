<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\Allocation\AllocationService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateAllocation extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = AllocationResource::class;

    /**
     * Track whether to activate after creation.
     */
    public bool $shouldActivateAfterCreate = false;

    /**
     * Get the form for creating an allocation.
     * Implements a multi-step wizard for allocation creation.
     */
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
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
     * Get the wizard submit actions (two buttons: Create as Draft, Create and Activate).
     */
    protected function getWizardSubmitActions(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        size="sm"
                        wire:click="createAsDraft"
                    >
                        Create as Draft
                    </x-filament::button>

                    @can('activate', \App\Models\Allocation\Allocation::class)
                    <x-filament::button
                        type="submit"
                        size="sm"
                        color="success"
                        wire:click="createAndActivate"
                    >
                        Create and Activate
                    </x-filament::button>
                    @endcan
                </div>
            BLADE)
        );
    }

    /**
     * Create the allocation as a Draft.
     */
    public function createAsDraft(): void
    {
        $this->shouldActivateAfterCreate = false;
        $this->create();
    }

    /**
     * Create the allocation and immediately activate it.
     */
    public function createAndActivate(): void
    {
        $this->shouldActivateAfterCreate = true;
        $this->create();
    }

    /**
     * Get the wizard steps.
     *
     * @return array<Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getBottleSkuStep(),
            $this->getSourceAndCapacityStep(),
            $this->getCommercialConstraintsStep(),
            $this->getAdvancedConstraintsStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Bottle SKU Selection
     * Allows selection of Wine (via WineMaster search) + Vintage + Format
     */
    protected function getBottleSkuStep(): Wizard\Step
    {
        return Wizard\Step::make('Bottle SKU')
            ->description('Select the wine and format for this allocation')
            ->icon('heroicon-o-cube')
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('bottle_sku_info')
                            ->label('')
                            ->content('Allocation always happens at Bottle SKU level (Wine + Vintage + Format). You cannot allocate at sellable SKU or packaging level.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Wine Selection')
                    ->description('Search and select the wine')
                    ->schema([
                        Forms\Components\Select::make('wine_master_id')
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
                                // Reset wine variant when wine master changes
                                $set('wine_variant_id', null);
                            })
                            ->required()
                            ->helperText('Type at least 2 characters to search for wines by name or producer'),

                        Forms\Components\Select::make('wine_variant_id')
                            ->label('Vintage')
                            ->placeholder('Select vintage year...')
                            ->options(function (Get $get): array {
                                $wineMasterId = $get('wine_master_id');

                                if ($wineMasterId === null) {
                                    return [];
                                }

                                return WineVariant::query()
                                    ->where('wine_master_id', $wineMasterId)
                                    ->orderByDesc('vintage_year')
                                    ->get()
                                    ->mapWithKeys(fn (WineVariant $variant): array => [
                                        $variant->id => $variant->getAttribute('vintage_year') !== null
                                            ? (string) $variant->getAttribute('vintage_year')
                                            : 'NV (Non-Vintage)',
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->hidden(fn (Get $get): bool => $get('wine_master_id') === null)
                            ->live()
                            ->helperText('Select the vintage year for this allocation'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Format Selection')
                    ->description('Select the bottle format (size)')
                    ->schema([
                        Forms\Components\Select::make('format_id')
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
                            ->helperText('Standard bottle sizes: 750ml (standard), 375ml (half), 1500ml (magnum)'),
                    ])
                    ->hidden(fn (Get $get): bool => $get('wine_variant_id') === null)
                    ->columns(1),

                Forms\Components\Section::make('Selected Bottle SKU')
                    ->schema([
                        Forms\Components\Placeholder::make('selected_bottle_sku')
                            ->label('Bottle SKU Preview')
                            ->content(function (Get $get): string {
                                $wineVariantId = $get('wine_variant_id');
                                $formatId = $get('format_id');

                                if ($wineVariantId === null || $formatId === null) {
                                    return 'Complete the selections above to see the Bottle SKU';
                                }

                                $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);
                                $format = Format::find($formatId);

                                if ($wineVariant === null || $format === null) {
                                    return 'Invalid selection';
                                }

                                $wineMaster = $wineVariant->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                $producer = $wineMaster !== null ? ($wineMaster->producer ?? '') : '';
                                $vintage = $wineVariant->getAttribute('vintage_year') ?? 'NV';
                                $formatLabel = self::formatFormatOption($format);

                                $label = "{$wineName}";
                                if ($producer !== '') {
                                    $label .= " ({$producer})";
                                }
                                $label .= " {$vintage} - {$formatLabel}";

                                return $label;
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('wine_variant_id') === null || $get('format_id') === null),
            ]);
    }

    /**
     * Step 2: Source & Capacity
     * Defines the source type, supply form, quantity, and availability window
     */
    protected function getSourceAndCapacityStep(): Wizard\Step
    {
        return Wizard\Step::make('Source & Capacity')
            ->description('Define the supply source and quantity')
            ->icon('heroicon-o-archive-box')
            ->schema([
                Forms\Components\Section::make('Supply Source')
                    ->description('Define where this supply comes from')
                    ->schema([
                        Forms\Components\Select::make('source_type')
                            ->label('Source Type')
                            ->options(
                                collect(AllocationSourceType::cases())
                                    ->mapWithKeys(fn (AllocationSourceType $type): array => [
                                        $type->value => $type->label(),
                                    ])
                                    ->toArray()
                            )
                            ->required()
                            ->native(false)
                            ->helperText('The commercial arrangement for this supply'),

                        Forms\Components\Select::make('supply_form')
                            ->label('Supply Form')
                            ->options(
                                collect(AllocationSupplyForm::cases())
                                    ->mapWithKeys(fn (AllocationSupplyForm $form): array => [
                                        $form->value => $form->label(),
                                    ])
                                    ->toArray()
                            )
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('Whether the supply is already bottled or still in liquid form'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('supply_form_guidance')
                            ->label('')
                            ->content(function (Get $get): string {
                                $supplyForm = $get('supply_form');

                                if ($supplyForm === AllocationSupplyForm::Bottled->value) {
                                    return '**Bottled supply:** The wine is already bottled and ready for sale. Each bottle can be individually serialized and tracked. This is the most common form of allocation.';
                                }

                                if ($supplyForm === AllocationSupplyForm::Liquid->value) {
                                    return '**Liquid supply:** The wine is still in barrel or tank and will be bottled later. Customers purchasing from liquid allocations may need to choose bottling options (format, case configuration). Additional constraints can be specified in Step 4.';
                                }

                                return 'Select a supply form above to see guidance.';
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('supply_form') === null),

                Forms\Components\Section::make('Capacity')
                    ->description('Define the total available quantity')
                    ->schema([
                        Forms\Components\TextInput::make('total_quantity')
                            ->label('Total Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
                            ->suffix('bottles')
                            ->helperText('Total number of bottles available for this allocation'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Availability Window')
                    ->description('When will this supply be available for fulfillment?')
                    ->schema([
                        Forms\Components\DatePicker::make('expected_availability_start')
                            ->label('Expected Availability Start')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->live()
                            ->helperText('Earliest date when this supply can be fulfilled'),

                        Forms\Components\DatePicker::make('expected_availability_end')
                            ->label('Expected Availability End')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->afterOrEqual('expected_availability_start')
                            ->helperText('Latest date by which this supply should be fulfilled'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Serialization')
                    ->description('Configure bottle tracking requirements')
                    ->schema([
                        Forms\Components\Toggle::make('serialization_required')
                            ->label('Serialization Required')
                            ->default(true)
                            ->live()
                            ->helperText('Each bottle must be assigned a unique serial number'),

                        Forms\Components\Placeholder::make('serialization_guidance')
                            ->label('')
                            ->content(function (Get $get): string {
                                $required = $get('serialization_required');

                                if ($required) {
                                    return '**Serialization enabled:** Each bottle from this allocation will receive a unique identifier for provenance tracking. This is recommended for fine wine and required for trading on secondary markets.';
                                }

                                return '**Serialization disabled:** Bottles will not be individually tracked. Use this only for commodity wines where individual bottle tracking is not needed. Note: This cannot be changed once vouchers are issued.';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * Step 3: Commercial Constraints
     * Defines the authoritative commercial constraints for the allocation
     */
    protected function getCommercialConstraintsStep(): Wizard\Step
    {
        return Wizard\Step::make('Commercial Constraints')
            ->description('Define where and to whom this allocation can be sold')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('constraints_warning')
                            ->label('')
                            ->content('⚠️ **AUTHORITATIVE CONSTRAINTS** — These constraints are binding and will be enforced by Module S (Sales). Any sale that violates these constraints will be rejected. If no values are selected for a constraint category, all options are allowed by default.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Sales Channels')
                    ->description('Which sales channels can sell from this allocation?')
                    ->schema([
                        Forms\Components\CheckboxList::make('constraint.allowed_channels')
                            ->label('Allowed Channels')
                            ->options([
                                'b2c' => 'B2C (Direct to Consumer)',
                                'b2b' => 'B2B (Business to Business)',
                                'private_sales' => 'Private Sales',
                                'wholesale' => 'Wholesale',
                                'club' => 'Club (Members Only)',
                            ])
                            ->columns(2)
                            ->helperText('Leave empty to allow all channels'),
                    ]),

                Forms\Components\Section::make('Geographic Restrictions')
                    ->description('Which geographic regions can receive from this allocation?')
                    ->schema([
                        Forms\Components\TagsInput::make('constraint.allowed_geographies')
                            ->label('Allowed Geographies')
                            ->placeholder('Add geography codes (e.g., IT, FR, US, EU)...')
                            ->helperText('Enter ISO country codes or region codes. Leave empty to allow all geographies.'),
                    ]),

                Forms\Components\Section::make('Customer Types')
                    ->description('Which customer types can purchase from this allocation?')
                    ->schema([
                        Forms\Components\CheckboxList::make('constraint.allowed_customer_types')
                            ->label('Allowed Customer Types')
                            ->options([
                                'retail' => 'Retail Customers',
                                'trade' => 'Trade Customers',
                                'private_client' => 'Private Clients',
                                'club_member' => 'Club Members',
                                'internal' => 'Internal (Staff/Company)',
                            ])
                            ->columns(2)
                            ->helperText('Leave empty to allow all customer types'),
                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('constraints_info')
                            ->label('')
                            ->content('**Note:** Constraints become read-only once the allocation is activated. To modify constraints, the allocation must remain in Draft status.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 4: Advanced/Liquid Constraints
     * Defines advanced constraints and liquid-specific options
     */
    protected function getAdvancedConstraintsStep(): Wizard\Step
    {
        return Wizard\Step::make('Advanced Constraints')
            ->description('Optional advanced constraints (expanded for liquid allocations)')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                // Section that shows when liquid is selected - expanded by default
                Forms\Components\Section::make('Liquid Allocation Constraints')
                    ->description('Additional constraints specific to liquid (not yet bottled) supply')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Forms\Components\Placeholder::make('liquid_constraints_info')
                            ->label('')
                            ->content('**Liquid allocations** require additional constraints to define bottling options and deadlines. Customers purchasing from liquid allocations may need to confirm bottling preferences before a deadline.')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('liquid_constraint.allowed_bottling_formats')
                            ->label('Allowed Bottling Formats')
                            ->placeholder('Add format codes (e.g., 750ml, 1500ml, 375ml)...')
                            ->helperText('Specify which bottle formats customers can choose for bottling. Leave empty to allow all formats.'),

                        Forms\Components\TagsInput::make('liquid_constraint.allowed_case_configurations')
                            ->label('Allowed Case Configurations')
                            ->placeholder('Add case configs (e.g., 6-pack, 12-pack, OWC-6)...')
                            ->helperText('Specify case packaging options available to customers. Leave empty to allow all configurations.'),

                        Forms\Components\DatePicker::make('liquid_constraint.bottling_confirmation_deadline')
                            ->label('Bottling Confirmation Deadline')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->helperText('Deadline by which customers must confirm their bottling preferences. Leave empty for no deadline.'),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => $get('supply_form') !== AllocationSupplyForm::Liquid->value)
                    ->hidden(fn (Get $get): bool => $get('supply_form') !== AllocationSupplyForm::Liquid->value),

                // Advanced constraints section - always available but collapsed by default
                Forms\Components\Section::make('Advanced Commercial Constraints')
                    ->description('Optional advanced constraints for special scenarios')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\TextInput::make('constraint.composition_constraint_group')
                            ->label('Composition Constraint Group')
                            ->placeholder('e.g., vertical-case-2020, premium-selection')
                            ->helperText('Optional identifier for grouping allocations that can be composed into vertical cases or themed selections. Allocations with the same group can be combined.'),

                        Forms\Components\Placeholder::make('composition_guidance')
                            ->label('')
                            ->content('**Composition Constraint Groups** are used for vertical cases and themed collections. For example, if you have separate allocations for different vintages of the same wine, you can assign them the same composition group (e.g., "château-margaux-vertical") to indicate they can be combined into a vertical case offering.')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('constraint.fungibility_exception')
                            ->label('Fungibility Exception')
                            ->default(false)
                            ->helperText('Enable if bottles from this allocation are NOT interchangeable with identical bottles from other allocations'),

                        Forms\Components\Placeholder::make('fungibility_guidance')
                            ->label('')
                            ->content('**Fungibility Exception:** By default, bottles of the same SKU are considered fungible (interchangeable). Enable this exception for special allocations where the specific provenance matters, such as ex-cellar releases or bottles with specific storage history.')
                            ->hidden(fn (Get $get): bool => ! $get('constraint.fungibility_exception'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => $get('supply_form') !== AllocationSupplyForm::Liquid->value),

                // Info message for non-liquid allocations
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('advanced_info')
                            ->label('')
                            ->content('**Tip:** For bottled allocations, advanced constraints are typically not needed. You can skip this step unless you need composition groups or fungibility exceptions.')
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('supply_form') === AllocationSupplyForm::Liquid->value),
            ]);
    }

    /**
     * Step 5: Review & Create
     * Shows a read-only summary of all data and provides creation options
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review & Create')
            ->description('Review your allocation before creating')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Draft status warning
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('draft_warning')
                            ->label('')
                            ->content('⚠️ **Draft allocations cannot be consumed and do not issue vouchers.** Activate the allocation when you are ready to start selling from it.')
                            ->columnSpanFull(),
                    ]),

                // Bottle SKU Summary
                Forms\Components\Section::make('Bottle SKU')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Forms\Components\Placeholder::make('review_bottle_sku')
                            ->label('Selected Bottle SKU')
                            ->content(function (Get $get): string {
                                $wineVariantId = $get('wine_variant_id');
                                $formatId = $get('format_id');

                                if ($wineVariantId === null || $formatId === null) {
                                    return 'Not selected';
                                }

                                $wineVariant = WineVariant::with('wineMaster')->find($wineVariantId);
                                $format = Format::find($formatId);

                                if ($wineVariant === null || $format === null) {
                                    return 'Invalid selection';
                                }

                                $wineMaster = $wineVariant->wineMaster;
                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                $producer = $wineMaster !== null ? ($wineMaster->producer ?? '') : '';
                                $vintage = $wineVariant->getAttribute('vintage_year') ?? 'NV';
                                $formatLabel = self::formatFormatOption($format);

                                $label = "{$wineName}";
                                if ($producer !== '') {
                                    $label .= " ({$producer})";
                                }
                                $label .= " {$vintage} - {$formatLabel}";

                                return $label;
                            }),
                    ])
                    ->columns(1),

                // Source & Capacity Summary
                Forms\Components\Section::make('Source & Capacity')
                    ->icon('heroicon-o-archive-box')
                    ->schema([
                        Forms\Components\Placeholder::make('review_source_type')
                            ->label('Source Type')
                            ->content(function (Get $get): string {
                                $sourceType = $get('source_type');
                                if ($sourceType === null) {
                                    return 'Not selected';
                                }
                                $enum = AllocationSourceType::tryFrom($sourceType);

                                return $enum !== null ? $enum->label() : $sourceType;
                            }),

                        Forms\Components\Placeholder::make('review_supply_form')
                            ->label('Supply Form')
                            ->content(function (Get $get): string {
                                $supplyForm = $get('supply_form');
                                if ($supplyForm === null) {
                                    return 'Not selected';
                                }
                                $enum = AllocationSupplyForm::tryFrom($supplyForm);

                                return $enum !== null ? $enum->label() : $supplyForm;
                            }),

                        Forms\Components\Placeholder::make('review_total_quantity')
                            ->label('Total Quantity')
                            ->content(fn (Get $get): string => ($get('total_quantity') ?? '0').' bottles'),

                        Forms\Components\Placeholder::make('review_availability')
                            ->label('Availability Window')
                            ->content(function (Get $get): string {
                                $start = $get('expected_availability_start');
                                $end = $get('expected_availability_end');

                                if ($start === null && $end === null) {
                                    return 'Not specified';
                                }

                                if ($start !== null && $end === null) {
                                    return "From {$start}";
                                }

                                if ($start === null) {
                                    return "Until {$end}";
                                }

                                return "{$start} - {$end}";
                            }),

                        Forms\Components\Placeholder::make('review_serialization')
                            ->label('Serialization')
                            ->content(fn (Get $get): string => $get('serialization_required') ? 'Required (individual tracking)' : 'Not required'),
                    ])
                    ->columns(2),

                // Commercial Constraints Summary
                Forms\Components\Section::make('Commercial Constraints')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Placeholder::make('review_channels')
                            ->label('Allowed Channels')
                            ->content(function (Get $get): string {
                                $channels = $get('constraint.allowed_channels');
                                if (empty($channels)) {
                                    return 'All channels (no restrictions)';
                                }

                                return implode(', ', array_map(fn (string $ch): string => match ($ch) {
                                    'b2c' => 'B2C',
                                    'b2b' => 'B2B',
                                    'private_sales' => 'Private Sales',
                                    'wholesale' => 'Wholesale',
                                    'club' => 'Club',
                                    default => $ch,
                                }, $channels));
                            }),

                        Forms\Components\Placeholder::make('review_geographies')
                            ->label('Allowed Geographies')
                            ->content(function (Get $get): string {
                                $geos = $get('constraint.allowed_geographies');
                                if (empty($geos)) {
                                    return 'All geographies (no restrictions)';
                                }

                                return implode(', ', $geos);
                            }),

                        Forms\Components\Placeholder::make('review_customer_types')
                            ->label('Allowed Customer Types')
                            ->content(function (Get $get): string {
                                $types = $get('constraint.allowed_customer_types');
                                if (empty($types)) {
                                    return 'All customer types (no restrictions)';
                                }

                                return implode(', ', array_map(fn (string $type): string => match ($type) {
                                    'retail' => 'Retail',
                                    'trade' => 'Trade',
                                    'private_client' => 'Private Client',
                                    'club_member' => 'Club Member',
                                    'internal' => 'Internal',
                                    default => $type,
                                }, $types));
                            }),
                    ])
                    ->columns(2),

                // Advanced Constraints Summary (only shown if any are set)
                Forms\Components\Section::make('Advanced Constraints')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Forms\Components\Placeholder::make('review_composition_group')
                            ->label('Composition Constraint Group')
                            ->content(fn (Get $get): string => $get('constraint.composition_constraint_group') ?? 'None'),

                        Forms\Components\Placeholder::make('review_fungibility')
                            ->label('Fungibility Exception')
                            ->content(fn (Get $get): string => $get('constraint.fungibility_exception') ? 'Yes (non-fungible)' : 'No (standard fungibility)'),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => empty($get('constraint.composition_constraint_group')) && ! $get('constraint.fungibility_exception')),

                // Liquid Constraints Summary (only shown for liquid allocations)
                Forms\Components\Section::make('Liquid Allocation Constraints')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Forms\Components\Placeholder::make('review_bottling_formats')
                            ->label('Allowed Bottling Formats')
                            ->content(function (Get $get): string {
                                $formats = $get('liquid_constraint.allowed_bottling_formats');
                                if (empty($formats)) {
                                    return 'All formats allowed';
                                }

                                return implode(', ', $formats);
                            }),

                        Forms\Components\Placeholder::make('review_case_configs')
                            ->label('Allowed Case Configurations')
                            ->content(function (Get $get): string {
                                $configs = $get('liquid_constraint.allowed_case_configurations');
                                if (empty($configs)) {
                                    return 'All configurations allowed';
                                }

                                return implode(', ', $configs);
                            }),

                        Forms\Components\Placeholder::make('review_bottling_deadline')
                            ->label('Bottling Confirmation Deadline')
                            ->content(fn (Get $get): string => $get('liquid_constraint.bottling_confirmation_deadline') ?? 'No deadline'),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => $get('supply_form') !== AllocationSupplyForm::Liquid->value),

                // Informational warnings section
                Forms\Components\Section::make('Before You Create')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Placeholder::make('info_constraints_readonly')
                            ->label('')
                            ->content('📋 **Constraints become read-only** once the allocation is activated. Review them carefully before proceeding.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('info_draft_explanation')
                            ->label('')
                            ->content('📝 **"Create as Draft"** creates the allocation in Draft status. You can review and edit constraints, then activate later.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('info_activate_explanation')
                            ->label('')
                            ->content('⚡ **"Create and Activate"** creates the allocation and immediately activates it. Constraints will be locked immediately.')
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
        if ($producer !== null && $producer !== '') {
            $label .= " ({$producer})";
        }

        $appellation = $wineMaster->getAttribute('appellation');
        if ($appellation !== null && $appellation !== '') {
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
        if ($name !== null && $name !== '' && $name !== "{$volumeMl}ml") {
            $label .= " ({$name})";
        }

        if ($format->is_standard) {
            $label .= ' ★';
        }

        return $label;
    }

    /**
     * Mutate form data before creating the record.
     * Removes temporary fields and extracts nested constraint data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove wine_master_id as it's only used for cascading selects
        unset($data['wine_master_id']);

        // Extract constraint data for later use (will be applied to AllocationConstraint after creation)
        // The constraint is auto-created by Allocation model, we just need to store the data
        if (isset($data['constraint'])) {
            // Store constraint data in session for afterCreate
            session(['allocation_constraint_data' => $data['constraint']]);
            unset($data['constraint']);
        }

        // Extract liquid constraint data for later use
        if (isset($data['liquid_constraint'])) {
            session(['allocation_liquid_constraint_data' => $data['liquid_constraint']]);
            unset($data['liquid_constraint']);
        }

        return $data;
    }

    /**
     * After creating the allocation, update the auto-created constraint with the wizard data.
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Allocation\Allocation $allocation */
        $allocation = $this->record;

        // Handle AllocationConstraint data (from Step 3 and Step 4)
        $constraintData = session('allocation_constraint_data');
        session()->forget('allocation_constraint_data');

        if ($constraintData !== null) {
            $constraint = $allocation->constraint;

            if ($constraint !== null) {
                $updateData = [];

                // Step 3 fields
                if (! empty($constraintData['allowed_channels'])) {
                    $updateData['allowed_channels'] = $constraintData['allowed_channels'];
                }

                if (! empty($constraintData['allowed_geographies'])) {
                    $updateData['allowed_geographies'] = $constraintData['allowed_geographies'];
                }

                if (! empty($constraintData['allowed_customer_types'])) {
                    $updateData['allowed_customer_types'] = $constraintData['allowed_customer_types'];
                }

                // Step 4 fields (advanced constraints)
                if (! empty($constraintData['composition_constraint_group'])) {
                    $updateData['composition_constraint_group'] = $constraintData['composition_constraint_group'];
                }

                if (isset($constraintData['fungibility_exception']) && $constraintData['fungibility_exception']) {
                    $updateData['fungibility_exception'] = true;
                }

                if (! empty($updateData)) {
                    $constraint->update($updateData);
                }
            }
        }

        // Handle LiquidAllocationConstraint data (from Step 4 - only for liquid allocations)
        $liquidConstraintData = session('allocation_liquid_constraint_data');
        session()->forget('allocation_liquid_constraint_data');

        if ($liquidConstraintData !== null && $allocation->isLiquid()) {
            $hasLiquidData = ! empty($liquidConstraintData['allowed_bottling_formats'])
                || ! empty($liquidConstraintData['allowed_case_configurations'])
                || ! empty($liquidConstraintData['bottling_confirmation_deadline']);

            if ($hasLiquidData) {
                $liquidUpdateData = [];

                if (! empty($liquidConstraintData['allowed_bottling_formats'])) {
                    $liquidUpdateData['allowed_bottling_formats'] = $liquidConstraintData['allowed_bottling_formats'];
                }

                if (! empty($liquidConstraintData['allowed_case_configurations'])) {
                    $liquidUpdateData['allowed_case_configurations'] = $liquidConstraintData['allowed_case_configurations'];
                }

                if (! empty($liquidConstraintData['bottling_confirmation_deadline'])) {
                    $liquidUpdateData['bottling_confirmation_deadline'] = $liquidConstraintData['bottling_confirmation_deadline'];
                }

                // Create or update the liquid constraint
                $allocation->liquidConstraint()->create(array_merge(
                    ['allocation_id' => $allocation->id],
                    $liquidUpdateData
                ));
            }
        }

        // Handle activation if "Create and Activate" was clicked
        if ($this->shouldActivateAfterCreate) {
            try {
                $allocationService = app(AllocationService::class);
                $allocationService->activate($allocation);

                Notification::make()
                    ->success()
                    ->title('Allocation created and activated')
                    ->body('The allocation has been created and is now active. Constraints are now read-only.')
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->warning()
                    ->title('Allocation created but activation failed')
                    ->body('The allocation was created as Draft. Activation failed: '.$e->getMessage())
                    ->send();
            }
        } else {
            Notification::make()
                ->success()
                ->title('Allocation created as Draft')
                ->body('The allocation has been created in Draft status. You can activate it when ready to start selling.')
                ->send();
        }
    }
}
