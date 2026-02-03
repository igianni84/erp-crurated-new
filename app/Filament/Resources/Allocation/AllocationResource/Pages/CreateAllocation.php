<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;

class CreateAllocation extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = AllocationResource::class;

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
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
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
            // Future steps will be added in US-011, US-012
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

        return $data;
    }

    /**
     * After creating the allocation, update the auto-created constraint with the wizard data.
     */
    protected function afterCreate(): void
    {
        $constraintData = session('allocation_constraint_data');
        session()->forget('allocation_constraint_data');

        if ($constraintData !== null) {
            /** @var \App\Models\Allocation\Allocation $allocation */
            $allocation = $this->record;
            $constraint = $allocation->constraint;

            if ($constraint !== null) {
                // Only update if there's actual data (empty arrays mean "allow all")
                $updateData = [];

                if (! empty($constraintData['allowed_channels'])) {
                    $updateData['allowed_channels'] = $constraintData['allowed_channels'];
                }

                if (! empty($constraintData['allowed_geographies'])) {
                    $updateData['allowed_geographies'] = $constraintData['allowed_geographies'];
                }

                if (! empty($constraintData['allowed_customer_types'])) {
                    $updateData['allowed_customer_types'] = $constraintData['allowed_customer_types'];
                }

                if (! empty($updateData)) {
                    $constraint->update($updateData);
                }
            }
        }
    }
}
