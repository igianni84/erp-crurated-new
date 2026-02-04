<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Services\Finance\InvoiceService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CreateInvoice extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Create Invoice';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Create a new draft invoice using the step-by-step wizard';
    }

    /**
     * Get the form for creating an invoice.
     * Implements a multi-step wizard for invoice creation.
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
                    Save as Draft
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
            $this->getInvoiceDetailsStep(),
            $this->getInvoiceLinesStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Invoice Details
     * Customer selection, invoice type, currency, due date, notes.
     */
    protected function getInvoiceDetailsStep(): Wizard\Step
    {
        return Wizard\Step::make('Invoice Details')
            ->description('Basic invoice information')
            ->icon('heroicon-o-document-text')
            ->schema([
                // Warning banner about manual invoices
                Forms\Components\Placeholder::make('manual_invoice_warning')
                    ->label('')
                    ->content(new HtmlString(<<<'HTML'
                        <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">Manual Invoice Creation</h3>
                                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                                        Manual invoices should be exceptional. Most invoices are auto-generated from ERP events
                                        (subscriptions, voucher sales, shipping orders, storage billing, service events).
                                        Only create manual invoices for ad-hoc or exceptional cases.
                                    </p>
                                </div>
                            </div>
                        </div>
                    HTML))
                    ->columnSpanFull(),

                Forms\Components\Section::make('Customer Selection')
                    ->description('Select the customer for this invoice')
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
                            ->required()
                            ->helperText('Type at least 2 characters to search. Only active customers are shown.'),

                        // Customer info (shown when customer is selected)
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

                                $emailHtml = $customer->email
                                    ? '<p class="text-gray-600 dark:text-gray-300"><strong>Email:</strong> '.e($customer->email).'</p>'
                                    : '';

                                return new HtmlString(<<<HTML
                                    <div class="text-sm space-y-1 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                        <p class="text-gray-700 dark:text-gray-200"><strong>Name:</strong> {$customer->name}</p>
                                        {$emailHtml}
                                    </div>
                                HTML);
                            }),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Invoice Configuration')
                    ->description('Define the invoice type and billing details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('invoice_type')
                                    ->label('Invoice Type')
                                    ->placeholder('Select invoice type...')
                                    ->options(
                                        collect(InvoiceType::cases())
                                            ->mapWithKeys(fn (InvoiceType $type) => [
                                                $type->value => $type->code().' - '.$type->label(),
                                            ])
                                            ->toArray()
                                    )
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        // Set default due date based on invoice type
                                        if ($state) {
                                            $type = InvoiceType::from($state);
                                            $days = $type->defaultDueDateDays();
                                            if ($days !== null) {
                                                $set('due_date', now()->addDays($days)->format('Y-m-d'));
                                            } else {
                                                $set('due_date', null);
                                            }
                                        }
                                    })
                                    ->helperText(function (Get $get): string {
                                        $value = $get('invoice_type');
                                        if (! $value) {
                                            return 'Select the type of invoice to create';
                                        }

                                        $type = InvoiceType::tryFrom($value);
                                        if ($type === null) {
                                            return '';
                                        }

                                        $info = [];
                                        if ($type->requiresSourceReference()) {
                                            $info[] = 'Normally auto-generated from '.$type->expectedSourceType();
                                        }
                                        if ($type->requiresDueDate()) {
                                            $info[] = 'Due date required';
                                        }

                                        return implode('. ', $info);
                                    }),

                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'EUR' => 'EUR - Euro',
                                        'GBP' => 'GBP - British Pound',
                                        'USD' => 'USD - US Dollar',
                                    ])
                                    ->default('EUR')
                                    ->required()
                                    ->helperText('Currency cannot be changed after issuance'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->placeholder('Select due date...')
                                    ->displayFormat('d M Y')
                                    ->native(false)
                                    ->minDate(now())
                                    ->helperText(function (Get $get): string {
                                        $typeValue = $get('invoice_type');
                                        if (! $typeValue) {
                                            return 'Payment due date';
                                        }

                                        $type = InvoiceType::tryFrom($typeValue);
                                        if ($type !== null && $type->requiresDueDate()) {
                                            return 'Required for '.$type->code().' invoices';
                                        }

                                        return 'Optional for this invoice type';
                                    }),

                                Forms\Components\Placeholder::make('due_date_info')
                                    ->label('')
                                    ->visible(fn (Get $get): bool => $get('invoice_type') !== null)
                                    ->content(function (Get $get): HtmlString {
                                        $typeValue = $get('invoice_type');
                                        if (! $typeValue) {
                                            return new HtmlString('');
                                        }

                                        $type = InvoiceType::tryFrom($typeValue);
                                        if ($type === null) {
                                            return new HtmlString('');
                                        }

                                        $days = $type->defaultDueDateDays();
                                        if ($days === null) {
                                            return new HtmlString(<<<'HTML'
                                                <div class="p-2 rounded-lg bg-info-50 dark:bg-info-900/20 text-sm text-info-700 dark:text-info-300">
                                                    Immediate payment expected for this invoice type
                                                </div>
                                            HTML);
                                        }

                                        return new HtmlString(<<<HTML
                                            <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400">
                                                Default: {$days} days from issuance
                                            </div>
                                        HTML);
                                    }),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Optional internal notes or comments...')
                            ->rows(3)
                            ->maxLength(2000)
                            ->helperText('Internal notes (not shown on invoice)'),
                    ])
                    ->columns(1),
            ])
            ->afterValidation(function (Get $get): void {
                // Validate customer is still active
                $customerId = $get('customer_id');
                if ($customerId) {
                    $customer = Customer::find($customerId);
                    if ($customer === null || ! $customer->isActive()) {
                        Notification::make()
                            ->title('Customer not eligible')
                            ->body('The selected customer is not active.')
                            ->danger()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt;
                    }
                }

                // Validate due date if required
                $typeValue = $get('invoice_type');
                $dueDate = $get('due_date');
                if ($typeValue) {
                    $type = InvoiceType::tryFrom($typeValue);
                    if ($type !== null && $type->requiresDueDate() && $dueDate === null) {
                        Notification::make()
                            ->title('Due date required')
                            ->body("Due date is required for {$type->code()} invoices.")
                            ->danger()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt;
                    }
                }
            });
    }

    /**
     * Step 2: Invoice Lines
     * Add line items with description, quantity, unit price, and tax.
     */
    protected function getInvoiceLinesStep(): Wizard\Step
    {
        return Wizard\Step::make('Invoice Lines')
            ->description('Add line items to the invoice')
            ->icon('heroicon-o-list-bullet')
            ->schema([
                Forms\Components\Section::make('Line Items')
                    ->description('Add at least one line item. Tax amount is calculated automatically based on tax rate.')
                    ->schema([
                        Forms\Components\Repeater::make('invoice_lines')
                            ->label('')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('description')
                                            ->label('Description')
                                            ->placeholder('Item description...')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(5),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix(fn (Get $get): string => $this->getCurrency($get))
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        Forms\Components\Placeholder::make('line_total_display')
                                            ->label('Line Total')
                                            ->content(function (Get $get): HtmlString {
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $taxRate = (float) ($get('tax_rate') ?? 0);

                                                $subtotal = $quantity * $unitPrice;
                                                $taxAmount = $subtotal * ($taxRate / 100);
                                                $lineTotal = $subtotal + $taxAmount;

                                                $currency = $this->getCurrency($get);
                                                $formatted = number_format($lineTotal, 2);

                                                return new HtmlString(<<<HTML
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{$currency} {$formatted}</span>
                                                HTML);
                                            })
                                            ->columnSpan(3),
                                    ]),
                            ])
                            ->addActionLabel('Add Line Item')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => $state['description'] ?? 'New Line Item')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Real-time totals section
                Forms\Components\Section::make('Invoice Totals')
                    ->description('Calculated automatically from line items')
                    ->schema([
                        Forms\Components\Placeholder::make('totals_display')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $lines = $get('invoice_lines') ?? [];
                                $currency = $get('../../currency') ?? 'EUR';

                                $subtotal = 0;
                                $totalTax = 0;

                                foreach ($lines as $line) {
                                    if (is_array($line)) {
                                        $quantity = (float) ($line['quantity'] ?? 0);
                                        $unitPrice = (float) ($line['unit_price'] ?? 0);
                                        $taxRate = (float) ($line['tax_rate'] ?? 0);

                                        $lineSubtotal = $quantity * $unitPrice;
                                        $lineTax = $lineSubtotal * ($taxRate / 100);

                                        $subtotal += $lineSubtotal;
                                        $totalTax += $lineTax;
                                    }
                                }

                                $total = $subtotal + $totalTax;

                                $subtotalFormatted = number_format($subtotal, 2);
                                $taxFormatted = number_format($totalTax, 2);
                                $totalFormatted = number_format($total, 2);

                                return new HtmlString(<<<HTML
                                    <div class="grid grid-cols-2 gap-4 max-w-md ml-auto">
                                        <div class="text-right text-gray-600 dark:text-gray-400">Subtotal:</div>
                                        <div class="text-right font-medium text-gray-900 dark:text-gray-100">{$currency} {$subtotalFormatted}</div>

                                        <div class="text-right text-gray-600 dark:text-gray-400">Tax:</div>
                                        <div class="text-right font-medium text-gray-900 dark:text-gray-100">{$currency} {$taxFormatted}</div>

                                        <div class="text-right text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-2">Total:</div>
                                        <div class="text-right font-bold text-lg text-primary-600 dark:text-primary-400 border-t border-gray-200 dark:border-gray-700 pt-2">{$currency} {$totalFormatted}</div>
                                    </div>
                                HTML);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->afterValidation(function (Get $get): void {
                $lines = $get('invoice_lines') ?? [];

                // Ensure at least one line
                $validLines = array_filter($lines, function ($line) {
                    return is_array($line)
                        && ! empty($line['description'])
                        && is_numeric($line['quantity'])
                        && is_numeric($line['unit_price']);
                });

                if (count($validLines) === 0) {
                    Notification::make()
                        ->title('No line items')
                        ->body('At least one complete line item is required.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }

                // Validate total is positive
                $total = 0;
                foreach ($validLines as $line) {
                    $quantity = (float) ($line['quantity'] ?? 0);
                    $unitPrice = (float) ($line['unit_price'] ?? 0);
                    $taxRate = (float) ($line['tax_rate'] ?? 0);

                    $lineSubtotal = $quantity * $unitPrice;
                    $lineTax = $lineSubtotal * ($taxRate / 100);
                    $total += $lineSubtotal + $lineTax;
                }

                if ($total <= 0) {
                    Notification::make()
                        ->title('Invalid total')
                        ->body('Invoice total must be greater than zero.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt;
                }
            });
    }

    /**
     * Step 3: Review
     * Summary of all invoice details before saving.
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review & Save')
            ->description('Review invoice details before saving')
            ->icon('heroicon-o-clipboard-document-check')
            ->schema([
                // Draft info banner
                Forms\Components\Placeholder::make('draft_info_banner')
                    ->label('')
                    ->content(new HtmlString(<<<'HTML'
                        <div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-info-600 dark:text-info-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-info-700 dark:text-info-300">
                                        <strong>This will create a Draft invoice.</strong><br>
                                        Draft invoices can be edited until they are issued. You must Issue the invoice to generate an invoice number and make it official.
                                    </p>
                                </div>
                            </div>
                        </div>
                    HTML))
                    ->columnSpanFull(),

                // Customer & Invoice Details
                Forms\Components\Section::make('Invoice Details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\Placeholder::make('details_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $customerId = $get('customer_id');
                                $typeValue = $get('invoice_type');
                                $currency = $get('currency') ?? 'EUR';
                                $dueDate = $get('due_date');
                                $notes = $get('notes');

                                // Customer info
                                $customerHtml = '<span class="text-gray-400">Not selected</span>';
                                if ($customerId) {
                                    $customer = Customer::find($customerId);
                                    if ($customer) {
                                        $customerHtml = '<span class="font-medium text-gray-900 dark:text-gray-100">'.e($customer->name).'</span>';
                                        if ($customer->email) {
                                            $customerHtml .= '<br><span class="text-gray-500 dark:text-gray-400">'.e($customer->email).'</span>';
                                        }
                                    }
                                }

                                // Invoice type
                                $typeHtml = '<span class="text-gray-400">Not selected</span>';
                                if ($typeValue) {
                                    $type = InvoiceType::tryFrom($typeValue);
                                    if ($type) {
                                        $typeHtml = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-'.$type->color().'-100 text-'.$type->color().'-800 dark:bg-'.$type->color().'-800/20 dark:text-'.$type->color().'-200">'.e($type->code().' - '.$type->label()).'</span>';
                                    }
                                }

                                // Due date
                                $dueDateHtml = '<span class="text-gray-400">Not specified</span>';
                                if ($dueDate) {
                                    $formattedDate = \Carbon\Carbon::parse($dueDate)->format('d M Y');
                                    $dueDateHtml = '<span class="font-medium text-gray-900 dark:text-gray-100">'.$formattedDate.'</span>';
                                }

                                // Notes
                                $notesHtml = '';
                                if ($notes) {
                                    $notesHtml = <<<HTML
                                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Notes</h4>
                                            <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{$notes}</p>
                                        </div>
                                    HTML;
                                }

                                return new HtmlString(<<<HTML
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Customer</h4>
                                            <p class="text-sm">{$customerHtml}</p>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Invoice Type</h4>
                                            <p class="text-sm">{$typeHtml}</p>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Currency</h4>
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{$currency}</p>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Due Date</h4>
                                            <p class="text-sm">{$dueDateHtml}</p>
                                        </div>
                                    </div>
                                    {$notesHtml}
                                HTML);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Line Items Summary
                Forms\Components\Section::make('Line Items')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Forms\Components\Placeholder::make('lines_summary')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $lines = $get('invoice_lines') ?? [];
                                $currency = $get('currency') ?? 'EUR';

                                if (empty($lines)) {
                                    return new HtmlString('<p class="text-gray-500">No line items</p>');
                                }

                                $subtotal = 0;
                                $totalTax = 0;
                                $lineRows = '';

                                foreach ($lines as $line) {
                                    if (is_array($line) && ! empty($line['description'])) {
                                        $description = e($line['description']);
                                        $quantity = (float) ($line['quantity'] ?? 0);
                                        $unitPrice = (float) ($line['unit_price'] ?? 0);
                                        $taxRate = (float) ($line['tax_rate'] ?? 0);

                                        $lineSubtotal = $quantity * $unitPrice;
                                        $lineTax = $lineSubtotal * ($taxRate / 100);
                                        $lineTotal = $lineSubtotal + $lineTax;

                                        $subtotal += $lineSubtotal;
                                        $totalTax += $lineTax;

                                        $qtyFormatted = number_format($quantity, 2);
                                        $priceFormatted = number_format($unitPrice, 2);
                                        $taxRateFormatted = number_format($taxRate, 2);
                                        $lineTotalFormatted = number_format($lineTotal, 2);

                                        $lineRows .= <<<HTML
                                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                                <td class="py-2 pr-4 text-sm text-gray-900 dark:text-gray-100">{$description}</td>
                                                <td class="py-2 pr-4 text-sm text-right text-gray-600 dark:text-gray-400">{$qtyFormatted}</td>
                                                <td class="py-2 pr-4 text-sm text-right text-gray-600 dark:text-gray-400">{$currency} {$priceFormatted}</td>
                                                <td class="py-2 pr-4 text-sm text-right text-gray-600 dark:text-gray-400">{$taxRateFormatted}%</td>
                                                <td class="py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{$currency} {$lineTotalFormatted}</td>
                                            </tr>
                                        HTML;
                                    }
                                }

                                $total = $subtotal + $totalTax;
                                $subtotalFormatted = number_format($subtotal, 2);
                                $taxFormatted = number_format($totalTax, 2);
                                $totalFormatted = number_format($total, 2);

                                return new HtmlString(<<<HTML
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="border-b border-gray-200 dark:border-gray-600">
                                                    <th class="py-2 pr-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                                    <th class="py-2 pr-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                                    <th class="py-2 pr-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unit Price</th>
                                                    <th class="py-2 pr-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tax %</th>
                                                    <th class="py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {$lineRows}
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="py-2 pr-4 text-sm text-right text-gray-600 dark:text-gray-400">Subtotal:</td>
                                                    <td class="py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{$currency} {$subtotalFormatted}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="py-2 pr-4 text-sm text-right text-gray-600 dark:text-gray-400">Tax:</td>
                                                    <td class="py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{$currency} {$taxFormatted}</td>
                                                </tr>
                                                <tr class="border-t border-gray-200 dark:border-gray-600">
                                                    <td colspan="4" class="py-2 pr-4 text-sm text-right font-semibold text-gray-700 dark:text-gray-300">Total:</td>
                                                    <td class="py-2 text-lg text-right font-bold text-primary-600 dark:text-primary-400">{$currency} {$totalFormatted}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                HTML);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Final confirmation
                Forms\Components\Placeholder::make('final_confirmation')
                    ->label('')
                    ->content(new HtmlString(<<<'HTML'
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                                Click <strong>"Save as Draft"</strong> to create this invoice.<br>
                                You can then Issue the invoice to generate an invoice number.
                            </p>
                        </div>
                    HTML))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Get the currency from form state.
     */
    protected function getCurrency(Get $get): string
    {
        // Try to get currency from current level or parent levels
        $currency = $get('../../currency') ?? $get('../currency') ?? $get('currency');

        return $currency ?? 'EUR';
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

    /**
     * Before creating the record, we set the status to draft.
     * We handle creation manually via InvoiceService.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure status is draft
        $data['status'] = InvoiceStatus::Draft->value;

        // Remove invoice_lines from data - we'll handle them in afterCreate
        unset($data['invoice_lines']);

        // Set initial amounts to 0
        $data['subtotal'] = '0.00';
        $data['tax_amount'] = '0.00';
        $data['total_amount'] = '0.00';
        $data['amount_paid'] = '0.00';

        return $data;
    }

    /**
     * After creating the invoice, add the line items.
     */
    protected function afterCreate(): void
    {
        $invoiceLines = $this->data['invoice_lines'] ?? [];

        if (! is_array($invoiceLines) || count($invoiceLines) === 0) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $this->record;

        $subtotal = '0.00';
        $taxAmount = '0.00';

        foreach ($invoiceLines as $lineData) {
            if (! is_array($lineData) || empty($lineData['description'])) {
                continue;
            }

            $quantity = (string) ($lineData['quantity'] ?? '1');
            $unitPrice = (string) ($lineData['unit_price'] ?? '0');
            $taxRate = (string) ($lineData['tax_rate'] ?? '0');

            // Calculate tax amount for this line
            $lineSubtotal = bcmul($quantity, $unitPrice, 2);
            $lineTaxAmount = bcmul($lineSubtotal, bcdiv($taxRate, '100', 4), 2);
            $lineTotal = bcadd($lineSubtotal, $lineTaxAmount, 2);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => $lineData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTaxAmount,
                'line_total' => $lineTotal,
            ]);

            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $taxAmount = bcadd($taxAmount, $lineTaxAmount, 2);
        }

        // Update invoice totals
        $totalAmount = bcadd($subtotal, $taxAmount, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        $lineCount = count(array_filter($invoiceLines, fn ($line) => is_array($line) && ! empty($line['description'])));
        $plural = $lineCount === 1 ? 'line item' : 'line items';

        Notification::make()
            ->success()
            ->title('Draft Invoice Created')
            ->body("Invoice created with {$lineCount} {$plural}. Issue the invoice to generate an invoice number.")
            ->send();
    }
}
