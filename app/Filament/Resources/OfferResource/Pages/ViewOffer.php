<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Filament\Resources\OfferResource;
use App\Models\AuditLog;
use App\Models\Commercial\Offer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class ViewOffer extends ViewRecord
{
    protected static string $resource = OfferResource::class;

    /**
     * Filter for audit log event type.
     */
    public ?string $auditEventFilter = null;

    /**
     * Filter for audit log date from.
     */
    public ?string $auditDateFrom = null;

    /**
     * Filter for audit log date until.
     */
    public ?string $auditDateUntil = null;

    public function getTitle(): string|Htmlable
    {
        /** @var Offer $record */
        $record = $this->record;

        return "Offer: {$record->name}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Offer Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getEligibilityTab(),
                        $this->getBenefitTab(),
                        $this->getProductsTab(),
                        $this->getPriorityConflictsTab(),
                        $this->getSimulationTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Summary, status, final price computed.
     */
    protected function getOverviewTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;

        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Offer Information')
                    ->description('Core offer configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Offer ID')
                                        ->copyable()
                                        ->copyMessage('ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextSize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('offer_type')
                                        ->label('Offer Type')
                                        ->badge()
                                        ->formatStateUsing(fn (OfferType $state): string => $state->label())
                                        ->color(fn (OfferType $state): string => $state->color())
                                        ->icon(fn (OfferType $state): string => $state->icon()),
                                    TextEntry::make('visibility')
                                        ->label('Visibility')
                                        ->badge()
                                        ->formatStateUsing(fn (OfferVisibility $state): string => $state->label())
                                        ->color(fn (OfferVisibility $state): string => $state->color())
                                        ->icon(fn (OfferVisibility $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (OfferStatus $state): string => $state->label())
                                        ->color(fn (OfferStatus $state): string => $state->color())
                                        ->icon(fn (OfferStatus $state): string => $state->icon()),
                                    TextEntry::make('campaign_tag')
                                        ->label('Campaign Tag')
                                        ->placeholder('None')
                                        ->badge()
                                        ->color('gray'),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Linked Entities')
                    ->description('Channel and Price Book configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('channel.name')
                                        ->label('Channel')
                                        ->weight(FontWeight::Medium)
                                        ->url(fn (Offer $record): ?string => $record->channel
                                            ? route('filament.admin.resources.channels.view', ['record' => $record->channel->id])
                                            : null),
                                    TextEntry::make('channel.channel_type')
                                        ->label('Channel Type')
                                        ->badge()
                                        ->formatStateUsing(fn ($state): string => $state?->label() ?? '-')
                                        ->color(fn ($state): string => $state?->color() ?? 'gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('priceBook.name')
                                        ->label('Price Book')
                                        ->weight(FontWeight::Medium)
                                        ->url(fn (Offer $record): ?string => $record->priceBook
                                            ? route('filament.admin.resources.price-books.view', ['record' => $record->priceBook->id])
                                            : null),
                                    TextEntry::make('priceBook.currency')
                                        ->label('Currency')
                                        ->badge()
                                        ->color('info'),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Validity Period')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('valid_from')
                                    ->label('Valid From')
                                    ->dateTime()
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('valid_to')
                                    ->label('Valid Until')
                                    ->dateTime()
                                    ->placeholder('Indefinite')
                                    ->weight(FontWeight::Medium)
                                    ->color(fn (Offer $record): string => $record->isExpiringSoon() ? 'danger' : 'gray'),
                                TextEntry::make('validity_status')
                                    ->label('Validity Status')
                                    ->getStateUsing(function (Offer $record): string {
                                        if ($record->isExpired()) {
                                            return 'Expired';
                                        }
                                        if (! $record->isWithinValidityPeriod()) {
                                            return 'Not yet valid';
                                        }
                                        if ($record->isExpiringSoon()) {
                                            $days = $record->getDaysUntilExpiry();

                                            return "Expiring soon ({$days} days)";
                                        }

                                        return 'Active';
                                    })
                                    ->badge()
                                    ->color(function (Offer $record): string {
                                        if ($record->isExpired()) {
                                            return 'danger';
                                        }
                                        if (! $record->isWithinValidityPeriod()) {
                                            return 'warning';
                                        }
                                        if ($record->isExpiringSoon()) {
                                            return 'warning';
                                        }

                                        return 'success';
                                    }),
                            ]),
                    ]),

                Section::make('Pricing Summary')
                    ->description('Final computed price for this offer')
                    ->icon('heroicon-o-currency-euro')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('base_price')
                                    ->label('Base Price')
                                    ->getStateUsing(function (Offer $record): string {
                                        $basePrice = $record->getBasePrice();
                                        if ($basePrice === null) {
                                            return 'Not available';
                                        }
                                        $priceBook = $record->priceBook;
                                        $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                        return $currency.' '.number_format((float) $basePrice, 2);
                                    })
                                    ->badge()
                                    ->color(fn (Offer $record): string => $record->hasBasePrice() ? 'gray' : 'danger'),
                                TextEntry::make('benefit_applied')
                                    ->label('Benefit')
                                    ->getStateUsing(function (Offer $record): string {
                                        $benefit = $record->benefit;
                                        if ($benefit === null) {
                                            return 'No benefit';
                                        }

                                        $priceBook = $record->priceBook;

                                        return $benefit->getBenefitSummary($priceBook !== null ? $priceBook->currency : 'EUR');
                                    })
                                    ->badge()
                                    ->color(function (Offer $record): string {
                                        $benefit = $record->benefit;
                                        if ($benefit === null || $benefit->isNone()) {
                                            return 'gray';
                                        }

                                        return 'success';
                                    }),
                                TextEntry::make('final_price')
                                    ->label('Final Price')
                                    ->getStateUsing(function (Offer $record): string {
                                        $basePrice = $record->getBasePrice();
                                        if ($basePrice === null) {
                                            return 'Not available';
                                        }
                                        $benefit = $record->benefit;
                                        $finalPrice = $benefit !== null
                                            ? $benefit->calculateFinalPrice((float) $basePrice)
                                            : (float) $basePrice;
                                        $priceBook = $record->priceBook;
                                        $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                        return $currency.' '.number_format($finalPrice, 2);
                                    })
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->color(fn (Offer $record): string => $record->hasBasePrice() ? 'primary' : 'danger'),
                                TextEntry::make('savings')
                                    ->label('Savings')
                                    ->getStateUsing(function (Offer $record): string {
                                        $basePrice = $record->getBasePrice();
                                        if ($basePrice === null) {
                                            return '-';
                                        }
                                        $benefit = $record->benefit;
                                        if ($benefit === null || $benefit->isNone()) {
                                            return 'No discount';
                                        }
                                        $discountAmount = $benefit->getDiscountAmount((float) $basePrice);
                                        $discountPercent = $benefit->getDiscountPercentage((float) $basePrice);
                                        $priceBook = $record->priceBook;
                                        $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                        return $currency.' '.number_format($discountAmount, 2).' ('.number_format($discountPercent, 1).'%)';
                                    })
                                    ->color('success'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Eligibility - Markets, customer types, allocation constraints.
     */
    protected function getEligibilityTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;
        $eligibility = $record->eligibility;

        return Tab::make('Eligibility')
            ->icon('heroicon-o-user-group')
            ->schema([
                Section::make('Eligibility Overview')
                    ->description('Who can access this offer')
                    ->schema([
                        TextEntry::make('eligibility_summary')
                            ->label('')
                            ->getStateUsing(fn (): string => $eligibility?->getEligibilitySummary() ?? 'No eligibility restrictions defined')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Medium)
                            ->color(fn (): string => $eligibility !== null && $eligibility->hasAnyRestrictions() ? 'warning' : 'success'),
                    ]),

                Section::make('Market Restrictions')
                    ->description('Geographic markets where this offer is available')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('markets')
                                    ->label('Allowed Markets')
                                    ->getStateUsing(fn (): string => $eligibility?->getMarketsDisplayString() ?? 'All markets'),
                                TextEntry::make('markets_count')
                                    ->label('Markets Count')
                                    ->getStateUsing(fn (): string => $eligibility !== null && $eligibility->hasMarketRestrictions()
                                        ? $eligibility->getMarketsCount().' market(s)'
                                        : 'No restrictions')
                                    ->badge()
                                    ->color(fn (): string => $eligibility !== null && $eligibility->hasMarketRestrictions() ? 'info' : 'gray'),
                            ]),
                    ]),

                Section::make('Customer Type Restrictions')
                    ->description('Types of customers who can access this offer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('customer_types')
                                    ->label('Allowed Customer Types')
                                    ->getStateUsing(fn (): string => $eligibility?->getCustomerTypesDisplayString() ?? 'All customer types'),
                                TextEntry::make('customer_types_count')
                                    ->label('Types Count')
                                    ->getStateUsing(fn (): string => $eligibility !== null && $eligibility->hasCustomerTypeRestrictions()
                                        ? $eligibility->getCustomerTypesCount().' type(s)'
                                        : 'No restrictions')
                                    ->badge()
                                    ->color(fn (): string => $eligibility !== null && $eligibility->hasCustomerTypeRestrictions() ? 'info' : 'gray'),
                            ]),
                    ]),

                Section::make('Membership Tier Restrictions')
                    ->description('Membership levels required to access this offer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('membership_tiers')
                                    ->label('Allowed Membership Tiers')
                                    ->getStateUsing(fn (): string => $eligibility?->getMembershipTiersDisplayString() ?? 'All membership tiers'),
                                TextEntry::make('membership_tiers_count')
                                    ->label('Tiers Count')
                                    ->getStateUsing(fn (): string => $eligibility !== null && $eligibility->hasMembershipTierRestrictions()
                                        ? $eligibility->getMembershipTiersCount().' tier(s)'
                                        : 'No restrictions')
                                    ->badge()
                                    ->color(fn (): string => $eligibility !== null && $eligibility->hasMembershipTierRestrictions() ? 'info' : 'gray'),
                            ]),
                    ]),

                Section::make('Allocation Constraints')
                    ->description('Link to authoritative allocation constraints from Module A')
                    ->schema([
                        TextEntry::make('allocation_constraint_info')
                            ->label('')
                            ->getStateUsing(function () use ($eligibility): string {
                                if ($eligibility === null || ! $eligibility->hasAllocationConstraint()) {
                                    return '<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">No allocation constraint reference linked to this offer.</p>
                                    </div>';
                                }

                                $constraintId = $eligibility->getAllocationConstraintId();

                                return '<div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                    <p class="font-medium text-blue-800 dark:text-blue-200 mb-1">Allocation Constraint Linked</p>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">Constraint ID: '.$constraintId.'</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">⚠️ Eligibility rules cannot override allocation constraints. The allocation constraint takes precedence.</p>
                                </div>';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Eligibility Rules')
                    ->description('How eligibility is evaluated')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('eligibility_rules_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-2 text-sm">
                                    <p><strong>Eligibility Evaluation:</strong></p>
                                    <ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400">
                                        <li>All restrictions are evaluated as AND conditions</li>
                                        <li>A customer must match ALL restrictions to be eligible</li>
                                        <li>Empty restriction = no restriction (all allowed)</li>
                                        <li>Allocation constraints from Module A take precedence</li>
                                    </ul>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 3: Benefit - Discount/fixed price config, resolved final price.
     */
    protected function getBenefitTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;
        $benefit = $record->benefit;
        $basePrice = $record->getBasePrice();

        return Tab::make('Benefit')
            ->icon('heroicon-o-gift')
            ->schema([
                Section::make('Benefit Configuration')
                    ->description('How the final price is calculated')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('benefit_type')
                                    ->label('Benefit Type')
                                    ->getStateUsing(fn (): string => $benefit?->getBenefitTypeLabel() ?? 'None')
                                    ->badge()
                                    ->color(fn (): string => $benefit?->getBenefitTypeColor() ?? 'gray')
                                    ->icon(fn (): string => $benefit?->getBenefitTypeIcon() ?? 'heroicon-o-minus')
                                    ->size(TextSize::Large),
                                TextEntry::make('benefit_value')
                                    ->label('Benefit Value')
                                    ->getStateUsing(function () use ($benefit, $record): string {
                                        if ($benefit === null || ! $benefit->hasValue()) {
                                            return '-';
                                        }
                                        $priceBook = $record->priceBook;

                                        return $benefit->getFormattedValue($priceBook !== null ? $priceBook->currency : 'EUR');
                                    })
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('discount_rule')
                                    ->label('Discount Rule')
                                    ->getStateUsing(fn (): string => $benefit !== null && $benefit->hasDiscountRule()
                                        ? 'Rule: '.$benefit->getDiscountRuleId()
                                        : 'No rule linked')
                                    ->badge()
                                    ->color(fn (): string => $benefit !== null && $benefit->hasDiscountRule() ? 'warning' : 'gray'),
                            ]),
                    ]),

                Section::make('Benefit Type Description')
                    ->schema([
                        TextEntry::make('benefit_type_description')
                            ->label('')
                            ->getStateUsing(function () use ($benefit): string {
                                if ($benefit === null) {
                                    return '<div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">No benefit configured for this offer.</p>
                                    </div>';
                                }

                                $description = $benefit->benefit_type->description();
                                $colorClass = match ($benefit->benefit_type) {
                                    BenefitType::None => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                                    BenefitType::PercentageDiscount => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
                                    BenefitType::FixedDiscount => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
                                    BenefitType::FixedPrice => 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800',
                                };

                                return "<div class='p-3 rounded border {$colorClass}'>
                                    <p class='font-medium'>{$benefit->getBenefitTypeLabel()}</p>
                                    <p class='text-sm mt-1'>{$description}</p>
                                </div>";
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Price Calculation')
                    ->description('Step-by-step price resolution')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('price_calculation')
                            ->label('')
                            ->getStateUsing(function () use ($record, $benefit, $basePrice): string {
                                $priceBook = $record->priceBook;
                                $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                if ($basePrice === null) {
                                    return '<div class="p-4 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800">
                                        <p class="text-red-800 dark:text-red-200 font-medium">⚠️ Base Price Not Available</p>
                                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                            No price entry found in the linked Price Book for this SKU.
                                            Please ensure the Price Book contains an entry for this Sellable SKU.
                                        </p>
                                    </div>';
                                }

                                $basePriceFloat = (float) $basePrice;
                                $finalPrice = $benefit !== null
                                    ? $benefit->calculateFinalPrice($basePriceFloat)
                                    : $basePriceFloat;
                                $discountAmount = $benefit !== null ? $benefit->getDiscountAmount($basePriceFloat) : 0;

                                $html = '<div class="space-y-3">';

                                // Step 1: Base Price
                                $html .= '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">';
                                $html .= '<div><span class="font-medium">1. Base Price</span> <span class="text-sm text-gray-500">(from Price Book)</span></div>';
                                $html .= '<div class="font-mono font-medium">'.$currency.' '.number_format($basePriceFloat, 2).'</div>';
                                $html .= '</div>';

                                // Step 2: Benefit Applied
                                if ($benefit !== null && ! $benefit->isNone()) {
                                    $html .= '<div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded">';
                                    $html .= '<div><span class="font-medium">2. Benefit Applied</span> <span class="text-sm text-gray-500">('.$benefit->getBenefitSummary($currency).')</span></div>';
                                    $html .= '<div class="font-mono font-medium text-green-600 dark:text-green-400">-'.$currency.' '.number_format($discountAmount, 2).'</div>';
                                    $html .= '</div>';
                                } else {
                                    $html .= '<div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">';
                                    $html .= '<div><span class="font-medium">2. Benefit Applied</span> <span class="text-sm text-gray-500">(none)</span></div>';
                                    $html .= '<div class="font-mono text-gray-500">No discount</div>';
                                    $html .= '</div>';
                                }

                                // Step 3: Final Price
                                $html .= '<div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 rounded border-2 border-primary-200 dark:border-primary-800">';
                                $html .= '<div><span class="font-bold text-lg">Final Price</span></div>';
                                $html .= '<div class="font-mono font-bold text-lg text-primary-600 dark:text-primary-400">'.$currency.' '.number_format($finalPrice, 2).'</div>';
                                $html .= '</div>';

                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Products - Sellable SKU info, allocation lineage.
     */
    protected function getProductsTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;
        $sku = $record->sellableSku;

        return Tab::make('Products')
            ->icon('heroicon-o-cube')
            ->schema([
                Section::make('Sellable SKU')
                    ->description('Product configuration for this offer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('sellableSku.sku_code')
                                        ->label('SKU Code')
                                        ->weight(FontWeight::Bold)
                                        ->copyable()
                                        ->copyMessage('SKU copied'),
                                    TextEntry::make('sellableSku.lifecycle_status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (?string $state): string => $sku?->getStatusLabel() ?? '-')
                                        ->color(fn (): string => $sku?->getStatusColor() ?? 'gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('sellableSku.source')
                                        ->label('Source')
                                        ->badge()
                                        ->formatStateUsing(fn (?string $state): string => $sku?->getSourceLabel() ?? '-')
                                        ->color(fn (): string => $sku?->getSourceColor() ?? 'gray'),
                                    TextEntry::make('sellableSku.is_composite')
                                        ->label('Composite')
                                        ->getStateUsing(fn (): string => $sku !== null && $sku->isComposite() ? 'Yes (Bundle)' : 'No')
                                        ->badge()
                                        ->color(fn (): string => $sku !== null && $sku->isComposite() ? 'warning' : 'gray'),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Wine Information')
                    ->description('Wine product details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('wine_name')
                                    ->label('Wine')
                                    ->getStateUsing(function () use ($sku): string {
                                        if ($sku === null) {
                                            return 'Unknown';
                                        }
                                        $wineVariant = $sku->wineVariant;
                                        $wineMaster = $wineVariant !== null ? $wineVariant->wineMaster : null;

                                        return $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                    })
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('vintage')
                                    ->label('Vintage')
                                    ->getStateUsing(function () use ($sku): string {
                                        if ($sku === null) {
                                            return '-';
                                        }
                                        $wineVariant = $sku->wineVariant;

                                        return $wineVariant !== null ? (string) $wineVariant->vintage_year : '-';
                                    })
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('format')
                                    ->label('Format')
                                    ->getStateUsing(function () use ($sku): string {
                                        $format = $sku?->format;
                                        if ($format === null) {
                                            return '-';
                                        }

                                        return $format->name.' ('.$format->volume_ml.'ml)';
                                    }),
                                TextEntry::make('packaging')
                                    ->label('Packaging')
                                    ->getStateUsing(function () use ($sku): string {
                                        $caseConfig = $sku?->caseConfiguration;
                                        if ($caseConfig === null) {
                                            return '-';
                                        }

                                        return $caseConfig->bottles_per_case.'x '.strtoupper($caseConfig->case_type);
                                    })
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),

                Section::make('Integrity Flags')
                    ->description('Product quality indicators')
                    ->schema([
                        TextEntry::make('integrity_flags')
                            ->label('')
                            ->getStateUsing(function () use ($sku): string {
                                if ($sku === null) {
                                    return '<div class="text-gray-500">No SKU data available</div>';
                                }

                                $flags = $sku->getIntegrityFlags();
                                if (empty($flags)) {
                                    return '<div class="text-gray-500">No integrity flags set</div>';
                                }

                                $html = '<div class="flex gap-2 flex-wrap">';
                                foreach ($flags as $flag) {
                                    $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">';
                                    $html .= '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';
                                    $html .= $flag.'</span>';
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Allocation Lineage')
                    ->description('How this product became available for sale')
                    ->schema([
                        TextEntry::make('allocation_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-3">
                                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                        <p class="font-medium text-blue-800 dark:text-blue-200 mb-2">📦 Allocation to Offer Flow</p>
                                        <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                            <p><strong>1. Allocation (Module A)</strong> → Makes inventory available for sale</p>
                                            <p><strong>2. Allocation Constraint</strong> → Defines channels, markets, customer types</p>
                                            <p><strong>3. Sellable SKU (PIM)</strong> → Product definition with pricing attributes</p>
                                            <p><strong>4. Offer (Module S)</strong> → Activates sellability on a specific channel</p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Note: Offers inherit constraints from the underlying allocation. Eligibility rules in the offer cannot override allocation constraints.
                                    </p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('EMP Reference')
                    ->description('Estimated Market Price for this SKU')
                    ->schema([
                        TextEntry::make('emp_info')
                            ->label('')
                            ->getStateUsing(function () use ($sku): string {
                                if ($sku === null) {
                                    return '<div class="text-gray-500">No SKU data available</div>';
                                }

                                $emps = $sku->estimatedMarketPrices()->orderBy('market')->get();

                                if ($emps->isEmpty()) {
                                    return '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">
                                        <p class="text-yellow-800 dark:text-yellow-200">⚠️ No EMP data available for this SKU</p>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">EMP (Estimated Market Price) helps benchmark pricing decisions.</p>
                                    </div>';
                                }

                                $html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $html .= '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Market</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">EMP Value</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Confidence</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Freshness</th>';
                                $html .= '</tr></thead>';
                                $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                                foreach ($emps as $emp) {
                                    $confidenceColor = $emp->confidence_level->color();
                                    $freshnessIndicator = $emp->getFreshnessIndicator();
                                    $freshnessColorClass = match ($freshnessIndicator) {
                                        'Fresh' => 'text-green-600',
                                        'Recent' => 'text-yellow-600',
                                        default => 'text-red-600',
                                    };

                                    $html .= '<tr>';
                                    $html .= '<td class="px-4 py-2 text-sm font-medium">'.$emp->market.'</td>';
                                    $html .= '<td class="px-4 py-2 text-sm">€'.number_format((float) $emp->emp_value, 2).'</td>';
                                    $html .= '<td class="px-4 py-2 text-sm"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$confidenceColor.'-100 text-'.$confidenceColor.'-800 dark:bg-'.$confidenceColor.'-900 dark:text-'.$confidenceColor.'-300">'.$emp->confidence_level->label().'</span></td>';
                                    $html .= '<td class="px-4 py-2 text-sm '.$freshnessColorClass.'">'.$freshnessIndicator.'</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 5: Priority & Conflicts - Conflicting offers, resolution rules.
     */
    protected function getPriorityConflictsTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;

        return Tab::make('Priority & Conflicts')
            ->icon('heroicon-o-exclamation-triangle')
            ->badge(fn (): ?int => $this->getConflictingOffersCount() > 0 ? $this->getConflictingOffersCount() : null)
            ->badgeColor('warning')
            ->schema([
                Section::make('Conflict Detection')
                    ->description('Other offers that may conflict with this one')
                    ->schema([
                        TextEntry::make('conflicts_info')
                            ->label('')
                            ->getStateUsing(function (): string {
                                $conflicts = $this->getConflictingOffers();

                                if ($conflicts->isEmpty()) {
                                    return '<div class="p-4 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-800">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                            <span class="font-medium text-green-800 dark:text-green-200">No conflicts detected</span>
                                        </div>
                                        <p class="text-sm text-green-700 dark:text-green-300 mt-1">This offer does not overlap with other active offers for the same SKU and channel.</p>
                                    </div>';
                                }

                                $html = '<div class="space-y-3">';
                                $html .= '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">';
                                $html .= '<p class="font-medium text-yellow-800 dark:text-yellow-200">⚠️ '.$conflicts->count().' conflicting offer(s) found</p>';
                                $html .= '<p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">These offers target the same SKU and channel with overlapping validity periods.</p>';
                                $html .= '</div>';

                                $html .= '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $html .= '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Offer</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valid From</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valid To</th>';
                                $html .= '</tr></thead>';
                                $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                                foreach ($conflicts as $conflict) {
                                    /** @var Offer $conflict */
                                    $statusColor = $conflict->status->color();
                                    $validTo = $conflict->valid_to?->format('M d, Y H:i') ?? 'Indefinite';

                                    $html .= '<tr>';
                                    $html .= '<td class="px-4 py-2 text-sm font-medium">'.$conflict->name.'</td>';
                                    $html .= '<td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-'.$statusColor.'-100 text-'.$statusColor.'-800 dark:bg-'.$statusColor.'-900 dark:text-'.$statusColor.'-300">'.$conflict->status->label().'</span></td>';
                                    $html .= '<td class="px-4 py-2 text-sm">'.$conflict->valid_from->format('M d, Y H:i').'</td>';
                                    $html .= '<td class="px-4 py-2 text-sm">'.$validTo.'</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution Rules')
                    ->description('How conflicts are resolved when multiple offers apply')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('resolution_rules')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-4">
                                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                        <p class="font-medium text-blue-800 dark:text-blue-200 mb-2">Offer Resolution Priority</p>
                                        <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                            <li><strong>Validity Period:</strong> Only offers within their validity period are considered</li>
                                            <li><strong>Status:</strong> Only active offers are eligible</li>
                                            <li><strong>Eligibility:</strong> Customer must match all eligibility criteria</li>
                                            <li><strong>Most Recent:</strong> If multiple offers qualify, the most recently created wins</li>
                                        </ol>
                                    </div>
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <strong>Note:</strong> To avoid confusion, it is recommended to ensure only one offer is active for a given SKU + Channel combination at any time.
                                        </p>
                                    </div>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Offer Priority')
                    ->description('This offer\'s position in the resolution order')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('offer_status_priority')
                                    ->label('Status')
                                    ->getStateUsing(fn (): string => $record->isActive() ? 'Active (Eligible)' : $record->status->label().' (Not Eligible)')
                                    ->badge()
                                    ->color(fn (): string => $record->isActive() ? 'success' : 'gray'),
                                TextEntry::make('validity_priority')
                                    ->label('Validity')
                                    ->getStateUsing(fn (): string => $record->isWithinValidityPeriod() ? 'Within Period (Eligible)' : 'Outside Period (Not Eligible)')
                                    ->badge()
                                    ->color(fn (): string => $record->isWithinValidityPeriod() ? 'success' : 'gray'),
                                TextEntry::make('created_priority')
                                    ->label('Created')
                                    ->getStateUsing(fn (): string => $record->created_at?->format('M d, Y H:i:s') ?? '-')
                                    ->size(TextSize::Small),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 6: Simulation - Price testing for this offer.
     */
    protected function getSimulationTab(): Tab
    {
        /** @var Offer $record */
        $record = $this->record;

        return Tab::make('Simulation')
            ->icon('heroicon-o-beaker')
            ->schema([
                Section::make('Price Testing')
                    ->description('Test how this offer applies to different scenarios')
                    ->schema([
                        TextEntry::make('simulation_intro')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                    <h3 class="font-medium text-blue-800 dark:text-blue-200 mb-2">🧪 Simulation for This Offer</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Use this section to test how this offer would apply to different customer scenarios.
                                        The simulation shows whether a customer would be eligible for this offer and what price they would pay.
                                    </p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Current Offer Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('sim_sku')
                                    ->label('SKU')
                                    ->getStateUsing(function () use ($record): string {
                                        $sku = $record->sellableSku;

                                        return $sku !== null ? $sku->sku_code : '-';
                                    })
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('sim_channel')
                                    ->label('Channel')
                                    ->getStateUsing(function () use ($record): string {
                                        $channel = $record->channel;

                                        return $channel !== null ? $channel->name : '-';
                                    })
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('sim_final_price')
                                    ->label('Final Price')
                                    ->getStateUsing(function () use ($record): string {
                                        $basePrice = $record->getBasePrice();
                                        if ($basePrice === null) {
                                            return 'N/A';
                                        }
                                        $benefit = $record->benefit;
                                        $finalPrice = $benefit !== null
                                            ? $benefit->calculateFinalPrice((float) $basePrice)
                                            : (float) $basePrice;
                                        $priceBook = $record->priceBook;
                                        $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                        return $currency.' '.number_format($finalPrice, 2);
                                    })
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                TextEntry::make('sim_status')
                                    ->label('Status')
                                    ->getStateUsing(fn (): string => $record->status->label())
                                    ->badge()
                                    ->color(fn (): string => $record->status->color()),
                            ]),
                    ]),

                Section::make('Eligibility Test')
                    ->description('Check if specific customers would be eligible')
                    ->schema([
                        TextEntry::make('eligibility_test_placeholder')
                            ->label('')
                            ->getStateUsing(function () use ($record): string {
                                $eligibility = $record->eligibility;

                                $html = '<div class="space-y-4">';

                                // Current eligibility rules
                                $html .= '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">';
                                $html .= '<p class="font-medium mb-2">Current Eligibility Rules:</p>';
                                $html .= '<ul class="list-disc list-inside text-sm space-y-1">';

                                if ($eligibility === null || ! $eligibility->hasMarketRestrictions()) {
                                    $html .= '<li>Markets: <span class="text-green-600 dark:text-green-400">All markets allowed</span></li>';
                                } else {
                                    $html .= '<li>Markets: <span class="text-yellow-600 dark:text-yellow-400">'.$eligibility->getMarketsDisplayString().'</span></li>';
                                }

                                if ($eligibility === null || ! $eligibility->hasCustomerTypeRestrictions()) {
                                    $html .= '<li>Customer Types: <span class="text-green-600 dark:text-green-400">All types allowed</span></li>';
                                } else {
                                    $html .= '<li>Customer Types: <span class="text-yellow-600 dark:text-yellow-400">'.$eligibility->getCustomerTypesDisplayString().'</span></li>';
                                }

                                if ($eligibility === null || ! $eligibility->hasMembershipTierRestrictions()) {
                                    $html .= '<li>Membership Tiers: <span class="text-green-600 dark:text-green-400">All tiers allowed</span></li>';
                                } else {
                                    $html .= '<li>Membership Tiers: <span class="text-yellow-600 dark:text-yellow-400">'.$eligibility->getMembershipTiersDisplayString().'</span></li>';
                                }

                                $html .= '</ul></div>';

                                // Test scenarios placeholder
                                $html .= '<div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">';
                                $html .= '<p class="text-sm text-yellow-700 dark:text-yellow-300">';
                                $html .= '<strong>💡 Tip:</strong> Use the global Simulation page (Commercial → Simulation) for full end-to-end price testing with customer selection.';
                                $html .= '</p>';
                                $html .= '</div>';

                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('What-If Analysis')
                    ->description('See how changes would affect the final price')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('what_if_analysis')
                            ->label('')
                            ->getStateUsing(function () use ($record): string {
                                $basePrice = $record->getBasePrice();
                                if ($basePrice === null) {
                                    return '<div class="text-gray-500">Base price not available for analysis</div>';
                                }

                                $basePriceFloat = (float) $basePrice;
                                $priceBook = $record->priceBook;
                                $currency = $priceBook !== null ? $priceBook->currency : 'EUR';

                                $html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $html .= '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Scenario</th>';
                                $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Result</th>';
                                $html .= '</tr></thead>';
                                $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                                // Scenario 1: No discount
                                $html .= '<tr>';
                                $html .= '<td class="px-4 py-2 text-sm">No discount (Price Book price)</td>';
                                $html .= '<td class="px-4 py-2 text-sm font-mono">'.$currency.' '.number_format($basePriceFloat, 2).'</td>';
                                $html .= '</tr>';

                                // Scenario 2: 10% discount
                                $price10 = $basePriceFloat * 0.9;
                                $html .= '<tr>';
                                $html .= '<td class="px-4 py-2 text-sm">10% discount</td>';
                                $html .= '<td class="px-4 py-2 text-sm font-mono">'.$currency.' '.number_format($price10, 2).'</td>';
                                $html .= '</tr>';

                                // Scenario 3: 20% discount
                                $price20 = $basePriceFloat * 0.8;
                                $html .= '<tr>';
                                $html .= '<td class="px-4 py-2 text-sm">20% discount</td>';
                                $html .= '<td class="px-4 py-2 text-sm font-mono">'.$currency.' '.number_format($price20, 2).'</td>';
                                $html .= '</tr>';

                                // Scenario 4: €50 discount
                                $priceFixed = max(0, $basePriceFloat - 50);
                                $html .= '<tr>';
                                $html .= '<td class="px-4 py-2 text-sm">'.$currency.' 50 discount</td>';
                                $html .= '<td class="px-4 py-2 text-sm font-mono">'.$currency.' '.number_format($priceFixed, 2).'</td>';
                                $html .= '</tr>';

                                $html .= '</tbody></table></div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 7: Audit - Full traceability.
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Audit History')
                    ->description(fn (): string => $this->getAuditFilterDescription())
                    ->headerActions([
                        Action::make('filter_audit')
                            ->label('Filter')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Select::make('event_type')
                                    ->label('Event Type')
                                    ->placeholder('All events')
                                    ->options([
                                        AuditLog::EVENT_CREATED => 'Created',
                                        AuditLog::EVENT_UPDATED => 'Updated',
                                        AuditLog::EVENT_DELETED => 'Deleted',
                                        AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                                    ])
                                    ->default($this->auditEventFilter),
                                DatePicker::make('date_from')
                                    ->label('From Date')
                                    ->default($this->auditDateFrom),
                                DatePicker::make('date_until')
                                    ->label('Until Date')
                                    ->default($this->auditDateUntil),
                            ])
                            ->action(function (array $data): void {
                                $this->auditEventFilter = isset($data['event_type']) && is_string($data['event_type']) ? $data['event_type'] : null;
                                $this->auditDateFrom = isset($data['date_from']) && is_string($data['date_from']) ? $data['date_from'] : null;
                                $this->auditDateUntil = isset($data['date_until']) && is_string($data['date_until']) ? $data['date_until'] : null;
                            }),
                        Action::make('clear_filters')
                            ->label('Clear Filters')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->visible(fn (): bool => $this->auditEventFilter !== null || $this->auditDateFrom !== null || $this->auditDateUntil !== null)
                            ->action(function (): void {
                                $this->auditEventFilter = null;
                                $this->auditDateFrom = null;
                                $this->auditDateUntil = null;
                            }),
                    ])
                    ->schema([
                        TextEntry::make('audit_logs_list')
                            ->label('')
                            ->getStateUsing(function (Offer $record): string {
                                $query = $record->auditLogs()->orderBy('created_at', 'desc');

                                if ($this->auditEventFilter) {
                                    $query->where('event', $this->auditEventFilter);
                                }

                                if ($this->auditDateFrom) {
                                    $query->whereDate('created_at', '>=', $this->auditDateFrom);
                                }

                                if ($this->auditDateUntil) {
                                    $query->whereDate('created_at', '<=', $this->auditDateUntil);
                                }

                                $logs = $query->get();

                                if ($logs->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No audit logs found matching the current filters.</div>';
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($logs as $log) {
                                    /** @var AuditLog $log */
                                    $eventColor = $log->getEventColor();
                                    $eventLabel = $log->getEventLabel();
                                    $user = $log->user;
                                    $userName = $user !== null ? $user->name : 'System';
                                    $timestamp = $log->created_at->format('M d, Y H:i:s');
                                    $changes = self::formatAuditChanges($log);

                                    $colorClass = match ($eventColor) {
                                        'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    };

                                    $html .= <<<HTML
                                    <div class="flex items-start gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colorClass}">
                                                {$eventLabel}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                    {$userName}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    {$timestamp}
                                                </span>
                                            </div>
                                            <div class="text-sm">{$changes}</div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                TextEntry::make('deleted_at')
                                    ->label('Deleted')
                                    ->dateTime()
                                    ->placeholder('Not deleted'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this offer, ensuring compliance and traceability.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Complete modification history'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
                AuditLog::EVENT_DELETED => 'Deleted',
                AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                default => $this->auditEventFilter,
            };
            $filters[] = "Event: {$eventLabel}";
        }
        if ($this->auditDateFrom) {
            $filters[] = "From: {$this->auditDateFrom}";
        }
        if ($this->auditDateUntil) {
            $filters[] = "Until: {$this->auditDateUntil}";
        }

        if (! empty($filters)) {
            $parts[] = 'Filters: '.implode(', ', $filters);
        }

        return implode(' | ', $parts);
    }

    /**
     * Get conflicting offers count.
     */
    protected function getConflictingOffersCount(): int
    {
        return $this->getConflictingOffers()->count();
    }

    /**
     * Get conflicting offers.
     *
     * @return Collection<int, Offer>
     */
    protected function getConflictingOffers(): Collection
    {
        /** @var Offer $record */
        $record = $this->record;

        return Offer::query()
            ->where('id', '!=', $record->id)
            ->where('sellable_sku_id', $record->sellable_sku_id)
            ->where('channel_id', $record->channel_id)
            ->whereIn('status', [OfferStatus::Active, OfferStatus::Draft])
            ->where(function ($query) use ($record) {
                // Check for date overlap
                $query->where(function ($q) use ($record) {
                    // Case 1: Other offer starts during this offer's period
                    $q->where('valid_from', '<=', $record->valid_to ?? now()->addYears(100))
                        ->where(function ($inner) use ($record) {
                            $inner->whereNull('valid_to')
                                ->orWhere('valid_to', '>=', $record->valid_from);
                        });
                });
            })
            ->orderBy('valid_from')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Offer $record): bool => $record->isEditable()),

            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Offer $record): bool => $record->canBeActivated())
                ->requiresConfirmation()
                ->modalHeading('Activate Offer')
                ->modalDescription(function (Offer $record): string {
                    $validation = $record->canActivateWithValidations();
                    $warnings = [];

                    // Show validation errors as warnings in the modal
                    foreach ($validation['errors'] as $error) {
                        $warnings[] = "⚠️ {$error}";
                    }

                    // Check for conflicts (not a blocking error, just a warning)
                    $conflicts = $this->getConflictingOffers()->where('status', OfferStatus::Active);
                    if ($conflicts->isNotEmpty()) {
                        $warnings[] = '⚠️ '.$conflicts->count().' active offer(s) exist for the same SKU/Channel';
                    }

                    $warningText = empty($warnings) ? '' : "\n\n".implode("\n", $warnings);

                    return "This will activate the offer and make it available for purchase.{$warningText}";
                })
                ->action(function (Offer $record): void {
                    // Run full validation
                    $validation = $record->canActivateWithValidations();

                    if (! $validation['valid']) {
                        $errorMessages = implode("\n", $validation['errors']);

                        Notification::make()
                            ->danger()
                            ->title('Cannot activate offer')
                            ->body("The following validation errors must be resolved:\n\n{$errorMessages}")
                            ->persistent()
                            ->send();

                        return;
                    }

                    $record->update(['status' => OfferStatus::Active]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => OfferStatus::Draft->value],
                        'new_values' => ['status' => OfferStatus::Active->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Offer activated')
                        ->body("The offer \"{$record->name}\" is now active.")
                        ->send();
                }),

            Action::make('pause')
                ->label('Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (Offer $record): bool => $record->canBePaused())
                ->requiresConfirmation()
                ->modalHeading('Pause Offer')
                ->modalDescription('This will pause the offer. Customers will not be able to purchase while paused.')
                ->action(function (Offer $record): void {
                    $record->update(['status' => OfferStatus::Paused]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => OfferStatus::Active->value],
                        'new_values' => ['status' => OfferStatus::Paused->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Offer paused')
                        ->body("The offer \"{$record->name}\" has been paused.")
                        ->send();
                }),

            Action::make('resume')
                ->label('Resume')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (Offer $record): bool => $record->canBeResumed())
                ->requiresConfirmation()
                ->modalHeading('Resume Offer')
                ->modalDescription('This will resume the offer, making it available for purchase again.')
                ->action(function (Offer $record): void {
                    $record->update(['status' => OfferStatus::Active]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => OfferStatus::Paused->value],
                        'new_values' => ['status' => OfferStatus::Active->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Offer resumed')
                        ->body("The offer \"{$record->name}\" is now active again.")
                        ->send();
                }),

            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Offer $record): bool => $record->canBeCancelled())
                ->requiresConfirmation()
                ->modalHeading('Cancel Offer')
                ->modalDescription('This will permanently cancel the offer. Cancelled offers cannot be reactivated. This action is irreversible.')
                ->action(function (Offer $record): void {
                    $oldStatus = $record->status;
                    $record->update(['status' => OfferStatus::Cancelled]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => $oldStatus->value],
                        'new_values' => ['status' => OfferStatus::Cancelled->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Offer cancelled')
                        ->body("The offer \"{$record->name}\" has been cancelled.")
                        ->send();
                }),
        ];
    }

    /**
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        /** @var array<string, mixed> $oldValues */
        $oldValues = $log->old_values ?? [];
        /** @var array<string, mixed> $newValues */
        $newValues = $log->new_values ?? [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            $fieldCount = count($newValues);

            return "<span class='text-sm text-gray-500'>{$fieldCount} field(s) set</span>";
        }

        if ($log->event === AuditLog::EVENT_DELETED) {
            return "<span class='text-sm text-gray-500'>Record deleted</span>";
        }

        $changes = [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $fieldLabel = ucfirst(str_replace('_', ' ', (string) $field));
                $oldDisplay = self::formatValue($oldValue);
                $newDisplay = self::formatValue($newValue);
                $changes[] = "<strong>{$fieldLabel}</strong>: {$oldDisplay} → {$newDisplay}";
            }
        }

        return count($changes) > 0
            ? '<span class="text-sm">'.implode('<br>', $changes).'</span>'
            : '<span class="text-sm text-gray-500">No field changes</span>';
    }

    /**
     * Format a value for display in audit logs.
     */
    protected static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">empty</em>';
        }

        if (is_array($value)) {
            return '<em class="text-gray-500">['.count($value).' items]</em>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            $stringValue = (string) $value;
            if (strlen($stringValue) > 50) {
                return htmlspecialchars(substr($stringValue, 0, 47)).'...';
            }

            return htmlspecialchars($stringValue);
        }

        return '<em class="text-gray-400">[complex value]</em>';
    }
}
