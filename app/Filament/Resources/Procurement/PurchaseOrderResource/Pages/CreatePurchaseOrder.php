<?php

namespace App\Filament\Resources\Procurement\PurchaseOrderResource\Pages;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\PurchaseOrderResource;
use App\Models\Customer\Party;
use App\Models\Procurement\ProcurementIntent;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreatePurchaseOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PurchaseOrderResource::class;

    /**
     * Get the form for creating a purchase order.
     * Implements a 5-step wizard for PO creation.
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->components([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getWizardSubmitActions())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
    }

    /**
     * Get the wizard submit actions (Create as Draft).
     */
    protected function getWizardSubmitActions(): HtmlString
    {
        return new HtmlString(
            Blade::render(<<<'BLADE'
                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Create as Draft
                    </x-filament::button>
                </div>
            BLADE)
        );
    }

    /**
     * Get the wizard steps.
     *
     * @return array<\Filament\Schemas\Components\Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getIntentStep(),
            $this->getSupplierStep(),
            $this->getCommercialTermsStep(),
            $this->getDeliveryStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Intent Selection
     * Select the procurement intent as prerequisite.
     */
    protected function getIntentStep(): Step
    {
        return Step::make('Intent')
            ->description('Select the Procurement Intent')
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                // Prominent message about PO-Intent requirement
                Section::make()
                    ->schema([
                        Placeholder::make('po_intent_requirement')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-amber-50 dark:bg-amber-950 rounded-lg border border-amber-200 dark:border-amber-800">'
                                .'<p class="text-amber-700 dark:text-amber-300 font-medium">'
                                .'⚠️ A PO cannot exist without a Procurement Intent. '
                                .'Select an approved or executed intent to proceed with PO creation.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Intent Selection
                Section::make('Select Procurement Intent')
                    ->description('Choose from approved or executed intents')
                    ->schema([
                        Select::make('procurement_intent_id')
                            ->label('Procurement Intent')
                            ->placeholder('Search for an intent by ID or product...')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search): array {
                                return ProcurementIntent::query()
                                    ->whereIn('status', [
                                        ProcurementIntentStatus::Approved->value,
                                        ProcurementIntentStatus::Executed->value,
                                    ])
                                    ->where(function ($query) use ($search): void {
                                        $query->where('id', 'like', "%{$search}%")
                                            ->orWhereHas('productReference', function ($q) use ($search): void {
                                                $q->where('sku_code', 'like', "%{$search}%");
                                            });
                                    })
                                    ->with(['productReference'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (ProcurementIntent $intent): array => [
                                        $intent->id => self::formatIntentOption($intent),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (string $value): ?string {
                                $intent = ProcurementIntent::with('productReference')->find($value);

                                return $intent !== null ? self::formatIntentOption($intent) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if ($state === null) {
                                    $set('intent_preview_data', null);

                                    return;
                                }

                                $intent = ProcurementIntent::with('productReference')->find($state);

                                if ($intent !== null) {
                                    // Store intent data for preview
                                    $set('intent_preview_data', [
                                        'product_label' => $intent->getProductLabel(),
                                        'quantity' => $intent->quantity,
                                        'sourcing_model' => $intent->sourcing_model->label(),
                                        'sourcing_model_value' => $intent->sourcing_model->value,
                                        'trigger_type' => $intent->trigger_type->label(),
                                        'status' => $intent->status->label(),
                                        'preferred_location' => $intent->preferred_inbound_location,
                                        'is_for_liquid' => $intent->isForLiquidProduct(),
                                        'product_reference_type' => $intent->product_reference_type,
                                        'product_reference_id' => $intent->product_reference_id,
                                    ]);

                                    // Pre-fill commercial terms from intent
                                    $set('quantity', $intent->quantity);
                                    $set('destination_warehouse', $intent->preferred_inbound_location);

                                    // Pre-fill ownership_transfer based on sourcing model
                                    $set('ownership_transfer', $intent->sourcing_model === SourcingModel::Purchase);
                                }
                            })
                            ->helperText('Only intents in Approved or Executed status can be linked to a PO'),
                    ]),

                // Intent Preview
                Section::make('Intent Summary')
                    ->description('Preview of the selected procurement intent')
                    ->schema([
                        Placeholder::make('intent_product')
                            ->label('Product')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['product_label'])) {
                                    return 'Select an intent to see details';
                                }

                                return is_string($data['product_label']) ? $data['product_label'] : 'Unknown';
                            }),

                        Placeholder::make('intent_quantity')
                            ->label('Quantity')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['quantity'])) {
                                    return '-';
                                }
                                $quantity = is_numeric($data['quantity']) ? (int) $data['quantity'] : 0;
                                $isLiquid = isset($data['is_for_liquid']) && $data['is_for_liquid'] === true;
                                $unit = $isLiquid ? 'bottle-equivalents' : 'bottles';

                                return "{$quantity} {$unit}";
                            }),

                        Placeholder::make('intent_sourcing_model')
                            ->label('Sourcing Model')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['sourcing_model'])) {
                                    return '-';
                                }

                                return is_string($data['sourcing_model']) ? $data['sourcing_model'] : 'Unknown';
                            }),

                        Placeholder::make('intent_status')
                            ->label('Intent Status')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['status'])) {
                                    return '-';
                                }

                                return is_string($data['status']) ? $data['status'] : 'Unknown';
                            }),

                        // Warning for ownership transfer and sourcing model mismatch
                        Placeholder::make('ownership_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 text-sm">'
                                .'ℹ️ <strong>Note:</strong> This intent uses "Purchase" sourcing model, which implies ownership transfer on delivery. '
                                .'The "Ownership Transfer" field in Commercial Terms will be pre-selected.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['sourcing_model_value'])) {
                                    return true;
                                }

                                return $data['sourcing_model_value'] !== SourcingModel::Purchase->value;
                            })
                            ->columnSpanFull(),

                        Placeholder::make('non_purchase_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 text-sm">'
                                .'⚠️ <strong>Note:</strong> This intent uses a non-Purchase sourcing model. '
                                .'Ownership transfer should NOT be selected in Commercial Terms for this PO.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['sourcing_model_value'])) {
                                    return true;
                                }

                                return $data['sourcing_model_value'] === SourcingModel::Purchase->value;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => $get('procurement_intent_id') === null),

                // Hidden field to store preview data
                Hidden::make('intent_preview_data'),
            ]);
    }

    /**
     * Step 2: Supplier Selection
     * Select the supplier for the PO.
     */
    protected function getSupplierStep(): Step
    {
        return Step::make('Supplier')
            ->description('Select the supplier')
            ->icon('heroicon-o-building-storefront')
            ->schema([
                Section::make('Select Supplier')
                    ->description('Choose a supplier or producer party')
                    ->schema([
                        Select::make('supplier_party_id')
                            ->label('Supplier')
                            ->placeholder('Search for a supplier by name...')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search): array {
                                return Party::query()
                                    ->where('legal_name', 'like', "%{$search}%")
                                    ->whereHas('roles', function ($query): void {
                                        $query->whereIn('role', [
                                            PartyRoleType::Supplier->value,
                                            PartyRoleType::Producer->value,
                                        ]);
                                    })
                                    ->with(['roles', 'supplierConfig'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Party $party): array => [
                                        $party->id => self::formatPartyOption($party),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (string $value): ?string {
                                $party = Party::with('roles')->find($value);

                                return $party !== null ? self::formatPartyOption($party) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if ($state === null) {
                                    $set('supplier_preview_data', null);
                                    $set('supplier_config_data', null);

                                    return;
                                }

                                $party = Party::with(['supplierConfig', 'roles'])->find($state);

                                if ($party !== null) {
                                    // Get roles as comma-separated string
                                    $roles = $party->roles->map(fn ($role) => $role->role->label())->join(', ');

                                    $set('supplier_preview_data', [
                                        'legal_name' => $party->legal_name,
                                        'party_type' => $party->party_type->label(),
                                        'roles' => $roles !== '' ? $roles : 'No roles',
                                        'jurisdiction' => $party->jurisdiction,
                                    ]);

                                    // Check for supplier config
                                    $config = $party->supplierConfig;
                                    if ($config !== null) {
                                        $set('supplier_config_data', [
                                            'default_bottling_deadline_days' => $config->default_bottling_deadline_days,
                                            'allowed_formats' => $config->allowed_formats,
                                            'serialization_constraints' => $config->serialization_constraints,
                                            'notes' => $config->notes,
                                        ]);
                                    } else {
                                        $set('supplier_config_data', null);
                                    }
                                }
                            })
                            ->helperText('Search suppliers and producers by name'),
                    ]),

                // Supplier Preview
                Section::make('Supplier Information')
                    ->description('Details of the selected supplier')
                    ->schema([
                        Placeholder::make('supplier_name')
                            ->label('Legal Name')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_preview_data');
                                if (! is_array($data) || ! isset($data['legal_name'])) {
                                    return 'Select a supplier to see details';
                                }

                                return is_string($data['legal_name']) ? $data['legal_name'] : 'Unknown';
                            }),

                        Placeholder::make('supplier_party_type')
                            ->label('Party Type')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_preview_data');
                                if (! is_array($data) || ! isset($data['party_type'])) {
                                    return '-';
                                }

                                return is_string($data['party_type']) ? $data['party_type'] : 'Unknown';
                            }),

                        Placeholder::make('supplier_roles')
                            ->label('Roles')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_preview_data');
                                if (! is_array($data) || ! isset($data['roles'])) {
                                    return '-';
                                }

                                return is_string($data['roles']) ? $data['roles'] : 'Unknown';
                            }),
                    ])
                    ->columns(3)
                    ->hidden(fn (Get $get): bool => $get('supplier_party_id') === null),

                // Supplier Config Preview (if exists)
                Section::make('Supplier Configuration')
                    ->description('Default settings from ProducerSupplierConfig')
                    ->schema([
                        Placeholder::make('config_deadline')
                            ->label('Default Bottling Deadline')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_config_data');
                                if (! is_array($data) || ! isset($data['default_bottling_deadline_days'])) {
                                    return 'Not specified';
                                }
                                $days = $data['default_bottling_deadline_days'];

                                return is_numeric($days) ? "{$days} days" : 'Not specified';
                            }),

                        Placeholder::make('config_formats')
                            ->label('Allowed Formats')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_config_data');
                                if (! is_array($data) || ! isset($data['allowed_formats'])) {
                                    return 'Not specified';
                                }
                                $formats = $data['allowed_formats'];
                                if (is_array($formats) && count($formats) > 0) {
                                    return implode(', ', $formats);
                                }

                                return 'Not specified';
                            }),

                        Placeholder::make('config_notes')
                            ->label('Notes')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_config_data');
                                if (! is_array($data) || ! isset($data['notes'])) {
                                    return 'No notes';
                                }
                                $notes = $data['notes'];

                                return is_string($notes) && $notes !== '' ? $notes : 'No notes';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => $get('supplier_config_data') === null),

                // No config message
                Section::make()
                    ->schema([
                        Placeholder::make('no_config_message')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                .'ℹ️ No ProducerSupplierConfig found for this supplier. Default constraints will not apply.'
                                .'</p></div>'
                            )),
                    ])
                    ->hidden(fn (Get $get): bool => $get('supplier_party_id') === null || $get('supplier_config_data') !== null),

                // Hidden fields for preview data
                Hidden::make('supplier_preview_data'),
                Hidden::make('supplier_config_data'),
            ]);
    }

    /**
     * Step 3: Commercial Terms
     * Define the commercial terms of the PO.
     */
    protected function getCommercialTermsStep(): Step
    {
        return Step::make('Commercial Terms')
            ->description('Define pricing and terms')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Section::make('Quantity')
                    ->description('Specify the quantity to order')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
                            ->live()
                            ->suffix(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (is_array($data) && isset($data['is_for_liquid']) && $data['is_for_liquid'] === true) {
                                    return 'bottle-equivalents';
                                }

                                return 'bottles';
                            })
                            ->helperText('Pre-filled from intent quantity'),

                        // Warning if quantity > intent quantity
                        Placeholder::make('quantity_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 text-sm">'
                                .'⚠️ <strong>Warning:</strong> The quantity exceeds the intent quantity. '
                                .'This may indicate an over-order situation.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $data = $get('intent_preview_data');
                                $intentQty = is_array($data) && isset($data['quantity']) && is_numeric($data['quantity'])
                                    ? (int) $data['quantity']
                                    : 0;
                                $poQty = is_numeric($get('quantity')) ? (int) $get('quantity') : 0;

                                return $poQty <= $intentQty;
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing')
                    ->description('Define unit cost and currency')
                    ->schema([
                        TextInput::make('unit_cost')
                            ->label('Unit Cost')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('€')
                            ->helperText('Cost per bottle or bottle-equivalent'),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'EUR' => 'EUR - Euro',
                                'USD' => 'USD - US Dollar',
                                'GBP' => 'GBP - British Pound',
                                'CHF' => 'CHF - Swiss Franc',
                            ])
                            ->default('EUR')
                            ->required()
                            ->native(false),

                        Select::make('incoterms')
                            ->label('Incoterms')
                            ->options([
                                'EXW' => 'EXW - Ex Works',
                                'FCA' => 'FCA - Free Carrier',
                                'CPT' => 'CPT - Carriage Paid To',
                                'CIP' => 'CIP - Carriage and Insurance Paid To',
                                'DAP' => 'DAP - Delivered at Place',
                                'DPU' => 'DPU - Delivered at Place Unloaded',
                                'DDP' => 'DDP - Delivered Duty Paid',
                                'FAS' => 'FAS - Free Alongside Ship',
                                'FOB' => 'FOB - Free on Board',
                                'CFR' => 'CFR - Cost and Freight',
                                'CIF' => 'CIF - Cost, Insurance and Freight',
                            ])
                            ->placeholder('Select Incoterms...')
                            ->native(false)
                            ->helperText('International commercial terms for delivery'),
                    ])
                    ->columns(3),

                Section::make('Ownership Transfer')
                    ->description('Define ownership transfer terms')
                    ->schema([
                        Toggle::make('ownership_transfer')
                            ->label('Ownership Transfer')
                            ->helperText('Check if ownership transfers to us on delivery')
                            ->live()
                            ->default(false),

                        Placeholder::make('ownership_explanation')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $ownershipTransfer = $get('ownership_transfer') === true;

                                if ($ownershipTransfer) {
                                    return new HtmlString(
                                        '<div class="p-3 bg-green-50 dark:bg-green-950 rounded-lg">'
                                        .'<p class="text-green-700 dark:text-green-300 text-sm">'
                                        .'✓ <strong>Ownership Transfer:</strong> Upon delivery, ownership of the goods will transfer to us. '
                                        .'The wine will become our inventory asset.'
                                        .'</p></div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                    .'ℹ️ <strong>No Ownership Transfer:</strong> Ownership remains with the supplier. '
                                    .'This is typical for consignment or custody arrangements.'
                                    .'</p></div>'
                                );
                            })
                            ->columnSpanFull(),

                        // Warning for ownership mismatch with sourcing model
                        Placeholder::make('ownership_mismatch_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">'
                                .'<p class="text-red-700 dark:text-red-300 text-sm">'
                                .'⚠️ <strong>Mismatch:</strong> The intent uses "Purchase" sourcing model, but ownership transfer is not selected. '
                                .'This may be inconsistent with the sourcing model. Please verify.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $data = $get('intent_preview_data');
                                $sourcingModel = is_array($data) && isset($data['sourcing_model_value'])
                                    ? $data['sourcing_model_value']
                                    : null;
                                $ownershipTransfer = $get('ownership_transfer') === true;

                                // Show warning if sourcing model is purchase but ownership transfer is false
                                return $sourcingModel !== SourcingModel::Purchase->value || $ownershipTransfer === true;
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 4: Delivery
     * Define delivery expectations.
     */
    protected function getDeliveryStep(): Step
    {
        return Step::make('Delivery')
            ->description('Define delivery expectations')
            ->icon('heroicon-o-truck')
            ->schema([
                Section::make('Delivery Window')
                    ->description('Expected delivery dates')
                    ->schema([
                        DatePicker::make('expected_delivery_start')
                            ->label('Expected Delivery Start')
                            ->placeholder('Select start date...')
                            ->native(false)
                            ->live()
                            ->helperText('Earliest expected delivery date'),

                        DatePicker::make('expected_delivery_end')
                            ->label('Expected Delivery End')
                            ->placeholder('Select end date...')
                            ->native(false)
                            ->afterOrEqual('expected_delivery_start')
                            ->helperText('Latest expected delivery date'),
                    ])
                    ->columns(2),

                Section::make('Destination')
                    ->description('Where should the goods be delivered?')
                    ->schema([
                        Select::make('destination_warehouse')
                            ->label('Destination Warehouse')
                            ->options([
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                            ])
                            ->placeholder('Select warehouse...')
                            ->helperText('Pre-filled from intent preferred location'),

                        Placeholder::make('destination_note')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                .'ℹ️ The destination warehouse is pre-filled from the intent\'s preferred inbound location. '
                                .'You can modify it if needed.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Section::make('Serialization Routing')
                    ->description('Special instructions for serialization')
                    ->schema([
                        Textarea::make('serialization_routing_note')
                            ->label('Serialization Routing Note')
                            ->placeholder('Enter special routing instructions (e.g., "France required for serialization")')
                            ->rows(3)
                            ->helperText('Special instructions for serialization routing (optional)'),
                    ]),
            ]);
    }

    /**
     * Step 5: Review
     * Review all data before creating.
     */
    protected function getReviewStep(): Step
    {
        return Step::make('Review')
            ->description('Review and create the PO')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Draft status info
                Section::make()
                    ->schema([
                        Placeholder::make('draft_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'📋 Draft POs are not sent to suppliers until status changes to Sent. '
                                .'Review the details below before creating.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Linked Intent Summary
                Section::make('Linked Procurement Intent')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Placeholder::make('review_intent')
                            ->label('Intent')
                            ->content(function (Get $get): string {
                                $intentId = $get('procurement_intent_id');
                                if (! is_string($intentId)) {
                                    return 'Not selected';
                                }

                                $intent = ProcurementIntent::find($intentId);

                                return $intent !== null ? self::formatIntentOption($intent) : 'Not found';
                            }),

                        Placeholder::make('review_intent_product')
                            ->label('Product')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['product_label'])) {
                                    return '-';
                                }

                                return is_string($data['product_label']) ? $data['product_label'] : 'Unknown';
                            }),
                    ])
                    ->columns(2),

                // Supplier Summary
                Section::make('Supplier')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Placeholder::make('review_supplier')
                            ->label('Supplier')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_preview_data');
                                if (! is_array($data) || ! isset($data['legal_name'])) {
                                    return 'Not selected';
                                }

                                return is_string($data['legal_name']) ? $data['legal_name'] : 'Unknown';
                            }),

                        Placeholder::make('review_supplier_roles')
                            ->label('Roles')
                            ->content(function (Get $get): string {
                                $data = $get('supplier_preview_data');
                                if (! is_array($data) || ! isset($data['roles'])) {
                                    return '-';
                                }

                                return is_string($data['roles']) ? $data['roles'] : 'Unknown';
                            }),
                    ])
                    ->columns(2),

                // Commercial Terms Summary
                Section::make('Commercial Terms')
                    ->icon('heroicon-o-currency-euro')
                    ->schema([
                        Placeholder::make('review_quantity')
                            ->label('Quantity')
                            ->content(function (Get $get): string {
                                $quantity = is_numeric($get('quantity')) ? (int) $get('quantity') : 0;
                                $data = $get('intent_preview_data');
                                $isLiquid = is_array($data) && isset($data['is_for_liquid']) && $data['is_for_liquid'] === true;
                                $unit = $isLiquid ? 'bottle-equivalents' : 'bottles';

                                return "{$quantity} {$unit}";
                            }),

                        Placeholder::make('review_unit_cost')
                            ->label('Unit Cost')
                            ->content(function (Get $get): string {
                                $unitCost = is_numeric($get('unit_cost')) ? number_format((float) $get('unit_cost'), 2) : '0.00';
                                $currency = is_string($get('currency')) ? $get('currency') : 'EUR';

                                return "{$currency} {$unitCost}";
                            }),

                        Placeholder::make('review_total_cost')
                            ->label('Total Cost')
                            ->content(function (Get $get): string {
                                $quantity = is_numeric($get('quantity')) ? (int) $get('quantity') : 0;
                                $unitCost = is_numeric($get('unit_cost')) ? (float) $get('unit_cost') : 0;
                                $total = $quantity * $unitCost;
                                $currency = is_string($get('currency')) ? $get('currency') : 'EUR';

                                return "{$currency} ".number_format($total, 2);
                            }),

                        Placeholder::make('review_incoterms')
                            ->label('Incoterms')
                            ->content(function (Get $get): string {
                                $incoterms = $get('incoterms');

                                return is_string($incoterms) && $incoterms !== '' ? $incoterms : 'Not specified';
                            }),

                        Placeholder::make('review_ownership')
                            ->label('Ownership Transfer')
                            ->content(fn (Get $get): string => $get('ownership_transfer') === true ? 'Yes' : 'No'),
                    ])
                    ->columns(3),

                // Delivery Summary
                Section::make('Delivery')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Placeholder::make('review_delivery_window')
                            ->label('Delivery Window')
                            ->content(function (Get $get): string {
                                $start = $get('expected_delivery_start');
                                $end = $get('expected_delivery_end');

                                if ($start === null && $end === null) {
                                    return 'Not specified';
                                }

                                if ($start !== null && $end !== null) {
                                    $startDate = is_string($start) ? $start : '';
                                    $endDate = is_string($end) ? $end : '';

                                    return "{$startDate} - {$endDate}";
                                }

                                if ($start !== null) {
                                    $startFormatted = $start instanceof Carbon
                                        ? $start->format('Y-m-d')
                                        : (is_scalar($start) ? (string) $start : '');

                                    return "From {$startFormatted}";
                                }

                                $endFormatted = $end instanceof Carbon
                                    ? $end->format('Y-m-d')
                                    : (is_scalar($end) ? (string) $end : '');

                                return "Until {$endFormatted}";
                            }),

                        Placeholder::make('review_destination')
                            ->label('Destination Warehouse')
                            ->content(function (Get $get): string {
                                $warehouse = $get('destination_warehouse');

                                return match ($warehouse) {
                                    'main_warehouse' => 'Main Warehouse',
                                    'secondary_warehouse' => 'Secondary Warehouse',
                                    'bonded_warehouse' => 'Bonded Warehouse',
                                    'third_party_storage' => 'Third Party Storage',
                                    default => 'Not specified',
                                };
                            }),

                        Placeholder::make('review_routing_note')
                            ->label('Serialization Routing Note')
                            ->content(function (Get $get): string {
                                $note = $get('serialization_routing_note');

                                return is_string($note) && $note !== '' ? $note : 'Not specified';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Format a ProcurementIntent for display in select options.
     */
    protected static function formatIntentOption(ProcurementIntent $intent): string
    {
        $id = substr($intent->id, 0, 8);
        $product = $intent->getProductLabel();
        $status = $intent->status->label();

        return "{$id}... | {$product} | {$status}";
    }

    /**
     * Format a Party for display in select options.
     */
    protected static function formatPartyOption(Party $party): string
    {
        $name = $party->legal_name ?? 'Unknown';
        $partyType = $party->party_type->label();

        // Get supplier/producer roles from the loaded relationship
        $relevantRoles = $party->roles
            ->filter(fn ($role): bool => $role->isSupplier() || $role->isProducer())
            ->map(fn ($role): string => $role->role->label())
            ->join(', ');

        $roleInfo = $relevantRoles !== '' ? " [{$relevantRoles}]" : '';

        return "{$name} ({$partyType}){$roleInfo}";
    }

    /**
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get product reference from intent
        $intentId = $data['procurement_intent_id'] ?? null;
        if (is_string($intentId)) {
            $intent = ProcurementIntent::find($intentId);
            if ($intent !== null) {
                $data['product_reference_type'] = $intent->product_reference_type;
                $data['product_reference_id'] = $intent->product_reference_id;
            }
        }

        // Set default status
        $data['status'] = PurchaseOrderStatus::Draft->value;

        // Clean up temporary fields
        unset(
            $data['intent_preview_data'],
            $data['supplier_preview_data'],
            $data['supplier_config_data']
        );

        return $data;
    }

    /**
     * After creating the purchase order, show success notification.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Purchase Order created as Draft')
            ->body('The purchase order has been created in Draft status. It will not be sent to the supplier until marked as Sent.')
            ->send();
    }
}
