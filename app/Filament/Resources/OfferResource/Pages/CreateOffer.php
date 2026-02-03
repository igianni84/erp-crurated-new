<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Enums\Commercial\PriceBookStatus;
use App\Filament\Resources\OfferResource;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\OfferBenefit;
use App\Models\Commercial\OfferEligibility;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
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
     * Step 2: Channel & Eligibility
     * Define the channel and eligibility conditions for the Offer.
     */
    protected function getChannelAndEligibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Channel & Eligibility')
            ->description('Define channel and customer eligibility')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                // Warning about allocation constraints
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('allocation_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-300 dark:border-warning-700">'
                                .'<div class="flex items-start gap-3">'
                                .'<div class="flex-shrink-0 text-warning-600 dark:text-warning-400">'
                                .'<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<p class="font-semibold text-warning-800 dark:text-warning-200">Eligibility Cannot Override Allocation Constraints</p>'
                                .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
                                .'Allocation constraints from Module A define the authoritative commercial boundaries. '
                                .'Eligibility rules defined here can only narrow the scope within those boundaries, not expand beyond them. '
                                .'Any restrictions you set here will be applied in addition to the allocation constraints.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Channel Selection Section
                Forms\Components\Section::make('Channel Selection')
                    ->description('Select the commercial channel for this offer')
                    ->schema([
                        Forms\Components\Select::make('channel_id')
                            ->label('Channel')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->placeholder('Select a channel...')
                            ->options(function (Get $get): array {
                                $skuId = $get('sellable_sku_id');
                                if ($skuId === null) {
                                    // Return all active channels if no SKU selected
                                    return Channel::query()
                                        ->where('status', \App\Enums\Commercial\ChannelStatus::Active)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                }

                                // Get allowed channels from allocation constraints
                                $sku = SellableSku::find($skuId);
                                if ($sku === null) {
                                    return [];
                                }

                                $allocations = $this->getActiveAllocationsForSku($sku);
                                $allowedChannelTypes = [];

                                foreach ($allocations as $allocation) {
                                    $constraint = $allocation->constraint;
                                    if ($constraint !== null) {
                                        $channels = $constraint->getEffectiveChannels();
                                        $allowedChannelTypes = array_unique(array_merge($allowedChannelTypes, $channels));
                                    }
                                }

                                // Map allocation channel types to Channel model records
                                $query = Channel::query()
                                    ->where('status', \App\Enums\Commercial\ChannelStatus::Active);

                                // If there are specific channel restrictions, filter by channel_type
                                if (! empty($allowedChannelTypes)) {
                                    $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($allowedChannelTypes): void {
                                        foreach ($allowedChannelTypes as $channelType) {
                                            // Match channel_type enum value with allocation constraint channel types
                                            $q->orWhere('channel_type', $channelType);
                                        }
                                    });
                                }

                                return $query->pluck('name', 'id')->toArray();
                            })
                            ->helperText('Only channels permitted by the SKU\'s allocation constraints are shown'),

                        // Channel Preview
                        Forms\Components\Placeholder::make('channel_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('channel_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $channelId = $get('channel_id');
                                if ($channelId === null) {
                                    return new HtmlString('');
                                }

                                $channel = Channel::find($channelId);
                                if ($channel === null) {
                                    return new HtmlString('<div class="text-gray-500">Channel not found</div>');
                                }

                                $typeColor = $channel->getChannelTypeColor();
                                $statusColor = $channel->getStatusColor();

                                return new HtmlString(
                                    '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
                                    .'<div class="grid grid-cols-2 md:grid-cols-4 gap-4">'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</p>'
                                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$typeColor.'-100 text-'.$typeColor.'-800 dark:bg-'.$typeColor.'-900 dark:text-'.$typeColor.'-200">'.$channel->getChannelTypeLabel().'</span>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Currency</p>'
                                    .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$channel->default_currency.'</p>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</p>'
                                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$statusColor.'-100 text-'.$statusColor.'-800 dark:bg-'.$statusColor.'-900 dark:text-'.$statusColor.'-200">'.$channel->getStatusLabel().'</span>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Commercial Models</p>'
                                    .'<p class="text-sm text-gray-600 dark:text-gray-400">'.implode(', ', $channel->allowed_commercial_models).'</p>'
                                    .'</div>'
                                    .'</div>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Allocation Constraints Preview
                Forms\Components\Section::make('Allocation Constraints')
                    ->description('Current allocation constraints that apply to this SKU')
                    ->schema([
                        Forms\Components\Placeholder::make('constraints_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('sellable_sku_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $skuId = $get('sellable_sku_id');
                                if ($skuId === null) {
                                    return new HtmlString('<div class="text-gray-500">Select a SKU to see allocation constraints</div>');
                                }

                                $sku = SellableSku::find($skuId);
                                if ($sku === null) {
                                    return new HtmlString('<div class="text-gray-500">SKU not found</div>');
                                }

                                return new HtmlString($this->buildAllocationConstraintsPreviewHtml($sku));
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Eligibility Section
                Forms\Components\Section::make('Eligibility Rules')
                    ->description('Define who can access this offer (within allocation constraints)')
                    ->schema([
                        Forms\Components\Select::make('allowed_markets')
                            ->label('Allowed Markets')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(function (Get $get): array {
                                $skuId = $get('sellable_sku_id');
                                $constrainedMarkets = $this->getConstrainedMarketsForSku($skuId);

                                // If there are constrained markets, only show those
                                if (! empty($constrainedMarkets)) {
                                    $options = [];
                                    foreach ($constrainedMarkets as $market) {
                                        $options[$market] = $this->getMarketLabel($market);
                                    }

                                    return $options;
                                }

                                // Otherwise show all common markets
                                return $this->getCommonMarkets();
                            })
                            ->helperText('Leave empty to allow all markets permitted by allocation constraints')
                            ->columnSpan(1),

                        Forms\Components\Select::make('allowed_customer_types')
                            ->label('Allowed Customer Types')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(function (Get $get): array {
                                $skuId = $get('sellable_sku_id');
                                $constrainedTypes = $this->getConstrainedCustomerTypesForSku($skuId);

                                // If there are constrained types, only show those
                                if (! empty($constrainedTypes)) {
                                    $options = [];
                                    foreach ($constrainedTypes as $type) {
                                        $options[$type] = $this->getCustomerTypeLabel($type);
                                    }

                                    return $options;
                                }

                                // Otherwise show all customer types
                                return $this->getCustomerTypeOptions();
                            })
                            ->helperText('Leave empty to allow all customer types permitted by allocation constraints')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                // Eligibility Summary
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('eligibility_summary')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $markets = $get('allowed_markets');
                                $types = $get('allowed_customer_types');

                                return ! empty($markets) || ! empty($types);
                            })
                            ->content(function (Get $get): HtmlString {
                                $markets = $get('allowed_markets') ?? [];
                                $customerTypes = $get('allowed_customer_types') ?? [];

                                $parts = [];

                                if (! empty($markets)) {
                                    $marketLabels = array_map(fn ($m) => $this->getMarketLabel($m), $markets);
                                    $parts[] = '<strong>Markets:</strong> '.implode(', ', $marketLabels);
                                }

                                if (! empty($customerTypes)) {
                                    $typeLabels = array_map(fn ($t) => $this->getCustomerTypeLabel($t), $customerTypes);
                                    $parts[] = '<strong>Customer Types:</strong> '.implode(', ', $typeLabels);
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-4 border border-primary-200 dark:border-primary-800">'
                                    .'<p class="text-sm font-medium text-primary-800 dark:text-primary-200 mb-2">Eligibility Summary</p>'
                                    .'<div class="text-sm text-primary-700 dark:text-primary-300">'
                                    .implode('<br>', $parts)
                                    .'</div>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(function (Get $get): bool {
                        $markets = $get('allowed_markets');
                        $types = $get('allowed_customer_types');

                        return empty($markets) && empty($types);
                    }),
            ]);
    }

    /**
     * Build HTML preview for allocation constraints.
     */
    protected function buildAllocationConstraintsPreviewHtml(SellableSku $sku): string
    {
        $allocations = $this->getActiveAllocationsForSku($sku);

        if ($allocations->isEmpty()) {
            return '<div class="text-gray-500 italic">No active allocations found for this SKU</div>';
        }

        $rows = '';
        foreach ($allocations as $allocation) {
            $constraint = $allocation->constraint;
            if ($constraint === null) {
                continue;
            }

            $channels = $constraint->getEffectiveChannels();
            $geographies = $constraint->getEffectiveGeographies();
            $customerTypes = $constraint->getEffectiveCustomerTypes();

            $channelBadges = '';
            foreach ($channels as $channel) {
                $channelBadges .= '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 mr-1 mb-1">'.ucfirst(str_replace('_', ' ', $channel)).'</span>';
            }

            $geographyBadges = empty($geographies)
                ? '<span class="text-gray-500 italic text-xs">All geographies</span>'
                : '';
            foreach ($geographies as $geo) {
                $geographyBadges .= '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200 mr-1 mb-1">'.$geo.'</span>';
            }

            $customerTypeBadges = '';
            foreach ($customerTypes as $type) {
                $customerTypeBadges .= '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200 mr-1 mb-1">'.ucfirst(str_replace('_', ' ', $type)).'</span>';
            }

            $rows .= '<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 mb-2">'
                .'<div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Allocation ID: '.substr((string) $allocation->id, 0, 8).'...</div>'
                .'<div class="grid grid-cols-3 gap-2">'
                .'<div>'
                .'<p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase mb-1">Channels</p>'
                .'<div>'.$channelBadges.'</div>'
                .'</div>'
                .'<div>'
                .'<p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase mb-1">Geographies</p>'
                .'<div>'.$geographyBadges.'</div>'
                .'</div>'
                .'<div>'
                .'<p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase mb-1">Customer Types</p>'
                .'<div>'.$customerTypeBadges.'</div>'
                .'</div>'
                .'</div>'
                .'</div>';
        }

        return '<div>'.$rows.'</div>';
    }

    /**
     * Get constrained markets from allocation constraints for a SKU.
     *
     * @return array<string>
     */
    protected function getConstrainedMarketsForSku(?string $skuId): array
    {
        if ($skuId === null) {
            return [];
        }

        $sku = SellableSku::find($skuId);
        if ($sku === null) {
            return [];
        }

        $allocations = $this->getActiveAllocationsForSku($sku);
        $markets = [];

        foreach ($allocations as $allocation) {
            $constraint = $allocation->constraint;
            if ($constraint !== null) {
                $geographies = $constraint->getEffectiveGeographies();
                // Only add if there are specific geography restrictions
                if (! empty($geographies)) {
                    $markets = array_unique(array_merge($markets, $geographies));
                }
            }
        }

        return $markets;
    }

    /**
     * Get constrained customer types from allocation constraints for a SKU.
     *
     * @return array<string>
     */
    protected function getConstrainedCustomerTypesForSku(?string $skuId): array
    {
        if ($skuId === null) {
            return [];
        }

        $sku = SellableSku::find($skuId);
        if ($sku === null) {
            return [];
        }

        $allocations = $this->getActiveAllocationsForSku($sku);
        $types = [];

        foreach ($allocations as $allocation) {
            $constraint = $allocation->constraint;
            if ($constraint !== null) {
                $constraintTypes = $constraint->getEffectiveCustomerTypes();
                $types = array_unique(array_merge($types, $constraintTypes));
            }
        }

        return $types;
    }

    /**
     * Get common markets list.
     *
     * @return array<string, string>
     */
    protected function getCommonMarkets(): array
    {
        return [
            'IT' => 'IT - Italy',
            'DE' => 'DE - Germany',
            'FR' => 'FR - France',
            'UK' => 'UK - United Kingdom',
            'US' => 'US - United States',
            'CH' => 'CH - Switzerland',
            'EU' => 'EU - European Union',
            'GLOBAL' => 'GLOBAL - Worldwide',
        ];
    }

    /**
     * Get a market label from a market code.
     */
    protected function getMarketLabel(string $market): string
    {
        $markets = $this->getCommonMarkets();

        return $markets[$market] ?? $market;
    }

    /**
     * Get customer type options.
     *
     * @return array<string, string>
     */
    protected function getCustomerTypeOptions(): array
    {
        return [
            'retail' => 'Retail',
            'trade' => 'Trade',
            'private_client' => 'Private Client',
            'club_member' => 'Club Member',
            'internal' => 'Internal',
        ];
    }

    /**
     * Get a customer type label from a type code.
     */
    protected function getCustomerTypeLabel(string $type): string
    {
        $types = $this->getCustomerTypeOptions();

        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Step 3: Pricing
     * Define the pricing of the Offer - Price Book selection and benefit configuration.
     */
    protected function getPricingStep(): Wizard\Step
    {
        return Wizard\Step::make('Pricing')
            ->description('Define pricing and discounts')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                // Price Book Selection
                Forms\Components\Section::make('Price Book Selection')
                    ->description('Select the Price Book that provides base prices for this offer')
                    ->schema([
                        Forms\Components\Select::make('price_book_id')
                            ->label('Price Book')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->placeholder('Select a Price Book...')
                            ->options(function (Get $get): array {
                                $channelId = $get('channel_id');

                                // Query for active Price Books
                                $query = PriceBook::query()
                                    ->where('status', PriceBookStatus::Active);

                                // If channel is selected, filter by channel or null (global)
                                if ($channelId !== null) {
                                    $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($channelId): void {
                                        $q->where('channel_id', $channelId)
                                            ->orWhereNull('channel_id');
                                    });
                                }

                                // Filter for valid dates
                                $query->where('valid_from', '<=', now())
                                    ->where(function (\Illuminate\Database\Eloquent\Builder $q): void {
                                        $q->whereNull('valid_to')
                                            ->orWhere('valid_to', '>=', now());
                                    });

                                return $query->get()->mapWithKeys(function (PriceBook $priceBook): array {
                                    $label = $priceBook->name.' ('.$priceBook->market.' / '.$priceBook->currency.')';

                                    return [$priceBook->id => $label];
                                })->toArray();
                            })
                            ->helperText('Only active Price Books within their validity period are shown'),

                        // Price Book Preview
                        Forms\Components\Placeholder::make('price_book_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('price_book_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $priceBookId = $get('price_book_id');
                                if ($priceBookId === null) {
                                    return new HtmlString('');
                                }

                                $priceBook = PriceBook::with('channel')->find($priceBookId);
                                if ($priceBook === null) {
                                    return new HtmlString('<div class="text-gray-500">Price Book not found</div>');
                                }

                                return new HtmlString($this->buildPriceBookPreviewHtml($priceBook, $get('sellable_sku_id')));
                            })
                            ->columnSpanFull(),
                    ]),

                // Base Price Preview (from selected Price Book)
                Forms\Components\Section::make('Base Price Preview')
                    ->description('Base price from the selected Price Book and EMP comparison')
                    ->visible(fn (Get $get): bool => $get('price_book_id') !== null && $get('sellable_sku_id') !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('base_price_preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $priceBookId = $get('price_book_id');
                                $skuId = $get('sellable_sku_id');

                                if ($priceBookId === null || $skuId === null) {
                                    return new HtmlString('<div class="text-gray-500">Select a Price Book and SKU to see pricing</div>');
                                }

                                $priceBook = PriceBook::find($priceBookId);
                                $sku = SellableSku::with('estimatedMarketPrices')->find($skuId);

                                if ($priceBook === null || $sku === null) {
                                    return new HtmlString('<div class="text-gray-500">Price Book or SKU not found</div>');
                                }

                                return new HtmlString($this->buildBasePricePreviewHtml($priceBook, $sku));
                            })
                            ->columnSpanFull(),
                    ]),

                // Benefit Configuration
                Forms\Components\Section::make('Benefit Configuration')
                    ->description('Apply discounts or price overrides to the base price')
                    ->schema([
                        Forms\Components\Radio::make('benefit_type')
                            ->label('Benefit Type')
                            ->required()
                            ->live()
                            ->options(collect(BenefitType::cases())->mapWithKeys(fn (BenefitType $type) => [
                                $type->value => $type->label(),
                            ]))
                            ->descriptions(collect(BenefitType::cases())->mapWithKeys(fn (BenefitType $type) => [
                                $type->value => $type->description(),
                            ]))
                            ->default(BenefitType::None->value)
                            ->columns(2),

                        // Value input for discount types
                        Forms\Components\TextInput::make('benefit_value')
                            ->label(fn (Get $get): string => match ($get('benefit_type')) {
                                BenefitType::PercentageDiscount->value => 'Discount Percentage (%)',
                                BenefitType::FixedDiscount->value => 'Discount Amount',
                                BenefitType::FixedPrice->value => 'Fixed Price',
                                default => 'Value',
                            })
                            ->numeric()
                            ->live(onBlur: true)
                            ->minValue(0)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => in_array($get('benefit_type'), [
                                BenefitType::PercentageDiscount->value,
                                BenefitType::FixedDiscount->value,
                                BenefitType::FixedPrice->value,
                            ]))
                            ->required(fn (Get $get): bool => in_array($get('benefit_type'), [
                                BenefitType::PercentageDiscount->value,
                                BenefitType::FixedDiscount->value,
                                BenefitType::FixedPrice->value,
                            ]))
                            ->suffix(fn (Get $get): string => match ($get('benefit_type')) {
                                BenefitType::PercentageDiscount->value => '%',
                                default => '',
                            })
                            ->prefix(fn (Get $get): string => match ($get('benefit_type')) {
                                BenefitType::FixedDiscount->value, BenefitType::FixedPrice->value => '€',
                                default => '',
                            })
                            ->helperText(fn (Get $get): string => match ($get('benefit_type')) {
                                BenefitType::PercentageDiscount->value => 'Enter the percentage discount (e.g., 10 for 10% off)',
                                BenefitType::FixedDiscount->value => 'Enter the fixed amount to subtract from the base price',
                                BenefitType::FixedPrice->value => 'Enter the fixed price that overrides the base price',
                                default => '',
                            }),

                        // Percentage validation warning
                        Forms\Components\Placeholder::make('percentage_warning')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $benefitType = $get('benefit_type');
                                $value = $get('benefit_value');

                                return $benefitType === BenefitType::PercentageDiscount->value
                                    && $value !== null
                                    && (float) $value > 100;
                            })
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-3 border border-danger-300 dark:border-danger-700">'
                                .'<p class="text-sm text-danger-700 dark:text-danger-300">'
                                .'<strong>Warning:</strong> Percentage discount cannot exceed 100%'
                                .'</p>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Final Price Preview
                Forms\Components\Section::make('Final Price Preview')
                    ->description('Preview of the final price after applying benefits')
                    ->visible(fn (Get $get): bool => $get('price_book_id') !== null && $get('sellable_sku_id') !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('final_price_preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $priceBookId = $get('price_book_id');
                                $skuId = $get('sellable_sku_id');
                                $benefitType = $get('benefit_type');
                                $benefitValue = $get('benefit_value');

                                if ($priceBookId === null || $skuId === null) {
                                    return new HtmlString('<div class="text-gray-500">Select a Price Book and SKU to see pricing</div>');
                                }

                                return new HtmlString($this->buildFinalPricePreviewHtml($priceBookId, $skuId, $benefitType, $benefitValue));
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Build HTML preview for selected Price Book.
     */
    protected function buildPriceBookPreviewHtml(PriceBook $priceBook, ?string $skuId): string
    {
        $statusColor = $priceBook->getStatusColor();
        $channelName = $priceBook->channel !== null ? $priceBook->channel->name : 'All Channels';
        $entriesCount = $priceBook->entries()->count();

        // Check if there's a price entry for the selected SKU
        $hasEntryForSku = false;
        if ($skuId !== null) {
            $hasEntryForSku = $priceBook->entries()->where('sellable_sku_id', $skuId)->exists();
        }

        $skuWarning = '';
        if ($skuId !== null && ! $hasEntryForSku) {
            $skuWarning = '<div class="mt-3 p-2 rounded bg-warning-50 dark:bg-warning-950 border border-warning-300 dark:border-warning-700">'
                .'<p class="text-sm text-warning-700 dark:text-warning-300">'
                .'<strong>Warning:</strong> This Price Book does not have a price entry for the selected SKU. '
                .'You may need to add an entry or select a different Price Book.'
                .'</p>'
                .'</div>';
        }

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="grid grid-cols-2 md:grid-cols-5 gap-4">'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Market</p>'
            .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">'.$priceBook->market.'</span>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Currency</p>'
            .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$priceBook->currency.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Channel</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$channelName.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</p>'
            .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$statusColor.'-100 text-'.$statusColor.'-800 dark:bg-'.$statusColor.'-900 dark:text-'.$statusColor.'-200">'.$priceBook->getStatusLabel().'</span>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price Entries</p>'
            .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$entriesCount.'</p>'
            .'</div>'
            .'</div>'
            .'<div class="mt-3 text-xs text-gray-500 dark:text-gray-400">'
            .'Valid: '.$priceBook->valid_from->format('Y-m-d').' → '.($priceBook->valid_to !== null ? $priceBook->valid_to->format('Y-m-d') : 'Indefinite')
            .'</div>'
            .$skuWarning
            .'</div>';
    }

    /**
     * Build HTML preview for base price from Price Book and EMP comparison.
     */
    protected function buildBasePricePreviewHtml(PriceBook $priceBook, SellableSku $sku): string
    {
        // Get price entry for this SKU
        $priceEntry = PriceBookEntry::where('price_book_id', $priceBook->id)
            ->where('sellable_sku_id', $sku->id)
            ->first();

        $basePrice = $priceEntry !== null ? (float) $priceEntry->base_price : null;
        $currency = $priceBook->currency;

        // Get EMP for comparison (using the Price Book's market)
        $emp = EstimatedMarketPrice::where('sellable_sku_id', $sku->id)
            ->where('market', $priceBook->market)
            ->first();

        $empValue = $emp !== null ? (float) $emp->emp_value : null;

        // Build the HTML
        $basePriceDisplay = $basePrice !== null
            ? '<span class="text-2xl font-bold text-gray-900 dark:text-gray-100">'.$currency.' '.number_format($basePrice, 2).'</span>'
            : '<span class="text-lg text-warning-600 dark:text-warning-400">No price entry found</span>';

        $sourceDisplay = '';
        if ($priceEntry !== null) {
            $sourceColor = $priceEntry->getSourceColor();
            $sourceDisplay = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$sourceColor.'-100 text-'.$sourceColor.'-800 dark:bg-'.$sourceColor.'-900 dark:text-'.$sourceColor.'-200">'.$priceEntry->getSourceLabel().'</span>';
        }

        // EMP comparison
        $empSection = '';
        if ($emp !== null && $empValue !== null) {
            $empConfidenceColor = $emp->confidence_level->color();

            $deltaSection = '';
            if ($basePrice !== null && $empValue > 0) {
                $delta = (($basePrice - $empValue) / $empValue) * 100;
                $deltaColor = abs($delta) > 15 ? 'danger' : (abs($delta) > 10 ? 'warning' : 'success');
                $deltaSign = $delta >= 0 ? '+' : '';
                $deltaSection = '<div>'
                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Delta vs EMP</p>'
                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$deltaColor.'-100 text-'.$deltaColor.'-800 dark:bg-'.$deltaColor.'-900 dark:text-'.$deltaColor.'-200">'.$deltaSign.number_format($delta, 1).'%</span>'
                    .'</div>';
            }

            $empSection = '<div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">'
                .'<p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">EMP Reference ('.$priceBook->market.')</p>'
                .'<div class="grid grid-cols-3 gap-4">'
                .'<div>'
                .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">EMP Value</p>'
                .'<span class="text-lg font-semibold text-gray-700 dark:text-gray-300">'.$currency.' '.number_format($empValue, 2).'</span>'
                .'</div>'
                .'<div>'
                .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Confidence</p>'
                .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$empConfidenceColor.'-100 text-'.$empConfidenceColor.'-800 dark:bg-'.$empConfidenceColor.'-900 dark:text-'.$empConfidenceColor.'-200">'.$emp->confidence_level->label().'</span>'
                .'</div>'
                .$deltaSection
                .'</div>'
                .'</div>';
        } else {
            $empSection = '<div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">'
                .'<p class="text-sm text-gray-500 dark:text-gray-400 italic">No EMP data available for market: '.$priceBook->market.'</p>'
                .'</div>';
        }

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="flex items-center justify-between">'
            .'<div>'
            .'<p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Base Price from Price Book</p>'
            .$basePriceDisplay
            .'</div>'
            .'<div class="text-right">'
            .$sourceDisplay
            .'</div>'
            .'</div>'
            .$empSection
            .'</div>';
    }

    /**
     * Build HTML preview for final price after benefit.
     */
    protected function buildFinalPricePreviewHtml(?string $priceBookId, ?string $skuId, ?string $benefitType, ?string $benefitValue): string
    {
        if ($priceBookId === null || $skuId === null) {
            return '<div class="text-gray-500">Select a Price Book and SKU to see pricing</div>';
        }

        $priceBook = PriceBook::find($priceBookId);
        if ($priceBook === null) {
            return '<div class="text-gray-500">Price Book not found</div>';
        }

        // Get price entry for this SKU
        $priceEntry = PriceBookEntry::where('price_book_id', $priceBookId)
            ->where('sellable_sku_id', $skuId)
            ->first();

        if ($priceEntry === null) {
            return '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-300 dark:border-warning-700">'
                .'<p class="text-warning-700 dark:text-warning-300">'
                .'<strong>No Price Entry:</strong> The selected Price Book does not have a price entry for this SKU. '
                .'Please select a different Price Book or add a price entry first.'
                .'</p>'
                .'</div>';
        }

        $basePrice = (float) $priceEntry->base_price;
        $currency = $priceBook->currency;

        // Calculate final price based on benefit type
        $benefitTypeEnum = $benefitType !== null ? BenefitType::tryFrom($benefitType) : BenefitType::None;
        $benefitValueFloat = $benefitValue !== null ? (float) $benefitValue : 0;

        $finalPrice = match ($benefitTypeEnum) {
            BenefitType::None => $basePrice,
            BenefitType::PercentageDiscount => max(0, $basePrice - ($basePrice * min($benefitValueFloat, 100) / 100)),
            BenefitType::FixedDiscount => max(0, $basePrice - $benefitValueFloat),
            BenefitType::FixedPrice => max(0, $benefitValueFloat),
            default => $basePrice,
        };

        $discount = $basePrice - $finalPrice;
        $discountPercentage = $basePrice > 0 ? ($discount / $basePrice) * 100 : 0;

        // Build benefit description
        $benefitDescription = match ($benefitTypeEnum) {
            BenefitType::None => 'No benefit applied - using Price Book price',
            BenefitType::PercentageDiscount => number_format(min($benefitValueFloat, 100), 0).'% discount applied',
            BenefitType::FixedDiscount => $currency.' '.number_format($benefitValueFloat, 2).' discount applied',
            BenefitType::FixedPrice => 'Fixed price override',
            default => 'No benefit applied',
        };

        // Color coding based on discount
        $priceColor = $discount > 0 ? 'success' : ($discount < 0 ? 'warning' : 'gray');

        return '<div class="rounded-lg bg-'.$priceColor.'-50 dark:bg-'.$priceColor.'-950 p-4 border border-'.$priceColor.'-200 dark:border-'.$priceColor.'-800">'
            .'<div class="grid grid-cols-2 md:grid-cols-4 gap-4">'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Base Price</p>'
            .'<p class="text-lg font-medium text-gray-700 dark:text-gray-300">'.$currency.' '.number_format($basePrice, 2).'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Discount</p>'
            .'<p class="text-lg font-medium text-'.$priceColor.'-700 dark:text-'.$priceColor.'-300">'.($discount > 0 ? '-'.$currency.' '.number_format($discount, 2) : '-').'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Final Price</p>'
            .'<p class="text-2xl font-bold text-'.$priceColor.'-800 dark:text-'.$priceColor.'-200">'.$currency.' '.number_format($finalPrice, 2).'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Savings</p>'
            .'<p class="text-lg font-medium text-'.$priceColor.'-700 dark:text-'.$priceColor.'-300">'.($discountPercentage > 0 ? number_format($discountPercentage, 1).'%' : '-').'</p>'
            .'</div>'
            .'</div>'
            .'<div class="mt-3 pt-3 border-t border-'.$priceColor.'-200 dark:border-'.$priceColor.'-700">'
            .'<p class="text-sm text-'.$priceColor.'-700 dark:text-'.$priceColor.'-300">'.$benefitDescription.'</p>'
            .'</div>'
            .'</div>';
    }

    /**
     * Step 4: Validity & Visibility
     * Define the validity period, visibility, and metadata of the Offer.
     */
    protected function getValidityAndVisibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Validity & Visibility')
            ->description('Define validity period and visibility settings')
            ->icon('heroicon-o-eye')
            ->schema([
                // Offer Identity Section
                Forms\Components\Section::make('Offer Identity')
                    ->description('Give your offer a name and define its type')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Offer Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->placeholder('e.g., Sassicaia 2018 - B2C IT Promo')
                            ->helperText('A descriptive name to identify this offer')
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('offer_type')
                            ->label('Offer Type')
                            ->required()
                            ->live()
                            ->options([
                                OfferType::Standard->value => OfferType::Standard->label(),
                                OfferType::Promotion->value => OfferType::Promotion->label(),
                                OfferType::Bundle->value => OfferType::Bundle->label(),
                            ])
                            ->descriptions([
                                OfferType::Standard->value => 'Regular offer using the Price Book base price or with minor adjustments',
                                OfferType::Promotion->value => 'Promotional offer with discounts or special pricing (implies a discount benefit)',
                                OfferType::Bundle->value => 'Bundle offer for composite SKUs containing multiple products',
                            ])
                            ->default(OfferType::Standard->value)
                            ->columns(3),

                        // Promotion type hint when discount is applied
                        Forms\Components\Placeholder::make('promotion_hint')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $offerType = (string) $get('offer_type');
                                $benefitType = (string) $get('benefit_type');

                                $isPromotion = $offerType === OfferType::Promotion->value;
                                $hasDiscount = in_array($benefitType, [
                                    BenefitType::PercentageDiscount->value,
                                    BenefitType::FixedDiscount->value,
                                ], true);

                                // Show hint if promotion type is selected but no discount is applied
                                if ($isPromotion && ! $hasDiscount) {
                                    return true;
                                }

                                // Show hint if discount is applied but type is not promotion
                                if ($hasDiscount && ! $isPromotion) {
                                    return true;
                                }

                                return false;
                            })
                            ->content(function (Get $get): HtmlString {
                                $offerType = (string) $get('offer_type');
                                $isPromotion = $offerType === OfferType::Promotion->value;

                                if ($isPromotion) {
                                    return new HtmlString(
                                        '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-3 border border-info-200 dark:border-info-800">'
                                        .'<p class="text-sm text-info-700 dark:text-info-300">'
                                        .'<strong>Tip:</strong> Promotion offers typically include a discount. '
                                        .'Consider adding a percentage or fixed discount in the Pricing step.'
                                        .'</p>'
                                        .'</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-3 border border-info-200 dark:border-info-800">'
                                    .'<p class="text-sm text-info-700 dark:text-info-300">'
                                    .'<strong>Tip:</strong> You have a discount applied. '
                                    .'Consider changing the offer type to "Promotion" for better categorization.'
                                    .'</p>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Visibility Section
                Forms\Components\Section::make('Visibility')
                    ->description('Control who can see this offer')
                    ->schema([
                        Forms\Components\Radio::make('visibility')
                            ->label('Visibility Level')
                            ->required()
                            ->live()
                            ->options([
                                OfferVisibility::Public->value => OfferVisibility::Public->label(),
                                OfferVisibility::Restricted->value => OfferVisibility::Restricted->label(),
                            ])
                            ->descriptions([
                                OfferVisibility::Public->value => 'Visible to all eligible customers on the selected channel',
                                OfferVisibility::Restricted->value => 'Only visible to specific customers or through direct links',
                            ])
                            ->default(OfferVisibility::Public->value)
                            ->columns(2),

                        // Visibility explanation
                        Forms\Components\Placeholder::make('visibility_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $visibility = $get('visibility');

                                if ($visibility === OfferVisibility::Restricted->value) {
                                    return new HtmlString(
                                        '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-3 border border-warning-200 dark:border-warning-800">'
                                        .'<div class="flex items-start gap-2">'
                                        .'<svg class="w-5 h-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                                        .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>'
                                        .'</svg>'
                                        .'<div>'
                                        .'<p class="text-sm font-medium text-warning-800 dark:text-warning-200">Restricted Visibility</p>'
                                        .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
                                        .'This offer will not appear in public catalogs or listings. Customers will need a direct link or invitation to access this offer.'
                                        .'</p>'
                                        .'</div>'
                                        .'</div>'
                                        .'</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg bg-success-50 dark:bg-success-950 p-3 border border-success-200 dark:border-success-800">'
                                    .'<div class="flex items-start gap-2">'
                                    .'<svg class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                                    .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                                    .'</svg>'
                                    .'<div>'
                                    .'<p class="text-sm font-medium text-success-800 dark:text-success-200">Public Visibility</p>'
                                    .'<p class="text-sm text-success-700 dark:text-success-300 mt-1">'
                                    .'This offer will be visible in public catalogs and listings for all eligible customers on the selected channel.'
                                    .'</p>'
                                    .'</div>'
                                    .'</div>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Validity Period Section
                Forms\Components\Section::make('Validity Period')
                    ->description('Define when this offer is active')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->live()
                            ->native(false)
                            ->seconds(false)
                            ->default(now())
                            ->helperText('The date and time when this offer becomes active')
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->live()
                            ->seconds(false)
                            ->helperText('Leave empty for indefinite validity')
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if ($value === null) {
                                        return;
                                    }

                                    $validFrom = $get('valid_from');
                                    if ($validFrom === null) {
                                        return;
                                    }

                                    /** @var \DateTimeInterface|string $validFrom */
                                    $fromDate = \Carbon\Carbon::parse($validFrom);
                                    /** @var \DateTimeInterface|string $value */
                                    $toDate = \Carbon\Carbon::parse($value);

                                    if ($toDate->lte($fromDate)) {
                                        $fail('The Valid To date must be after the Valid From date.');
                                    }
                                },
                            ])
                            ->columnSpan(1),

                        // Validity period summary
                        Forms\Components\Placeholder::make('validity_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $validFrom = $get('valid_from');
                                $validTo = $get('valid_to');

                                if ($validFrom === null) {
                                    return new HtmlString('');
                                }

                                /** @var \DateTimeInterface|string $validFrom */
                                $fromDate = \Carbon\Carbon::parse($validFrom);
                                $now = now();

                                // Determine status
                                $startsInFuture = $fromDate->gt($now);

                                if ($validTo === null) {
                                    // Indefinite validity
                                    if ($startsInFuture) {
                                        $daysUntilStart = (int) $now->diffInDays($fromDate);

                                        return new HtmlString(
                                            '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-3 border border-info-200 dark:border-info-800">'
                                            .'<p class="text-sm text-info-700 dark:text-info-300">'
                                            .'<strong>Scheduled Start:</strong> This offer will become active in '.$daysUntilStart.' day(s) on '.$fromDate->format('M j, Y \a\t g:i A').'. '
                                            .'It will remain active indefinitely once started.'
                                            .'</p>'
                                            .'</div>'
                                        );
                                    }

                                    return new HtmlString(
                                        '<div class="rounded-lg bg-success-50 dark:bg-success-950 p-3 border border-success-200 dark:border-success-800">'
                                        .'<p class="text-sm text-success-700 dark:text-success-300">'
                                        .'<strong>Indefinite Validity:</strong> This offer will be active starting '.$fromDate->format('M j, Y \a\t g:i A').' with no expiration date.'
                                        .'</p>'
                                        .'</div>'
                                    );
                                }

                                /** @var \DateTimeInterface|string $validTo */
                                $toDate = \Carbon\Carbon::parse($validTo);

                                // Validation check
                                if ($toDate->lte($fromDate)) {
                                    return new HtmlString(
                                        '<div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-3 border border-danger-200 dark:border-danger-800">'
                                        .'<p class="text-sm text-danger-700 dark:text-danger-300">'
                                        .'<strong>Invalid Period:</strong> The Valid To date must be after the Valid From date.'
                                        .'</p>'
                                        .'</div>'
                                    );
                                }

                                $duration = $fromDate->diff($toDate);
                                $durationStr = $this->formatDuration($duration);

                                // Check if end date is soon (within 7 days)
                                $daysUntilEnd = (int) $now->diffInDays($toDate, false);
                                $urgencyClass = $daysUntilEnd <= 7 && $daysUntilEnd >= 0 ? 'warning' : 'primary';

                                if ($startsInFuture) {
                                    $daysUntilStart = (int) $now->diffInDays($fromDate);

                                    return new HtmlString(
                                        '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-3 border border-info-200 dark:border-info-800">'
                                        .'<p class="text-sm text-info-700 dark:text-info-300">'
                                        .'<strong>Scheduled Period:</strong> Starting in '.$daysUntilStart.' day(s) on '.$fromDate->format('M j, Y \a\t g:i A').', '
                                        .'ending on '.$toDate->format('M j, Y \a\t g:i A').'. Duration: '.$durationStr.'.'
                                        .'</p>'
                                        .'</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg bg-'.$urgencyClass.'-50 dark:bg-'.$urgencyClass.'-950 p-3 border border-'.$urgencyClass.'-200 dark:border-'.$urgencyClass.'-800">'
                                    .'<p class="text-sm text-'.$urgencyClass.'-700 dark:text-'.$urgencyClass.'-300">'
                                    .'<strong>Active Period:</strong> From '.$fromDate->format('M j, Y \a\t g:i A').' to '.$toDate->format('M j, Y \a\t g:i A').'. '
                                    .'Duration: '.$durationStr.'.'
                                    .($daysUntilEnd <= 7 && $daysUntilEnd >= 0 ? ' <strong>Expiring in '.$daysUntilEnd.' day(s)!</strong>' : '')
                                    .'</p>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Campaign Tag Section
                Forms\Components\Section::make('Campaign & Grouping')
                    ->description('Optional grouping for promotional campaigns')
                    ->schema([
                        Forms\Components\TextInput::make('campaign_tag')
                            ->label('Campaign Tag')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->placeholder('e.g., summer-2026, black-friday, vip-exclusive')
                            ->helperText('Use campaign tags to group related offers together for reporting and management'),

                        Forms\Components\Placeholder::make('campaign_info')
                            ->label('')
                            ->visible(fn (Get $get): bool => ! empty($get('campaign_tag')))
                            ->content(function (Get $get): HtmlString {
                                $tag = (string) $get('campaign_tag');

                                return new HtmlString(
                                    '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-3 border border-gray-200 dark:border-gray-700">'
                                    .'<p class="text-sm text-gray-600 dark:text-gray-400">'
                                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 mr-2">'.$tag.'</span>'
                                    .'This offer will be grouped with other offers using the same campaign tag.'
                                    .'</p>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Configuration Summary
                Forms\Components\Section::make('Configuration Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('step4_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $name = $get('name');
                                $offerType = $get('offer_type');
                                $visibility = $get('visibility');
                                $validFrom = $get('valid_from');
                                $validTo = $get('valid_to');
                                $campaignTag = $get('campaign_tag');

                                if (empty($name)) {
                                    return new HtmlString(
                                        '<div class="text-gray-500 dark:text-gray-400 text-sm">Complete the fields above to see a summary.</div>'
                                    );
                                }

                                $nameStr = (string) $name;
                                $offerTypeEnum = OfferType::tryFrom((string) $offerType);
                                $visibilityEnum = OfferVisibility::tryFrom((string) $visibility);

                                $typeColor = $offerTypeEnum !== null ? $offerTypeEnum->color() : 'gray';
                                $typeLabel = $offerTypeEnum !== null ? $offerTypeEnum->label() : (string) $offerType;

                                $visibilityColor = $visibilityEnum !== null ? $visibilityEnum->color() : 'gray';
                                $visibilityLabel = $visibilityEnum !== null ? $visibilityEnum->label() : (string) $visibility;
                                $visibilityIcon = $visibilityEnum === OfferVisibility::Public
                                    ? '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                    : '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>';

                                /** @var \DateTimeInterface|string|null $validFrom */
                                /** @var \DateTimeInterface|string|null $validTo */
                                $validFromStr = $validFrom !== null ? \Carbon\Carbon::parse($validFrom)->format('M j, Y g:i A') : '-';
                                $validToStr = $validTo !== null ? \Carbon\Carbon::parse($validTo)->format('M j, Y g:i A') : 'Indefinite';

                                $campaignTagStr = (string) $campaignTag;
                                $campaignTagHtml = ! empty($campaignTagStr)
                                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">'.$campaignTagStr.'</span>'
                                    : '<span class="text-gray-500">None</span>';

                                return new HtmlString(
                                    '<div class="rounded-lg bg-gradient-to-r from-primary-50 to-success-50 dark:from-primary-950 dark:to-success-950 p-4 border border-primary-200 dark:border-primary-800">'
                                    .'<div class="grid grid-cols-2 md:grid-cols-3 gap-4">'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Offer Name</p>'
                                    .'<p class="text-sm font-semibold text-gray-900 dark:text-gray-100">'.$nameStr.'</p>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</p>'
                                    .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$typeColor.'-100 text-'.$typeColor.'-800 dark:bg-'.$typeColor.'-900 dark:text-'.$typeColor.'-200">'.$typeLabel.'</span>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Visibility</p>'
                                    .'<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-'.$visibilityColor.'-100 text-'.$visibilityColor.'-800 dark:bg-'.$visibilityColor.'-900 dark:text-'.$visibilityColor.'-200">'.$visibilityIcon.' '.$visibilityLabel.'</span>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valid From</p>'
                                    .'<p class="text-sm text-gray-700 dark:text-gray-300">'.$validFromStr.'</p>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valid To</p>'
                                    .'<p class="text-sm text-gray-700 dark:text-gray-300">'.$validToStr.'</p>'
                                    .'</div>'
                                    .'<div>'
                                    .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Campaign</p>'
                                    .$campaignTagHtml
                                    .'</div>'
                                    .'</div>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Format a DateInterval as a human-readable duration string.
     */
    protected function formatDuration(\DateInterval $interval): string
    {
        $parts = [];

        if ($interval->y > 0) {
            $parts[] = $interval->y.' year'.($interval->y > 1 ? 's' : '');
        }
        if ($interval->m > 0) {
            $parts[] = $interval->m.' month'.($interval->m > 1 ? 's' : '');
        }
        if ($interval->d > 0) {
            $parts[] = $interval->d.' day'.($interval->d > 1 ? 's' : '');
        }
        if ($interval->h > 0 && count($parts) < 2) {
            $parts[] = $interval->h.' hour'.($interval->h > 1 ? 's' : '');
        }

        if (count($parts) === 0) {
            return 'less than 1 hour';
        }

        return implode(', ', array_slice($parts, 0, 2));
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

        // Store eligibility data in session for use in afterCreate
        // These fields are not part of the Offer model
        $eligibilityData = [
            'allowed_markets' => $data['allowed_markets'] ?? [],
            'allowed_customer_types' => $data['allowed_customer_types'] ?? [],
        ];
        session(['offer_eligibility_data' => $eligibilityData]);

        // Store benefit data in session for use in afterCreate
        // These fields are not part of the Offer model
        $benefitData = [
            'benefit_type' => $data['benefit_type'] ?? BenefitType::None->value,
            'benefit_value' => $data['benefit_value'] ?? null,
        ];
        session(['offer_benefit_data' => $benefitData]);

        // Get the first applicable allocation constraint ID for reference
        $skuId = $data['sellable_sku_id'] ?? null;
        if ($skuId !== null) {
            $sku = SellableSku::find($skuId);
            if ($sku !== null) {
                $allocations = $this->getActiveAllocationsForSku($sku);
                $firstAllocation = $allocations->first();
                if ($firstAllocation !== null) {
                    $constraint = $firstAllocation->constraint;
                    if ($constraint !== null) {
                        session(['offer_allocation_constraint_id' => $constraint->id]);
                    }
                }
            }
        }

        // Remove eligibility and benefit fields from offer data (they belong to separate models)
        unset($data['allowed_markets'], $data['allowed_customer_types']);
        unset($data['benefit_type'], $data['benefit_value']);

        return $data;
    }

    /**
     * Actions to perform after creating the record.
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Commercial\Offer $offer */
        $offer = $this->record;

        // Create OfferEligibility record
        $eligibilityData = session('offer_eligibility_data', []);
        $allocationConstraintId = session('offer_allocation_constraint_id');

        OfferEligibility::create([
            'offer_id' => $offer->id,
            'allowed_markets' => ! empty($eligibilityData['allowed_markets']) ? $eligibilityData['allowed_markets'] : null,
            'allowed_customer_types' => ! empty($eligibilityData['allowed_customer_types']) ? $eligibilityData['allowed_customer_types'] : null,
            'allowed_membership_tiers' => null,
            'allocation_constraint_id' => $allocationConstraintId,
        ]);

        // Create OfferBenefit record
        $benefitData = session('offer_benefit_data', []);
        $benefitType = $benefitData['benefit_type'] ?? BenefitType::None->value;
        $benefitValue = $benefitData['benefit_value'] ?? null;

        // Only store value if the benefit type requires it
        $benefitTypeEnum = BenefitType::tryFrom($benefitType);
        $storedValue = null;
        if ($benefitTypeEnum !== null && $benefitTypeEnum->requiresValue() && $benefitValue !== null) {
            $storedValue = (string) $benefitValue;
        }

        OfferBenefit::create([
            'offer_id' => $offer->id,
            'benefit_type' => $benefitType,
            'benefit_value' => $storedValue,
            'discount_rule_id' => null,
        ]);

        // Clean up session
        session()->forget(['offer_eligibility_data', 'offer_allocation_constraint_id', 'offer_benefit_data']);

        // If activated, log the activation
        if ($this->activateAfterCreate) {
            $offer->auditLogs()->create([
                'event' => 'status_change',
                'user_id' => auth()->id(),
                'old_values' => ['status' => OfferStatus::Draft->value],
                'new_values' => ['status' => OfferStatus::Active->value],
            ]);
        }
    }
}
