<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderResource\Pages;

use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Customer\Customer;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
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
            // Future steps will be added in subsequent US stories:
            // US-C019: getVoucherSelectionStep()
            // US-C020: getShippingMethodStep()
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

        return $data;
    }

    /**
     * After creating the shipping order.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Shipping Order Created')
            ->body('Draft Shipping Orders require planning before execution. Add vouchers and plan the order when ready.')
            ->send();
    }
}
