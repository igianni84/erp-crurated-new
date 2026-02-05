<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Enums\Commercial\PriceBookStatus;
use App\Filament\Resources\OfferResource;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\OfferBenefit;
use App\Models\Commercial\OfferEligibility;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\SellableSku;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Bulk Create Offers Page
 *
 * A multi-step wizard for creating multiple offers at once.
 * Allows selecting multiple SKUs and applying shared configuration
 * (channel, eligibility, benefit, validity) to all of them.
 *
 * Entry points:
 * - Offer List header action
 * - Allocation detail action
 * - Price Book detail action
 *
 * @property Form $form
 */
class BulkCreateOffers extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OfferResource::class;

    protected static string $view = 'filament.resources.offer-resource.pages.bulk-create-offers';

    protected static ?string $title = 'Bulk Create Offers';

    protected static ?string $navigationLabel = 'Bulk Create';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Form data storage.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Track creation progress.
     */
    public bool $isCreating = false;

    public int $createdCount = 0;

    public int $failedCount = 0;

    public int $totalToCreate = 0;

    /**
     * @var array<string, string>
     */
    public array $creationErrors = [];

    /**
     * Mount the page.
     */
    public function mount(): void
    {
        $this->fillForm();
    }

    /**
     * Fill the form with initial data.
     */
    protected function fillForm(): void
    {
        // Initialize form with empty data
    }

    /**
     * Get the forms used by this page.
     *
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Wizard::make([
                        $this->getSkuSelectionStep(),
                        $this->getChannelAndEligibilityStep(),
                        $this->getPricingStep(),
                        $this->getValidityAndVisibilityStep(),
                        $this->getReviewStep(),
                    ])
                        ->submitAction(new HtmlString(
                            '<button type="submit" wire:loading.attr="disabled" wire:target="create" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-success fi-color-success fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50" style="--c-400:var(--success-400);--c-500:var(--success-500);--c-600:var(--success-600);">
                                <span wire:loading.remove wire:target="create">Create Offers</span>
                                <span wire:loading wire:target="create">Creating...</span>
                            </button>'
                        ))
                        ->contained(false),
                ])
                ->statePath('data'),
        ];
    }

    /**
     * Step 1: SKU Selection
     * Select multiple Sellable SKUs for bulk offer creation.
     */
    protected function getSkuSelectionStep(): Wizard\Step
    {
        return Wizard\Step::make('Select SKUs')
            ->description('Choose the Sellable SKUs to create offers for')
            ->icon('heroicon-o-cube')
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('bulk_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-4 border border-primary-200 dark:border-primary-800">'
                                .'<div class="flex items-start gap-3">'
                                .'<div class="flex-shrink-0 text-primary-600 dark:text-primary-400">'
                                .'<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<p class="font-semibold text-primary-800 dark:text-primary-200">Bulk Offer Creation</p>'
                                .'<p class="text-sm text-primary-700 dark:text-primary-300 mt-1">'
                                .'Select multiple Sellable SKUs to create offers for. Each SKU will generate one independent offer '
                                .'with shared configuration (channel, pricing, validity) but unique per-SKU pricing based on the Price Book.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Select Sellable SKUs')
                    ->description('Choose the products you want to create offers for')
                    ->schema([
                        Forms\Components\Select::make('sellable_sku_ids')
                            ->label('Sellable SKUs')
                            ->required()
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->placeholder('Search and select SKUs...')
                            ->options(function (): array {
                                return SellableSku::query()
                                    ->where('lifecycle_status', SellableSku::STATUS_ACTIVE)
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->get()
                                    ->filter(fn (SellableSku $sku): bool => $this->skuHasActiveAllocation($sku))
                                    ->mapWithKeys(fn (SellableSku $sku): array => [
                                        $sku->id => $this->buildSkuLabel($sku),
                                    ])
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
                                    ->filter(fn (SellableSku $sku): bool => $this->skuHasActiveAllocation($sku))
                                    ->mapWithKeys(fn (SellableSku $sku): array => [
                                        $sku->id => $this->buildSkuLabel($sku),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Only SKUs with active allocations are shown. Select at least one SKU.')
                            ->columnSpanFull(),

                        // Selected SKUs Preview
                        Forms\Components\Placeholder::make('selected_skus_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => ! empty($get('sellable_sku_ids')))
                            ->content(function (Get $get): HtmlString {
                                $skuIds = $get('sellable_sku_ids') ?? [];
                                if (empty($skuIds)) {
                                    return new HtmlString('');
                                }

                                $skus = SellableSku::whereIn('id', $skuIds)
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->get();

                                return new HtmlString($this->buildSelectedSkusPreviewHtml($skus));
                            })
                            ->columnSpanFull(),
                    ]),

                // Allocation Validation Warning
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('allocation_validation_note')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-3 border border-info-200 dark:border-info-800">'
                                .'<p class="text-sm text-info-700 dark:text-info-300">'
                                .'<strong>Note:</strong> All selected SKUs must have an active allocation for the chosen channel. '
                                .'SKUs without a matching allocation will be flagged in the next step.'
                                .'</p>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => empty($get('sellable_sku_ids'))),
            ]);
    }

    /**
     * Step 2: Channel & Eligibility
     * Define shared channel and eligibility settings for all offers.
     */
    protected function getChannelAndEligibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Channel & Eligibility')
            ->description('Define shared channel and eligibility settings')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                // Validation Warning
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
                                .'<p class="font-semibold text-warning-800 dark:text-warning-200">Allocation Constraint Validation</p>'
                                .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
                                .'All selected SKUs must have an allocation that permits the chosen channel. '
                                .'SKUs without matching allocations will be excluded from offer creation.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Channel Selection
                Forms\Components\Section::make('Target Channel')
                    ->description('Select the channel for all offers')
                    ->schema([
                        Forms\Components\Select::make('channel_id')
                            ->label('Channel')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->options(function (): array {
                                return Channel::query()
                                    ->where('status', ChannelStatus::Active)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->helperText('This channel will be used for all created offers'),

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

                                return new HtmlString($this->buildChannelPreviewHtml($channel));
                            })
                            ->columnSpanFull(),

                        // SKU Allocation Validation Preview
                        Forms\Components\Placeholder::make('sku_allocation_validation')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('channel_id') !== null && ! empty($get('sellable_sku_ids')))
                            ->content(function (Get $get): HtmlString {
                                $channelId = $get('channel_id');
                                $skuIds = $get('sellable_sku_ids') ?? [];

                                if ($channelId === null || empty($skuIds)) {
                                    return new HtmlString('');
                                }

                                $channel = Channel::find($channelId);
                                if ($channel === null) {
                                    return new HtmlString('');
                                }

                                return new HtmlString($this->buildSkuAllocationValidationHtml($skuIds, $channel));
                            })
                            ->columnSpanFull(),
                    ]),

                // Eligibility Settings
                Forms\Components\Section::make('Shared Eligibility')
                    ->description('Define eligibility restrictions for all offers')
                    ->schema([
                        Forms\Components\Select::make('allowed_markets')
                            ->label('Allowed Markets')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options([
                                'IT' => 'Italy',
                                'UK' => 'United Kingdom',
                                'US' => 'United States',
                                'DE' => 'Germany',
                                'FR' => 'France',
                                'CH' => 'Switzerland',
                                'HK' => 'Hong Kong',
                                'SG' => 'Singapore',
                                'JP' => 'Japan',
                                'EU' => 'European Union',
                                'APAC' => 'Asia Pacific',
                                'ROW' => 'Rest of World',
                            ])
                            ->helperText('Leave empty to allow all markets within allocation constraints'),

                        Forms\Components\Select::make('allowed_customer_types')
                            ->label('Allowed Customer Types')
                            ->multiple()
                            ->searchable()
                            ->options([
                                'retail' => 'Retail Customer',
                                'trade' => 'Trade Customer',
                                'member' => 'Member',
                                'vip' => 'VIP',
                                'wholesale' => 'Wholesale',
                            ])
                            ->helperText('Leave empty to allow all customer types within allocation constraints'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Step 3: Pricing
     * Define shared pricing configuration.
     */
    protected function getPricingStep(): Wizard\Step
    {
        return Wizard\Step::make('Pricing')
            ->description('Define shared pricing settings')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                // Price Book Selection
                Forms\Components\Section::make('Price Book')
                    ->description('Select the Price Book that provides base prices')
                    ->schema([
                        Forms\Components\Select::make('price_book_id')
                            ->label('Price Book')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->options(function (): array {
                                return PriceBook::query()
                                    ->where('status', PriceBookStatus::Active)
                                    ->where('valid_from', '<=', now())
                                    ->where(function (Builder $query): void {
                                        $query->whereNull('valid_to')
                                            ->orWhere('valid_to', '>=', now());
                                    })
                                    ->pluck('name', 'id')
                                    ->toArray();
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

                                $priceBook = PriceBook::find($priceBookId);
                                if ($priceBook === null) {
                                    return new HtmlString('<div class="text-gray-500">Price Book not found</div>');
                                }

                                return new HtmlString($this->buildPriceBookPreviewHtml($priceBook));
                            })
                            ->columnSpanFull(),

                        // Price Coverage Preview
                        Forms\Components\Placeholder::make('price_coverage_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('price_book_id') !== null && ! empty($get('sellable_sku_ids')))
                            ->content(function (Get $get): HtmlString {
                                $priceBookId = $get('price_book_id');
                                $skuIds = $get('sellable_sku_ids') ?? [];

                                if ($priceBookId === null || empty($skuIds)) {
                                    return new HtmlString('');
                                }

                                return new HtmlString($this->buildPriceCoveragePreviewHtml($priceBookId, $skuIds));
                            })
                            ->columnSpanFull(),
                    ]),

                // Benefit Configuration
                Forms\Components\Section::make('Shared Benefit')
                    ->description('Apply the same benefit to all offers')
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
                                BenefitType::FixedDiscount->value, BenefitType::FixedPrice->value => "\u{20AC}",
                                default => '',
                            }),
                    ]),
            ]);
    }

    /**
     * Step 4: Validity & Visibility
     * Define shared validity and visibility settings.
     */
    protected function getValidityAndVisibilityStep(): Wizard\Step
    {
        return Wizard\Step::make('Validity & Visibility')
            ->description('Define shared validity period and visibility')
            ->icon('heroicon-o-eye')
            ->schema([
                // Offer Settings
                Forms\Components\Section::make('Offer Settings')
                    ->description('Shared settings for all offers')
                    ->schema([
                        Forms\Components\TextInput::make('name_prefix')
                            ->label('Offer Name Prefix')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('e.g., B2C IT Promo -')
                            ->helperText('Each offer name will be: "[Prefix] [Wine Name] [Vintage]"')
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('offer_type')
                            ->label('Offer Type')
                            ->required()
                            ->options([
                                OfferType::Standard->value => OfferType::Standard->label(),
                                OfferType::Promotion->value => OfferType::Promotion->label(),
                                OfferType::Bundle->value => OfferType::Bundle->label(),
                            ])
                            ->descriptions([
                                OfferType::Standard->value => 'Regular offer using Price Book price',
                                OfferType::Promotion->value => 'Promotional offer with discounts',
                                OfferType::Bundle->value => 'Bundle offer for composite SKUs',
                            ])
                            ->default(OfferType::Standard->value)
                            ->columns(3),

                        Forms\Components\Radio::make('visibility')
                            ->label('Visibility')
                            ->required()
                            ->options([
                                OfferVisibility::Public->value => OfferVisibility::Public->label(),
                                OfferVisibility::Restricted->value => OfferVisibility::Restricted->label(),
                            ])
                            ->descriptions([
                                OfferVisibility::Public->value => 'Visible to all eligible customers',
                                OfferVisibility::Restricted->value => 'Only visible to specific customer groups',
                            ])
                            ->default(OfferVisibility::Public->value)
                            ->columns(2),
                    ]),

                // Validity Period
                Forms\Components\Section::make('Validity Period')
                    ->description('When will these offers be active?')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->seconds(false)
                            ->after('valid_from')
                            ->helperText('Leave empty for indefinite validity'),
                    ])
                    ->columns(2),

                // Campaign Tag
                Forms\Components\Section::make('Campaign & Grouping')
                    ->description('Optional campaign tag for grouping offers')
                    ->schema([
                        Forms\Components\TextInput::make('campaign_tag')
                            ->label('Campaign Tag')
                            ->maxLength(255)
                            ->placeholder('e.g., summer-2026, black-friday')
                            ->helperText('Use the same tag to group related offers for reporting'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Step 5: Review
     * Review all settings before creating offers.
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review & Create')
            ->description('Review and create offers')
            ->icon('heroicon-o-check-circle')
            ->schema([
                // Summary Header
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('summary_header')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-gradient-to-r from-success-50 to-success-100 dark:from-success-950 dark:to-success-900 p-6 border border-success-200 dark:border-success-800">'
                                .'<div class="flex items-center gap-4">'
                                .'<div class="flex-shrink-0 bg-success-500 dark:bg-success-600 rounded-full p-3">'
                                .'<svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<h2 class="text-xl font-bold text-success-800 dark:text-success-200">Ready to Create Offers</h2>'
                                .'<p class="text-success-700 dark:text-success-300 mt-1">Review the summary below and click "Create Offers" to proceed.</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Offers to Create Summary
                Forms\Components\Section::make('Offers to Create')
                    ->description('Summary of offers that will be created')
                    ->schema([
                        Forms\Components\Placeholder::make('offers_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                return new HtmlString($this->buildOffersSummaryHtml($get));
                            })
                            ->columnSpanFull(),
                    ]),

                // Configuration Summary
                Forms\Components\Section::make('Configuration Summary')
                    ->description('Shared settings for all offers')
                    ->schema([
                        Forms\Components\Placeholder::make('config_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                return new HtmlString($this->buildConfigSummaryHtml($get));
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Status Info
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('status_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-4 border border-info-200 dark:border-info-800">'
                                .'<p class="text-sm text-info-700 dark:text-info-300">'
                                .'<strong>Note:</strong> All offers will be created in <strong>Draft</strong> status. '
                                .'You can activate them individually or in bulk from the Offers list.'
                                .'</p>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Create the offers.
     */
    public function create(): void
    {
        $data = $this->form->getState();

        $skuIds = $data['sellable_sku_ids'] ?? [];
        $channelId = $data['channel_id'] ?? null;
        $priceBookId = $data['price_book_id'] ?? null;

        if (empty($skuIds) || $channelId === null || $priceBookId === null) {
            Notification::make()
                ->title('Missing required data')
                ->body('Please complete all required fields.')
                ->danger()
                ->send();

            return;
        }

        $this->isCreating = true;
        $this->createdCount = 0;
        $this->failedCount = 0;
        $this->creationErrors = [];

        // Get channel for validation
        $channel = Channel::find($channelId);
        if ($channel === null) {
            Notification::make()
                ->title('Channel not found')
                ->danger()
                ->send();
            $this->isCreating = false;

            return;
        }

        // Filter SKUs that have valid allocations for this channel
        $validSkuIds = $this->getValidSkuIdsForChannel($skuIds, $channel);
        $this->totalToCreate = count($validSkuIds);

        if (empty($validSkuIds)) {
            Notification::make()
                ->title('No valid SKUs')
                ->body('None of the selected SKUs have allocations for the chosen channel.')
                ->warning()
                ->send();
            $this->isCreating = false;

            return;
        }

        // Create offers in a transaction
        DB::beginTransaction();

        try {
            foreach ($validSkuIds as $skuId) {
                try {
                    $this->createOfferForSku($skuId, $data);
                    $this->createdCount++;
                } catch (\Exception $e) {
                    $this->failedCount++;
                    $this->creationErrors[$skuId] = $e->getMessage();
                }
            }

            DB::commit();

            // Show result notification
            if ($this->failedCount === 0) {
                Notification::make()
                    ->title('Offers created successfully')
                    ->body("{$this->createdCount} offer(s) created.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Offers created with errors')
                    ->body("{$this->createdCount} created, {$this->failedCount} failed.")
                    ->warning()
                    ->send();
            }

            // Redirect to offers list
            $this->redirect(OfferResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Error creating offers')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->isCreating = false;
        }
    }

    /**
     * Create a single offer for a SKU.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createOfferForSku(string $skuId, array $data): void
    {
        $sku = SellableSku::with(['wineVariant.wineMaster'])->find($skuId);
        if ($sku === null) {
            throw new \RuntimeException('SKU not found');
        }

        // Build offer name
        $wineName = 'Unknown';
        $vintage = '';
        if ($sku->wineVariant !== null) {
            $wineMaster = $sku->wineVariant->wineMaster;
            if ($wineMaster !== null) {
                $wineName = $wineMaster->name;
            }
            $vintage = $sku->wineVariant->vintage_year ?? '';
        }

        $namePrefix = $data['name_prefix'] ?? 'Offer';
        $offerName = trim("{$namePrefix} {$wineName} {$vintage}");

        // Create the offer
        $offer = Offer::create([
            'name' => $offerName,
            'sellable_sku_id' => $skuId,
            'channel_id' => $data['channel_id'],
            'price_book_id' => $data['price_book_id'],
            'offer_type' => $data['offer_type'] ?? OfferType::Standard->value,
            'visibility' => $data['visibility'] ?? OfferVisibility::Public->value,
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'status' => OfferStatus::Draft->value,
            'campaign_tag' => $data['campaign_tag'] ?? null,
        ]);

        // Get allocation constraint ID
        $allocationConstraintId = $this->getFirstAllocationConstraintIdForSku($sku);

        // Create OfferEligibility
        OfferEligibility::create([
            'offer_id' => $offer->id,
            'allowed_markets' => ! empty($data['allowed_markets']) ? $data['allowed_markets'] : null,
            'allowed_customer_types' => ! empty($data['allowed_customer_types']) ? $data['allowed_customer_types'] : null,
            'allowed_membership_tiers' => null,
            'allocation_constraint_id' => $allocationConstraintId,
        ]);

        // Create OfferBenefit
        $benefitType = $data['benefit_type'] ?? BenefitType::None->value;
        $benefitValue = $data['benefit_value'] ?? null;

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
    }

    /**
     * Get the first allocation constraint ID for a SKU.
     */
    protected function getFirstAllocationConstraintIdForSku(SellableSku $sku): ?string
    {
        $allocation = Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('status', AllocationStatus::Active)
            ->with('constraint')
            ->first();

        if ($allocation === null) {
            return null;
        }

        $constraint = $allocation->constraint;

        return $constraint !== null ? $constraint->id : null;
    }

    /**
     * Get valid SKU IDs for a channel.
     *
     * @param  array<string>  $skuIds
     * @return array<string>
     */
    protected function getValidSkuIdsForChannel(array $skuIds, Channel $channel): array
    {
        $validIds = [];

        foreach ($skuIds as $skuId) {
            $sku = SellableSku::find($skuId);
            if ($sku === null) {
                continue;
            }

            if ($this->skuHasAllocationForChannel($sku, $channel)) {
                $validIds[] = $skuId;
            }
        }

        return $validIds;
    }

    /**
     * Check if a SKU has allocation for a specific channel.
     */
    protected function skuHasAllocationForChannel(SellableSku $sku, Channel $channel): bool
    {
        $allocations = Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('status', AllocationStatus::Active)
            ->with('constraint')
            ->get();

        foreach ($allocations as $allocation) {
            $constraint = $allocation->constraint;
            if ($constraint !== null) {
                $allowedChannels = $constraint->getEffectiveChannels();
                if (in_array($channel->channel_type->value, $allowedChannels, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if a Sellable SKU has at least one active allocation.
     */
    protected function skuHasActiveAllocation(SellableSku $sku): bool
    {
        return Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('status', AllocationStatus::Active)
            ->exists();
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
     * Build HTML preview for selected SKUs.
     *
     * @param  Collection<int, SellableSku>  $skus
     */
    protected function buildSelectedSkusPreviewHtml(Collection $skus): string
    {
        $count = $skus->count();

        $rows = '';
        foreach ($skus->take(10) as $sku) {
            $wineVariant = $sku->wineVariant;
            $wineMaster = $wineVariant !== null ? $wineVariant->wineMaster : null;
            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
            $vintage = $wineVariant !== null ? ($wineVariant->vintage_year ?? 'NV') : 'NV';
            $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '-';

            $rows .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                .'<td class="py-2 text-sm font-mono text-gray-600 dark:text-gray-400">'.$sku->sku_code.'</td>'
                .'<td class="py-2 text-sm text-gray-900 dark:text-gray-100">'.$wineName.'</td>'
                .'<td class="py-2 text-sm text-gray-600 dark:text-gray-400">'.$vintage.'</td>'
                .'<td class="py-2 text-sm text-gray-600 dark:text-gray-400">'.$format.'</td>'
                .'</tr>';
        }

        $moreText = '';
        if ($count > 10) {
            $moreText = '<p class="text-sm text-gray-500 mt-2">+'.($count - 10).' more SKUs selected</p>';
        }

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="flex items-center justify-between mb-3">'
            .'<p class="text-sm font-medium text-gray-700 dark:text-gray-300">Selected SKUs</p>'
            .'<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">'.$count.' selected</span>'
            .'</div>'
            .'<table class="w-full">'
            .'<thead>'
            .'<tr class="text-xs text-gray-500 dark:text-gray-400 uppercase">'
            .'<th class="text-left py-1">SKU</th>'
            .'<th class="text-left py-1">Wine</th>'
            .'<th class="text-left py-1">Vintage</th>'
            .'<th class="text-left py-1">Format</th>'
            .'</tr>'
            .'</thead>'
            .'<tbody>'
            .$rows
            .'</tbody>'
            .'</table>'
            .$moreText
            .'</div>';
    }

    /**
     * Build HTML preview for a Channel.
     */
    protected function buildChannelPreviewHtml(Channel $channel): string
    {
        $typeColor = $channel->getChannelTypeColor();
        $statusColor = $channel->getStatusColor();

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
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
            .'</div>';
    }

    /**
     * Build HTML for SKU allocation validation.
     *
     * @param  array<string>  $skuIds
     */
    protected function buildSkuAllocationValidationHtml(array $skuIds, Channel $channel): string
    {
        $validCount = 0;
        $invalidSkus = [];

        foreach ($skuIds as $skuId) {
            $sku = SellableSku::find($skuId);
            if ($sku === null) {
                continue;
            }

            if ($this->skuHasAllocationForChannel($sku, $channel)) {
                $validCount++;
            } else {
                $invalidSkus[] = $sku->sku_code;
            }
        }

        $totalCount = count($skuIds);
        $invalidCount = count($invalidSkus);

        if ($invalidCount === 0) {
            return '<div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 border border-success-200 dark:border-success-800">'
                .'<div class="flex items-center gap-2">'
                .'<svg class="w-5 h-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                .'<p class="text-sm text-success-700 dark:text-success-300">'
                .'<strong>All '.$validCount.' SKU(s)</strong> have valid allocations for this channel.'
                .'</p>'
                .'</div>'
                .'</div>';
        }

        $invalidList = implode(', ', array_slice($invalidSkus, 0, 5));
        if ($invalidCount > 5) {
            $invalidList .= ', +'.($invalidCount - 5).' more';
        }

        return '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-300 dark:border-warning-700">'
            .'<div class="flex items-start gap-2">'
            .'<svg class="w-5 h-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            .'<div>'
            .'<p class="text-sm font-medium text-warning-800 dark:text-warning-200">'
            .$validCount.' of '.$totalCount.' SKU(s) have valid allocations'
            .'</p>'
            .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
            .'The following SKUs will be <strong>excluded</strong> from offer creation: '.$invalidList
            .'</p>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * Build HTML preview for a Price Book.
     */
    protected function buildPriceBookPreviewHtml(PriceBook $priceBook): string
    {
        $statusColor = $priceBook->getStatusColor();
        $channelName = $priceBook->channel !== null ? $priceBook->channel->name : 'All Channels';
        $entriesCount = $priceBook->entries()->count();

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
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Entries</p>'
            .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$entriesCount.'</p>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * Build HTML for price coverage preview.
     *
     * @param  array<string>  $skuIds
     */
    protected function buildPriceCoveragePreviewHtml(string $priceBookId, array $skuIds): string
    {
        $coveredCount = PriceBookEntry::where('price_book_id', $priceBookId)
            ->whereIn('sellable_sku_id', $skuIds)
            ->count();

        $totalCount = count($skuIds);
        $missingCount = $totalCount - $coveredCount;

        if ($missingCount === 0) {
            return '<div class="rounded-lg bg-success-50 dark:bg-success-950 p-4 border border-success-200 dark:border-success-800">'
                .'<div class="flex items-center gap-2">'
                .'<svg class="w-5 h-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                .'<p class="text-sm text-success-700 dark:text-success-300">'
                .'<strong>All '.$coveredCount.' SKU(s)</strong> have price entries in this Price Book.'
                .'</p>'
                .'</div>'
                .'</div>';
        }

        return '<div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-300 dark:border-warning-700">'
            .'<div class="flex items-start gap-2">'
            .'<svg class="w-5 h-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
            .'<div>'
            .'<p class="text-sm font-medium text-warning-800 dark:text-warning-200">'
            .$coveredCount.' of '.$totalCount.' SKU(s) have price entries'
            .'</p>'
            .'<p class="text-sm text-warning-700 dark:text-warning-300 mt-1">'
            .$missingCount.' SKU(s) are missing prices in this Price Book. '
            .'Offers can still be created but will have no base price until entries are added.'
            .'</p>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * Build HTML for offers summary.
     */
    protected function buildOffersSummaryHtml(Get $get): string
    {
        $skuIds = $get('sellable_sku_ids') ?? [];
        $channelId = $get('channel_id');
        $priceBookId = $get('price_book_id');

        if (empty($skuIds) || $channelId === null || $priceBookId === null) {
            return '<div class="text-gray-500">Please complete all previous steps to see the summary.</div>';
        }

        $channel = Channel::find($channelId);
        if ($channel === null) {
            return '<div class="text-gray-500">Channel not found.</div>';
        }

        $validSkuIds = $this->getValidSkuIdsForChannel($skuIds, $channel);
        $validCount = count($validSkuIds);
        $excludedCount = count($skuIds) - $validCount;

        // Build table of offers to be created
        $rows = '';
        $skus = SellableSku::whereIn('id', $validSkuIds)
            ->with(['wineVariant.wineMaster', 'format'])
            ->get();

        $namePrefix = $get('name_prefix') ?? 'Offer';

        foreach ($skus->take(10) as $sku) {
            $wineVariant = $sku->wineVariant;
            $wineMaster = $wineVariant !== null ? $wineVariant->wineMaster : null;
            $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
            $vintage = $wineVariant !== null ? ($wineVariant->vintage_year ?? '') : '';
            $offerName = trim("{$namePrefix} {$wineName} {$vintage}");

            // Check price entry
            $priceEntry = PriceBookEntry::where('price_book_id', $priceBookId)
                ->where('sellable_sku_id', $sku->id)
                ->first();

            $priceDisplay = $priceEntry !== null
                ? "\u{20AC} ".number_format((float) $priceEntry->base_price, 2)
                : '<span class="text-warning-600">No price</span>';

            $rows .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                .'<td class="py-2 text-sm text-gray-900 dark:text-gray-100">'.htmlspecialchars($offerName).'</td>'
                .'<td class="py-2 text-sm font-mono text-gray-600 dark:text-gray-400">'.$sku->sku_code.'</td>'
                .'<td class="py-2 text-sm text-gray-600 dark:text-gray-400">'.$priceDisplay.'</td>'
                .'</tr>';
        }

        $moreText = '';
        if ($validCount > 10) {
            $moreText = '<p class="text-sm text-gray-500 mt-2">+'.($validCount - 10).' more offers will be created</p>';
        }

        $excludedWarning = '';
        if ($excludedCount > 0) {
            $excludedWarning = '<div class="mt-4 p-3 rounded bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800">'
                .'<p class="text-sm text-warning-700 dark:text-warning-300">'
                .'<strong>'.$excludedCount.' SKU(s) excluded</strong> due to missing allocation for the selected channel.'
                .'</p>'
                .'</div>';
        }

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="flex items-center justify-between mb-4">'
            .'<p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Offers to be Created</p>'
            .'<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">'.$validCount.' offers</span>'
            .'</div>'
            .'<table class="w-full">'
            .'<thead>'
            .'<tr class="text-xs text-gray-500 dark:text-gray-400 uppercase">'
            .'<th class="text-left py-2">Offer Name</th>'
            .'<th class="text-left py-2">SKU</th>'
            .'<th class="text-left py-2">Base Price</th>'
            .'</tr>'
            .'</thead>'
            .'<tbody>'
            .$rows
            .'</tbody>'
            .'</table>'
            .$moreText
            .$excludedWarning
            .'</div>';
    }

    /**
     * Build HTML for configuration summary.
     */
    protected function buildConfigSummaryHtml(Get $get): string
    {
        $channel = null;
        $channelId = $get('channel_id');
        if ($channelId !== null) {
            $channel = Channel::find($channelId);
        }

        $priceBook = null;
        $priceBookId = $get('price_book_id');
        if ($priceBookId !== null) {
            $priceBook = PriceBook::find($priceBookId);
        }

        $benefitType = $get('benefit_type') ?? BenefitType::None->value;
        $benefitValue = $get('benefit_value');
        $benefitTypeEnum = BenefitType::tryFrom($benefitType);

        $benefitDisplay = $benefitTypeEnum !== null ? $benefitTypeEnum->label() : 'None';
        if ($benefitTypeEnum !== null && $benefitTypeEnum->requiresValue() && $benefitValue !== null) {
            if ($benefitTypeEnum === BenefitType::PercentageDiscount) {
                $benefitDisplay .= " ({$benefitValue}%)";
            } else {
                $benefitDisplay .= " (\u{20AC}{$benefitValue})";
            }
        }

        $offerType = $get('offer_type') ?? OfferType::Standard->value;
        $offerTypeEnum = OfferType::tryFrom($offerType);
        $offerTypeDisplay = $offerTypeEnum !== null ? $offerTypeEnum->label() : 'Standard';

        $visibility = $get('visibility') ?? OfferVisibility::Public->value;
        $visibilityEnum = OfferVisibility::tryFrom($visibility);
        $visibilityDisplay = $visibilityEnum !== null ? $visibilityEnum->label() : 'Public';

        $validFrom = $get('valid_from');
        $validTo = $get('valid_to');

        $validFromDisplay = $validFrom !== null ? date('Y-m-d H:i', strtotime((string) $validFrom)) : '-';
        $validToDisplay = $validTo !== null ? date('Y-m-d H:i', strtotime((string) $validTo)) : 'Indefinite';

        $campaignTag = $get('campaign_tag');
        $campaignDisplay = ! empty($campaignTag) ? $campaignTag : '-';

        return '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Channel</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.($channel !== null ? $channel->name : '-').'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Price Book</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.($priceBook !== null ? $priceBook->name : '-').'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Benefit</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.$benefitDisplay.'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Offer Type</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.$offerTypeDisplay.'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Visibility</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.$visibilityDisplay.'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Campaign</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.$campaignDisplay.'</p>'
            .'</div>'
            .'<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded col-span-2 md:col-span-3">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Validity Period</p>'
            .'<p class="font-medium text-gray-900 dark:text-gray-100">'.$validFromDisplay.' → '.$validToDisplay.'</p>'
            .'</div>'
            .'</div>';
    }
}
