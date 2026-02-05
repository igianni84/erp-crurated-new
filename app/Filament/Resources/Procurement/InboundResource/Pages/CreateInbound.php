<?php

namespace App\Filament\Resources\Procurement\InboundResource\Pages;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\InboundResource;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateInbound extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = InboundResource::class;

    /**
     * Get the form for creating an inbound.
     * Implements a 4-step wizard for Inbound creation.
     */
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
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
     * Get the wizard submit actions (Record Inbound).
     */
    protected function getWizardSubmitActions(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Record Inbound
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
            $this->getPhysicalReceiptStep(),
            $this->getSourcingContextStep(),
            $this->getSerializationStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Physical Receipt (US-038)
     * Record the physical details of the inbound.
     */
    protected function getPhysicalReceiptStep(): Wizard\Step
    {
        return Wizard\Step::make('Physical Receipt')
            ->description('Record physical arrival details')
            ->icon('heroicon-o-inbox-arrow-down')
            ->schema([
                // Informational message about Inbound
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('inbound_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-amber-50 dark:bg-amber-950 rounded-lg border border-amber-200 dark:border-amber-800">'
                                .'<p class="text-amber-700 dark:text-amber-300 font-medium">'
                                .'📦 <strong>Important:</strong> Inbound records physical arrival - it does NOT imply ownership. '
                                .'Ownership status must be explicitly set in the next step.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Warehouse Selection
                Forms\Components\Section::make('Receiving Location')
                    ->description('Select where the goods were received')
                    ->schema([
                        Forms\Components\Select::make('warehouse')
                            ->label('Warehouse')
                            ->options([
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                            ])
                            ->required()
                            ->native(false)
                            ->placeholder('Select receiving warehouse...')
                            ->helperText('The physical warehouse where goods were received'),
                    ]),

                // Receipt Details
                Forms\Components\Section::make('Receipt Details')
                    ->description('Record the details of the physical receipt')
                    ->schema([
                        Forms\Components\DatePicker::make('received_date')
                            ->label('Received Date')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->default(now())
                            ->helperText('Date when goods physically arrived'),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(1)
                            ->suffix('bottles')
                            ->helperText('Number of bottles received'),

                        Forms\Components\Select::make('packaging')
                            ->label('Packaging')
                            ->options(collect(InboundPackaging::cases())
                                ->mapWithKeys(fn (InboundPackaging $packaging) => [$packaging->value => $packaging->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->placeholder('Select packaging type...')
                            ->helperText('How the bottles are packaged')
                            ->live(),

                        // Packaging info
                        Forms\Components\Placeholder::make('packaging_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $packaging = $get('packaging');

                                $info = match ($packaging) {
                                    'cases' => '📦 <strong>Cases:</strong> Bottles are packed in original cases/cartons.',
                                    'loose' => '🍾 <strong>Loose:</strong> Individual bottles without case packaging.',
                                    'mixed' => '📦🍾 <strong>Mixed:</strong> Combination of cases and loose bottles.',
                                    default => 'ℹ️ Select a packaging type to see details.',
                                };

                                $colorClass = $packaging !== null
                                    ? 'bg-gray-50 dark:bg-gray-800'
                                    : 'bg-gray-50 dark:bg-gray-800';

                                return new HtmlString(
                                    '<div class="p-3 '.$colorClass.' rounded-lg">'
                                    .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                    .$info
                                    .'</p></div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Condition Notes
                Forms\Components\Section::make('Condition Notes')
                    ->description('Document any issues or observations')
                    ->schema([
                        Forms\Components\Textarea::make('condition_notes')
                            ->label('Condition Notes')
                            ->placeholder('Enter any notes about the condition of the goods (damage, discrepancies, etc.)')
                            ->rows(4)
                            ->helperText('Optional but recommended: Document any damage, missing items, or quality issues'),

                        Forms\Components\Placeholder::make('condition_tips')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg">'
                                .'<p class="text-blue-700 dark:text-blue-300 text-sm">'
                                .'💡 <strong>Tips for condition notes:</strong><br>'
                                .'• Record any visible damage to packaging or bottles<br>'
                                .'• Note any quantity discrepancies from expected delivery<br>'
                                .'• Document temperature or storage concerns<br>'
                                .'• Include delivery documentation reference numbers'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 2: Sourcing Context (US-039)
     * Link the inbound to the sourcing context.
     */
    protected function getSourcingContextStep(): Wizard\Step
    {
        return Wizard\Step::make('Sourcing Context')
            ->description('Link to procurement source')
            ->icon('heroicon-o-link')
            ->schema([
                // Sourcing Context Selection
                Forms\Components\Section::make('Link to Procurement')
                    ->description('Optionally link this inbound to a Procurement Intent or Purchase Order')
                    ->schema([
                        Forms\Components\Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->placeholder('Search for a PO by ID or supplier...')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return PurchaseOrder::query()
                                    ->whereIn('status', [
                                        PurchaseOrderStatus::Sent->value,
                                        PurchaseOrderStatus::Confirmed->value,
                                    ])
                                    ->where(function ($query) use ($search): void {
                                        $query->where('id', 'like', "%{$search}%")
                                            ->orWhereHas('supplier', function ($q) use ($search): void {
                                                $q->where('legal_name', 'like', "%{$search}%");
                                            });
                                    })
                                    ->with(['supplier', 'procurementIntent'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (PurchaseOrder $po): array => [
                                        $po->id => self::formatPurchaseOrderOption($po),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (string $value): ?string {
                                $po = PurchaseOrder::with(['supplier', 'procurementIntent'])->find($value);

                                return $po !== null ? self::formatPurchaseOrderOption($po) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if ($state === null) {
                                    $set('po_preview_data', null);
                                    // Don't clear intent if it was manually selected
                                    if ($get('intent_manually_selected') !== true) {
                                        $set('procurement_intent_id', null);
                                        $set('intent_preview_data', null);
                                    }

                                    return;
                                }

                                $po = PurchaseOrder::with(['supplier', 'procurementIntent'])->find($state);

                                if ($po !== null) {
                                    // Store PO preview data
                                    $set('po_preview_data', [
                                        'supplier_name' => $po->supplier->legal_name ?? 'Unknown',
                                        'product_label' => $po->getProductLabel(),
                                        'quantity' => $po->quantity,
                                        'status' => $po->status->label(),
                                        'ownership_transfer' => $po->ownership_transfer,
                                    ]);

                                    // Auto-fill intent from PO
                                    $set('procurement_intent_id', $po->procurement_intent_id);
                                    $set('intent_manually_selected', false);

                                    // Also set intent preview
                                    $intent = $po->procurementIntent;
                                    if ($intent !== null) {
                                        $set('intent_preview_data', [
                                            'product_label' => $intent->getProductLabel(),
                                            'quantity' => $intent->quantity,
                                            'status' => $intent->status->label(),
                                        ]);
                                    }

                                    // Pre-fill product reference from PO
                                    $set('product_reference_type', $po->product_reference_type);
                                    $set('product_reference_id', $po->product_reference_id);

                                    // Suggest ownership flag based on PO ownership_transfer
                                    if ($po->ownership_transfer) {
                                        $set('ownership_flag', OwnershipFlag::Owned->value);
                                    } else {
                                        $set('ownership_flag', OwnershipFlag::InCustody->value);
                                    }
                                }
                            })
                            ->helperText('Optional: Link to an existing Purchase Order'),

                        Forms\Components\Select::make('procurement_intent_id')
                            ->label('Procurement Intent')
                            ->placeholder('Search for an intent by ID or product...')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return ProcurementIntent::query()
                                    ->whereIn('status', [
                                        ProcurementIntentStatus::Approved->value,
                                        ProcurementIntentStatus::Executed->value,
                                    ])
                                    ->where(function ($query) use ($search): void {
                                        $query->where('id', 'like', "%{$search}%");
                                    })
                                    ->with('productReference')
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
                                    $set('intent_manually_selected', false);

                                    return;
                                }

                                $set('intent_manually_selected', true);

                                $intent = ProcurementIntent::with('productReference')->find($state);

                                if ($intent !== null) {
                                    $set('intent_preview_data', [
                                        'product_label' => $intent->getProductLabel(),
                                        'quantity' => $intent->quantity,
                                        'status' => $intent->status->label(),
                                    ]);

                                    // Pre-fill product reference from intent if not already set from PO
                                    if ($get('product_reference_type') === null) {
                                        $set('product_reference_type', $intent->product_reference_type);
                                        $set('product_reference_id', $intent->product_reference_id);
                                    }
                                }
                            })
                            ->helperText('Optional: Link to a Procurement Intent (auto-filled from PO if selected)'),
                    ])
                    ->columns(2),

                // PO Preview
                Forms\Components\Section::make('Purchase Order Summary')
                    ->description('Preview of the selected Purchase Order')
                    ->schema([
                        Forms\Components\Placeholder::make('po_supplier')
                            ->label('Supplier')
                            ->content(function (Get $get): string {
                                $data = $get('po_preview_data');
                                if (! is_array($data) || ! isset($data['supplier_name'])) {
                                    return 'Select a PO to see details';
                                }

                                return is_string($data['supplier_name']) ? $data['supplier_name'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('po_product')
                            ->label('Product')
                            ->content(function (Get $get): string {
                                $data = $get('po_preview_data');
                                if (! is_array($data) || ! isset($data['product_label'])) {
                                    return '-';
                                }

                                return is_string($data['product_label']) ? $data['product_label'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('po_quantity')
                            ->label('PO Quantity')
                            ->content(function (Get $get): string {
                                $data = $get('po_preview_data');
                                if (! is_array($data) || ! isset($data['quantity'])) {
                                    return '-';
                                }

                                return is_numeric($data['quantity']) ? (string) (int) $data['quantity'].' bottles' : '-';
                            }),

                        Forms\Components\Placeholder::make('po_status')
                            ->label('PO Status')
                            ->content(function (Get $get): string {
                                $data = $get('po_preview_data');
                                if (! is_array($data) || ! isset($data['status'])) {
                                    return '-';
                                }

                                return is_string($data['status']) ? $data['status'] : 'Unknown';
                            }),
                    ])
                    ->columns(4)
                    ->hidden(fn (Get $get): bool => $get('purchase_order_id') === null),

                // Ownership Flag
                Forms\Components\Section::make('Ownership Status')
                    ->description('Clarify the ownership status of these goods')
                    ->schema([
                        Forms\Components\Select::make('ownership_flag')
                            ->label('Ownership Flag')
                            ->options(collect(OwnershipFlag::cases())
                                ->mapWithKeys(fn (OwnershipFlag $flag) => [$flag->value => $flag->label()])
                                ->toArray())
                            ->required()
                            ->native(false)
                            ->default(OwnershipFlag::Pending->value)
                            ->placeholder('Select ownership status...')
                            ->live()
                            ->helperText('Clarify who owns these goods'),

                        // Ownership explanation
                        Forms\Components\Placeholder::make('ownership_explanation')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $flag = $get('ownership_flag');

                                $content = match ($flag) {
                                    'owned' => '<div class="p-3 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">'
                                        .'<p class="text-green-700 dark:text-green-300 text-sm">'
                                        .'✓ <strong>Owned:</strong> We own these goods. They are our inventory asset.'
                                        .'</p></div>',
                                    'in_custody' => '<div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                        .'<p class="text-blue-700 dark:text-blue-300 text-sm">'
                                        .'📦 <strong>In Custody:</strong> We hold but do not own these goods. Ownership remains with the supplier or another party.'
                                        .'</p></div>',
                                    'pending' => '<div class="p-3 bg-amber-50 dark:bg-amber-950 rounded-lg border border-amber-200 dark:border-amber-800">'
                                        .'<p class="text-amber-700 dark:text-amber-300 text-sm">'
                                        .'⚠️ <strong>Pending:</strong> Ownership status is yet to be determined. <strong>This must be clarified before hand-off to inventory.</strong>'
                                        .'</p></div>',
                                    default => '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                        .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                        .'ℹ️ Select an ownership status to see details.'
                                        .'</p></div>',
                                };

                                return new HtmlString($content);
                            })
                            ->columnSpanFull(),
                    ]),

                // Unlinked warning
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('unlinked_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 font-medium">'
                                .'⚠️ <strong>Unlinked Inbound:</strong> This inbound has no linked Procurement Intent. '
                                .'Unlinked inbounds require manual validation before hand-off to inventory.'
                                .'</p></div>'
                            )),
                    ])
                    ->hidden(fn (Get $get): bool => $get('procurement_intent_id') !== null),

                // Hidden fields
                Forms\Components\Hidden::make('po_preview_data'),
                Forms\Components\Hidden::make('intent_preview_data'),
                Forms\Components\Hidden::make('intent_manually_selected'),
                Forms\Components\Hidden::make('product_reference_type'),
                Forms\Components\Hidden::make('product_reference_id'),
            ]);
    }

    /**
     * Step 3: Serialization (US-040)
     * Define serialization requirements.
     */
    protected function getSerializationStep(): Wizard\Step
    {
        return Wizard\Step::make('Serialization')
            ->description('Define serialization requirements')
            ->icon('heroicon-o-qr-code')
            ->schema([
                // Serialization Requirements
                Forms\Components\Section::make('Serialization Requirements')
                    ->description('Configure serialization settings for this inbound')
                    ->schema([
                        Forms\Components\Toggle::make('serialization_required')
                            ->label('Serialization Required')
                            ->helperText('Does this inbound require bottle serialization?')
                            ->live()
                            ->default(true),

                        Forms\Components\Placeholder::make('serialization_info')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $required = $get('serialization_required') === true;

                                if ($required) {
                                    return new HtmlString(
                                        '<div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                        .'<p class="text-blue-700 dark:text-blue-300 text-sm">'
                                        .'📱 <strong>Serialization Required:</strong> Each bottle must receive a unique serial number before entering inventory. '
                                        .'This enables tracking throughout the supply chain.'
                                        .'</p></div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                    .'ℹ️ <strong>No Serialization:</strong> Bottles will not be individually serialized. '
                                    .'This is uncommon and should only be used for specific product types.'
                                    .'</p></div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                // Serialization Location
                Forms\Components\Section::make('Serialization Location')
                    ->description('Where should serialization be performed?')
                    ->schema([
                        Forms\Components\Select::make('serialization_location_authorized')
                            ->label('Authorized Serialization Location')
                            ->options([
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                                'france_facility' => 'France Facility',
                                'uk_facility' => 'UK Facility',
                                'origin_winery' => 'Origin Winery',
                            ])
                            ->placeholder('Select serialization location...')
                            ->native(false)
                            ->live()
                            ->helperText('Where serialization is authorized for this product'),

                        Forms\Components\Placeholder::make('location_preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $location = $get('serialization_location_authorized');
                                $serializationRequired = $get('serialization_required') === true;

                                if (! $serializationRequired) {
                                    return new HtmlString(
                                        '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                        .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                        .'ℹ️ Serialization is not required for this inbound.'
                                        .'</p></div>'
                                    );
                                }

                                if ($location === null) {
                                    return new HtmlString(
                                        '<div class="p-3 bg-amber-50 dark:bg-amber-950 rounded-lg border border-amber-200 dark:border-amber-800">'
                                        .'<p class="text-amber-700 dark:text-amber-300 text-sm">'
                                        .'⚠️ <strong>No location selected:</strong> An authorized serialization location is required before routing.'
                                        .'</p></div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="p-3 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">'
                                    .'<p class="text-green-700 dark:text-green-300 text-sm">'
                                    .'✓ Serialization will be performed at the selected location.'
                                    .'</p></div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('serialization_required') !== true),

                // Serialization Routing Rule
                Forms\Components\Section::make('Serialization Routing Rule')
                    ->description('Special instructions for serialization routing')
                    ->schema([
                        Forms\Components\Textarea::make('serialization_routing_rule')
                            ->label('Serialization Routing Rule')
                            ->placeholder('Enter special routing instructions (e.g., "France only for this wine due to regulatory requirements")')
                            ->rows(3)
                            ->helperText('Optional: Define any special constraints or requirements for serialization routing'),

                        Forms\Components\Placeholder::make('routing_tips')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg">'
                                .'<p class="text-blue-700 dark:text-blue-300 text-sm">'
                                .'💡 <strong>Common routing rules:</strong><br>'
                                .'• Geographic restrictions (e.g., "Must be serialized in EU")<br>'
                                .'• Regulatory requirements (e.g., "French fiscal marks required")<br>'
                                .'• Producer requirements (e.g., "Producer requires origin serialization")<br>'
                                .'• Product-specific rules (e.g., "Large formats require special equipment")'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn (Get $get): bool => $get('serialization_required') !== true),
            ]);
    }

    /**
     * Step 4: Review (US-041)
     * Review all data before creating the inbound.
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review')
            ->description('Review and record the inbound')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Recorded status info
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('recorded_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'📋 The inbound will be created in "Recorded" status. '
                                .'Next steps: Route to serialization location, then complete.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Warnings Section
                Forms\Components\Section::make()
                    ->schema([
                        // Ownership pending warning
                        Forms\Components\Placeholder::make('ownership_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">'
                                .'<p class="text-red-700 dark:text-red-300 font-medium">'
                                .'⚠️ <strong>Ownership Pending:</strong> Ownership must be clarified before completing this inbound. '
                                .'You can update the ownership status from the inbound detail page.'
                                .'</p></div>'
                            ))
                            ->hidden(fn (Get $get): bool => $get('ownership_flag') !== OwnershipFlag::Pending->value),

                        // Unlinked warning
                        Forms\Components\Placeholder::make('unlinked_inbound_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">'
                                .'<p class="text-yellow-700 dark:text-yellow-300 font-medium">'
                                .'⚠️ <strong>Unlinked Inbound:</strong> This inbound has no linked Procurement Intent. '
                                .'Manual validation will be required before hand-off to inventory.'
                                .'</p></div>'
                            ))
                            ->hidden(fn (Get $get): bool => $get('procurement_intent_id') !== null),
                    ]),

                // Physical Receipt Summary
                Forms\Components\Section::make('Physical Receipt')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->schema([
                        Forms\Components\Placeholder::make('review_warehouse')
                            ->label('Warehouse')
                            ->content(function (Get $get): string {
                                $warehouse = $get('warehouse');

                                return match ($warehouse) {
                                    'main_warehouse' => 'Main Warehouse',
                                    'secondary_warehouse' => 'Secondary Warehouse',
                                    'bonded_warehouse' => 'Bonded Warehouse',
                                    'third_party_storage' => 'Third Party Storage',
                                    default => 'Not specified',
                                };
                            }),

                        Forms\Components\Placeholder::make('review_received_date')
                            ->label('Received Date')
                            ->content(function (Get $get): string {
                                $date = $get('received_date');
                                if ($date === null) {
                                    return 'Not specified';
                                }

                                if ($date instanceof \Carbon\Carbon) {
                                    return $date->format('F j, Y');
                                }

                                return is_string($date) ? $date : 'Invalid date';
                            }),

                        Forms\Components\Placeholder::make('review_quantity')
                            ->label('Quantity')
                            ->content(function (Get $get): string {
                                $quantity = is_numeric($get('quantity')) ? (int) $get('quantity') : 0;

                                return "{$quantity} bottles";
                            }),

                        Forms\Components\Placeholder::make('review_packaging')
                            ->label('Packaging')
                            ->content(function (Get $get): string {
                                $packaging = $get('packaging');

                                return match ($packaging) {
                                    'cases' => 'Cases',
                                    'loose' => 'Loose',
                                    'mixed' => 'Mixed',
                                    default => 'Not specified',
                                };
                            }),

                        Forms\Components\Placeholder::make('review_condition')
                            ->label('Condition Notes')
                            ->content(function (Get $get): string {
                                $notes = $get('condition_notes');

                                return is_string($notes) && $notes !== '' ? $notes : 'No notes';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                // Sourcing Context Summary
                Forms\Components\Section::make('Sourcing Context')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Forms\Components\Placeholder::make('review_po')
                            ->label('Purchase Order')
                            ->content(function (Get $get): string {
                                $poId = $get('purchase_order_id');
                                if (! is_string($poId) || $poId === '') {
                                    return 'Not linked';
                                }

                                $po = PurchaseOrder::with('supplier')->find($poId);

                                return $po !== null ? self::formatPurchaseOrderOption($po) : 'Not found';
                            }),

                        Forms\Components\Placeholder::make('review_intent')
                            ->label('Procurement Intent')
                            ->content(function (Get $get): string {
                                $intentId = $get('procurement_intent_id');
                                if (! is_string($intentId) || $intentId === '') {
                                    return 'Not linked';
                                }

                                $intent = ProcurementIntent::with('productReference')->find($intentId);

                                return $intent !== null ? self::formatIntentOption($intent) : 'Not found';
                            }),

                        Forms\Components\Placeholder::make('review_ownership')
                            ->label('Ownership Status')
                            ->content(function (Get $get): HtmlString {
                                $flag = $get('ownership_flag');

                                $label = match ($flag) {
                                    'owned' => 'Owned',
                                    'in_custody' => 'In Custody',
                                    'pending' => 'Pending',
                                    default => 'Not specified',
                                };

                                $color = match ($flag) {
                                    'owned' => 'text-green-600 dark:text-green-400',
                                    'in_custody' => 'text-blue-600 dark:text-blue-400',
                                    'pending' => 'text-amber-600 dark:text-amber-400 font-semibold',
                                    default => 'text-gray-600 dark:text-gray-400',
                                };

                                return new HtmlString("<span class=\"{$color}\">{$label}</span>");
                            }),
                    ])
                    ->columns(3),

                // Serialization Summary
                Forms\Components\Section::make('Serialization')
                    ->icon('heroicon-o-qr-code')
                    ->schema([
                        Forms\Components\Placeholder::make('review_serialization_required')
                            ->label('Serialization Required')
                            ->content(fn (Get $get): string => $get('serialization_required') === true ? 'Yes' : 'No'),

                        Forms\Components\Placeholder::make('review_serialization_location')
                            ->label('Authorized Location')
                            ->content(function (Get $get): string {
                                if ($get('serialization_required') !== true) {
                                    return 'N/A';
                                }

                                $location = $get('serialization_location_authorized');

                                return match ($location) {
                                    'main_warehouse' => 'Main Warehouse',
                                    'secondary_warehouse' => 'Secondary Warehouse',
                                    'bonded_warehouse' => 'Bonded Warehouse',
                                    'third_party_storage' => 'Third Party Storage',
                                    'france_facility' => 'France Facility',
                                    'uk_facility' => 'UK Facility',
                                    'origin_winery' => 'Origin Winery',
                                    default => 'Not specified',
                                };
                            }),

                        Forms\Components\Placeholder::make('review_routing_rule')
                            ->label('Routing Rule')
                            ->content(function (Get $get): string {
                                if ($get('serialization_required') !== true) {
                                    return 'N/A';
                                }

                                $rule = $get('serialization_routing_rule');

                                return is_string($rule) && $rule !== '' ? $rule : 'No special rules';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Format a PurchaseOrder for display in select options.
     */
    protected static function formatPurchaseOrderOption(PurchaseOrder $po): string
    {
        $id = substr($po->id, 0, 8);
        $supplier = $po->supplier->legal_name ?? 'Unknown';
        $status = $po->status->label();

        return "{$id}... | {$supplier} | {$status}";
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
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default status to Recorded
        $data['status'] = InboundStatus::Recorded->value;

        // Set default ownership flag if not set
        if (empty($data['ownership_flag'])) {
            $data['ownership_flag'] = OwnershipFlag::Pending->value;
        }

        // Set default serialization_required if not set
        if (! isset($data['serialization_required'])) {
            $data['serialization_required'] = true;
        }

        // Ensure quantity is an integer
        if (isset($data['quantity'])) {
            $data['quantity'] = (int) $data['quantity'];
        }

        // Set handed_to_module_b default
        $data['handed_to_module_b'] = false;

        // If PO is selected but product reference is not set, get it from PO
        if (isset($data['purchase_order_id']) && is_string($data['purchase_order_id'])) {
            $po = PurchaseOrder::find($data['purchase_order_id']);
            if ($po !== null && empty($data['product_reference_type'])) {
                $data['product_reference_type'] = $po->product_reference_type;
                $data['product_reference_id'] = $po->product_reference_id;
            }
        }

        // If Intent is selected but product reference is not set, get it from Intent
        if (isset($data['procurement_intent_id']) && is_string($data['procurement_intent_id'])) {
            $intent = ProcurementIntent::find($data['procurement_intent_id']);
            if ($intent !== null && empty($data['product_reference_type'])) {
                $data['product_reference_type'] = $intent->product_reference_type;
                $data['product_reference_id'] = $intent->product_reference_id;
            }
        }

        // Clean up temporary fields
        unset(
            $data['po_preview_data'],
            $data['intent_preview_data'],
            $data['intent_manually_selected']
        );

        return $data;
    }

    /**
     * After creating the inbound, show success notification.
     */
    protected function afterCreate(): void
    {
        $record = $this->record;
        $warnings = [];

        if ($record instanceof \App\Models\Procurement\Inbound) {
            if ($record->hasOwnershipPending()) {
                $warnings[] = 'Ownership status is pending and must be clarified';
            }
            if ($record->isUnlinked()) {
                $warnings[] = 'Inbound is not linked to a Procurement Intent';
            }
        }

        if (count($warnings) > 0) {
            Notification::make()
                ->warning()
                ->title('Inbound Recorded with Warnings')
                ->body('The inbound has been recorded, but: '.implode('; ', $warnings))
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Inbound Recorded')
                ->body('The inbound has been recorded in "Recorded" status. Next: Route to serialization location.')
                ->send();
        }
    }
}
