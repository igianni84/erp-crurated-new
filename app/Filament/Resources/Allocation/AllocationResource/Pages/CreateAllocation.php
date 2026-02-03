<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

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
            // Future steps will be added in US-009, US-010, US-011, US-012
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
     * Removes the temporary wine_master_id field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove wine_master_id as it's only used for cascading selects
        unset($data['wine_master_id']);

        return $data;
    }
}
