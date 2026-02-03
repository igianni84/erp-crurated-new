<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;

class CreatePricingPolicy extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PricingPolicyResource::class;

    /**
     * Get the form for creating a pricing policy.
     * Implements a multi-step wizard for pricing policy creation.
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
     * Get the wizard submit action.
     */
    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
                <x-filament::button
                    type="submit"
                    size="sm"
                >
                    Create as Draft
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
            $this->getTypeStep(),
        ];
    }

    /**
     * Step 1: Type Selection
     * Defines the name and policy type of the pricing policy.
     */
    protected function getTypeStep(): Wizard\Step
    {
        return Wizard\Step::make('Type')
            ->description('Select the type of Pricing Policy')
            ->icon('heroicon-o-calculator')
            ->schema([
                // Info section
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('policy_info')
                            ->label('')
                            ->content('Pricing Policies automate the generation of prices for your Price Books. Each policy type uses a different calculation method to determine prices.')
                            ->columnSpanFull(),
                    ]),

                // Name section
                Forms\Components\Section::make('Policy Identity')
                    ->description('Give your policy a clear, descriptive name')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., B2C Cost + 30% Margin Policy')
                            ->helperText('Use a descriptive name that indicates the policy type and purpose'),
                    ]),

                // Policy type selection
                Forms\Components\Section::make('Policy Type')
                    ->description('Choose how this policy will calculate prices')
                    ->schema([
                        Forms\Components\Radio::make('policy_type')
                            ->label('')
                            ->options([
                                PricingPolicyType::CostPlusMargin->value => PricingPolicyType::CostPlusMargin->label(),
                                PricingPolicyType::ReferencePriceBook->value => PricingPolicyType::ReferencePriceBook->label(),
                                PricingPolicyType::IndexBased->value => 'External Index (EMP/FX)',
                                PricingPolicyType::FixedAdjustment->value => PricingPolicyType::FixedAdjustment->label(),
                                PricingPolicyType::Rounding->value => 'Rounding/Normalization',
                            ])
                            ->descriptions([
                                PricingPolicyType::CostPlusMargin->value => 'Calculate prices as cost plus a percentage margin. Best for maintaining consistent profit margins across products.',
                                PricingPolicyType::ReferencePriceBook->value => 'Use another Price Book as a reference and apply adjustments. Useful for creating channel-specific pricing from a master price book.',
                                PricingPolicyType::IndexBased->value => 'Calculate prices based on external market indexes like Estimated Market Price (EMP) or currency exchange rates (FX).',
                                PricingPolicyType::FixedAdjustment->value => 'Apply a fixed percentage or amount adjustment to existing prices. Good for temporary promotions or regional adjustments.',
                                PricingPolicyType::Rounding->value => 'Normalize prices to specific patterns (e.g., .99, .95, nearest 5). Apply as a final step to other policies.',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        // Dynamic info based on selected type
                        Forms\Components\Placeholder::make('type_details')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $type = $get('policy_type');

                                if ($type === null) {
                                    return new HtmlString('<p class="text-gray-500">Select a policy type to see more details.</p>');
                                }

                                $policyType = PricingPolicyType::from($type);

                                $details = match ($policyType) {
                                    PricingPolicyType::CostPlusMargin => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'green',
                                        'title' => 'Cost + Margin Policy',
                                        'description' => 'This policy calculates prices by adding a margin to the product cost.',
                                        'inputs' => 'Cost data from your product catalog',
                                        'example' => 'If cost is €50 and margin is 30%, the price will be €65',
                                        'best_for' => 'Standard retail pricing with consistent profit margins',
                                    ],
                                    PricingPolicyType::ReferencePriceBook => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'blue',
                                        'title' => 'Reference Price Book Policy',
                                        'description' => 'This policy uses prices from another Price Book as a starting point and applies adjustments.',
                                        'inputs' => 'An existing Price Book with established prices',
                                        'example' => 'B2B prices = B2C prices - 15% discount',
                                        'best_for' => 'Creating derivative pricing (e.g., wholesale from retail)',
                                    ],
                                    PricingPolicyType::IndexBased => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'amber',
                                        'title' => 'External Index Policy',
                                        'description' => 'This policy calculates prices based on external market data like Estimated Market Price (EMP) or currency exchange rates.',
                                        'inputs' => 'EMP values, FX rates, or other external indexes',
                                        'example' => 'Price = EMP × 1.1 (10% above market)',
                                        'best_for' => 'Market-aware pricing that tracks competition',
                                    ],
                                    PricingPolicyType::FixedAdjustment => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'purple',
                                        'title' => 'Fixed Adjustment Policy',
                                        'description' => 'This policy applies a fixed percentage or amount adjustment to prices.',
                                        'inputs' => 'Existing prices from the target Price Book',
                                        'example' => '+5% price increase or -€10 per unit',
                                        'best_for' => 'Price adjustments, seasonal changes, regional pricing',
                                    ],
                                    PricingPolicyType::Rounding => [
                                        'icon' => $policyType->icon(),
                                        'color' => 'gray',
                                        'title' => 'Rounding/Normalization Policy',
                                        'description' => 'This policy normalizes prices to psychological price points.',
                                        'inputs' => 'Existing prices from the target Price Book',
                                        'example' => '€64.37 → €64.99 (rounded to .99)',
                                        'best_for' => 'Final price formatting for customer-facing prices',
                                    ],
                                };

                                $colorClass = match ($details['color']) {
                                    'green' => 'bg-green-50 border-green-200',
                                    'blue' => 'bg-blue-50 border-blue-200',
                                    'amber' => 'bg-amber-50 border-amber-200',
                                    'purple' => 'bg-purple-50 border-purple-200',
                                    'gray' => 'bg-gray-50 border-gray-200',
                                };

                                return new HtmlString(
                                    "<div class=\"p-4 rounded-lg border {$colorClass}\">".
                                    "<h4 class=\"font-semibold text-lg mb-2\">{$details['title']}</h4>".
                                    "<p class=\"mb-3\">{$details['description']}</p>".
                                    '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">'.
                                    '<div>'.
                                    '<p class="font-medium">Input Source:</p>'.
                                    "<p class=\"text-gray-600\">{$details['inputs']}</p>".
                                    '</div>'.
                                    '<div>'.
                                    '<p class="font-medium">Example:</p>'.
                                    "<p class=\"text-gray-600\">{$details['example']}</p>".
                                    '</div>'.
                                    '</div>'.
                                    '<div class="mt-3 text-sm">'.
                                    '<p class="font-medium">Best for:</p>'.
                                    "<p class=\"text-gray-600\">{$details['best_for']}</p>".
                                    '</div>'.
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set status to draft
        $data['status'] = PricingPolicyStatus::Draft->value;

        // Set default execution cadence to manual (will be configurable in Step 5)
        $data['execution_cadence'] = ExecutionCadence::Manual->value;

        // Set default input source based on policy type
        // This will be refined in Step 2 (Inputs)
        $policyType = PricingPolicyType::from($data['policy_type']);
        $data['input_source'] = match ($policyType) {
            PricingPolicyType::CostPlusMargin => PricingPolicyInputSource::Cost->value,
            PricingPolicyType::ReferencePriceBook => PricingPolicyInputSource::PriceBook->value,
            PricingPolicyType::IndexBased => PricingPolicyInputSource::Emp->value,
            PricingPolicyType::FixedAdjustment => PricingPolicyInputSource::PriceBook->value,
            PricingPolicyType::Rounding => PricingPolicyInputSource::PriceBook->value,
        };

        // Initialize empty logic_definition (will be populated in Step 3)
        $data['logic_definition'] = [];

        return $data;
    }

    /**
     * After creating the pricing policy.
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\Commercial\PricingPolicy $pricingPolicy */
        $pricingPolicy = $this->record;

        Notification::make()
            ->success()
            ->title('Pricing Policy created')
            ->body("The pricing policy \"{$pricingPolicy->name}\" has been created as Draft. Configure logic and scope, then activate when ready.")
            ->send();
    }
}
