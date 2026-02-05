<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\Commercial\SimulationResult;
use App\Enums\Commercial\ChannelStatus;
use App\Models\Commercial\Channel;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use App\Services\Commercial\SimulationService;
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

        // Use SimulationService for the actual simulation
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
     * Uses the SimulationService to perform the complete pricing simulation.
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

        // Validate required inputs
        if ($sku === null || $channel === null) {
            return $this->buildErrorResult($sku, $channel, $customer, $date, $quantity);
        }

        // Use SimulationService for the actual simulation
        $simulationService = app(SimulationService::class);
        $this->typedResult = $simulationService->simulate(
            $sku,
            $customer,
            $channel,
            $date,
            $quantity
        );

        // Return array representation for view
        return $this->typedResult->toArray();
    }

    /**
     * Build an error result when required inputs are missing.
     *
     * @return array<string, mixed>
     */
    protected function buildErrorResult(
        ?SellableSku $sku,
        ?Channel $channel,
        ?Customer $customer,
        Carbon $date,
        int $quantity
    ): array {
        $errors = [];
        if ($sku === null) {
            $errors[] = 'Sellable SKU is required';
        }
        if ($channel === null) {
            $errors[] = 'Channel is required';
        }

        return [
            'context' => [
                'sku' => $sku !== null ? $sku->sku_code : 'Not selected',
                'sku_code' => $sku !== null ? $sku->sku_code : 'N/A',
                'sku_id' => $sku?->id,
                'channel' => $channel !== null ? $channel->name : 'Not selected',
                'channel_id' => $channel?->id,
                'customer' => $customer !== null ? $customer->name : 'Anonymous',
                'customer_id' => $customer?->id,
                'date' => $date->format('Y-m-d'),
                'quantity' => $quantity,
            ],
            'steps' => [
                'allocation' => [
                    'title' => 'Allocation Check',
                    'status' => 'error',
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'message' => 'Cannot check allocation: missing required inputs',
                    'details' => [],
                ],
                'emp' => [
                    'title' => 'EMP Reference',
                    'status' => 'pending',
                    'icon' => 'heroicon-o-chart-bar-square',
                    'message' => 'Pending',
                    'details' => [],
                ],
                'price_book' => [
                    'title' => 'Price Book Resolution',
                    'status' => 'pending',
                    'icon' => 'heroicon-o-book-open',
                    'message' => 'Pending',
                    'details' => [],
                ],
                'offer' => [
                    'title' => 'Offer Resolution',
                    'status' => 'pending',
                    'icon' => 'heroicon-o-tag',
                    'message' => 'Pending',
                    'details' => [],
                ],
                'final' => [
                    'title' => 'Final Price Calculation',
                    'status' => 'error',
                    'icon' => 'heroicon-o-currency-euro',
                    'message' => 'Cannot calculate final price: missing required inputs',
                    'details' => [],
                ],
            ],
            'errors' => $errors,
            'warnings' => [],
            'status' => 'error',
            'final_price' => null,
        ];
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
