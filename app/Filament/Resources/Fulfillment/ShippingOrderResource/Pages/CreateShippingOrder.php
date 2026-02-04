<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\Carrier;
use App\Enums\Fulfillment\Incoterms;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Fulfillment\ShippingOrder;
use App\Models\Fulfillment\ShippingOrderLine;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class CreateShippingOrder extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ShippingOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Create Shipping Order';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Create a new shipping order using the step-by-step wizard';
    }

    /**
     * Get the form for creating a shipping order.
     * Implements a multi-step wizard for SO creation.
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
     * Get the wizard submit action button.
     */
    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <x-filament::button
                    type="submit"
                    size="sm"
                    color="primary"
                >
                    Create Draft
                </x-filament::button>
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
            $this->getCustomerAndDestinationStep(),
            $this->getVoucherSelectionStep(),
            $this->getShippingMethodStep(),
            // Future steps will be added in subsequent US stories:
            // US-C021: getPackagingPreferencesStep()
            // US-C022: getReviewAndSubmitStep()
        ];
    }

    /**
     * Step 1: Customer & Destination Selection (US-C018)
     * Allows selection of Customer with autocomplete and destination address entry.
     */
    protected function getCustomerAndDestinationStep(): Wizard\Step
    {
        return Wizard\Step::make('Customer & Destination')
            ->description('Select the customer and destination address')
            ->icon('heroicon-o-user')
            ->schema([
                Forms\Components\Section::make('Customer Selection')
                    ->description('Search and select the customer for this shipping order')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->placeholder('Search for a customer by name or email...')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return Customer::query()
                                    ->where('status', Customer::STATUS_ACTIVE)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Customer $customer): array => [
                                        $customer->id => self::formatCustomerOption($customer),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (string $value): ?string {
                                $customer = Customer::find($value);

                                return $customer !== null ? self::formatCustomerOption($customer) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                // Clear destination address when customer changes
                                $set('destination_address', null);
                            })
                            ->required()
                            ->helperText('Type at least 2 characters to search for customers. Only active customers are shown.'),

                        // Customer info placeholder (shown when customer is selected)
                        Forms\Components\Placeholder::make('customer_info')
                            ->label('Customer Details')
                            ->visible(fn (Get $get): bool => $get('customer_id') !== null)
                            ->content(function (Get $get): HtmlString {
                                $customerId = $get('customer_id');
                                if (! $customerId) {
                                    return new HtmlString('');
                                }

                                $customer = Customer::find($customerId);
                                if (! $customer) {
                                    return new HtmlString('<p class="text-red-500">Customer not found</p>');
                                }

                                // Check eligibility (Module K checks - for now just status)
                                $isEligible = $customer->isActive();
                                $statusBadge = $isEligible
                                    ? '<span class="px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">Active</span>'
                                    : '<span class="px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">'.ucfirst($customer->status).'</span>';

                                $emailHtml = $customer->email
                                    ? '<p class="text-gray-600 dark:text-gray-300"><strong>Email:</strong> '.e($customer->email).'</p>'
                                    : '';

                                $voucherCount = $customer->vouchers()
                                    ->where('lifecycle_state', 'issued')
                                    ->where('suspended', false)
                                    ->count();

                                return new HtmlString(<<<HTML
                                    <div class="text-sm space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-200">Status:</span>
                                            {$statusBadge}
                                        </div>
                                        {$emailHtml}
                                        <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                            <p class="text-gray-600 dark:text-gray-300"><strong>{$voucherCount}</strong> eligible voucher(s) available for shipping</p>
                                        </div>
                                    </div>
                                HTML);
                            }),

                        // Eligibility warning (shown when customer not eligible)
                        Forms\Components\Placeholder::make('eligibility_warning')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $customerId = $get('customer_id');
                                if (! $customerId) {
                                    return false;
                                }

                                $customer = Customer::find($customerId);

                                return $customer !== null && ! $customer->isActive();
                            })
                            ->content(function (Get $get): HtmlString {
                                $customerId = $get('customer_id');
                                $customer = $customerId ? Customer::find($customerId) : null;

                                if (! $customer) {
                                    return new HtmlString('');
                                }

                                $reason = match ($customer->status) {
                                    Customer::STATUS_SUSPENDED => 'This customer account is suspended. Please resolve the suspension before creating a shipping order.',
                                    Customer::STATUS_CLOSED => 'This customer account is closed. Shipping orders cannot be created for closed accounts.',
                                    default => 'This customer is not eligible for shipping orders.',
                                };

                                return new HtmlString(<<<HTML
                                    <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-danger-600 dark:text-danger-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-danger-700 dark:text-danger-300">
                                                    <strong>Customer Not Eligible</strong><br>
                                                    {$reason}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                HTML);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Destination Address')
                    ->description('Enter the shipping destination address')
                    ->schema([
                        // Note: When Module K (Addresses) is implemented, this will show
                        // a select of saved addresses plus an option to add new
                        Forms\Components\Placeholder::make('saved_addresses_info')
                            ->label('')
                            ->content(new HtmlString(<<<'HTML'
                                <div class="p-3 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
                                    <p class="text-sm text-info-700 dark:text-info-300">
                                        <strong>Note:</strong> Saved customer addresses will be available when Module K (Customer Addresses) is implemented.
                                        For now, please enter the destination address manually below.
                                    </p>
                                </div>
                            HTML))
                            ->visible(fn (Get $get): bool => $get('customer_id') !== null)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('destination_address')
                            ->label('Destination Address')
                            ->placeholder("Enter the full shipping address...\n\nExample:\nJohn Doe\n123 Wine Street\nBordeaux 33000\nFrance")
                            ->rows(5)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('customer_id') !== null)
                            ->helperText('Enter the complete shipping address including recipient name, street, city, postal code, and country.')
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('customer_id') === null)
                    ->columns(1),
            ])
            ->afterValidation(function (Get $get): void {
                // Validate customer eligibility
                $customerId = $get('customer_id');
                if (! $customerId) {
                    return;
                }

                $customer = Customer::find($customerId);
                if (! $customer) {
                    Notification::make()
                        ->title('Customer not found')
                        ->body('The selected customer no longer exists.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }

                if (! $customer->isActive()) {
                    Notification::make()
                        ->title('Customer not eligible')
                        ->body('The selected customer is not eligible for shipping orders. Status: '.ucfirst($customer->status))
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }
            });
    }

    /**
     * Step 2: Voucher Selection (US-C019)
     * Allows multi-select of customer vouchers for the shipping order.
     */
    protected function getVoucherSelectionStep(): Wizard\Step
    {
        return Wizard\Step::make('Voucher Selection')
            ->description('Select vouchers to include in this shipment')
            ->icon('heroicon-o-ticket')
            ->schema([
                Forms\Components\Section::make('Available Vouchers')
                    ->description('Select the vouchers to ship to this customer')
                    ->schema([
                        // Info about voucher selection
                        Forms\Components\Placeholder::make('voucher_selection_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $customerId = $get('customer_id');
                                if (! $customerId) {
                                    return new HtmlString('<p class="text-gray-500">Please select a customer first.</p>');
                                }

                                $voucherCounts = $this->getVoucherCounts($customerId);

                                return new HtmlString(<<<HTML
                                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                                            <div>
                                                <div class="text-2xl font-bold text-success-600 dark:text-success-400">{$voucherCounts['eligible']}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Eligible</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">{$voucherCounts['locked']}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Locked</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{$voucherCounts['suspended']}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Suspended</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{$voucherCounts['other']}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Other</div>
                                            </div>
                                        </div>
                                    </div>
                                HTML);
                            }),

                        // Suspended vouchers warning
                        Forms\Components\Placeholder::make('suspended_warning')
                            ->label('')
                            ->visible(function (Get $get): bool {
                                $customerId = $get('customer_id');
                                if (! $customerId) {
                                    return false;
                                }

                                return Voucher::where('customer_id', $customerId)
                                    ->where('lifecycle_state', VoucherLifecycleState::Issued)
                                    ->where('suspended', true)
                                    ->exists();
                            })
                            ->content(new HtmlString(<<<'HTML'
                                <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-warning-700 dark:text-warning-300">
                                                <strong>Some vouchers are suspended</strong><br>
                                                Suspended vouchers cannot be selected for shipping. They are shown below for reference but are not selectable.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            HTML)),

                        // Filters section
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('voucher_filter_wine')
                                    ->label('Filter by Wine')
                                    ->placeholder('Search wine name...')
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(fn (Set $set) => $set('selected_vouchers', [])),

                                Forms\Components\Select::make('voucher_filter_allocation')
                                    ->label('Filter by Allocation')
                                    ->placeholder('All allocations')
                                    ->options(function (Get $get): array {
                                        $customerId = $get('customer_id');
                                        if (! $customerId) {
                                            return [];
                                        }

                                        $allocationIds = Voucher::where('customer_id', $customerId)
                                            ->where('lifecycle_state', VoucherLifecycleState::Issued)
                                            ->where('suspended', false)
                                            ->distinct()
                                            ->pluck('allocation_id');

                                        return $allocationIds
                                            ->mapWithKeys(function ($allocationId) {
                                                $shortId = substr((string) $allocationId, 0, 8);

                                                return [$allocationId => "Allocation: {$shortId}..."];
                                            })
                                            ->toArray();
                                    })
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('selected_vouchers', [])),
                            ]),

                        // Voucher selection checklist
                        Forms\Components\CheckboxList::make('selected_vouchers')
                            ->label('Select Vouchers')
                            ->required()
                            ->minItems(1)
                            ->options(function (Get $get): array {
                                return $this->getEligibleVoucherOptions($get);
                            })
                            ->descriptions(function (Get $get): array {
                                return $this->getVoucherDescriptions($get);
                            })
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row')
                            ->columns(1)
                            ->helperText('At least one voucher must be selected')
                            ->validationMessages([
                                'required' => 'At least one voucher must be selected.',
                                'min' => 'At least one voucher must be selected.',
                            ]),

                        // Selected vouchers count
                        Forms\Components\Placeholder::make('selected_count')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $selected = $get('selected_vouchers') ?? [];
                                $count = count($selected);

                                if ($count === 0) {
                                    return new HtmlString('<p class="text-sm text-gray-500">No vouchers selected</p>');
                                }

                                $plural = $count === 1 ? 'voucher' : 'vouchers';

                                return new HtmlString(<<<HTML
                                    <div class="p-3 rounded-lg bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800">
                                        <p class="text-sm text-success-700 dark:text-success-300">
                                            <strong>{$count}</strong> {$plural} selected for shipping
                                        </p>
                                    </div>
                                HTML);
                            }),
                    ])
                    ->columns(1),
            ])
            ->afterValidation(function (Get $get): void {
                $selectedVouchers = $get('selected_vouchers') ?? [];

                if (count($selectedVouchers) === 0) {
                    Notification::make()
                        ->title('No vouchers selected')
                        ->body('Please select at least one voucher to include in the shipping order.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }

                // Validate that selected vouchers are still eligible
                $customerId = $get('customer_id');
                $invalidVouchers = Voucher::whereIn('id', $selectedVouchers)
                    ->where(function ($query) use ($customerId) {
                        $query->where('customer_id', '!=', $customerId)
                            ->orWhere('lifecycle_state', '!=', VoucherLifecycleState::Issued)
                            ->orWhere('suspended', true);
                    })
                    ->count();

                if ($invalidVouchers > 0) {
                    Notification::make()
                        ->title('Invalid voucher selection')
                        ->body('Some selected vouchers are no longer eligible. Please refresh and try again.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }

                // Check if any vouchers are already in another active SO
                $vouchersInOtherSO = $this->getVouchersInActiveSOs($selectedVouchers);
                if ($vouchersInOtherSO->count() > 0) {
                    $voucherIds = $vouchersInOtherSO->pluck('id')->take(3)->implode(', ');
                    Notification::make()
                        ->title('Vouchers already in Shipping Order')
                        ->body("Some vouchers are already assigned to another shipping order: {$voucherIds}")
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }
            });
    }

    /**
     * Step 3: Shipping Method (US-C020)
     * Allows configuration of carrier, shipping method, incoterms, and requested ship date.
     */
    protected function getShippingMethodStep(): Wizard\Step
    {
        return Wizard\Step::make('Shipping Method')
            ->description('Configure shipping details and delivery preferences')
            ->icon('heroicon-o-truck')
            ->schema([
                Forms\Components\Section::make('Carrier & Method')
                    ->description('Select the carrier and shipping method')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('carrier')
                                    ->label('Carrier')
                                    ->placeholder('Select a carrier...')
                                    ->options(
                                        collect(Carrier::cases())
                                            ->mapWithKeys(fn (Carrier $carrier) => [$carrier->value => $carrier->label()])
                                            ->toArray()
                                    )
                                    ->helperText(function (Get $get): string {
                                        $carrierValue = $get('carrier');
                                        if (! $carrierValue) {
                                            return 'Choose from available shipping carriers';
                                        }

                                        $carrier = Carrier::tryFrom($carrierValue);

                                        return $carrier ? $carrier->description() : '';
                                    })
                                    ->live()
                                    ->required(),

                                Forms\Components\TextInput::make('shipping_method')
                                    ->label('Shipping Method')
                                    ->placeholder('e.g., Express, Standard, Economy...')
                                    ->helperText('Specify the shipping service tier or method')
                                    ->maxLength(255),
                            ]),

                        // Carrier info note for "Other"
                        Forms\Components\Placeholder::make('other_carrier_note')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('carrier') === Carrier::Other->value)
                            ->content(new HtmlString(<<<'HTML'
                                <div class="p-3 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
                                    <p class="text-sm text-info-700 dark:text-info-300">
                                        <strong>Note:</strong> When using "Other" carrier, please specify the carrier name in the Shipping Method field above.
                                    </p>
                                </div>
                            HTML))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Terms & Schedule')
                    ->description('Set delivery terms and requested ship date')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('incoterms')
                                    ->label('Incoterms')
                                    ->placeholder('Select incoterms...')
                                    ->options(
                                        collect(Incoterms::cases())
                                            ->mapWithKeys(fn (Incoterms $term) => [$term->value => $term->label()])
                                            ->toArray()
                                    )
                                    ->helperText(function (Get $get): string {
                                        $termsValue = $get('incoterms');
                                        if (! $termsValue) {
                                            return 'International Commercial Terms defining buyer/seller responsibilities';
                                        }

                                        $terms = Incoterms::tryFrom($termsValue);

                                        return $terms ? $terms->description() : '';
                                    })
                                    ->live(),

                                Forms\Components\DatePicker::make('requested_ship_date')
                                    ->label('Requested Ship Date')
                                    ->placeholder('Select date...')
                                    ->minDate(now()->startOfDay())
                                    ->helperText('Date must be today or in the future')
                                    ->displayFormat('d M Y')
                                    ->native(false),
                            ]),

                        // Incoterms explanation
                        Forms\Components\Placeholder::make('incoterms_explanation')
                            ->label('')
                            ->visible(fn (Get $get): bool => $get('incoterms') !== null)
                            ->content(function (Get $get): HtmlString {
                                $termsValue = $get('incoterms');
                                if (! $termsValue) {
                                    return new HtmlString('');
                                }

                                $terms = Incoterms::tryFrom($termsValue);
                                if (! $terms) {
                                    return new HtmlString('');
                                }

                                $dutiesInfo = $terms->sellerPaysImportDuties()
                                    ? '<span class="text-success-600 dark:text-success-400">✓ Seller pays import duties</span>'
                                    : '<span class="text-gray-500 dark:text-gray-400">✗ Buyer pays import duties</span>';

                                $insuranceInfo = $terms->sellerPaysInsurance()
                                    ? '<span class="text-success-600 dark:text-success-400">✓ Seller arranges insurance</span>'
                                    : '<span class="text-gray-500 dark:text-gray-400">✗ Buyer arranges insurance</span>';

                                return new HtmlString(<<<HTML
                                    <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="text-sm space-y-1">
                                            <p class="font-medium text-gray-700 dark:text-gray-300">Responsibilities:</p>
                                            <div class="ml-2">
                                                <p>{$dutiesInfo}</p>
                                                <p>{$insuranceInfo}</p>
                                            </div>
                                        </div>
                                    </div>
                                HTML);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Special Instructions')
                    ->description('Additional notes or requirements for this shipment')
                    ->schema([
                        Forms\Components\Textarea::make('special_instructions')
                            ->label('Special Instructions')
                            ->placeholder("Enter any special handling instructions, delivery notes, or requirements...\n\nExamples:\n- Fragile wine, handle with care\n- Temperature controlled storage required\n- Delivery appointment required")
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('Optional. Include any special handling or delivery requirements.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsed(false),
            ])
            ->afterValidation(function (Get $get): void {
                // Validate requested_ship_date is not in the past
                $requestedShipDate = $get('requested_ship_date');
                if ($requestedShipDate) {
                    $date = \Carbon\Carbon::parse($requestedShipDate);
                    if ($date->isBefore(now()->startOfDay())) {
                        Notification::make()
                            ->title('Invalid Ship Date')
                            ->body('Requested ship date cannot be in the past.')
                            ->danger()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt;
                    }
                }
            });
    }

    /**
     * Get voucher counts for display.
     *
     * @return array{eligible: int, locked: int, suspended: int, other: int}
     */
    protected function getVoucherCounts(string $customerId): array
    {
        $vouchers = Voucher::where('customer_id', $customerId)->get();

        $eligible = $vouchers->filter(fn (Voucher $v) => $v->lifecycle_state === VoucherLifecycleState::Issued && ! $v->suspended)->count();

        $locked = $vouchers->filter(fn (Voucher $v) => $v->lifecycle_state === VoucherLifecycleState::Locked)->count();

        $suspended = $vouchers->filter(fn (Voucher $v) => $v->lifecycle_state === VoucherLifecycleState::Issued && $v->suspended)->count();

        $other = $vouchers->count() - $eligible - $locked - $suspended;

        return [
            'eligible' => $eligible,
            'locked' => $locked,
            'suspended' => $suspended,
            'other' => $other,
        ];
    }

    /**
     * Get eligible voucher options for the checkbox list.
     *
     * @return array<string, string>
     */
    protected function getEligibleVoucherOptions(Get $get): array
    {
        $customerId = $get('customer_id');
        if (! $customerId) {
            return [];
        }

        $wineFilter = $get('voucher_filter_wine');
        $allocationFilter = $get('voucher_filter_allocation');

        $query = Voucher::query()
            ->with(['wineVariant.wineMaster', 'format', 'allocation'])
            ->where('customer_id', $customerId)
            ->where('lifecycle_state', VoucherLifecycleState::Issued)
            ->where('suspended', false);

        // Apply wine name filter
        if ($wineFilter) {
            $query->whereHas('wineVariant.wineMaster', function ($q) use ($wineFilter) {
                $q->where('name', 'like', "%{$wineFilter}%");
            });
        }

        // Apply allocation filter
        if ($allocationFilter) {
            $query->where('allocation_id', $allocationFilter);
        }

        // Exclude vouchers already in active SOs
        $vouchersInActiveSOs = $this->getVoucherIdsInActiveSOs();
        if ($vouchersInActiveSOs->isNotEmpty()) {
            $query->whereNotIn('id', $vouchersInActiveSOs);
        }

        return $query->get()
            ->mapWithKeys(function (Voucher $voucher) {
                $label = $this->formatVoucherOption($voucher);

                return [$voucher->id => $label];
            })
            ->toArray();
    }

    /**
     * Get voucher descriptions for the checkbox list.
     *
     * @return array<string, string>
     */
    protected function getVoucherDescriptions(Get $get): array
    {
        $customerId = $get('customer_id');
        if (! $customerId) {
            return [];
        }

        $wineFilter = $get('voucher_filter_wine');
        $allocationFilter = $get('voucher_filter_allocation');

        $query = Voucher::query()
            ->with(['wineVariant.wineMaster', 'format', 'allocation'])
            ->where('customer_id', $customerId)
            ->where('lifecycle_state', VoucherLifecycleState::Issued)
            ->where('suspended', false);

        // Apply wine name filter
        if ($wineFilter) {
            $query->whereHas('wineVariant.wineMaster', function ($q) use ($wineFilter) {
                $q->where('name', 'like', "%{$wineFilter}%");
            });
        }

        // Apply allocation filter
        if ($allocationFilter) {
            $query->where('allocation_id', $allocationFilter);
        }

        // Exclude vouchers already in active SOs
        $vouchersInActiveSOs = $this->getVoucherIdsInActiveSOs();
        if ($vouchersInActiveSOs->isNotEmpty()) {
            $query->whereNotIn('id', $vouchersInActiveSOs);
        }

        return $query->get()
            ->mapWithKeys(function (Voucher $voucher) {
                $description = $this->formatVoucherDescription($voucher);

                return [$voucher->id => $description];
            })
            ->toArray();
    }

    /**
     * Format a voucher for display in the options list.
     */
    protected function formatVoucherOption(Voucher $voucher): string
    {
        $wineVariant = $voucher->wineVariant;
        $format = $voucher->format;

        $wineName = 'Unknown Wine';
        $vintage = 'NV';

        if ($wineVariant) {
            $wineMaster = $wineVariant->wineMaster;
            if ($wineMaster) {
                $wineName = $wineMaster->name;
            }
            $vintage = $wineVariant->vintage_year ?? 'NV';
        }

        $formatLabel = $format ? "{$format->volume_ml}ml" : 'Unknown Format';

        // Check for early binding (personalized voucher)
        $earlyBindingBadge = '';
        // Note: Early binding is tracked in ShippingOrderLine, not Voucher directly
        // For personalized vouchers from Module D, we would need additional logic

        $voucherId = substr($voucher->id, 0, 8);

        return "#{$voucherId} - {$wineName} {$vintage} ({$formatLabel})";
    }

    /**
     * Format a voucher description for the checkbox list.
     */
    protected function formatVoucherDescription(Voucher $voucher): string
    {
        $allocation = $voucher->allocation;
        $allocationRef = $allocation ? substr($allocation->id, 0, 8) : 'Unknown';

        return "Allocation: {$allocationRef}";
    }

    /**
     * Get IDs of vouchers that are already in active Shipping Orders.
     *
     * @return Collection<int, string>
     */
    protected function getVoucherIdsInActiveSOs(): Collection
    {
        return ShippingOrderLine::query()
            ->whereHas('shippingOrder', function ($q) {
                $q->whereIn('status', [
                    ShippingOrderStatus::Draft,
                    ShippingOrderStatus::Planned,
                    ShippingOrderStatus::Picking,
                    ShippingOrderStatus::OnHold,
                ]);
            })
            ->pluck('voucher_id');
    }

    /**
     * Get vouchers that are already in active SOs.
     *
     * @param  array<string>  $voucherIds
     * @return Collection<int, Voucher>
     */
    protected function getVouchersInActiveSOs(array $voucherIds): Collection
    {
        $existingVoucherIds = ShippingOrderLine::query()
            ->whereHas('shippingOrder', function ($q) {
                $q->whereIn('status', [
                    ShippingOrderStatus::Draft,
                    ShippingOrderStatus::Planned,
                    ShippingOrderStatus::Picking,
                    ShippingOrderStatus::OnHold,
                ]);
            })
            ->whereIn('voucher_id', $voucherIds)
            ->pluck('voucher_id');

        return Voucher::whereIn('id', $existingVoucherIds)->get();
    }

    /**
     * Format a Customer record for display in select options.
     */
    protected static function formatCustomerOption(Customer $customer): string
    {
        $label = $customer->name;

        $email = $customer->email;
        if ($email !== '') {
            $label .= ' <'.$email.'>';
        }

        return $label;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure new orders start in draft status
        $data['status'] = ShippingOrderStatus::Draft->value;
        $data['created_by'] = auth()->id();

        // Remove non-model fields that are only used for the form
        unset($data['voucher_filter_wine']);
        unset($data['voucher_filter_allocation']);
        unset($data['selected_vouchers']);

        return $data;
    }

    /**
     * After creating the shipping order.
     */
    protected function afterCreate(): void
    {
        // Get selected vouchers from form data
        $selectedVouchers = $this->data['selected_vouchers'] ?? [];

        // Create ShippingOrderLine for each selected voucher
        if (is_array($selectedVouchers) && count($selectedVouchers) > 0) {
            $vouchers = Voucher::whereIn('id', $selectedVouchers)->get();

            /** @var ShippingOrder $shippingOrder */
            $shippingOrder = $this->record;

            foreach ($vouchers as $voucher) {
                ShippingOrderLine::create([
                    'shipping_order_id' => $shippingOrder->id,
                    'voucher_id' => $voucher->id,
                    'allocation_id' => $voucher->allocation_id,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        $count = count($selectedVouchers);
        $plural = $count === 1 ? 'voucher' : 'vouchers';

        Notification::make()
            ->success()
            ->title('Shipping Order Created')
            ->body("Draft Shipping Order created with {$count} {$plural}. Planning required before execution.")
            ->send();
    }
}
