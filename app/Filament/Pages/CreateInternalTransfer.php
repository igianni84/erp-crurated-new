<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Filament\Resources\Inventory\InventoryMovementResource;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
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
 * Page for creating internal transfer movements between locations.
 *
 * @property Form $form
 */
class CreateInternalTransfer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Create Transfer';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Create Internal Transfer';

    protected static string $view = 'filament.pages.create-internal-transfer';

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
        return 'Create Internal Transfer';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return 'Transfer bottles and cases between locations';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    // Step 1: Select Source Location
                    Wizard\Step::make('Source Location')
                        ->description('Select the source location')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Section::make('Select Source Location')
                                ->description('Choose the location where the items are currently stored.')
                                ->schema([
                                    Forms\Components\Select::make('source_location_id')
                                        ->label('Source Location')
                                        ->options(function (): array {
                                            return Location::query()
                                                ->where('status', 'active')
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
                                            // Reset selected items when source changes
                                            $set('selected_bottles', []);
                                            $set('selected_cases', []);
                                        })
                                        ->helperText('Only active locations are shown.'),

                                    Forms\Components\Placeholder::make('source_location_info')
                                        ->label('Location Details')
                                        ->visible(fn (Get $get): bool => $get('source_location_id') !== null)
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('source_location_id');
                                            if (! $locationId) {
                                                return new HtmlString('');
                                            }

                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return new HtmlString('');
                                            }

                                            $bottleCount = SerializedBottle::where('current_location_id', $locationId)
                                                ->where('state', BottleState::Stored)
                                                ->count();

                                            $caseCount = InventoryCase::where('current_location_id', $locationId)
                                                ->where('integrity_status', CaseIntegrityStatus::Intact)
                                                ->count();

                                            $locationType = $location->location_type->label();
                                            $locationStatus = $location->status->label();

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
                                                    <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                                        <p class="text-gray-600 dark:text-gray-300"><strong>{$bottleCount}</strong> stored bottles available</p>
                                                        <p class="text-gray-600 dark:text-gray-300"><strong>{$caseCount}</strong> intact cases available</p>
                                                    </div>
                                                </div>
                                            HTML);
                                        }),
                                ]),
                        ])
                        ->afterValidation(function (Get $get): void {
                            $locationId = $get('source_location_id');
                            if (! $locationId) {
                                return;
                            }

                            $bottleCount = SerializedBottle::where('current_location_id', $locationId)
                                ->where('state', BottleState::Stored)
                                ->count();

                            $caseCount = InventoryCase::where('current_location_id', $locationId)
                                ->where('integrity_status', CaseIntegrityStatus::Intact)
                                ->count();

                            if ($bottleCount === 0 && $caseCount === 0) {
                                Notification::make()
                                    ->title('No items available')
                                    ->body('The selected location has no stored bottles or intact cases to transfer.')
                                    ->warning()
                                    ->send();
                            }
                        }),

                    // Step 2: Select Items
                    Wizard\Step::make('Select Items')
                        ->description('Choose bottles and/or cases')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Section::make('Select Bottles')
                                ->description('Choose individual bottles to transfer.')
                                ->collapsible()
                                ->schema([
                                    Forms\Components\CheckboxList::make('selected_bottles')
                                        ->label('')
                                        ->options(function (Get $get): array {
                                            $locationId = $get('source_location_id');
                                            if (! $locationId) {
                                                return [];
                                            }

                                            return SerializedBottle::query()
                                                ->with(['wineVariant.wineMaster', 'format', 'allocation'])
                                                ->where('current_location_id', $locationId)
                                                ->where('state', BottleState::Stored)
                                                ->orderBy('serial_number')
                                                ->limit(100)
                                                ->get()
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

                                                    // Include allocation lineage prominently (US-B048)
                                                    $allocation = $bottle->allocation;
                                                    $allocationLabel = $allocation !== null
                                                        ? '['.substr($allocation->id, 0, 8).'...]'
                                                        : '[No Allocation]';

                                                    return [
                                                        $bottle->id => "{$bottle->serial_number} - {$wineName} ({$formatName}) {$allocationLabel}",
                                                    ];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->bulkToggleable()
                                        ->columns(1)
                                        ->helperText('Showing up to 100 stored bottles at this location.'),
                                ]),

                            Section::make('Select Cases')
                                ->description('Choose intact cases to transfer. All bottles in selected cases will be transferred.')
                                ->collapsible()
                                ->schema([
                                    Forms\Components\CheckboxList::make('selected_cases')
                                        ->label('')
                                        ->options(function (Get $get): array {
                                            $locationId = $get('source_location_id');
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
                                        <div class="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800">
                                            <p class="text-primary-700 dark:text-primary-300">
                                                <strong>{$total}</strong> item(s) selected:
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
                                    ->body('You must select at least one bottle or case to transfer.')
                                    ->danger()
                                    ->send();

                                throw new \Filament\Support\Exceptions\Halt;
                            }
                        }),

                    // Step 3: Select Destination Location
                    Wizard\Step::make('Destination')
                        ->description('Select the destination location')
                        ->icon('heroicon-o-map')
                        ->schema([
                            Section::make('Select Destination Location')
                                ->description('Choose where to transfer the selected items.')
                                ->schema([
                                    Forms\Components\Select::make('destination_location_id')
                                        ->label('Destination Location')
                                        ->options(function (Get $get): array {
                                            $sourceId = $get('source_location_id');

                                            return Location::query()
                                                ->where('status', 'active')
                                                ->when($sourceId, fn ($query) => $query->where('id', '!=', $sourceId))
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
                                        ->helperText('The source location is excluded from this list.'),

                                    Forms\Components\Placeholder::make('destination_location_info')
                                        ->label('Destination Details')
                                        ->visible(fn (Get $get): bool => $get('destination_location_id') !== null)
                                        ->content(function (Get $get): HtmlString {
                                            $locationId = $get('destination_location_id');
                                            if (! $locationId) {
                                                return new HtmlString('');
                                            }

                                            $location = Location::find($locationId);
                                            if (! $location) {
                                                return new HtmlString('');
                                            }

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
                                                </div>
                                            HTML);
                                        }),

                                    Forms\Components\Textarea::make('reason')
                                        ->label('Transfer Reason (Optional)')
                                        ->placeholder('Enter a reason for this transfer...')
                                        ->rows(2),
                                ]),
                        ]),

                    // Step 4: Review and Confirm
                    Wizard\Step::make('Review & Confirm')
                        ->description('Review and execute transfer')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Section::make('Transfer Summary')
                                ->schema([
                                    Forms\Components\Placeholder::make('transfer_summary')
                                        ->label('')
                                        ->content(function (Get $get): HtmlString {
                                            $sourceId = $get('source_location_id');
                                            $destId = $get('destination_location_id');
                                            /** @var array<int, string> $bottles */
                                            $bottles = $get('selected_bottles') ?? [];
                                            /** @var array<int, string> $cases */
                                            $cases = $get('selected_cases') ?? [];
                                            $reason = $get('reason') ?? '';

                                            $source = $sourceId ? Location::find($sourceId) : null;
                                            $dest = $destId ? Location::find($destId) : null;

                                            $sourceName = $source ? "{$source->name} ({$source->country})" : 'Not selected';
                                            $destName = $dest ? "{$dest->name} ({$dest->country})" : 'Not selected';

                                            $bottleCount = count($bottles);
                                            $caseCount = count($cases);
                                            $reasonHtml = $reason ? '<p class="text-gray-600 dark:text-gray-300"><strong>Reason:</strong> '.e($reason).'</p>' : '<p class="text-gray-500 dark:text-gray-400"><em>No reason provided</em></p>';

                                            return new HtmlString(<<<HTML
                                                <div class="space-y-4">
                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">From</p>
                                                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{$sourceName}</p>
                                                        </div>
                                                        <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                                                            <p class="text-sm text-primary-600 dark:text-primary-400 mb-1">To</p>
                                                            <p class="text-lg font-semibold text-primary-700 dark:text-primary-300">{$destName}</p>
                                                        </div>
                                                    </div>

                                                    <div class="p-4 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                                        <p class="text-gray-700 dark:text-gray-200 mb-2"><strong>Items to Transfer:</strong></p>
                                                        <ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-300">
                                                            <li><strong>{$bottleCount}</strong> individual bottle(s)</li>
                                                            <li><strong>{$caseCount}</strong> case(s) (with all contained bottles)</li>
                                                        </ul>
                                                    </div>

                                                    <div class="p-4 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                                                        {$reasonHtml}
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
                                            <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm text-warning-700 dark:text-warning-300">
                                                            <strong>Please review carefully.</strong> This action will create an internal transfer movement record
                                                            and update the location of all selected items. The movement record is <strong>immutable</strong> and cannot be undone.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        HTML)),

                                    Forms\Components\Checkbox::make('confirm_transfer')
                                        ->label('I confirm that I want to execute this internal transfer')
                                        ->required()
                                        ->accepted(),
                                ]),
                        ]),
                ])
                    ->submitAction(new HtmlString(<<<'HTML'
                        <button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-success fi-color-success fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50" style="--c-400:var(--success-400);--c-500:var(--success-500);--c-600:var(--success-600);">
                            <svg class="fi-btn-icon h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            <span class="fi-btn-label">Execute Transfer</span>
                        </button>
                    HTML)),
            ])
            ->statePath('data');
    }

    /**
     * Execute the internal transfer.
     */
    public function create(): void
    {
        $data = $this->form->getState();

        $sourceLocationId = $data['source_location_id'];
        $destinationLocationId = $data['destination_location_id'];
        /** @var array<int, string> $selectedBottles */
        $selectedBottles = $data['selected_bottles'] ?? [];
        /** @var array<int, string> $selectedCases */
        $selectedCases = $data['selected_cases'] ?? [];
        $reason = $data['reason'] ?? null;

        // Validate at least one item selected
        if (count($selectedBottles) === 0 && count($selectedCases) === 0) {
            Notification::make()
                ->title('No items selected')
                ->body('You must select at least one bottle or case to transfer.')
                ->danger()
                ->send();

            return;
        }

        // Validate source and destination are different
        if ($sourceLocationId === $destinationLocationId) {
            Notification::make()
                ->title('Invalid destination')
                ->body('Source and destination locations must be different.')
                ->danger()
                ->send();

            return;
        }

        $user = auth()->user();
        if (! $user) {
            Notification::make()
                ->title('Authentication required')
                ->body('You must be logged in to perform transfers.')
                ->danger()
                ->send();

            return;
        }

        $destination = Location::find($destinationLocationId);
        if (! $destination) {
            Notification::make()
                ->title('Destination not found')
                ->body('The selected destination location no longer exists.')
                ->danger()
                ->send();

            return;
        }

        $movementService = app(MovementService::class);
        $movements = new Collection;
        $transferredBottles = 0;
        $transferredCases = 0;
        $errors = [];

        try {
            DB::transaction(function () use (
                $selectedBottles,
                $selectedCases,
                $destination,
                $user,
                $reason,
                $movementService,
                &$movements,
                &$transferredBottles,
                &$transferredCases,
                &$errors
            ): void {
                // Transfer individual bottles
                foreach ($selectedBottles as $bottleId) {
                    try {
                        $bottle = SerializedBottle::find($bottleId);
                        if ($bottle) {
                            $movement = $movementService->transferBottle($bottle, $destination, $user, $reason);
                            $movements->push($movement);
                            $transferredBottles++;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $errors[] = "Bottle {$bottleId}: {$e->getMessage()}";
                    }
                }

                // Transfer cases (with contained bottles)
                foreach ($selectedCases as $caseId) {
                    try {
                        $case = InventoryCase::find($caseId);
                        if ($case) {
                            $movement = $movementService->transferCase($case, $destination, $user, $reason);
                            $movements->push($movement);
                            $transferredCases++;
                        }
                    } catch (\InvalidArgumentException $e) {
                        $errors[] = "Case {$caseId}: {$e->getMessage()}";
                    }
                }

                // If all transfers failed, throw exception to rollback
                if ($transferredBottles === 0 && $transferredCases === 0) {
                    throw new \Exception('No items were transferred successfully.');
                }
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Transfer Failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Show success notification
        $total = $transferredBottles + $transferredCases;
        $successMessage = "Successfully transferred {$total} item(s): {$transferredBottles} bottle(s), {$transferredCases} case(s).";

        if (count($errors) > 0) {
            $errorMessage = implode("\n", $errors);
            Notification::make()
                ->title('Transfer Partially Completed')
                ->body("{$successMessage}\n\nSome items could not be transferred:\n{$errorMessage}")
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Transfer Completed')
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

    /**
     * Get the first (most recent) movement for redirection.
     */
    protected function getLatestMovement(): ?InventoryMovement
    {
        return InventoryMovement::query()
            ->latest('executed_at')
            ->first();
    }
}
