<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Enums\Inventory\ConsumptionReason;
use App\Enums\Inventory\LocationType;
use App\Enums\Inventory\OwnershipType;
use App\Filament\Resources\Inventory\InventoryMovementResource;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\MovementService;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Page for recording event consumption of inventory.
 *
 * Allows operators to consume Crurated-owned inventory for events.
 * Only stored, non-committed bottles can be consumed. Committed bottles
 * (reserved for voucher fulfillment) are blocked from consumption.
 *
 * @property Form $form
 */
class EventConsumption extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Event Consumption';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Event Consumption';

    protected static string $view = 'filament.pages.event-consumption';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string|Htmlable
    {
        return 'Event Consumption';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Record consumption of inventory for events';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Step 1: Select Event Location
                    Wizard\Step::make('Event Location')
                        ->description('Select the event location')
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            Section::make('Select Event Location')
                                ->description('Choose the location where the event is taking place. Inventory will be consumed from this location.')
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
                                            // Reset selected items when location changes
                                            $set('selected_bottles', []);
                                            $set('selected_cases', []);
                                        })
                                        ->helperText('Only active event locations are shown.'),

                                    Forms\Components\Placeholder::make('event_location_info')
                                        ->label('Location Details')
                                        ->visible(fn (Get $get): bool => $get('event_location_id') !== null)
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return new HtmlString('');
                                            }

                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return new HtmlString('');
                                            }

                                            // Count only Crurated-owned stored bottles at this location
                                            $bottleCount = SerializedBottle::where('current_location_id', $locationId)
                                                ->where('state', BottleState::Stored)
                                                ->where('ownership_type', OwnershipType::CururatedOwned->value)
                                                ->count();

                                            $caseCount = InventoryCase::where('current_location_id', $locationId)
                                                ->where('integrity_status', CaseIntegrityStatus::Intact)
                                                ->count();

                                            $locationType = $location->location_type->label();
                                            $locationStatus = $location->status->label();
                                            $address = $location->address !== null && $location->address !== '' ? $location->address : 'Not specified';

                                            return new HtmlString(<<<HTML
                                                <div class="text-sm space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700 dark:text-gray-200">Type:</span>
                                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">{$locationType}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700 dark:text-gray-200">Status:</span>
                                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">{$locationStatus}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-gray-700 dark:text-gray-200">Address:</span>
                                                        <span class="text-gray-600 dark:text-gray-300">{$address}</span>
                                                    </div>
                                                    <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                                        <p class="text-gray-600 dark:text-gray-300"><strong>{$bottleCount}</strong> Crurated-owned stored bottles</p>
                                                        <p class="text-gray-600 dark:text-gray-300"><strong>{$caseCount}</strong> intact cases</p>
                                                    </div>
                                                </div>
                                            HTML);
                                        }),
                                ]),
                        ]),

                    // Step 2: Event Reference
                    Wizard\Step::make('Event Reference')
                        ->description('Enter event details')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('Event Information')
                                ->description('Provide details about the event for audit purposes.')
                                ->schema([
                                    Forms\Components\TextInput::make('event_reference')
                                        ->label('Event Reference')
                                        ->placeholder('e.g., Annual Tasting 2026, VIP Dinner #123')
                                        ->maxLength(255)
                                        ->helperText('Optional - enter a reference to identify this event.'),

                                    Forms\Components\Textarea::make('event_notes')
                                        ->label('Event Notes (Optional)')
                                        ->placeholder('Enter any additional notes about this event consumption...')
                                        ->rows(3),
                                ]),
                        ]),

                    // Step 3: Select Items (only Crurated-owned, stored, non-committed)
                    Wizard\Step::make('Select Items')
                        ->description('Choose items to consume')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Section::make()
                                ->schema([
                                    Forms\Components\Placeholder::make('consumption_notice')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-danger-600 dark:text-danger-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-danger-700 dark:text-danger-300">
                                                            <strong>Event Consumption</strong> - Only <strong>Crurated-owned</strong> bottles in <strong>stored</strong> state are shown.
                                                            <strong>Committed bottles</strong> (reserved for customer fulfillment) are automatically excluded and cannot be consumed.
                                                            Consumption is <strong>irreversible</strong>.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        HTML)),
                                ]),

                            Section::make('Select Bottles')
                                ->description('Choose individual bottles to consume. Only free (non-committed) bottles are shown.')
                                ->collapsible()
                                ->schema([
                                    Forms\Components\CheckboxList::make('selected_bottles')
                                        ->label('')
                                        ->options(function (Get $get): array {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return [];
                                            }

                                            $inventoryService = app(InventoryService::class);

                                            return SerializedBottle::query()
                                                ->with(['wineVariant.wineMaster', 'format', 'allocation'])
                                                ->where('current_location_id', $locationId)
                                                ->where('state', BottleState::Stored)
                                                ->where('ownership_type', OwnershipType::CururatedOwned->value)
                                                ->orderBy('serial_number')
                                                ->limit(100)
                                                ->get()
                                                ->filter(function (SerializedBottle $bottle) use ($inventoryService): bool {
                                                    // Only show bottles that can be consumed (not committed)
                                                    return $inventoryService->canConsume($bottle);
                                                })
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

                                                    return [
                                                        $bottle->id => "{$bottle->serial_number} - {$wineName} ({$formatName})",
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->helperText('Showing up to 100 consumable bottles (Crurated-owned, stored, not committed).'),
                                ]),

                            // Show committed bottles with warning (blocked from selection)
                            Section::make('Committed Bottles (BLOCKED)')
                                ->description('These bottles are reserved for customer fulfillment and cannot be consumed.')
                                ->collapsible()
                                ->collapsed()
                                ->extraAttributes(['class' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-300 dark:border-warning-700'])
                                ->visible(function (Get $get): bool {
                                    $locationId = $get('event_location_id');
                                    if (! $locationId) {
                                        return false;
                                    }

                                    $inventoryService = app(InventoryService::class);
                                    $location = Location::find($locationId);
                                    if (! $location) {
                                        return false;
                                    }

                                    return $inventoryService->getCommittedBottlesAtLocation($location)->count() > 0;
                                })
                                ->schema([
                                    Forms\Components\Placeholder::make('committed_warning')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-300 dark:border-warning-700">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-warning-700 dark:text-warning-300">
                                                            <strong>⚠️ Committed Inventory</strong> - The bottles listed below are <strong>reserved for customer fulfillment</strong> and cannot be consumed through the standard flow.
                                                            <br><br>
                                                            If you need to consume committed inventory for an exceptional reason, please use the <strong>Committed Inventory Override</strong> flow which requires administrator permission and explicit justification.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        HTML)),

                                    Forms\Components\Placeholder::make('committed_bottles_list')
                                        ->label('Committed Bottles at this Location')
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return new HtmlString('<p class="text-gray-500">No location selected.</p>');
                                            }

                                            $inventoryService = app(InventoryService::class);
                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return new HtmlString('<p class="text-gray-500">Location not found.</p>');
                                            }

                                            $committedBottles = $inventoryService->getCommittedBottlesAtLocation($location)
                                                ->load(['wineVariant.wineMaster', 'format', 'allocation']);

                                            if ($committedBottles->isEmpty()) {
                                                return new HtmlString('<p class="text-success-600 dark:text-success-400">No committed bottles at this location.</p>');
                                            }

                                            $rows = $committedBottles->take(50)->map(function (SerializedBottle $bottle): string {
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
                                                $allocationName = $allocation !== null ? substr($allocation->id, 0, 8).'...' : 'N/A';

                                                return <<<HTML
                                                    <tr class="border-b border-warning-200 dark:border-warning-700">
                                                        <td class="py-2 px-3 text-sm font-mono">{$bottle->serial_number}</td>
                                                        <td class="py-2 px-3 text-sm">{$wineName}</td>
                                                        <td class="py-2 px-3 text-sm">{$formatName}</td>
                                                        <td class="py-2 px-3 text-sm text-warning-600 dark:text-warning-400">
                                                            <span class="inline-flex items-center gap-1">
                                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                                </svg>
                                                                Reserved
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-3 text-xs text-gray-500 dark:text-gray-400">{$allocationName}</td>
                                                    </tr>
                                                HTML;
                                            })->join('');

                                            $count = $committedBottles->count();
                                            $showingNote = $count > 50 ? "<p class=\"text-sm text-gray-500 mt-2\">Showing first 50 of {$count} committed bottles.</p>" : '';

                                            return new HtmlString(<<<HTML
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-warning-200 dark:divide-warning-700">
                                                        <thead class="bg-warning-100 dark:bg-warning-800/30">
                                                            <tr>
                                                                <th class="py-2 px-3 text-left text-xs font-medium text-warning-700 dark:text-warning-300 uppercase tracking-wider">Serial Number</th>
                                                                <th class="py-2 px-3 text-left text-xs font-medium text-warning-700 dark:text-warning-300 uppercase tracking-wider">Wine</th>
                                                                <th class="py-2 px-3 text-left text-xs font-medium text-warning-700 dark:text-warning-300 uppercase tracking-wider">Format</th>
                                                                <th class="py-2 px-3 text-left text-xs font-medium text-warning-700 dark:text-warning-300 uppercase tracking-wider">Status</th>
                                                                <th class="py-2 px-3 text-left text-xs font-medium text-warning-700 dark:text-warning-300 uppercase tracking-wider">Allocation</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-warning-200 dark:divide-warning-700">
                                                            {$rows}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                {$showingNote}
                                            HTML);
                                        }),
                                ]),

                            Section::make('Select Cases')
                                ->description('Choose intact cases to consume. All bottles in selected cases will be consumed and the case will be marked as broken.')
                                ->collapsible()
                                ->schema([
                                    Forms\Components\Placeholder::make('case_warning')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-3 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
                                                <p class="text-sm text-warning-700 dark:text-warning-300">
                                                    <strong>Note:</strong> When consuming a case, the case will be <strong>marked as broken</strong> and all contained bottles will be consumed.
                                                </p>
                                            </div>
                                        HTML)),

                                    Forms\Components\CheckboxList::make('selected_cases')
                                        ->label('')
                                        ->options(function (Get $get): array {
                                            $locationId = $get('event_location_id');
                                            if (! $locationId) {
                                                return [];
                                            }

                                            return InventoryCase::query()
                                                ->with(['caseConfiguration'])
                                                ->where('current_location_id', $locationId)
                                                ->where('integrity_status', CaseIntegrityStatus::Intact)
                                                ->orderBy('id')
                                                ->limit(100)
                                                ->get()
                                                ->mapWithKeys(function (InventoryCase $case): array {
                                                    $config = $case->caseConfiguration;
                                                    $configName = $config !== null ? $config->name : 'Unknown Config';
                                                    $bottleCount = $case->bottle_count;

                                                    return [
                                                        $case->id => 'Case #'.substr($case->id, 0, 8)."... - {$configName} ({$bottleCount} bottles)",
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->helperText('Showing up to 100 intact cases at this location.'),
                                ]),

                            Forms\Components\Placeholder::make('selection_summary')
                                ->label('Selection Summary')
                                ->content(function (Get $get): HtmlString {
                                    /** @var array<int, string> $bottles */
                                    $bottles = $get('selected_bottles') ?? [];
                                    /** @var array<int, string> $cases */
                                    $cases = $get('selected_cases') ?? [];

                                    $bottleCount = count($bottles);
                                    $caseCount = count($cases);
                                    $total = $bottleCount + $caseCount;

                                    if ($total === 0) {
                                        return new HtmlString('<p class="text-gray-500 dark:text-gray-400">No items selected yet.</p>');
                                    }

                                    return new HtmlString(<<<HTML
                                        <div class="p-3 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                            <p class="text-danger-700 dark:text-danger-300">
                                                <strong>{$total}</strong> item(s) selected for consumption:
                                                <strong>{$bottleCount}</strong> bottle(s), <strong>{$caseCount}</strong> case(s)
                                            </p>
                                        </div>
                                    HTML);
                                }),
                        ])
                        ->afterValidation(function (Get $get): void {
                            /** @var array<int, string> $bottles */
                            $bottles = $get('selected_bottles') ?? [];
                            /** @var array<int, string> $cases */
                            $cases = $get('selected_cases') ?? [];

                            if (count($bottles) === 0 && count($cases) === 0) {
                                Notification::make()
                                    ->title('No items selected')
                                    ->body('You must select at least one bottle or case to consume.')
                                    ->danger()
                                    ->send();

                                throw new \Filament\Support\Exceptions\Halt;
                            }
                        }),

                    // Step 4: Review and Confirm
                    Wizard\Step::make('Review & Confirm')
                        ->description('Review and execute consumption')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Section::make('Consumption Summary')
                                ->schema([
                                    Forms\Components\Placeholder::make('consumption_summary')
                                        ->label('')
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('event_location_id');
                                            $eventReference = $get('event_reference') ?? '';
                                            $eventNotes = $get('event_notes') ?? '';
                                            /** @var array<int, string> $bottles */
                                            $bottles = $get('selected_bottles') ?? [];
                                            /** @var array<int, string> $cases */
                                            $cases = $get('selected_cases') ?? [];

                                            $location = $locationId ? Location::find($locationId) : null;
                                            $locationName = $location ? "{$location->name} ({$location->country})" : 'Not selected';

                                            $bottleCount = count($bottles);
                                            $caseCount = count($cases);

                                            $eventRefHtml = $eventReference ? e($eventReference) : '<em class="text-gray-500">Not specified</em>';
                                            $eventNotesHtml = $eventNotes ? e($eventNotes) : '<em class="text-gray-500">No notes provided</em>';

                                            return new HtmlString(<<<HTML
                                                <div class="space-y-4">
                                                    <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                                                        <p class="text-sm text-primary-600 dark:text-primary-400 mb-1">Event Location</p>
                                                        <p class="text-lg font-semibold text-primary-700 dark:text-primary-300">{$locationName}</p>
                                                    </div>

                                                    <div class="p-4 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                                        <p class="text-gray-700 dark:text-gray-200 mb-2"><strong>Event Reference:</strong> {$eventRefHtml}</p>
                                                        <p class="text-gray-700 dark:text-gray-200"><strong>Notes:</strong> {$eventNotesHtml}</p>
                                                    </div>

                                                    <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                                        <p class="text-danger-700 dark:text-danger-200 mb-2"><strong>Items to be CONSUMED:</strong></p>
                                                        <ul class="list-disc list-inside space-y-1 text-danger-600 dark:text-danger-300">
                                                            <li><strong>{$bottleCount}</strong> individual bottle(s) → state will become <strong>CONSUMED</strong></li>
                                                            <li><strong>{$caseCount}</strong> case(s) → will be <strong>BROKEN</strong>, all contained bottles <strong>CONSUMED</strong></li>
                                                        </ul>
                                                    </div>

                                                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                                                        <p class="text-gray-700 dark:text-gray-200">
                                                            <strong>Consumption Reason:</strong> Event Consumption
                                                        </p>
                                                    </div>
                                                </div>
                                            HTML);
                                        }),
                                ]),

                            Section::make('Confirmation')
                                ->schema([
                                    Forms\Components\Placeholder::make('warning')
                                        ->label('')
                                        ->content(new HtmlString(<<<'HTML'
                                            <div class="p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-danger-600 dark:text-danger-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-danger-700 dark:text-danger-300">
                                                            <strong>WARNING: This action is IRREVERSIBLE.</strong>
                                                            <ul class="list-disc list-inside mt-1">
                                                                <li>All selected bottles will be marked as <strong>CONSUMED</strong></li>
                                                                <li>Selected cases will be marked as <strong>BROKEN</strong></li>
                                                                <li>Consumed bottles <strong>cannot be recovered</strong> or used for fulfillment</li>
                                                                <li>Physical inventory will be <strong>permanently reduced</strong></li>
                                                            </ul>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        HTML)),

                                    Forms\Components\Checkbox::make('confirm_consumption')
                                        ->label('I confirm that I want to consume these items for this event. I understand this action is IRREVERSIBLE.')
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
                            <span class="fi-btn-label">Execute Consumption</span>
                        </button>
                    HTML)),
            ])
            ->statePath('data');
    }

    /**
     * Execute the event consumption.
     */
    public function create(): void
    {
        $data = $this->form->getState();

        $eventLocationId = $data['event_location_id'];
        $eventReference = $data['event_reference'] ?? null;
        $eventNotes = $data['event_notes'] ?? null;
        /** @var array<int, string> $selectedBottles */
        $selectedBottles = $data['selected_bottles'] ?? [];
        /** @var array<int, string> $selectedCases */
        $selectedCases = $data['selected_cases'] ?? [];

        // Validate at least one item selected
        if (count($selectedBottles) === 0 && count($selectedCases) === 0) {
            Notification::make()
                ->title('No items selected')
                ->body('You must select at least one bottle or case to consume.')
                ->danger()
                ->send();

            return;
        }

        $user = auth()->user();
        if (! $user) {
            Notification::make()
                ->title('Authentication required')
                ->body('You must be logged in to perform event consumption.')
                ->danger()
                ->send();

            return;
        }

        $eventLocation = Location::find($eventLocationId);
        if (! $eventLocation) {
            Notification::make()
                ->title('Location not found')
                ->body('The selected event location no longer exists.')
                ->danger()
                ->send();

            return;
        }

        $movementService = app(MovementService::class);
        $inventoryService = app(InventoryService::class);
        $movements = new Collection;
        $consumedBottles = 0;
        $consumedCases = 0;
        $errors = [];

        // Build notes string
        $notes = 'Event Consumption';
        if ($eventReference !== null && $eventReference !== '') {
            $notes .= " - {$eventReference}";
        }
        if ($eventNotes !== null && $eventNotes !== '') {
            $notes .= ". {$eventNotes}";
        }

        try {
            DB::transaction(function () use (
                $selectedBottles,
                $selectedCases,
                $user,
                $notes,
                $movementService,
                $inventoryService,
                &$movements,
                &$consumedBottles,
                &$consumedCases,
                &$errors
            ): void {
                // Consume individual bottles
                foreach ($selectedBottles as $bottleId) {
                    try {
                        $bottle = SerializedBottle::find($bottleId);
                        if ($bottle) {
                            // Double-check the bottle can still be consumed (using InventoryService.canConsume())
                            if (! $inventoryService->canConsume($bottle)) {
                                $reason = $inventoryService->getCannotConsumeReason($bottle);
                                $errors[] = "Bottle {$bottle->serial_number}: {$reason}";

                                continue;
                            }

                            $movement = $movementService->recordConsumption(
                                $bottle,
                                ConsumptionReason::EventConsumption,
                                $user,
                                $notes
                            );
                            $movements->push($movement);
                            $consumedBottles++;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $errors[] = "Bottle {$bottleId}: {$e->getMessage()}";
                    }
                }

                // Consume cases (break case and consume all bottles)
                foreach ($selectedCases as $caseId) {
                    try {
                        $case = InventoryCase::with('serializedBottles')->find($caseId);
                        if ($case) {
                            // First, break the case
                            $movementService->breakCase($case, "Opened for event consumption: {$notes}", $user);

                            // Then consume all bottles in the case
                            foreach ($case->serializedBottles as $bottle) {
                                try {
                                    if ($bottle->isStored() && $bottle->ownership_type->canConsumeForEvents()) {
                                        $movement = $movementService->recordConsumption(
                                            $bottle,
                                            ConsumptionReason::EventConsumption,
                                            $user,
                                            $notes
                                        );
                                        $movements->push($movement);
                                    }
                                } catch (\InvalidArgumentException $e) {
                                    $errors[] = "Case bottle {$bottle->serial_number}: {$e->getMessage()}";
                                }
                            }
                            $consumedCases++;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $errors[] = "Case {$caseId}: {$e->getMessage()}";
                    }
                }

                // If all consumption failed, throw exception to rollback
                if ($consumedBottles === 0 && $consumedCases === 0) {
                    throw new \Exception('No items were consumed successfully.');
                }
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Consumption Failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Show success notification
        $total = $consumedBottles + $consumedCases;
        $successMessage = "Successfully consumed {$total} item(s): {$consumedBottles} bottle(s), {$consumedCases} case(s).";

        if (count($errors) > 0) {
            $errorMessage = implode("\n", $errors);
            Notification::make()
                ->title('Consumption Partially Completed')
                ->body("{$successMessage}\n\nSome items could not be consumed:\n{$errorMessage}")
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Consumption Completed')
                ->body($successMessage)
                ->success()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view_movements')
                        ->label('View Movements')
                        ->url(InventoryMovementResource::getUrl('index'))
                        ->button(),
                ])
                ->send();
        }

        // Reset form
        $this->form->fill();

        // Redirect to movements list
        $this->redirect(InventoryMovementResource::getUrl('index'));
    }
}
