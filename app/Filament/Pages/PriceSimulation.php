<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\Commercial\AllocationCheckResult;
use App\DataTransferObjects\Commercial\EmpReferenceResult;
use App\DataTransferObjects\Commercial\FinalPriceResult;
use App\DataTransferObjects\Commercial\OfferResolutionResult;
use App\DataTransferObjects\Commercial\PriceBookResolutionResult;
use App\DataTransferObjects\Commercial\SimulationContext;
use App\DataTransferObjects\Commercial\SimulationResult;
use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\PriceBookStatus;
use App\Models\Allocation\Allocation;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Price Simulation Page
 *
 * Allows operators to simulate end-to-end price resolution for debugging.
 * Inputs: sellable_sku_id, customer_id (optional), channel_id, date, quantity.
 */
class PriceSimulation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Price Simulation';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Price Simulation';

    protected static string $view = 'filament.pages.price-simulation';

    /**
     * Form data storage.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Whether simulation has been run.
     */
    public bool $hasSimulated = false;

    /**
     * Simulation result data.
     *
     * @var array<string, mixed>|null
     */
    public ?array $simulationResult = null;

    /**
     * The typed simulation result object.
     */
    protected ?SimulationResult $typedResult = null;

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
        $this->data = [
            'date' => now()->format('Y-m-d'),
            'quantity' => 1,
        ];
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Simulation Parameters')
                    ->description('Configure the pricing simulation context')
                    ->schema([
                        // Info Box
                        Forms\Components\Placeholder::make('simulation_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 dark:bg-info-950 p-4 border border-info-200 dark:border-info-800">'
                                .'<div class="flex items-start gap-3">'
                                .'<div class="flex-shrink-0 text-info-600 dark:text-info-400">'
                                .'<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                                .'</div>'
                                .'<div>'
                                .'<p class="font-semibold text-info-800 dark:text-info-200">End-to-End Price Resolution</p>'
                                .'<p class="text-sm text-info-700 dark:text-info-300 mt-1">'
                                .'Simulate the complete price resolution process: from allocation check, through EMP reference, '
                                .'Price Book resolution, to Offer application. Use this tool to debug pricing issues and understand '
                                .'how the final price is computed for a specific context.'
                                .'</p>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                            ))
                            ->columnSpanFull(),

                        // Sellable SKU
                        Forms\Components\Select::make('sellable_sku_id')
                            ->label('Sellable SKU')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->placeholder('Search and select a SKU...')
                            ->options(function (): array {
                                return SellableSku::query()
                                    ->where('lifecycle_status', SellableSku::STATUS_ACTIVE)
                                    ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->limit(100)
                                    ->get()
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
                                    ->mapWithKeys(fn (SellableSku $sku): array => [
                                        $sku->id => $this->buildSkuLabel($sku),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Select the Sellable SKU to simulate pricing for')
                            ->columnSpan(2),

                        // SKU Preview
                        Forms\Components\Placeholder::make('sku_preview')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('sellable_sku_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $skuId = $get('sellable_sku_id');
                                if ($skuId === null) {
                                    return new HtmlString('');
                                }

                                $sku = SellableSku::with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
                                    ->find($skuId);
                                if ($sku === null) {
                                    return new HtmlString('<div class="text-gray-500">SKU not found</div>');
                                }

                                return new HtmlString($this->buildSkuPreviewHtml($sku));
                            })
                            ->columnSpanFull(),

                        // Channel
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
                            ->helperText('The sales channel context for the simulation'),

                        // Customer (Optional)
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer (Optional)')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->options(function (): array {
                                return Customer::query()
                                    ->where('status', Customer::STATUS_ACTIVE)
                                    ->limit(100)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->getSearchResultsUsing(function (string $search): array {
                                return Customer::query()
                                    ->where('status', Customer::STATUS_ACTIVE)
                                    ->where(function (Builder $query) use ($search): void {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->helperText('Optional: specify a customer to check eligibility'),

                        // Date
                        Forms\Components\DatePicker::make('date')
                            ->label('Simulation Date')
                            ->required()
                            ->native(false)
                            ->default(now())
                            ->helperText('The date for validity checks'),

                        // Quantity
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(1)
                            ->helperText('Number of units for volume-based pricing'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * Run the price simulation.
     */
    public function simulate(): void
    {
        $this->hasSimulated = true;

        // For now, build a placeholder result structure
        // This will be connected to SimulationService in US-056
        $this->simulationResult = $this->buildSimulationResult($this->data);
    }

    /**
     * Reset the simulation.
     */
    public function resetSimulation(): void
    {
        $this->hasSimulated = false;
        $this->simulationResult = null;
    }

    /**
     * Build simulation result with full breakdown.
     *
     * This method performs a preliminary simulation using available data.
     * The complete simulation logic will be implemented in SimulationService (US-056).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildSimulationResult(array $data): array
    {
        $skuId = $data['sellable_sku_id'] ?? null;
        $channelId = $data['channel_id'] ?? null;
        $customerId = $data['customer_id'] ?? null;
        $dateStr = $data['date'] ?? now()->format('Y-m-d');
        $date = Carbon::parse($dateStr);
        $quantity = (int) ($data['quantity'] ?? 1);

        // Load entities with relationships
        $sku = $skuId !== null
            ? SellableSku::with([
                'wineVariant.wineMaster',
                'format',
                'caseConfiguration',
                'estimatedMarketPrices',
            ])->find($skuId)
            : null;
        $channel = $channelId !== null ? Channel::find($channelId) : null;
        $customer = $customerId !== null ? Customer::find($customerId) : null;

        // Create simulation context
        $context = new SimulationContext($sku, $channel, $customer, $date, $quantity);

        // Build each step result
        $allocationCheck = $this->buildAllocationCheckResult($sku, $channel, $quantity);
        $empReference = $this->buildEmpReferenceResult($sku);
        $priceBookResolution = $this->buildPriceBookResolutionResult($sku, $channel);
        $offerResolution = $this->buildOfferResolutionResult($sku, $channel, $priceBookResolution);
        $finalPrice = $this->buildFinalPriceResult(
            $priceBookResolution,
            $offerResolution,
            $quantity,
            $channel
        );

        // Collect errors and warnings
        $errors = [];
        $warnings = [];

        if ($allocationCheck->status === AllocationCheckResult::STATUS_ERROR) {
            $errors[] = 'Allocation: '.$allocationCheck->message;
        }
        if ($priceBookResolution->status === PriceBookResolutionResult::STATUS_ERROR) {
            $errors[] = 'Price Book: '.$priceBookResolution->message;
        }
        if ($offerResolution->status === OfferResolutionResult::STATUS_ERROR) {
            $errors[] = 'Offer: '.$offerResolution->message;
        }
        if ($empReference->status === EmpReferenceResult::STATUS_WARNING) {
            $warnings[] = 'EMP: '.$empReference->message;
        }

        // Create the typed result
        $this->typedResult = new SimulationResult(
            context: $context,
            allocationCheck: $allocationCheck,
            empReference: $empReference,
            priceBookResolution: $priceBookResolution,
            offerResolution: $offerResolution,
            finalPrice: $finalPrice,
            errors: $errors,
            warnings: $warnings,
        );

        // Return array representation for view
        return $this->typedResult->toArray();
    }

    /**
     * Build Allocation Check result (Step 1).
     */
    protected function buildAllocationCheckResult(
        ?SellableSku $sku,
        ?Channel $channel,
        int $quantity
    ): AllocationCheckResult {
        if ($sku === null) {
            return AllocationCheckResult::error('No SKU selected');
        }

        // Try to find an active allocation for this SKU's wine variant and format
        $wineVariantId = $sku->wine_variant_id;
        $formatId = $sku->format_id;

        $allocation = Allocation::query()
            ->where('wine_variant_id', $wineVariantId)
            ->where('format_id', $formatId)
            ->where('status', \App\Enums\Allocation\AllocationStatus::Active)
            ->with('constraint')
            ->first();

        if ($allocation === null) {
            return AllocationCheckResult::error(
                'No active allocation found for this SKU',
                [
                    'wine_variant_id' => $wineVariantId,
                    'format_id' => $formatId,
                    'rationale' => 'SKU requires an active allocation to be sellable',
                ]
            );
        }

        // Check availability
        $remaining = $allocation->remaining_quantity;
        if ($remaining < $quantity) {
            return AllocationCheckResult::warning(
                "Insufficient allocation: only {$remaining} units available, requested {$quantity}",
                $allocation,
                [
                    'total_quantity' => (string) $allocation->total_quantity,
                    'sold_quantity' => (string) $allocation->sold_quantity,
                    'remaining' => (string) $remaining,
                    'requested' => (string) $quantity,
                    'source' => 'Allocation ID: '.$allocation->id,
                ]
            );
        }

        // Check channel constraint if applicable
        $constraint = $allocation->constraint;
        $constraintDetails = [];
        if ($constraint !== null) {
            $allowedChannels = $constraint->allowed_channels ?? [];
            $allowedMarkets = $constraint->allowed_markets ?? [];
            $constraintDetails = [
                'allowed_channels' => $allowedChannels !== [] ? implode(', ', $allowedChannels) : 'All',
                'allowed_markets' => $allowedMarkets !== [] ? implode(', ', $allowedMarkets) : 'All',
            ];

            if ($channel !== null && $allowedChannels !== [] && ! in_array($channel->id, $allowedChannels, true)) {
                return AllocationCheckResult::warning(
                    'Channel not in allowed channels for this allocation',
                    $allocation,
                    array_merge($constraintDetails, [
                        'rationale' => 'Allocation constraint restricts channels',
                    ])
                );
            }
        }

        return AllocationCheckResult::success(
            $allocation,
            'Allocation available with sufficient quantity',
            [
                'total_quantity' => (string) $allocation->total_quantity,
                'sold_quantity' => (string) $allocation->sold_quantity,
                'remaining' => (string) $remaining,
                'supply_form' => $allocation->supply_form->label(),
                'source' => 'Allocation ID: '.substr((string) $allocation->id, 0, 8).'...',
                'rationale' => 'Active allocation with sufficient remaining quantity',
                ...$constraintDetails,
            ]
        );
    }

    /**
     * Build EMP Reference result (Step 2).
     */
    protected function buildEmpReferenceResult(?SellableSku $sku): EmpReferenceResult
    {
        if ($sku === null) {
            return EmpReferenceResult::warning('No SKU selected for EMP lookup');
        }

        $empRecord = $sku->estimatedMarketPrices()->first();

        if ($empRecord === null) {
            return EmpReferenceResult::warning(
                'No EMP data available for this SKU',
                null,
                [
                    'sku_code' => $sku->sku_code,
                    'note' => 'Pricing will proceed without EMP reference',
                    'rationale' => 'EMP data may be pending import or not applicable for this SKU',
                ]
            );
        }

        $details = [
            'market' => $empRecord->market,
            'emp_value' => '€ '.number_format((float) $empRecord->emp_value, 2),
            'confidence' => $empRecord->confidence_level->label(),
            'source' => $empRecord->source->label(),
            'freshness' => $empRecord->getFreshnessIndicator(),
            'fetched_at' => $empRecord->fetched_at?->format('Y-m-d H:i') ?? 'N/A',
            'rationale' => 'EMP provides market reference for pricing decisions',
        ];

        // Check if EMP data is stale
        if ($empRecord->isStale()) {
            return EmpReferenceResult::warning(
                'EMP data is stale (older than 7 days)',
                $empRecord,
                $details
            );
        }

        return EmpReferenceResult::success(
            $empRecord,
            'EMP data available and fresh',
            $details
        );
    }

    /**
     * Build Price Book Resolution result (Step 3).
     */
    protected function buildPriceBookResolutionResult(
        ?SellableSku $sku,
        ?Channel $channel
    ): PriceBookResolutionResult {
        if ($sku === null || $channel === null) {
            return PriceBookResolutionResult::error(
                'SKU or Channel not selected',
                ['rationale' => 'Both SKU and Channel are required for Price Book resolution']
            );
        }

        // Find active Price Book for this channel
        $priceBook = PriceBook::query()
            ->where('status', PriceBookStatus::Active)
            ->where(function ($query) use ($channel): void {
                $query->where('channel_id', $channel->id)
                    ->orWhereNull('channel_id');
            })
            ->where('valid_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->orderByRaw('CASE WHEN channel_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($priceBook === null) {
            return PriceBookResolutionResult::error(
                'No active Price Book found for this context',
                [
                    'channel' => $channel->name,
                    'rationale' => 'An active Price Book is required for pricing',
                ]
            );
        }

        // Look for price entry
        $entry = $priceBook->entries()
            ->where('sellable_sku_id', $sku->id)
            ->first();

        if ($entry === null) {
            return PriceBookResolutionResult::warning(
                'No price entry found for this SKU in the active Price Book',
                $priceBook,
                null,
                null,
                [
                    'price_book_name' => $priceBook->name,
                    'price_book_market' => $priceBook->market,
                    'price_book_currency' => $priceBook->currency,
                    'rationale' => 'SKU must have a price entry in the Price Book',
                ]
            );
        }

        $basePrice = (float) $entry->base_price;

        return PriceBookResolutionResult::success(
            $priceBook,
            $entry,
            $basePrice,
            'Price Book resolved with base price',
            [
                'price_book_name' => $priceBook->name,
                'market' => $priceBook->market,
                'currency' => $priceBook->currency,
                'base_price' => '€ '.number_format($basePrice, 2),
                'price_source' => $entry->source->label(),
                'valid_from' => $priceBook->valid_from->format('Y-m-d'),
                'valid_to' => $priceBook->valid_to !== null ? $priceBook->valid_to->format('Y-m-d') : 'Indefinite',
                'rationale' => 'Using channel-specific or default Price Book',
            ]
        );
    }

    /**
     * Build Offer Resolution result (Step 4).
     */
    protected function buildOfferResolutionResult(
        ?SellableSku $sku,
        ?Channel $channel,
        PriceBookResolutionResult $priceBookResult
    ): OfferResolutionResult {
        if ($sku === null || $channel === null) {
            return OfferResolutionResult::error(
                'SKU or Channel not selected',
                ['rationale' => 'Both SKU and Channel are required for Offer resolution']
            );
        }

        if ($priceBookResult->basePrice === null) {
            return OfferResolutionResult::error(
                'No base price available from Price Book',
                ['rationale' => 'Offer resolution requires a base price']
            );
        }

        // Find active Offer for this SKU and Channel
        $offer = Offer::query()
            ->where('status', OfferStatus::Active)
            ->where('sellable_sku_id', $sku->id)
            ->where('channel_id', $channel->id)
            ->where('valid_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->with(['benefit', 'eligibility'])
            ->first();

        if ($offer === null) {
            return OfferResolutionResult::warning(
                'No active Offer found for this SKU on this Channel',
                null,
                [
                    'sku_code' => $sku->sku_code,
                    'channel' => $channel->name,
                    'rationale' => 'SKU is not currently offered on this channel',
                ]
            );
        }

        // Get benefit details
        $benefit = $offer->benefit;
        if ($benefit === null || $benefit->isNone()) {
            return OfferResolutionResult::successNoDiscount(
                $offer,
                'Offer found - using Price Book price (no benefit)',
                [
                    'offer_name' => $offer->name,
                    'offer_type' => $offer->offer_type->label(),
                    'visibility' => $offer->visibility->label(),
                    'valid_from' => $offer->valid_from->format('Y-m-d H:i'),
                    'valid_to' => $offer->valid_to !== null ? $offer->valid_to->format('Y-m-d H:i') : 'Indefinite',
                    'benefit_type' => 'None',
                    'rationale' => 'Offer does not apply additional benefit to base price',
                ]
            );
        }

        // Calculate discount
        $basePrice = $priceBookResult->basePrice;
        $finalPrice = $benefit->calculateFinalPrice($basePrice);
        $discountAmount = $benefit->getDiscountAmount($basePrice);
        $discountPercent = $benefit->getDiscountPercentage($basePrice);
        $benefitDescription = $benefit->getBenefitSummary();

        return OfferResolutionResult::successWithDiscount(
            $offer,
            $discountAmount,
            $discountPercent,
            $benefitDescription,
            'Offer found with benefit applied',
            [
                'offer_name' => $offer->name,
                'offer_type' => $offer->offer_type->label(),
                'visibility' => $offer->visibility->label(),
                'valid_from' => $offer->valid_from->format('Y-m-d H:i'),
                'valid_to' => $offer->valid_to !== null ? $offer->valid_to->format('Y-m-d H:i') : 'Indefinite',
                'benefit_type' => $benefit->benefit_type->label(),
                'benefit_value' => $benefit->benefit_value !== null
                    ? ($benefit->isPercentageDiscount() ? number_format((float) $benefit->benefit_value, 1).'%' : '€ '.number_format((float) $benefit->benefit_value, 2))
                    : 'N/A',
                'discount_amount' => '€ '.number_format($discountAmount, 2),
                'rationale' => 'Benefit applied to base price from Price Book',
            ]
        );
    }

    /**
     * Build Final Price result (Step 5).
     */
    protected function buildFinalPriceResult(
        PriceBookResolutionResult $priceBookResult,
        OfferResolutionResult $offerResult,
        int $quantity,
        ?Channel $channel
    ): FinalPriceResult {
        // Check if we have the required data
        if ($priceBookResult->basePrice === null) {
            return FinalPriceResult::error(
                'Cannot calculate final price: no base price from Price Book',
                ['rationale' => 'Final price calculation requires a base price']
            );
        }

        if ($offerResult->status === OfferResolutionResult::STATUS_ERROR) {
            return FinalPriceResult::error(
                'Cannot calculate final price: '.$offerResult->message,
                ['rationale' => 'Offer resolution failed']
            );
        }

        // No active offer means we can't sell (offer is the activation of sellability)
        if ($offerResult->offer === null) {
            return FinalPriceResult::error(
                'Cannot calculate final price: no active Offer found',
                [
                    'base_price' => '€ '.number_format($priceBookResult->basePrice, 2),
                    'rationale' => 'An active Offer is required for the SKU to be sellable on this channel',
                ]
            );
        }

        // Calculate final price
        $basePrice = $priceBookResult->basePrice;
        $finalPrice = $basePrice;
        $explanation = 'Base price from Price Book';

        if ($offerResult->discountAmount !== null && $offerResult->discountAmount > 0) {
            $finalPrice = $basePrice - $offerResult->discountAmount;
            $explanation = sprintf(
                'Base price (€%.2f) - %s discount (€%.2f) = Final price',
                $basePrice,
                $offerResult->benefitDescription ?? 'offer',
                $offerResult->discountAmount
            );
        }

        $currency = $channel !== null
            ? $channel->default_currency
            : ($priceBookResult->priceBook !== null ? $priceBookResult->priceBook->currency : 'EUR');

        $totalPrice = $finalPrice * $quantity;

        return FinalPriceResult::success(
            $finalPrice,
            $quantity,
            $currency,
            $explanation,
            'Final price calculated successfully',
            [
                'base_price' => '€ '.number_format($basePrice, 2),
                'discount' => $offerResult->discountAmount !== null && $offerResult->discountAmount > 0
                    ? '€ '.number_format($offerResult->discountAmount, 2).' ('.number_format($offerResult->discountPercent ?? 0, 1).'%)'
                    : 'None',
                'final_price' => '€ '.number_format($finalPrice, 2),
                'quantity' => (string) $quantity,
                'total_price' => '€ '.number_format($totalPrice, 2),
                'currency' => $currency,
                'rationale' => $explanation,
            ]
        );
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
     * Build HTML preview for a SKU.
     */
    protected function buildSkuPreviewHtml(SellableSku $sku): string
    {
        $wineVariant = $sku->wineVariant;
        $wineMaster = $wineVariant !== null ? $wineVariant->wineMaster : null;
        $wineName = $wineMaster !== null ? htmlspecialchars($wineMaster->name) : 'Unknown Wine';
        $vintage = $wineVariant !== null ? ($wineVariant->vintage_year ?? 'NV') : 'NV';
        $format = $sku->format !== null ? $sku->format->volume_ml.'ml' : '-';
        $caseConfig = $sku->caseConfiguration;
        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.' bottles' : '-';
        $statusColor = $sku->getStatusColor();
        $statusLabel = $sku->getStatusLabel();

        // Get EMP if available
        $emp = $sku->estimatedMarketPrices()->first();
        $empDisplay = $emp !== null
            ? "\u{20AC} ".number_format((float) $emp->emp_value, 2).' ('.$emp->market.')'
            : '<span class="text-gray-400">No EMP</span>';

        return '<div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 border border-gray-200 dark:border-gray-700">'
            .'<div class="grid grid-cols-2 md:grid-cols-5 gap-4">'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Wine</p>'
            .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'.$wineName.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vintage</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$vintage.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Format</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$format.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Packaging</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$packaging.'</p>'
            .'</div>'
            .'<div>'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</p>'
            .'<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$statusColor.'-100 text-'.$statusColor.'-800 dark:bg-'.$statusColor.'-900 dark:text-'.$statusColor.'-200">'.$statusLabel.'</span>'
            .'</div>'
            .'<div class="col-span-2 md:col-span-5">'
            .'<p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">EMP Reference</p>'
            .'<p class="text-sm text-gray-600 dark:text-gray-400">'.$empDisplay.'</p>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * Get the simulation result for the view.
     *
     * @return array<string, mixed>|null
     */
    public function getSimulationResult(): ?array
    {
        return $this->simulationResult;
    }
}
