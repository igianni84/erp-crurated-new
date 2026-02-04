<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\ConsumptionReason;
use App\Enums\Inventory\LocationType;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InventoryMovementResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\CommittedInventoryOverrideService;
use App\Services\Inventory\InventoryService;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Page for exceptional committed inventory consumption override.
 *
 * This page allows administrators to consume inventory that is committed
 * to voucher fulfillment. This is an EXCEPTIONAL operation that:
 *
 * - Requires Admin+ permission
 * - Requires explicit justification
 * - Creates InventoryException records for finance & ops review
 * - Is intentionally painful (multiple confirmations required)
 *
 * WARNING: This is NOT a normal operation and should only be used
 * in exceptional circumstances.
 *
 * @property Form $form
 */
class CommittedInventoryOverride extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Override Committed';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Committed Inventory Override';

    protected static string $view = 'filament.pages.committed-inventory-override';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Control visibility based on user permission.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->canConsumeCommittedInventory();
    }

    public function mount(): void
    {
        // Verify permission on mount
        if (! static::canAccess()) {
            abort(403, 'You do not have permission to consume committed inventory.');
        }

        $this->form->fill();
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string|Htmlable
    {
        return 'Committed Inventory Override';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'EXCEPTIONAL: Consume inventory committed to customer fulfillment';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Step 1: WARNING - Read and acknowledge
                    Wizard\Step::make('Warning')
                        ->description('Read the warning')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->schema([
                            Section::make('EXCEPTIONAL OPERATION WARNING')
                                ->schema([
                                    Forms\Components\Placeholder::make('warning_text')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="space-y-4">
                                                <div class="p-6 rounded-lg bg-danger-50 dark:bg-danger-900/30 border-2 border-danger-500 dark:border-danger-700">
                                                    <div class="flex">
                                                        <div class="flex-shrink-0">
                                                            <svg class="h-8 w-8 text-danger-600 dark:text-danger-400" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <div class="ml-4">
                                                            <h3 class="text-xl font-bold text-danger-800 dark:text-danger-200">
                                                                ⚠️ EXCEPTIONAL OPERATION
                                                            </h3>
                                                            <div class="mt-4 text-danger-700 dark:text-danger-300 space-y-3">
                                                                <p class="font-semibold">
                                                                    You are about to consume inventory that is <strong>COMMITTED TO CUSTOMER FULFILLMENT</strong>.
                                                                </p>
                                                                <p>
                                                                    This means:
                                                                </p>
                                                                <ul class="list-disc list-inside space-y-1 ml-4">
                                                                    <li>Customers have <strong>unredeemed vouchers</strong> tied to this inventory</li>
                                                                    <li>Consuming this inventory <strong>may impact customer fulfillment</strong></li>
                                                                    <li>This action creates an <strong>InventoryException record</strong> for finance & ops review</li>
                                                                    <li>You <strong>MUST provide explicit justification</strong> for this action</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-300 dark:border-warning-700">
                                                    <p class="text-warning-700 dark:text-warning-300">
                                                        <strong>This operation should ONLY be used in exceptional circumstances</strong>, such as:
                                                        <ul class="list-disc list-inside mt-2 space-y-1 ml-4">
                                                            <li>Emergency event consumption with no alternative inventory</li>
                                                            <li>Quality issues requiring immediate removal from inventory</li>
                                                            <li>Legal/regulatory requirements</li>
                                                        </ul>
                                                    </p>
                                                </div>

                                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                                                    <p class="text-gray-700 dark:text-gray-200">
                                                        <strong>If you can use non-committed inventory instead, please cancel this flow and use the standard Event Consumption page.</strong>
                                                    </p>
                                                </div>
                                            </div>
                                        HTML)),

                                    Forms\Components\Checkbox::make('acknowledge_warning')
                                        ->label('I have read and understand that this is an EXCEPTIONAL operation that impacts committed customer inventory')
                                        ->required()
                                        ->accepted()
                                        ->extraAttributes(['class' => 'text-danger-600']),
                                ]),
                        ]),

                    // Step 2: Select Location and Bottles
                    Wizard\Step::make('Select Items')
                        ->description('Choose committed bottles')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Section::make('Select Location')
                                ->schema([
                                    Forms\Components\Select::make('event_location_id')
                                        ->label('Event Location')
                                        ->options(function (): array {
                                            return Location::query()
                                                ->where('status', 'active')
                                                ->where('location_type', LocationType::EventLocation->value)
                                                ->orderBy('name')
                                                ->get()
                                                ->mapWithKeys(fn (Location $location): array => [
                                                    $location->id => "{$location->name} ({$location->country})",
                                                ])
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set): void {
                                            $set('selected_committed_bottles', []);
                                        })
                                        ->helperText('Select the event location where consumption will occur.'),

                                    Forms\Components\Placeholder::make('committed_bottles_info')
                                        ->label('Committed Bottles at Location')
                                        ->visible(fn (Get $get): bool => $get('event_location_id') !== null)
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return new HtmlString('');
                                            }

                                            $inventoryService = app(InventoryService::class);
                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return new HtmlString('<p class="text-gray-500">Location not found.</p>');
                                            }

                                            $committedCount = $inventoryService->getCommittedBottlesAtLocation($location)->count();
                                            $freeCount = SerializedBottle::where('current_location_id', $locationId)
                                                ->where('state', BottleState::Stored)
                                                ->where('ownership_type', OwnershipType::CururatedOwned->value)
                                                ->count() - $committedCount;

                                            if ($committedCount === 0) {
                                                return new HtmlString(<<<'HTML'
                                                    <div class="p-4 rounded-lg bg-success-50 dark:bg-success-900/20 border border-success-300 dark:border-success-700">
                                                        <p class="text-success-700 dark:text-success-300">
                                                            <strong>No committed bottles at this location.</strong>
                                                            <br><br>
                                                            Please use the standard <strong>Event Consumption</strong> page instead.
                                                        </p>
                                                    </div>
                                                HTML);
                                            }

                                            return new HtmlString(<<<HTML
                                                <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-300 dark:border-danger-700">
                                                    <p class="text-danger-700 dark:text-danger-300">
                                                        <strong>{$committedCount}</strong> committed bottles at this location
                                                        <br>
                                                        <span class="text-sm">({$freeCount} free bottles available for normal consumption)</span>
                                                    </p>
                                                </div>
                                            HTML);
                                        }),
                                ]),

                            Section::make('Select Committed Bottles')
                                ->description('Choose committed bottles to consume via override')
                                ->visible(fn (Get $get): bool => $get('event_location_id') !== null)
                                ->schema([
                                    Forms\Components\Placeholder::make('committed_warning')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-300 dark:border-danger-700">
                                                <p class="text-sm text-danger-700 dark:text-danger-300">
                                                    <strong>⚠️ These bottles are COMMITTED</strong> to customer voucher fulfillment.
                                                    Consuming them may impact customer orders.
                                                </p>
                                            </div>
                                        HTML)),

                                    Forms\Components\CheckboxList::make('selected_committed_bottles')
                                        ->label('')
                                        ->options(function (Get $get): array {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return [];
                                            }

                                            $inventoryService = app(InventoryService::class);
                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return [];
                                            }

                                            return $inventoryService->getCommittedBottlesAtLocation($location)
                                                ->load(['wineVariant.wineMaster', 'format', 'allocation'])
                                                ->take(50)
                                                ->mapWithKeys(function (SerializedBottle $bottle): array {
                                                    $wineName = 'Unknown Wine';
                                                    $wineVariant = $bottle->wineVariant;
                                                    if ($wineVariant !== null) {
                                                        $wineMaster = $wineVariant->wineMaster;
                                                        if ($wineMaster !== null) {
                                                            $wineName = "{$wineMaster->name} {$wineVariant->vintage_year}";
                                                        }
                                                    }
                                                    $format = $bottle->format;
                                                    $formatName = $format !== null ? $format->name : '';
                                                    $allocation = $bottle->allocation;
                                                    $allocationRef = $allocation ? 'Alloc: '.substr($allocation->id, 0, 8).'...' : '';

                                                    return [
                                                        $bottle->id => "⚠️ {$bottle->serial_number} - {$wineName} ({$formatName}) [{$allocationRef}]",
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->columns(1)
                                        ->helperText('Showing up to 50 committed bottles. Each bottle is tied to customer vouchers.'),
                                ]),
                        ])
                        ->afterValidation(function (Get $get): void {
                            /** @var array<int, string> $bottles */
                            $bottles = $get('selected_committed_bottles') ?? [];

                            if (count($bottles) === 0) {
                                Notification::make()
                                    ->title('No bottles selected')
                                    ->body('You must select at least one committed bottle to override.')
                                    ->danger()
                                    ->send();

                                throw new \Filament\Support\Exceptions\Halt;
                            }
                        }),

                    // Step 3: Justification (MANDATORY)
                    Wizard\Step::make('Justification')
                        ->description('Provide mandatory justification')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('Mandatory Justification')
                                ->description('You MUST provide a detailed justification for this exceptional operation.')
                                ->schema([
                                    Forms\Components\Placeholder::make('justification_notice')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-300 dark:border-primary-700">
                                                <p class="text-primary-700 dark:text-primary-300">
                                                    This justification will be:
                                                    <ul class="list-disc list-inside mt-2 space-y-1">
                                                        <li>Recorded in the <strong>InventoryException</strong> record</li>
                                                        <li>Reviewed by <strong>Finance & Operations</strong></li>
                                                        <li>Part of the permanent <strong>audit trail</strong></li>
                                                    </ul>
                                                </p>
                                            </div>
                                        HTML)),

                                    Forms\Components\Select::make('consumption_reason')
                                        ->label('Consumption Reason')
                                        ->options([
                                            ConsumptionReason::EventConsumption->value => ConsumptionReason::EventConsumption->label(),
                                            ConsumptionReason::Sampling->value => ConsumptionReason::Sampling->label(),
                                            ConsumptionReason::DamageWriteoff->value => ConsumptionReason::DamageWriteoff->label(),
                                        ])
                                        ->required()
                                        ->default(ConsumptionReason::EventConsumption->value),

                                    Forms\Components\TextInput::make('event_reference')
                                        ->label('Event Reference')
                                        ->placeholder('e.g., Annual Tasting 2026, VIP Dinner #123')
                                        ->maxLength(255)
                                        ->helperText('Optional - enter a reference to identify this event.'),

                                    Forms\Components\Textarea::make('override_justification')
                                        ->label('Justification (REQUIRED)')
                                        ->placeholder("Explain in detail why you need to consume committed inventory...\n\nInclude:\n- Why free inventory is not available\n- What event/situation requires this\n- Impact assessment\n- Any alternatives considered")
                                        ->required()
                                        ->minLength(20)
                                        ->rows(6)
                                        ->helperText('Minimum 20 characters. Be specific and detailed - this will be reviewed by Finance & Ops.'),
                                ]),
                        ]),

                    // Step 4: First Confirmation
                    Wizard\Step::make('Confirm 1/2')
                        ->description('First confirmation')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Section::make('Review Your Override Request')
                                ->schema([
                                    Forms\Components\Placeholder::make('review_summary')
                                        ->label('')
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('event_location_id');
                                            /** @var array<int, string> $bottles */
                                            $bottles = $get('selected_committed_bottles') ?? [];
                                            $justification = $get('override_justification') ?? '';
                                            $reason = $get('consumption_reason') ?? '';
                                            $eventRef = $get('event_reference') ?? '';

                                            $location = $locationId ? Location::find($locationId) : null;
                                            $locationName = $location ? "{$location->name} ({$location->country})" : 'Not selected';
                                            $bottleCount = count($bottles);
                                            $reasonLabel = $reason ? ConsumptionReason::tryFrom($reason)?->label() ?? $reason : 'Not selected';
                                            $justificationDisplay = htmlspecialchars(substr($justification, 0, 200)).(strlen($justification) > 200 ? '...' : '');
                                            $eventRefDisplay = $eventRef ? htmlspecialchars($eventRef) : '<em>Not specified</em>';

                                            return new HtmlString(<<<HTML
                                                <div class="space-y-4">
                                                    <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                                        <h4 class="font-bold text-danger-700 dark:text-danger-300 mb-2">Override Summary</h4>
                                                        <dl class="space-y-2 text-sm">
                                                            <div class="flex">
                                                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-40">Location:</dt>
                                                                <dd class="text-gray-900 dark:text-gray-100">{$locationName}</dd>
                                                            </div>
                                                            <div class="flex">
                                                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-40">Bottles to consume:</dt>
                                                                <dd class="text-danger-600 dark:text-danger-400 font-bold">{$bottleCount} COMMITTED bottle(s)</dd>
                                                            </div>
                                                            <div class="flex">
                                                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-40">Reason:</dt>
                                                                <dd class="text-gray-900 dark:text-gray-100">{$reasonLabel}</dd>
                                                            </div>
                                                            <div class="flex">
                                                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-40">Event Reference:</dt>
                                                                <dd class="text-gray-900 dark:text-gray-100">{$eventRefDisplay}</dd>
                                                            </div>
                                                            <div class="mt-3 pt-3 border-t border-danger-200 dark:border-danger-700">
                                                                <dt class="font-medium text-gray-700 dark:text-gray-300 mb-1">Justification:</dt>
                                                                <dd class="text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 p-2 rounded">{$justificationDisplay}</dd>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </div>
                                            HTML);
                                        }),

                                    Forms\Components\Checkbox::make('first_confirmation')
                                        ->label('I confirm that I want to proceed with this COMMITTED INVENTORY OVERRIDE')
                                        ->required()
                                        ->accepted(),
                                ]),
                        ]),

                    // Step 5: Final Confirmation (intentionally painful)
                    Wizard\Step::make('FINAL Confirm')
                        ->description('Final confirmation')
                        ->icon('heroicon-o-fire')
                        ->schema([
                            Section::make('FINAL CONFIRMATION')
                                ->schema([
                                    Forms\Components\Placeholder::make('final_warning')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-6 rounded-lg bg-danger-100 dark:bg-danger-900/40 border-4 border-danger-500">
                                                <div class="flex justify-center mb-4">
                                                    <svg class="h-16 w-16 text-danger-600 dark:text-danger-400 animate-pulse" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <h3 class="text-2xl font-bold text-center text-danger-800 dark:text-danger-200 mb-4">
                                                    LAST CHANCE TO CANCEL
                                                </h3>
                                                <div class="text-center text-danger-700 dark:text-danger-300 space-y-3">
                                                    <p class="text-lg font-semibold">
                                                        You are about to IRREVERSIBLY consume COMMITTED inventory.
                                                    </p>
                                                    <ul class="text-left list-disc list-inside space-y-2 max-w-md mx-auto">
                                                        <li>This action <strong>CANNOT be undone</strong></li>
                                                        <li>Bottles will be marked as <strong>CONSUMED</strong></li>
                                                        <li>An <strong>InventoryException</strong> will be created</li>
                                                        <li>Finance & Ops will be <strong>notified for review</strong></li>
                                                        <li>Customer fulfillment <strong>may be impacted</strong></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        HTML)),

                                    Forms\Components\TextInput::make('confirmation_text')
                                        ->label('Type "OVERRIDE COMMITTED INVENTORY" to confirm')
                                        ->placeholder('OVERRIDE COMMITTED INVENTORY')
                                        ->required()
                                        ->rules(['in:OVERRIDE COMMITTED INVENTORY'])
                                        ->validationMessages([
                                            'in' => 'You must type exactly "OVERRIDE COMMITTED INVENTORY" to proceed.',
                                        ]),

                                    Forms\Components\Checkbox::make('final_confirmation')
                                        ->label('I understand this action is IRREVERSIBLE and will create an exception for Finance & Ops review')
                                        ->required()
                                        ->accepted(),
                                ]),
                        ]),
                ])
                    ->submitAction(new HtmlString(<<<'HTML'
                        <button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-danger fi-color-danger fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50" style="--c-400:var(--danger-400);--c-500:var(--danger-500);--c-600:var(--danger-600);">
                            <svg class="fi-btn-icon h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <span class="fi-btn-label">EXECUTE OVERRIDE</span>
                        </button>
                    HTML)),
            ])
            ->statePath('data');
    }

    /**
     * Execute the committed inventory override.
     */
    public function create(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();
        if (! $user) {
            Notification::make()
                ->title('Authentication required')
                ->body('You must be logged in to perform this operation.')
                ->danger()
                ->send();

            return;
        }

        if (! $user->canConsumeCommittedInventory()) {
            Notification::make()
                ->title('Permission denied')
                ->body('You do not have permission to consume committed inventory.')
                ->danger()
                ->send();

            return;
        }

        $eventLocationId = $data['event_location_id'];
        /** @var array<int, string> $selectedBottleIds */
        $selectedBottleIds = $data['selected_committed_bottles'] ?? [];
        $justification = $data['override_justification'] ?? '';
        $consumptionReasonValue = $data['consumption_reason'] ?? ConsumptionReason::EventConsumption->value;
        $eventReference = $data['event_reference'] ?? null;

        // Validate confirmation text
        if (($data['confirmation_text'] ?? '') !== 'OVERRIDE COMMITTED INVENTORY') {
            Notification::make()
                ->title('Confirmation failed')
                ->body('You must type "OVERRIDE COMMITTED INVENTORY" exactly to proceed.')
                ->danger()
                ->send();

            return;
        }

        // Get the bottles
        $bottles = SerializedBottle::whereIn('id', $selectedBottleIds)->get()->all();
        if (count($bottles) === 0) {
            Notification::make()
                ->title('No bottles found')
                ->body('The selected bottles could not be found.')
                ->danger()
                ->send();

            return;
        }

        // Build notes
        $notes = $eventReference !== null && $eventReference !== '' ? "Event: {$eventReference}" : null;

        // Execute the override
        $overrideService = app(CommittedInventoryOverrideService::class);
        $consumptionReason = ConsumptionReason::from($consumptionReasonValue);

        $result = $overrideService->executeOverride(
            $user,
            $justification,
            $bottles,
            $consumptionReason,
            $notes
        );

        if (! $result['success']) {
            $errorMessage = implode("\n", $result['errors']);
            Notification::make()
                ->title('Override Failed')
                ->body($errorMessage)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Show success notification
        $exceptionCount = $result['exceptions']->count();
        Notification::make()
            ->title('Override Completed')
            ->body("Successfully consumed {$result['consumed_count']} committed bottle(s). {$exceptionCount} InventoryException record(s) created for Finance & Ops review.")
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_movements')
                    ->label('View Movements')
                    ->url(InventoryMovementResource::getUrl('index'))
                    ->button(),
            ])
            ->send();

        // Reset form
        $this->form->fill();

        // Redirect to movements list
        $this->redirect(InventoryMovementResource::getUrl('index'));
    }
}
