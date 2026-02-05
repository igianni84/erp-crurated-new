<?php

namespace App\Filament\Resources\Procurement\BottlingInstructionResource\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Filament\Resources\Procurement\BottlingInstructionResource;
use App\Models\Pim\LiquidProduct;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\ProducerSupplierConfig;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreateBottlingInstruction extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = BottlingInstructionResource::class;

    /**
     * Get the form for creating a bottling instruction.
     * Implements a 4-step wizard for Bottling Instruction creation.
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
     * Get the wizard submit actions (Create as Draft, Create and Activate).
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
                        Create as Draft
                    </x-filament::button>
                    <x-filament::button
                        type="submit"
                        size="sm"
                        color="success"
                        wire:click="$set('create_and_activate', true)"
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
            $this->getLiquidProductStep(),
            $this->getRulesStep(),
            $this->getPersonalisationStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Liquid Product Selection (US-029)
     * Select the procurement intent with liquid product as prerequisite.
     */
    protected function getLiquidProductStep(): Wizard\Step
    {
        return Wizard\Step::make('Liquid Product')
            ->description('Select the Procurement Intent')
            ->icon('heroicon-o-beaker')
            ->schema([
                // Informational message about Bottling Instructions
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('bottling_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'🍾 <strong>Bottling Instructions</strong> manage post-sale bottling decisions for liquid products. '
                                .'They define allowed formats, customer preference deadlines, and default bottling rules.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Intent Selection
                Forms\Components\Section::make('Select Procurement Intent')
                    ->description('Choose from approved or executed intents with liquid products')
                    ->schema([
                        Forms\Components\Select::make('procurement_intent_id')
                            ->label('Procurement Intent')
                            ->placeholder('Search for an intent by ID or wine name...')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search): array {
                                return ProcurementIntent::query()
                                    ->whereIn('status', [
                                        ProcurementIntentStatus::Approved->value,
                                        ProcurementIntentStatus::Executed->value,
                                    ])
                                    // Filter for liquid products only
                                    ->where('product_reference_type', 'liquid_products')
                                    ->where(function ($query) use ($search): void {
                                        $query->where('id', 'like', "%{$search}%")
                                            ->orWhereHas('productReference', function ($q) use ($search): void {
                                                // Search through liquid product's wine variant -> wine master
                                                $q->whereHas('wineVariant.wineMaster', function ($wq) use ($search): void {
                                                    $wq->where('name', 'like', "%{$search}%");
                                                });
                                            });
                                    })
                                    ->with(['productReference.wineVariant.wineMaster'])
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (ProcurementIntent $intent): array => [
                                        $intent->id => self::formatIntentOption($intent),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (string $value): ?string {
                                $intent = ProcurementIntent::with('productReference.wineVariant.wineMaster')->find($value);

                                return $intent !== null ? self::formatIntentOption($intent) : null;
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if ($state === null) {
                                    $set('intent_preview_data', null);
                                    $set('liquid_product_id', null);
                                    $set('bottle_equivalents', null);

                                    return;
                                }

                                $intent = ProcurementIntent::with(['productReference.wineVariant.wineMaster'])->find($state);

                                if ($intent !== null) {
                                    // Verify it's for a liquid product
                                    if (! $intent->isForLiquidProduct()) {
                                        Notification::make()
                                            ->warning()
                                            ->title('Invalid Intent')
                                            ->body('Bottling Instructions can only be created for intents with liquid products.')
                                            ->send();

                                        $set('procurement_intent_id', null);

                                        return;
                                    }

                                    // Store intent data for preview
                                    $liquidProduct = $intent->productReference;
                                    $productLabel = $intent->getProductLabel();
                                    $wineInfo = '';
                                    $vintageYear = '';

                                    if ($liquidProduct instanceof LiquidProduct) {
                                        $wineVariant = $liquidProduct->wineVariant;
                                        if ($wineVariant !== null && $wineVariant->wineMaster !== null) {
                                            $wineInfo = $wineVariant->wineMaster->name;
                                            $vintageYear = (string) $wineVariant->vintage_year;
                                        }
                                    }

                                    $set('intent_preview_data', [
                                        'product_label' => $productLabel,
                                        'wine_name' => $wineInfo,
                                        'vintage_year' => $vintageYear,
                                        'quantity' => $intent->quantity,
                                        'sourcing_model' => $intent->sourcing_model->label(),
                                        'trigger_type' => $intent->trigger_type->label(),
                                        'status' => $intent->status->label(),
                                        'preferred_location' => $intent->preferred_inbound_location,
                                    ]);

                                    // Auto-fill liquid_product_id and bottle_equivalents from intent
                                    $set('liquid_product_id', $intent->product_reference_id);
                                    $set('bottle_equivalents', $intent->quantity);

                                    // Pre-fill delivery location from intent
                                    $set('delivery_location', $intent->preferred_inbound_location);
                                }
                            })
                            ->helperText('Only intents with liquid products in Approved or Executed status are shown'),
                    ]),

                // Intent Preview
                Forms\Components\Section::make('Intent Summary')
                    ->description('Preview of the selected procurement intent')
                    ->schema([
                        Forms\Components\Placeholder::make('intent_product')
                            ->label('Liquid Product')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['product_label'])) {
                                    return 'Select an intent to see details';
                                }

                                return is_string($data['product_label']) ? $data['product_label'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('intent_wine')
                            ->label('Wine')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['wine_name'])) {
                                    return '-';
                                }

                                return is_string($data['wine_name']) ? $data['wine_name'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('intent_vintage')
                            ->label('Vintage')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['vintage_year'])) {
                                    return '-';
                                }

                                return is_string($data['vintage_year']) ? $data['vintage_year'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('intent_quantity')
                            ->label('Bottle Equivalents')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['quantity'])) {
                                    return '-';
                                }
                                $quantity = is_numeric($data['quantity']) ? (int) $data['quantity'] : 0;

                                return "{$quantity} bottle-equivalents";
                            }),

                        Forms\Components\Placeholder::make('intent_sourcing_model')
                            ->label('Sourcing Model')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['sourcing_model'])) {
                                    return '-';
                                }

                                return is_string($data['sourcing_model']) ? $data['sourcing_model'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('intent_status')
                            ->label('Intent Status')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['status'])) {
                                    return '-';
                                }

                                return is_string($data['status']) ? $data['status'] : 'Unknown';
                            }),
                    ])
                    ->columns(3)
                    ->hidden(fn (Get $get): bool => $get('procurement_intent_id') === null),

                // Liquid Product Info
                Forms\Components\Section::make('Liquid Product Information')
                    ->description('Details of the liquid product from the intent')
                    ->schema([
                        Forms\Components\Placeholder::make('auto_filled_note')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">'
                                .'<p class="text-green-700 dark:text-green-300 text-sm">'
                                .'✓ <strong>Auto-filled:</strong> The liquid product ID and bottle equivalents have been automatically filled from the selected intent. '
                                .'These values will be used for the bottling instruction.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('liquid_product_display')
                            ->label('Liquid Product ID')
                            ->content(function (Get $get): string {
                                $liquidProductId = $get('liquid_product_id');
                                if (! is_string($liquidProductId) || $liquidProductId === '') {
                                    return '-';
                                }

                                return substr($liquidProductId, 0, 8).'...';
                            }),

                        Forms\Components\Placeholder::make('bottle_equivalents_display')
                            ->label('Bottle Equivalents')
                            ->content(function (Get $get): string {
                                $bottleEquivalents = $get('bottle_equivalents');
                                if (! is_numeric($bottleEquivalents)) {
                                    return '-';
                                }

                                return (string) (int) $bottleEquivalents.' bottle-equivalents';
                            }),
                    ])
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => $get('procurement_intent_id') === null),

                // Hidden fields to store data
                Forms\Components\Hidden::make('intent_preview_data'),
                Forms\Components\Hidden::make('liquid_product_id'),
                Forms\Components\Hidden::make('bottle_equivalents'),
            ]);
    }

    /**
     * Step 2: Bottling Rules (US-030)
     * Define bottling rules (allowed formats, case configurations, default rule).
     */
    protected function getRulesStep(): Wizard\Step
    {
        return Wizard\Step::make('Bottling Rules')
            ->description('Define allowed formats and default rules')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Forms\Components\Section::make('Allowed Formats')
                    ->description('Define which bottle formats are allowed for this bottling instruction')
                    ->schema([
                        Forms\Components\Select::make('allowed_formats')
                            ->label('Allowed Bottle Formats')
                            ->multiple()
                            ->options([
                                '375ml' => '375ml (Half Bottle)',
                                '750ml' => '750ml (Standard)',
                                '1500ml' => '1500ml (Magnum)',
                                '3000ml' => '3000ml (Double Magnum)',
                                '6000ml' => '6000ml (Imperial)',
                            ])
                            ->default(['750ml'])
                            ->helperText('Select all formats that customers can choose from')
                            ->live(),

                        // Show suggestion from ProducerSupplierConfig if available
                        Forms\Components\Placeholder::make('format_suggestion')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                // In a full implementation, we would fetch the supplier config
                                // based on the intent's allocation source
                                return new HtmlString(
                                    '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">'
                                    .'<p class="text-gray-600 dark:text-gray-400 text-sm">'
                                    .'ℹ️ <strong>Tip:</strong> Common formats are 750ml (standard) and 1500ml (Magnum). '
                                    .'The producer may have specific format constraints in their configuration.'
                                    .'</p></div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Case Configurations')
                    ->description('Define allowed case configurations')
                    ->schema([
                        Forms\Components\Select::make('allowed_case_configurations')
                            ->label('Allowed Case Configurations')
                            ->multiple()
                            ->options([
                                '1' => '1 bottle per case',
                                '3' => '3 bottles per case',
                                '6' => '6 bottles per case',
                                '12' => '12 bottles per case',
                            ])
                            ->default(['6', '12'])
                            ->helperText('Select all case configurations that customers can choose from'),
                    ]),

                Forms\Components\Section::make('Default Bottling Rule')
                    ->description('Define the default rule applied if customer doesn\'t specify preferences by deadline')
                    ->schema([
                        Forms\Components\Textarea::make('default_bottling_rule')
                            ->label('Default Bottling Rule')
                            ->placeholder('e.g., "If no preference received by deadline, bottle in 750ml format, 6 bottles per case, standard labels"')
                            ->rows(4)
                            ->helperText('This rule will be applied automatically if the customer doesn\'t specify preferences by the bottling deadline'),

                        // Preview of the rule in plain language
                        Forms\Components\Placeholder::make('rule_preview')
                            ->label('Rule Preview')
                            ->content(function (Get $get): HtmlString {
                                $formats = $get('allowed_formats');
                                $cases = $get('allowed_case_configurations');
                                $rule = $get('default_bottling_rule');

                                $formatsText = is_array($formats) && count($formats) > 0
                                    ? implode(', ', $formats)
                                    : 'None selected';

                                $casesText = is_array($cases) && count($cases) > 0
                                    ? implode(', ', array_map(fn ($c) => $c.' bottles/case', $cases))
                                    : 'None selected';

                                $ruleText = is_string($rule) && $rule !== ''
                                    ? $rule
                                    : 'No default rule specified';

                                return new HtmlString(
                                    '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-2">'
                                    .'<p class="text-sm"><strong>Allowed Formats:</strong> '.$formatsText.'</p>'
                                    .'<p class="text-sm"><strong>Case Configurations:</strong> '.$casesText.'</p>'
                                    .'<p class="text-sm"><strong>Default Rule:</strong> '.$ruleText.'</p>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 3: Personalisation Flags (US-031)
     * Define personalisation flags and deadline.
     */
    protected function getPersonalisationStep(): Wizard\Step
    {
        return Wizard\Step::make('Personalisation')
            ->description('Define deadline and personalisation options')
            ->icon('heroicon-o-user-circle')
            ->schema([
                Forms\Components\Section::make('Bottling Deadline')
                    ->description('Set the deadline for customer preference collection')
                    ->schema([
                        Forms\Components\DatePicker::make('bottling_deadline')
                            ->label('Bottling Deadline')
                            ->required()
                            ->native(false)
                            ->minDate(now()->addDays(1))
                            ->live()
                            ->helperText('Customers must submit their preferences by this date'),

                        // Warning for short deadline
                        Forms\Components\Placeholder::make('deadline_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-amber-50 dark:bg-amber-950 rounded-lg border border-amber-200 dark:border-amber-800">'
                                .'<p class="text-amber-700 dark:text-amber-300 text-sm">'
                                .'⚠️ <strong>Important:</strong> After the deadline, defaults will be applied automatically '
                                .'to all customers who haven\'t submitted their preferences.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Delivery Location')
                    ->description('Where should the bottled wine be delivered?')
                    ->schema([
                        Forms\Components\Select::make('delivery_location')
                            ->label('Delivery Location')
                            ->options([
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                            ])
                            ->placeholder('Select delivery location...')
                            ->helperText('Pre-filled from intent preferred location if available'),
                    ]),

                Forms\Components\Section::make('Personalisation Options')
                    ->description('Advanced options for personalised bottling')
                    ->schema([
                        Forms\Components\Toggle::make('personalised_bottling_required')
                            ->label('Personalised Bottling Required')
                            ->helperText('Check if customers need personalised labels or special bottling requirements')
                            ->live()
                            ->default(false),

                        Forms\Components\Placeholder::make('personalised_explanation')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-purple-50 dark:bg-purple-950 rounded-lg">'
                                .'<p class="text-purple-700 dark:text-purple-300 text-sm">'
                                .'🎨 <strong>Personalised Bottling:</strong> When enabled, customers can request custom labels, '
                                .'special packaging, or other personalisation options for their bottles.'
                                .'</p></div>'
                            ))
                            ->hidden(fn (Get $get): bool => $get('personalised_bottling_required') !== true)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('early_binding_required')
                            ->label('Early Binding Required')
                            ->helperText('If true, voucher-bottle binding happens before bottling')
                            ->live()
                            ->default(false),

                        Forms\Components\Placeholder::make('early_binding_explanation')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-indigo-50 dark:bg-indigo-950 rounded-lg">'
                                .'<p class="text-indigo-700 dark:text-indigo-300 text-sm">'
                                .'🔗 <strong>Early Binding:</strong> When enabled, each voucher is bound to a specific bottle '
                                .'before the bottling process begins. This is required for serialized or limited editions.'
                                .'</p></div>'
                            ))
                            ->hidden(fn (Get $get): bool => $get('early_binding_required') !== true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 4: Review (US-032)
     * Review all data before creating with deadline warning.
     */
    protected function getReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review')
            ->description('Review and create the instruction')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Draft status info
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('draft_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">'
                                .'<p class="text-blue-700 dark:text-blue-300 font-medium">'
                                .'📋 Draft instructions are not active until status changes to Active. '
                                .'You can also choose "Create and Activate" to start immediately.'
                                .'</p></div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                // Deadline countdown
                Forms\Components\Section::make('Deadline Status')
                    ->schema([
                        Forms\Components\Placeholder::make('deadline_countdown')
                            ->label('Bottling Deadline')
                            ->content(function (Get $get): HtmlString {
                                $deadline = $get('bottling_deadline');

                                if ($deadline === null) {
                                    return new HtmlString('<span class="text-gray-500">Not set</span>');
                                }

                                $deadlineDate = \Carbon\Carbon::parse($deadline);
                                $daysUntil = (int) now()->startOfDay()->diffInDays($deadlineDate, false);

                                $colorClass = match (true) {
                                    $daysUntil < 14 => 'text-red-600 dark:text-red-400',
                                    $daysUntil < 30 => 'text-amber-600 dark:text-amber-400',
                                    default => 'text-green-600 dark:text-green-400',
                                };

                                $urgencyText = match (true) {
                                    $daysUntil < 14 => '⚠️ Critical - Less than 2 weeks!',
                                    $daysUntil < 30 => '⏰ Warning - Less than 30 days',
                                    default => '✓ Adequate time',
                                };

                                return new HtmlString(
                                    '<div class="space-y-2">'
                                    .'<p class="text-lg font-semibold">'.$deadlineDate->format('F j, Y').'</p>'
                                    .'<p class="'.$colorClass.' font-medium">'.$daysUntil.' days remaining</p>'
                                    .'<p class="text-sm '.$colorClass.'">'.$urgencyText.'</p>'
                                    .'</div>'
                                );
                            }),

                        // Warning for deadline < 30 days
                        Forms\Components\Placeholder::make('deadline_short_warning')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-3 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">'
                                .'<p class="text-red-700 dark:text-red-300 text-sm">'
                                .'⚠️ <strong>Warning:</strong> The deadline is less than 30 days away. '
                                .'Customers may not have enough time to submit their preferences.'
                                .'</p></div>'
                            ))
                            ->hidden(function (Get $get): bool {
                                $deadline = $get('bottling_deadline');
                                if ($deadline === null) {
                                    return true;
                                }
                                $daysUntil = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($deadline), false);

                                return $daysUntil >= 30;
                            })
                            ->columnSpanFull(),
                    ]),

                // Linked Intent Summary
                Forms\Components\Section::make('Linked Procurement Intent')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Forms\Components\Placeholder::make('review_intent')
                            ->label('Intent')
                            ->content(function (Get $get): string {
                                $intentId = $get('procurement_intent_id');
                                if (! is_string($intentId)) {
                                    return 'Not selected';
                                }

                                $intent = ProcurementIntent::find($intentId);

                                return $intent !== null ? self::formatIntentOption($intent) : 'Not found';
                            }),

                        Forms\Components\Placeholder::make('review_intent_product')
                            ->label('Liquid Product')
                            ->content(function (Get $get): string {
                                $data = $get('intent_preview_data');
                                if (! is_array($data) || ! isset($data['product_label'])) {
                                    return '-';
                                }

                                return is_string($data['product_label']) ? $data['product_label'] : 'Unknown';
                            }),

                        Forms\Components\Placeholder::make('review_bottle_equivalents')
                            ->label('Bottle Equivalents')
                            ->content(function (Get $get): string {
                                $bottleEquivalents = $get('bottle_equivalents');
                                if (! is_numeric($bottleEquivalents)) {
                                    return '-';
                                }

                                return (string) (int) $bottleEquivalents.' bottle-equivalents';
                            }),
                    ])
                    ->columns(3),

                // Bottling Rules Summary
                Forms\Components\Section::make('Bottling Rules')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Placeholder::make('review_formats')
                            ->label('Allowed Formats')
                            ->content(function (Get $get): string {
                                $formats = $get('allowed_formats');
                                if (! is_array($formats) || count($formats) === 0) {
                                    return 'None selected';
                                }

                                return implode(', ', $formats);
                            }),

                        Forms\Components\Placeholder::make('review_cases')
                            ->label('Case Configurations')
                            ->content(function (Get $get): string {
                                $cases = $get('allowed_case_configurations');
                                if (! is_array($cases) || count($cases) === 0) {
                                    return 'None selected';
                                }

                                return implode(', ', array_map(fn ($c) => $c.' bottles/case', $cases));
                            }),

                        Forms\Components\Placeholder::make('review_default_rule')
                            ->label('Default Rule')
                            ->content(function (Get $get): string {
                                $rule = $get('default_bottling_rule');

                                return is_string($rule) && $rule !== '' ? $rule : 'Not specified';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Personalisation Summary
                Forms\Components\Section::make('Personalisation')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Forms\Components\Placeholder::make('review_delivery')
                            ->label('Delivery Location')
                            ->content(function (Get $get): string {
                                $location = $get('delivery_location');

                                return match ($location) {
                                    'main_warehouse' => 'Main Warehouse',
                                    'secondary_warehouse' => 'Secondary Warehouse',
                                    'bonded_warehouse' => 'Bonded Warehouse',
                                    'third_party_storage' => 'Third Party Storage',
                                    default => 'Not specified',
                                };
                            }),

                        Forms\Components\Placeholder::make('review_personalised')
                            ->label('Personalised Bottling')
                            ->content(fn (Get $get): string => $get('personalised_bottling_required') === true ? 'Yes' : 'No'),

                        Forms\Components\Placeholder::make('review_early_binding')
                            ->label('Early Binding')
                            ->content(fn (Get $get): string => $get('early_binding_required') === true ? 'Yes' : 'No'),
                    ])
                    ->columns(3),

                // Hidden field for create and activate
                Forms\Components\Hidden::make('create_and_activate')
                    ->default(false),
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
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure liquid_product_id is set
        if (empty($data['liquid_product_id'])) {
            $intentId = $data['procurement_intent_id'] ?? null;
            if (is_string($intentId)) {
                $intent = ProcurementIntent::find($intentId);
                if ($intent !== null && $intent->isForLiquidProduct()) {
                    $data['liquid_product_id'] = $intent->product_reference_id;
                    $data['bottle_equivalents'] = $intent->quantity;
                }
            }
        }

        // Ensure bottle_equivalents is an integer
        if (isset($data['bottle_equivalents'])) {
            $data['bottle_equivalents'] = (int) $data['bottle_equivalents'];
        }

        // Set default status based on create_and_activate flag
        $createAndActivate = $data['create_and_activate'] ?? false;
        $data['status'] = $createAndActivate === true
            ? BottlingInstructionStatus::Active->value
            : BottlingInstructionStatus::Draft->value;

        // Set default preference status
        $data['preference_status'] = BottlingPreferenceStatus::Pending->value;

        // Set default boolean values
        $data['personalised_bottling_required'] = $data['personalised_bottling_required'] ?? false;
        $data['early_binding_required'] = $data['early_binding_required'] ?? false;

        // Clean up temporary fields
        unset(
            $data['intent_preview_data'],
            $data['create_and_activate']
        );

        return $data;
    }

    /**
     * After creating the bottling instruction, show success notification.
     */
    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record instanceof \App\Models\Procurement\BottlingInstruction && $record->isActive()) {
            Notification::make()
                ->success()
                ->title('Bottling Instruction created and activated')
                ->body('The bottling instruction has been created and is now active. Customer preferences can be collected.')
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title('Bottling Instruction created as Draft')
                ->body('The bottling instruction has been created in Draft status. Activate it when ready to start collecting preferences.')
                ->send();
        }
    }
}
