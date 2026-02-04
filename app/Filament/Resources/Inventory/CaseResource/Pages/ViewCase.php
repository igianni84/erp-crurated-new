<?php

namespace App\Filament\Resources\Inventory\CaseResource\Pages;

use App\Enums\Inventory\CaseIntegrityStatus;
use App\Enums\Inventory\MovementTrigger;
use App\Filament\Resources\Inventory\CaseResource;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\InventoryException;
use App\Models\Inventory\MovementItem;
use App\Services\Inventory\MovementService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * ViewCase Page - Comprehensive view of case details with 5 tabs (US-B031, US-B056).
 *
 * Tabs:
 * 1. Summary: configuration, is_original, is_breakable, allocation_lineage, integrity status
 * 2. Contained Bottles: list of SerializedBottles in this case
 * 3. Integrity & Handling: broken_at, broken_by, broken_reason
 * 4. Movements: movement history for this case
 * 5. Audit: immutable log of all case modifications (US-B056)
 */
class ViewCase extends ViewRecord
{
    protected static string $resource = CaseResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var InventoryCase $record */
        $record = $this->record;

        return "Case: {$record->display_label}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                $this->getIntegrityStatusBanner(),
                Tabs::make('Case Details')
                    ->tabs([
                        $this->getSummaryTab(),
                        $this->getContainedBottlesTab(),
                        $this->getIntegrityHandlingTab(),
                        $this->getMovementsTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Banner showing integrity status prominently.
     */
    protected function getIntegrityStatusBanner(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('integrity_banner')
                    ->label('')
                    ->visible(fn (InventoryCase $record): bool => $record->isBroken())
                    ->getStateUsing(fn (): string => '⚠️ BROKEN CASE: This case has been opened/broken and can no longer be handled as a unit. Individual bottles are now managed separately.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->color('danger'),
                TextEntry::make('intact_banner')
                    ->label('')
                    ->visible(fn (InventoryCase $record): bool => $record->isIntact())
                    ->getStateUsing(fn (): string => '✓ INTACT CASE: This case is sealed and can be handled as a complete unit.')
                    ->icon('heroicon-o-check-badge')
                    ->iconColor('success')
                    ->color('success'),
            ])
            ->columnSpanFull();
    }

    /**
     * Tab 1: Summary - configuration, is_original, is_breakable, allocation_lineage, integrity status.
     */
    protected function getSummaryTab(): Tab
    {
        return Tab::make('Summary')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Case Identity')
                    ->description('Basic case information and configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Case ID')
                                        ->copyable()
                                        ->copyMessage('Case ID copied')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large)
                                        ->icon('heroicon-o-archive-box'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('configuration_display')
                                        ->label('Configuration')
                                        ->getStateUsing(function (InventoryCase $record): string {
                                            $config = $record->caseConfiguration;
                                            if ($config === null) {
                                                return 'Unknown Configuration';
                                            }

                                            return $config->name.' ('.$config->bottles_per_case.' bottles)';
                                        })
                                        ->icon('heroicon-o-cube'),
                                    TextEntry::make('bottle_count')
                                        ->label('Bottles in Case')
                                        ->getStateUsing(fn (InventoryCase $record): int => $record->bottle_count)
                                        ->badge()
                                        ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('integrity_status')
                                        ->label('Integrity Status')
                                        ->badge()
                                        ->formatStateUsing(fn (CaseIntegrityStatus $state): string => $state->label())
                                        ->color(fn (CaseIntegrityStatus $state): string => $state->color())
                                        ->icon(fn (CaseIntegrityStatus $state): string => $state->icon())
                                        ->size(TextEntry\TextEntrySize::Large),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Case Attributes')
                    ->description('Physical characteristics and handling rules')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('is_original')
                                    ->label('Original Producer Case')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes - Original' : 'No - Repacked')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-badge' : 'heroicon-o-minus-circle')
                                    ->helperText('Whether this is the original case from the producer'),
                                TextEntry::make('is_breakable')
                                    ->label('Breakable')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes - Can be opened' : 'No - Must stay sealed')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'info' : 'danger')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-scissors' : 'heroicon-o-lock-closed')
                                    ->helperText('Whether this case can be opened for individual bottle access'),
                                TextEntry::make('can_handle_as_unit')
                                    ->label('Unit Handling')
                                    ->getStateUsing(fn (InventoryCase $record): string => $record->canHandleAsUnit()
                                        ? 'Can handle as unit'
                                        : 'Handle bottles individually')
                                    ->badge()
                                    ->color(fn (InventoryCase $record): string => $record->canHandleAsUnit() ? 'success' : 'gray')
                                    ->icon(fn (InventoryCase $record): string => $record->canHandleAsUnit()
                                        ? 'heroicon-o-archive-box'
                                        : 'heroicon-o-squares-2x2'),
                            ]),
                    ]),
                Section::make('Allocation Lineage')
                    ->description('Immutable allocation reference for this case')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('allocation_lineage_display')
                                    ->label('Allocation')
                                    ->getStateUsing(function (InventoryCase $record): string {
                                        $allocation = $record->allocation;
                                        if ($allocation === null) {
                                            return 'N/A';
                                        }

                                        return $allocation->getBottleSkuLabel();
                                    })
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-link')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('allocation_id')
                                    ->label('Allocation ID')
                                    ->copyable()
                                    ->copyMessage('Allocation ID copied')
                                    ->icon('heroicon-o-lock-closed')
                                    ->helperText('This ID was assigned when the case was created'),
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-primary-50 dark:bg-primary-900/10']),
                Section::make('Location')
                    ->description('Current physical location of this case')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('currentLocation.name')
                                    ->label('Location Name')
                                    ->icon('heroicon-o-map-pin')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('currentLocation.location_type')
                                    ->label('Location Type')
                                    ->badge()
                                    ->formatStateUsing(fn (InventoryCase $record): string => $record->currentLocation?->location_type?->label() ?? 'Unknown')
                                    ->color(fn (InventoryCase $record): string => $record->currentLocation?->location_type?->color() ?? 'gray'),
                                TextEntry::make('currentLocation.country')
                                    ->label('Country')
                                    ->icon('heroicon-o-globe-alt')
                                    ->placeholder('Not specified'),
                            ]),
                    ])
                    ->collapsible(),
                Section::make('Origin')
                    ->description('Source of this case')
                    ->schema([
                        TextEntry::make('inbound_batch_info')
                            ->label('Inbound Batch')
                            ->getStateUsing(function (InventoryCase $record): string {
                                if ($record->inbound_batch_id === null) {
                                    return 'No inbound batch linked';
                                }

                                return 'Batch #'.substr($record->inbound_batch_id, 0, 8).'...';
                            })
                            ->url(function (InventoryCase $record): ?string {
                                if ($record->inbound_batch_id === null) {
                                    return null;
                                }

                                return route('filament.admin.resources.inventory.inbound-batches.view', ['record' => $record->inbound_batch_id]);
                            })
                            ->color(fn (InventoryCase $record): string => $record->inbound_batch_id !== null ? 'primary' : 'gray')
                            ->icon('heroicon-o-inbox-arrow-down'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Tab 2: Contained Bottles - list of SerializedBottles in this case.
     */
    protected function getContainedBottlesTab(): Tab
    {
        return Tab::make('Contained Bottles')
            ->icon('heroicon-o-beaker')
            ->badge(fn (InventoryCase $record): ?string => $record->bottle_count > 0 ? (string) $record->bottle_count : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Bottles in This Case')
                    ->description('List of serialized bottles contained in this case')
                    ->schema([
                        TextEntry::make('bottles_notice')
                            ->label('')
                            ->visible(fn (InventoryCase $record): bool => $record->isBroken())
                            ->getStateUsing(fn (): string => 'This case has been broken. The bottles listed below are now managed as individual items (loose stock).')
                            ->icon('heroicon-o-information-circle')
                            ->iconColor('warning')
                            ->color('warning'),
                        TextEntry::make('bottle_count_summary')
                            ->label('Total Bottles')
                            ->getStateUsing(function (InventoryCase $record): string {
                                $count = $record->bottle_count;
                                $config = $record->caseConfiguration;
                                $expected = $config !== null ? $config->bottles_per_case : null;

                                if ($expected !== null) {
                                    return "{$count} of {$expected} bottles";
                                }

                                return "{$count} bottles";
                            })
                            ->badge()
                            ->color(function (InventoryCase $record): string {
                                $config = $record->caseConfiguration;
                                if ($config === null) {
                                    return 'gray';
                                }

                                return $record->bottle_count === $config->bottles_per_case ? 'success' : 'warning';
                            }),
                        RepeatableEntry::make('serializedBottles')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('serial_number')
                                            ->label('Serial Number')
                                            ->copyable()
                                            ->copyMessage('Serial number copied')
                                            ->weight(FontWeight::Bold)
                                            ->url(fn ($record): string => route('filament.admin.resources.inventory.serialized-bottles.view', ['record' => $record->id]))
                                            ->color('primary'),
                                        TextEntry::make('wine_display')
                                            ->label('Wine')
                                            ->getStateUsing(function ($record): string {
                                                $wineVariant = $record->wineVariant;
                                                if ($wineVariant === null) {
                                                    return 'Unknown Wine';
                                                }
                                                $wineMaster = $wineVariant->wineMaster;
                                                $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
                                                $vintage = $wineVariant->vintage_year ?? 'NV';

                                                return "{$wineName} {$vintage}";
                                            }),
                                        TextEntry::make('format.name')
                                            ->label('Format')
                                            ->placeholder('Standard'),
                                        TextEntry::make('state')
                                            ->label('State')
                                            ->badge()
                                            ->formatStateUsing(fn ($state): string => $state->label())
                                            ->color(fn ($state): string => $state->color())
                                            ->icon(fn ($state): string => $state->icon()),
                                        TextEntry::make('allocation_lineage')
                                            ->label('Allocation')
                                            ->getStateUsing(function ($record): string {
                                                $allocation = $record->allocation;
                                                if ($allocation === null) {
                                                    return 'N/A';
                                                }

                                                return substr($allocation->id, 0, 8).'...';
                                            })
                                            ->badge()
                                            ->color('primary')
                                            ->icon('heroicon-o-link'),
                                        TextEntry::make('nft_status')
                                            ->label('NFT')
                                            ->getStateUsing(fn ($record): string => $record->hasNft() ? 'Minted' : 'Pending')
                                            ->badge()
                                            ->color(fn ($record): string => $record->hasNft() ? 'success' : 'warning')
                                            ->icon(fn ($record): string => $record->hasNft()
                                                ? 'heroicon-o-check-badge'
                                                : 'heroicon-o-clock'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (InventoryCase $record): bool => $record->bottle_count > 0),
                        TextEntry::make('no_bottles')
                            ->label('')
                            ->visible(fn (InventoryCase $record): bool => $record->bottle_count === 0)
                            ->getStateUsing(fn (): string => 'No bottles are currently in this case.')
                            ->icon('heroicon-o-inbox')
                            ->iconColor('gray')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 3: Integrity & Handling - broken_at, broken_by, broken_reason (if applicable).
     */
    protected function getIntegrityHandlingTab(): Tab
    {
        return Tab::make('Integrity & Handling')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Section::make('Current Integrity Status')
                    ->description('Physical integrity of the case')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('integrity_status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (CaseIntegrityStatus $state): string => $state->label())
                                    ->color(fn (CaseIntegrityStatus $state): string => $state->color())
                                    ->icon(fn (CaseIntegrityStatus $state): string => $state->icon())
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('can_break')
                                    ->label('Can Be Opened')
                                    ->getStateUsing(fn (InventoryCase $record): string => $record->canBreak()
                                        ? 'Yes - Eligible for breaking'
                                        : 'No - Cannot be opened')
                                    ->badge()
                                    ->color(fn (InventoryCase $record): string => $record->canBreak() ? 'info' : 'gray')
                                    ->icon(fn (InventoryCase $record): string => $record->canBreak()
                                        ? 'heroicon-o-scissors'
                                        : 'heroicon-o-lock-closed'),
                                TextEntry::make('handling_method')
                                    ->label('Handling Method')
                                    ->getStateUsing(fn (InventoryCase $record): string => $record->canHandleAsUnit()
                                        ? 'Handle as complete unit'
                                        : 'Handle bottles individually')
                                    ->badge()
                                    ->color(fn (InventoryCase $record): string => $record->canHandleAsUnit() ? 'success' : 'warning'),
                            ]),
                    ]),
                Section::make('Breaking Details')
                    ->description('Information about when and why the case was opened')
                    ->visible(fn (InventoryCase $record): bool => $record->isBroken())
                    ->schema([
                        TextEntry::make('breaking_irreversible_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => '⚠️ Breaking is IRREVERSIBLE. Once a case is opened, it cannot be resealed.')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->iconColor('warning')
                            ->color('warning'),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('broken_at')
                                    ->label('Broken At')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('brokenByUser.name')
                                    ->label('Broken By')
                                    ->placeholder('Unknown')
                                    ->icon('heroicon-o-user'),
                                TextEntry::make('broken_reason')
                                    ->label('Reason')
                                    ->placeholder('No reason provided')
                                    ->icon('heroicon-o-document-text'),
                            ]),
                    ])
                    ->extraAttributes(['class' => 'bg-danger-50 dark:bg-danger-900/10']),
                Section::make('Intact Case Information')
                    ->description('This case is still sealed')
                    ->visible(fn (InventoryCase $record): bool => $record->isIntact())
                    ->schema([
                        TextEntry::make('intact_notice')
                            ->label('')
                            ->getStateUsing(function (InventoryCase $record): string {
                                if ($record->is_breakable) {
                                    return '✓ This case is intact and can be opened using the "Break Case" action if needed (for inspection, sampling, or events).';
                                }

                                return '✓ This case is intact and is NOT breakable. It must remain sealed.';
                            })
                            ->icon('heroicon-o-check-circle')
                            ->iconColor('success')
                            ->color('success'),
                    ])
                    ->extraAttributes(['class' => 'bg-success-50 dark:bg-success-900/10']),
                Section::make('Handling Guidelines')
                    ->description('Rules for handling this case')
                    ->schema([
                        TextEntry::make('guidelines')
                            ->label('')
                            ->getStateUsing(function (InventoryCase $record): string {
                                $guidelines = [];

                                if ($record->is_original) {
                                    $guidelines[] = '• Original producer case - handle with care to preserve authenticity';
                                } else {
                                    $guidelines[] = '• Repacked case - standard handling procedures apply';
                                }

                                if ($record->is_breakable) {
                                    $guidelines[] = '• Case CAN be opened for individual bottle access';
                                } else {
                                    $guidelines[] = '• Case CANNOT be opened - must remain sealed';
                                }

                                if ($record->isBroken()) {
                                    $guidelines[] = '• Case is BROKEN - bottles must be handled individually';
                                    $guidelines[] = '• Case is no longer eligible for case-based movements';
                                } else {
                                    $guidelines[] = '• Case is INTACT - can be moved as a complete unit';
                                }

                                return implode("\n", $guidelines);
                            })
                            ->icon('heroicon-o-clipboard-document-list')
                            ->color('gray'),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 4: Movements - movement history for this case.
     */
    protected function getMovementsTab(): Tab
    {
        return Tab::make('Movements')
            ->icon('heroicon-o-arrow-path')
            ->badge(function (InventoryCase $record): ?string {
                $count = MovementItem::where('case_id', $record->id)->count();

                return $count > 0 ? (string) $count : null;
            })
            ->badgeColor('info')
            ->schema([
                Section::make('Movement History')
                    ->description('Complete, immutable record of all movements for this case')
                    ->schema([
                        TextEntry::make('movement_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Movements are append-only records. They cannot be modified or deleted. This ensures complete audit trail integrity.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('success')
                            ->color('gray'),
                        TextEntry::make('movement_history')
                            ->label('')
                            ->getStateUsing(function (InventoryCase $record): string {
                                $movementItems = MovementItem::where('case_id', $record->id)
                                    ->with(['inventoryMovement.sourceLocation', 'inventoryMovement.destinationLocation', 'inventoryMovement.executor'])
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                if ($movementItems->isEmpty()) {
                                    return '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                                        <p class="text-gray-500">No movements recorded for this case.</p>
                                        <p class="text-sm text-gray-400 mt-1">The case has remained in its original location since creation.</p>
                                    </div>';
                                }

                                $html = '<div class="space-y-3">';

                                foreach ($movementItems as $item) {
                                    /** @var MovementItem $item */
                                    $movement = $item->inventoryMovement;
                                    if ($movement === null) {
                                        continue;
                                    }

                                    $typeLabel = $movement->movement_type->label();
                                    $typeColor = $movement->movement_type->color();

                                    $triggerLabel = $movement->trigger->label();
                                    $triggerColor = match ($movement->trigger) {
                                        MovementTrigger::WmsEvent => 'info',
                                        MovementTrigger::ErpOperator => 'success',
                                        MovementTrigger::SystemAutomatic => 'gray',
                                    };

                                    $source = $movement->sourceLocation;
                                    $dest = $movement->destinationLocation;
                                    $sourceName = $source !== null ? e($source->name) : '—';
                                    $destName = $dest !== null ? e($dest->name) : '—';

                                    $executor = $movement->executor;
                                    $executorName = $executor !== null ? e($executor->name) : 'System';
                                    $executedAt = $movement->executed_at->format('M d, Y H:i:s');

                                    $reason = $movement->reason !== null ? e($movement->reason) : '—';
                                    $wmsEventId = $movement->wms_event_id !== null ? e($movement->wms_event_id) : '—';
                                    $custodyChanged = $movement->custody_changed ? 'Yes' : 'No';
                                    $movementId = substr($movement->id, 0, 8).'...';

                                    $colorClass = match ($typeColor) {
                                        'success' => 'border-success-200 dark:border-success-800',
                                        'info' => 'border-info-200 dark:border-info-800',
                                        'warning' => 'border-warning-200 dark:border-warning-800',
                                        'danger' => 'border-danger-200 dark:border-danger-800',
                                        default => 'border-gray-200 dark:border-gray-700',
                                    };

                                    $badgeClass = match ($typeColor) {
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300',
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-700 dark:text-info-300',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-300',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                                    };

                                    $triggerBadgeClass = match ($triggerColor) {
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-600 dark:text-info-400',
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-600 dark:text-success-400',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                                    };

                                    $html .= <<<HTML
                                    <div class="p-4 bg-white dark:bg-gray-900 rounded-lg border-l-4 {$colorClass} shadow-sm">
                                        <div class="flex items-start justify-between mb-3">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 text-sm font-medium rounded {$badgeClass}">{$typeLabel}</span>
                                                <span class="px-2 py-1 text-xs rounded {$triggerBadgeClass}">{$triggerLabel}</span>
                                            </div>
                                            <span class="text-sm text-gray-500">{$executedAt}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">From:</span>
                                                <span class="font-medium ml-1">{$sourceName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">To:</span>
                                                <span class="font-medium ml-1">{$destName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Executed by:</span>
                                                <span class="ml-1">{$executorName}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Custody Changed:</span>
                                                <span class="ml-1">{$custodyChanged}</span>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 text-sm">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <span class="text-gray-500">Reason:</span>
                                                    <span class="ml-1">{$reason}</span>
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    Movement #{$movementId}
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-gray-500">WMS Event:</span>
                                                <span class="ml-1 text-xs font-mono">{$wmsEventId}</span>
                                            </div>
                                        </div>
                                    </div>
                                    HTML;
                                }

                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - immutable log of all case modifications (US-B056).
     *
     * Events logged: creation, location_change, breaking, bottle_added, bottle_removed
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-clipboard-document-list')
            ->badge(function (InventoryCase $record): ?string {
                $count = $record->auditLogs()->count();

                return $count > 0 ? (string) $count : null;
            })
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Log')
                    ->description('Immutable record of all changes to this case')
                    ->schema([
                        TextEntry::make('audit_immutability_notice')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes for compliance purposes.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('success')
                            ->color('gray'),
                        TextEntry::make('audit_timeline')
                            ->label('')
                            ->getStateUsing(function (InventoryCase $record): string {
                                $auditLogs = $record->auditLogs()
                                    ->with('user')
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                if ($auditLogs->isEmpty()) {
                                    return '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                                        <p class="text-gray-500">No audit logs recorded for this case.</p>
                                    </div>';
                                }

                                $html = '<div class="space-y-3">';

                                foreach ($auditLogs as $log) {
                                    /** @var \App\Models\AuditLog $log */
                                    $eventLabel = $log->getEventLabel();
                                    $eventIcon = $log->getEventIcon();
                                    $eventColor = $log->getEventColor();

                                    $user = $log->user;
                                    $userName = $user !== null ? e($user->name) : 'System';
                                    $timestamp = $log->created_at->format('M d, Y H:i:s');

                                    $oldValues = $log->old_values ?? [];
                                    $newValues = $log->new_values ?? [];

                                    $colorClass = match ($eventColor) {
                                        'success' => 'border-success-200 dark:border-success-800 bg-success-50/50 dark:bg-success-900/10',
                                        'info' => 'border-info-200 dark:border-info-800 bg-info-50/50 dark:bg-info-900/10',
                                        'warning' => 'border-warning-200 dark:border-warning-800 bg-warning-50/50 dark:bg-warning-900/10',
                                        'danger' => 'border-danger-200 dark:border-danger-800 bg-danger-50/50 dark:bg-danger-900/10',
                                        default => 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50',
                                    };

                                    $badgeClass = match ($eventColor) {
                                        'success' => 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300',
                                        'info' => 'bg-info-100 dark:bg-info-900/30 text-info-700 dark:text-info-300',
                                        'warning' => 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300',
                                        'danger' => 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-300',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                                    };

                                    // Build changes display
                                    $changesHtml = '';
                                    if (! empty($oldValues) || ! empty($newValues)) {
                                        $changesHtml = '<div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 text-sm">';
                                        $changesHtml .= '<div class="font-medium text-gray-500 mb-1">Changes:</div>';
                                        $changesHtml .= '<div class="grid grid-cols-2 gap-2">';

                                        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
                                        foreach ($allKeys as $key) {
                                            /** @var mixed $oldValRaw */
                                            $oldValRaw = $oldValues[$key] ?? null;
                                            /** @var mixed $newValRaw */
                                            $newValRaw = $newValues[$key] ?? null;

                                            // Format values for display (handle arrays, objects, etc.)
                                            $oldValStr = $this->formatAuditValue($oldValRaw);
                                            $newValStr = $this->formatAuditValue($newValRaw);

                                            $changesHtml .= "<div class='col-span-2 flex items-center gap-2'>";
                                            $changesHtml .= "<span class='text-gray-400'>".e($key).':</span>';
                                            $changesHtml .= "<span class='text-danger-500 line-through'>{$oldValStr}</span>";
                                            $changesHtml .= "<span class='text-gray-400'>→</span>";
                                            $changesHtml .= "<span class='text-success-500'>{$newValStr}</span>";
                                            $changesHtml .= '</div>';
                                        }

                                        $changesHtml .= '</div></div>';
                                    }

                                    $html .= <<<HTML
                                    <div class="p-4 rounded-lg border {$colorClass}">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 text-sm font-medium rounded {$badgeClass}">{$eventLabel}</span>
                                            </div>
                                            <span class="text-sm text-gray-500">{$timestamp}</span>
                                        </div>
                                        <div class="text-sm">
                                            <span class="text-gray-500">By:</span>
                                            <span class="ml-1">{$userName}</span>
                                        </div>
                                        {$changesHtml}
                                    </div>
                                    HTML;
                                }

                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Format a value from audit log for display.
     */
    protected function formatAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '<em>null</em>';
        }

        if (is_array($value)) {
            return e(json_encode($value) ?: '[]');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return e((string) $value);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getBreakCaseAction(),
        ];
    }

    /**
     * Break Case action - opens/breaks a case for individual bottle access.
     *
     * Pre-checks:
     * - integrity_status = intact (hard blocker)
     * - is_breakable = true (hard blocker)
     *
     * Effects:
     * - integrity_status becomes BROKEN
     * - broken_at = now
     * - broken_by = current user
     * - broken_reason = provided reason
     * - Bottles remain individually tracked
     * - Case no longer eligible for case-based handling
     * - Case disappears from intact case filters
     * - Bottles immediately appear as loose stock
     * - Breaking is IRREVERSIBLE
     */
    protected function getBreakCaseAction(): Actions\Action
    {
        return Actions\Action::make('breakCase')
            ->label('Break Case')
            ->icon('heroicon-o-scissors')
            ->color('danger')
            ->visible(fn (InventoryCase $record): bool => $record->canBreak())
            ->requiresConfirmation()
            ->modalHeading(fn (InventoryCase $record): string => "Break Case #{$record->id}")
            ->modalDescription(function (InventoryCase $record): string {
                $bottleCount = $record->bottle_count;
                $config = $record->caseConfiguration;
                $configName = $config !== null ? $config->name : 'Unknown';

                return "You are about to break this case ({$configName}) containing {$bottleCount} bottles. This action is IRREVERSIBLE.";
            })
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->form([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('warning_content')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-danger-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                        </div>
                                        <div class="text-sm">
                                            <h4 class="font-bold text-danger-700 dark:text-danger-400 mb-2">⚠️ IRREVERSIBLE ACTION</h4>
                                            <ul class="list-disc list-inside space-y-1 text-danger-600 dark:text-danger-300">
                                                <li><strong>Permanent:</strong> Once broken, the case CANNOT be resealed</li>
                                                <li><strong>Unit handling ends:</strong> Case can no longer be moved, tracked, or fulfilled as a unit</li>
                                                <li><strong>Bottles become loose stock:</strong> Individual bottles must be managed separately</li>
                                                <li><strong>Audit trail:</strong> This action will be permanently recorded</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>'
                            )),
                    ]),
                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Breaking')
                    ->helperText('Explain why this case needs to be opened (e.g., inspection, sampling, event preparation)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder('Enter the reason for opening this case...'),
                Forms\Components\Checkbox::make('confirm_irreversible')
                    ->label('I understand that breaking this case is IRREVERSIBLE and the case can never be handled as a unit again')
                    ->required()
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'You must acknowledge that this action is irreversible.',
                    ]),
            ])
            ->action(function (InventoryCase $record, array $data, MovementService $movementService): void {
                try {
                    $user = auth()->user();
                    if ($user === null) {
                        throw new \RuntimeException('No authenticated user');
                    }

                    /** @var string $reason */
                    $reason = $data['reason'];

                    // Break the case using the service
                    $movementService->breakCase($record, $reason, $user);

                    // Create an InventoryException record for audit trail
                    InventoryException::create([
                        'exception_type' => 'case_broken',
                        'case_id' => $record->id,
                        'reason' => "Case broken: {$reason}",
                        'created_by' => $user->id,
                        'resolution' => 'Case broken as per operator request. Bottles now managed individually.',
                        'resolved_at' => now(),
                        'resolved_by' => $user->id,
                    ]);

                    Notification::make()
                        ->title('Case Broken Successfully')
                        ->body("Case #{$record->id} has been broken. The {$record->bottle_count} contained bottles are now managed as loose stock.")
                        ->success()
                        ->persistent()
                        ->send();

                    // Refresh the page to show updated status
                    $this->refreshFormData(['integrity_status', 'broken_at', 'broken_by', 'broken_reason']);

                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot Break Case')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Error Breaking Case')
                        ->body('An unexpected error occurred: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
