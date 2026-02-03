<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Pim\SellableSku;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CreateOffer extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = OfferResource::class;

    /**
     * Track whether to activate after creation.
     */
    public bool $activateAfterCreate = false;

    /**
     * Get the form for creating an offer.
     * Implements a multi-step wizard for offer creation.
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
     * Get the wizard submit action with two options.
     */
    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Create as Draft
                    </x-filament::button>
                    <x-filament::button
                        type="submit"
                        size="sm"
                        color="success"
                        wire:click="$set('activateAfterCreate', true)"
                    >
                        Create and Activate
                    </x-filament::button>
                </div>
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
            $this->getProductStep(),
            $this->getChannelAndEligibilityStep(),
            $this->getPricingStep(),
            $this->getValidityAndVisibilityStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Product Selection
     * Select the Sellable SKU for the new Offer.
     */
    protected function getProductStep(): Wizard\Step
    {
        return Wizard\Step::make('Product')
            ->description('Select the Sellable SKU for this Offer')
            ->icon('heroicon-o-cube')
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('offer_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-4 border border-primary-200 dark:border-primary-800">'
                                .'<div class="flex items-start gap-3">'
                                .'<div class="flex-shrink-0 text-primary-600 dark:text-primary-400">'
                                .'<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<p class="font-semibold text-primary-800 dark:text-primary-200">1 Offer = 1 Sellable SKU</p>'
                                .'<p class="text-sm text-primary-700 dark:text-primary-300 mt-1">'
                                .'Each Offer represents the activation of sellability for a single Sellable SKU on a specific channel. '
                                .'Bundles are handled as composite SKUs and are created as single offers.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Select Sellable SKU')
                    ->description('Choose the product you want to create an offer for')
                    ->schema([
                        Forms\Components\Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->placeholder('Search by SKU code or wine name...')
                            ->options(function (): array {
                                // Get SKUs that have at least one active allocation
                                return SellableSku::query()
                                    ->where('lifecycle_status', SellableSku::STATUS_ACTIVE)
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->get()
                                    ->filter(function (SellableSku $sku): bool {
                                        // Check if SKU has active allocation
                                        return $this->skuHasActiveAllocation($sku);
                                    })
                                    ->mapWithKeys(function (SellableSku $sku): array {
                                        $label = $this->buildSkuLabel($sku);

                                        return [$sku->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->getSearchResultsUsing(function (string $search): array {
                                return SellableSku::query()
                                    ->where('lifecycle_status', SellableSku::STATUS_ACTIVE)
                                    ->where(function (Builder $query) use ($search): void {
                                        $query->where('sku_code', 'like', "%{$search}%")
                                            ->orWhereHas('wineVariant.wineMaster', function (Builder $q) use ($search): void {
                                                $q->where('name', 'like', "%{$search}%");
                                            });
                                    })
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->limit(50)
                                    ->get()
                                    ->filter(function (SellableSku $sku): bool {
                                        return $this->skuHasActiveAllocation($sku);
                                    })
                                    ->mapWithKeys(function (SellableSku $sku): array {
                                        $label = $this->buildSkuLabel($sku);

                                        return [$sku->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Only SKUs with active allocations are shown'),

                        // SKU Preview Section
                        Forms\Components\Placeholder::make('sku_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('sellable_sku_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $skuId = $get('sellable_sku_id');
                                if ($skuId === null) {
                                    return new HtmlString('');
                                }

                                $sku = SellableSku::with([
                                    'wineVariant.wineMaster',
                                    'format',
                                    'caseConfiguration',
                                    'estimatedMarketPrices',
                                ])->find($skuId);

                                if ($sku === null) {
                                    return new HtmlString('<div class="text-gray-500">SKU not found</div>');
                                }

                                return new HtmlString($this->buildSkuPreviewHtml($sku));
                            })
                            ->columnSpanFull(),
                    ]),

                // No EMP Warning
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('no_emp_warning')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $skuId = $get('sellable_sku_id');
                                if ($skuId === null) {
                                    return false;
                                }

                                $empCount = EstimatedMarketPrice::where('sellable_sku_id', $skuId)->count();

                                return $empCount === 0;
                            })
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-300 dark:border-warning-700">'
                                .'<div class="flex items-start gap-3">'
                                .'<div class="flex-shrink-0 text-warning-600 dark:text-warning-400">'
                                .'<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<p class="font-medium text-warning-800 dark:text-warning-200">No EMP Data Available</p>'
                                .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
                                .'This SKU does not have any Estimated Market Price (EMP) data. '
                                .'You can still create an offer, but EMP comparison features will not be available.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('sellable_sku_id') === null),
            ]);
    }

    /**
     * Step 2: Channel & Eligibility (placeholder for US-038)
     */
    protected function getChannelAndEligibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Channel & Eligibility')
            ->description('Define channel and eligibility (Coming in US-038)')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Forms\Components\Section::make('Channel & Eligibility')
                    ->description('This step will be implemented in US-038')
                    ->schema([
                        Forms\Components\Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    /**
     * Step 3: Pricing (placeholder for US-039)
     */
    protected function getPricingStep(): Wizard\Step
    {
        return Wizard\Step::make('Pricing')
            ->description('Define pricing (Coming in US-039)')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Forms\Components\Section::make('Pricing')
                    ->description('This step will be implemented in US-039')
                    ->schema([
                        Forms\Components\Select::make('price_book_id')
                            ->label('Price Book')
                            ->relationship('priceBook', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    /**
     * Step 4: Validity & Visibility (placeholder for US-040)
     */
    protected function getValidityAndVisibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Validity & Visibility')
            ->description('Define validity and visibility (Coming in US-040)')
            ->icon('heroicon-o-eye')
            ->schema([
                Forms\Components\Section::make('Offer Details')
                    ->description('This step will be implemented in US-040')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Sassicaia 2018 - B2C IT Promo'),
                        Forms\Components\Select::make('offer_type')
                            ->label('Offer Type')
                            ->options(collect(OfferType::cases())->mapWithKeys(fn (OfferType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->required()
                            ->default(OfferType::Standard->value)
                            ->native(false),
                        Forms\Components\Select::make('visibility')
                            ->options(collect(OfferVisibility::cases())->mapWithKeys(fn (OfferVisibility $v) => [
                                $v->value => $v->label(),
                            ]))
                            ->required()
                            ->default(OfferVisibility::Public->value)
                            ->native(false),
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false)
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->after('valid_from'),
                        Forms\Components\TextInput::make('campaign_tag')
                            ->label('Campaign Tag')
                            ->maxLength(255)
                            ->placeholder('e.g., summer-2026'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Step 5: Review (placeholder for US-041)
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review')
            ->description('Review and create (Coming in US-041)')
            ->icon('heroicon-o-check-circle')
            ->schema([
                Forms\Components\Section::make('Review')
                    ->description('This step will be fully implemented in US-041')
                    ->schema([
                        Forms\Components\Placeholder::make('review_info')
                            ->label('')
                            ->content('Review your offer configuration before creating.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Check if a Sellable SKU has at least one active allocation.
     */
    protected function skuHasActiveAllocation(SellableSku $sku): bool
    {
        // Find allocations that match this SKU's wine variant and format
        return Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('status', \App\Enums\Allocation\AllocationStatus::Active)
            ->exists();
    }

    /**
     * Get active allocations for a Sellable SKU.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Allocation>
     */
    protected function getActiveAllocationsForSku(SellableSku $sku): \Illuminate\Database\Eloquent\Collection
    {
        return Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('status', \App\Enums\Allocation\AllocationStatus::Active)
            ->with('constraint')
            ->get();
    }

    /**
     * Build a display label for a Sellable SKU.
     */
    protected function buildSkuLabel(SellableSku $sku): string
    {
        $wineVariant = $sku->wineVariant;
        if ($wineVariant === null) {
            return $sku->sku_code;
        }

        $wineMaster = $wineVariant->wineMaster;
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant->vintage_year ?? 'NV';
        $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '';
        $caseConfig = $sku->caseConfiguration;
        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.'x' : '';

        return "{$sku->sku_code} - {$wineName} {$vintage} ({$format} {$packaging})";
    }

    /**
     * Build the HTML preview for a selected SKU.
     */
    protected function buildSkuPreviewHtml(SellableSku $sku): string
    {
        // Get SKU details
        $wineVariant = $sku->wineVariant;
        $wineMaster = $wineVariant !== null ? $wineVariant->wineMaster : null;
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant !== null ? ($wineVariant->vintage_year ?? 'NV') : 'NV';
        $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '-';
        $caseConfig = $sku->caseConfiguration;
        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.'x '.$caseConfig->case_type : '-';

        // Get allocations
        $allocations = $this->getActiveAllocationsForSku($sku);
        $totalQuantity = $allocations->sum('remaining_quantity');

        // Get available channels from allocations
        $availableChannels = [];
        foreach ($allocations as $allocation) {
            $constraint = $allocation->constraint;
            if ($constraint !== null) {
                $channels = $constraint->getEffectiveChannels();
                $availableChannels = array_unique(array_merge($availableChannels, $channels));
            }
        }

        // Format channels for display
        $channelBadges = '';
        if (count($availableChannels) > 0) {
            foreach ($availableChannels as $channel) {
                $channelLabel = ucfirst(str_replace('_', ' ', $channel));
                $channelBadges .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 mr-1">'.$channelLabel.'</span>';
            }
        } else {
            $channelBadges = '<span class="text-gray-500">All channels</span>';
        }

        // Get EMP data
        $emps = $sku->estimatedMarketPrices;
        $empSection = '';
        if ($emps->count() > 0) {
            $empItems = '';
            foreach ($emps->take(3) as $emp) {
                /** @var EstimatedMarketPrice $emp */
                $confidenceColor = $emp->confidence_level->color();
                $empItems .= '<div class="flex justify-between items-center py-1">'
                    .'<span class="text-sm text-gray-600 dark:text-gray-400">'.$emp->market.'</span>'
                    .'<span class="text-sm font-medium">€'.number_format((float) $emp->emp_value, 2).'</span>'
                    .'<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-'.$confidenceColor.'-100 text-'.$confidenceColor.'-800 dark:bg-'.$confidenceColor.'-900 dark:text-'.$confidenceColor.'-200">'.$emp->confidence_level->label().'</span>'
                    .'</div>';
            }
            if ($emps->count() > 3) {
                $empItems .= '<div class="text-xs text-gray-500 mt-1">+'.($emps->count() - 3).' more markets</div>';
            }
            $empSection = '<div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">'
                .'<p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">EMP Reference</p>'
                .$empItems
                .'</div>';
        }

        // Build the preview HTML
        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="grid grid-cols-2 gap-4">'
            // Product Info
            .'<div>'
            .'<p class="text-sm font-medium text-gray-500 dark:text-gray-400">Product</p>'
            .'<p class="font-semibold text-gray-900 dark:text-gray-100">'.$wineName.'</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">Vintage '.$vintage.' • '.$format.' • '.$packaging.'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-500 mt-1">SKU: '.$sku->sku_code.'</p>'
            .'</div>'
            // Allocation Info
            .'<div>'
            .'<p class="text-sm font-medium text-gray-500 dark:text-gray-400">Allocation</p>'
            .'<p class="font-semibold text-gray-900 dark:text-gray-100">'.$allocations->count().' active allocation(s)</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$totalQuantity.' units available</p>'
            .'</div>'
            .'</div>'
            // Channels
            .'<div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">'
            .'<p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Available Channels</p>'
            .'<div>'.$channelBadges.'</div>'
            .'</div>'
            // EMP Section
            .$empSection
            .'</div>';
    }

    /**
     * Mutate form data before creating the offer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure new offers are created as draft (unless activateAfterCreate is true)
        $data['status'] = $this->activateAfterCreate
            ? OfferStatus::Active->value
            : OfferStatus::Draft->value;

        return $data;
    }

    /**
     * Actions to perform after creating the record.
     */
    protected function afterCreate(): void
    {
        // If activated, log the activation
        if ($this->activateAfterCreate) {
            /** @var \App\Models\Commercial\Offer $offer */
            $offer = $this->record;

            $offer->auditLogs()->create([
                'event' => 'status_change',
                'user_id' => auth()->id(),
                'old_values' => ['status' => OfferStatus::Draft->value],
                'new_values' => ['status' => OfferStatus::Active->value],
            ]);
        }
    }
}
