<?php

namespace App\Filament\Resources\Allocation\VoucherResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\AuditLog;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Voucher $record */
        $record = $this->record;

        return "Voucher #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Voucher $record */
        $record = $this->record;

        return $record->getBottleSkuLabel().' - '.$record->lifecycle_state->label();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Header: Lifecycle state banner
                $this->getHeaderSection(),

                // Section 1: What was sold
                $this->getWhatWasSoldSection(),

                // Section 2: Allocation lineage
                $this->getAllocationLineageSection(),

                // Section 3: Lifecycle state
                $this->getLifecycleStateSection(),

                // Section 4: Behavioral flags
                $this->getBehavioralFlagsSection(),

                // Section 5: Transfer context
                $this->getTransferContextSection(),

                // Section 6: Event history
                $this->getEventHistorySection(),
            ]);
    }

    /**
     * Header section with prominent lifecycle state banner.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make()
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('id')
                                ->label('Voucher ID')
                                ->copyable()
                                ->copyMessage('Voucher ID copied')
                                ->weight(FontWeight::Bold)
                                ->size(TextEntry\TextEntrySize::Large),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('customer.name')
                                ->label('Current Holder')
                                ->weight(FontWeight::Bold)
                                ->url(fn (Voucher $record): ?string => $record->customer
                                    ? route('filament.admin.resources.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('customer.email')
                                ->label('')
                                ->color('gray'),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('lifecycle_state')
                                ->label('Lifecycle State')
                                ->badge()
                                ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                                ->color(fn (VoucherLifecycleState $state): string => $state->color())
                                ->icon(fn (VoucherLifecycleState $state): string => $state->icon())
                                ->size(TextEntry\TextEntrySize::Large),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('created_at')
                                ->label('Issued On')
                                ->dateTime(),
                        ])->columnSpan(1),
                    ]),
            ])
            ->extraAttributes(fn (Voucher $record): array => [
                'class' => match ($record->lifecycle_state) {
                    VoucherLifecycleState::Issued => 'border-l-4 border-l-success-500',
                    VoucherLifecycleState::Locked => 'border-l-4 border-l-warning-500',
                    VoucherLifecycleState::Redeemed => 'border-l-4 border-l-info-500',
                    VoucherLifecycleState::Cancelled => 'border-l-4 border-l-danger-500',
                },
            ]);
    }

    /**
     * Section 1: What was sold.
     */
    protected function getWhatWasSoldSection(): Section
    {
        return Section::make('What Was Sold')
            ->description('Details of the sellable product and entitlement')
            ->icon('heroicon-o-shopping-bag')
            ->collapsible()
            ->schema([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('sellable_sku')
                            ->label('Sellable SKU')
                            ->getStateUsing(fn (Voucher $record): string => $record->sellableSku
                                ? $record->sellableSku->sku_code
                                : 'N/A (individual bottle)')
                            ->weight(FontWeight::Medium),
                        TextEntry::make('bottle_sku')
                            ->label('Bottle SKU')
                            ->getStateUsing(fn (Voucher $record): string => $record->getBottleSkuLabel())
                            ->copyable()
                            ->weight(FontWeight::Bold),
                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->getStateUsing(fn (): string => '1 bottle')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('sale_reference')
                            ->label('Sale Reference')
                            ->default('N/A')
                            ->copyable(),
                    ]),
                Section::make('Case Entitlement')
                    ->description(fn (Voucher $record): string => $record->isPartOfCase()
                        ? 'This voucher is part of a case purchase'
                        : 'This voucher is not part of a case')
                    ->icon(fn (Voucher $record): string => $record->isPartOfCase()
                        ? 'heroicon-o-archive-box'
                        : 'heroicon-o-archive-box-x-mark')
                    ->collapsed(fn (Voucher $record): bool => ! $record->isPartOfCase())
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('caseEntitlement.id')
                                    ->label('Case Entitlement ID')
                                    ->copyable()
                                    ->visible(fn (Voucher $record): bool => $record->isPartOfCase()),
                                TextEntry::make('caseEntitlement.status')
                                    ->label('Case Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?CaseEntitlementStatus $state): string => $state
                                        ? $state->label()
                                        : 'N/A')
                                    ->color(fn (?CaseEntitlementStatus $state): string => $state
                                        ? $state->color()
                                        : 'gray')
                                    ->icon(fn (?CaseEntitlementStatus $state): ?string => $state
                                        ? $state->icon()
                                        : null)
                                    ->visible(fn (Voucher $record): bool => $record->isPartOfCase()),
                                TextEntry::make('caseEntitlement.sellableSku.sku_code')
                                    ->label('Case SKU')
                                    ->visible(fn (Voucher $record): bool => $record->isPartOfCase()),
                                TextEntry::make('case_vouchers_count')
                                    ->label('Vouchers in Case')
                                    ->getStateUsing(fn (Voucher $record): string => $record->caseEntitlement
                                        ? (string) $record->caseEntitlement->getVouchersCount()
                                        : 'N/A')
                                    ->visible(fn (Voucher $record): bool => $record->isPartOfCase()),
                            ]),
                        TextEntry::make('case_broken_info')
                            ->label('')
                            ->getStateUsing(function (Voucher $record): string {
                                $case = $record->caseEntitlement;
                                if (! $case || $case->isIntact()) {
                                    return '';
                                }

                                $reason = $case->broken_reason ?? 'Unknown reason';
                                $brokenAt = $case->broken_at?->format('Y-m-d H:i:s') ?? 'Unknown time';

                                return "Case was broken on {$brokenAt}. Reason: {$reason}";
                            })
                            ->visible(fn (Voucher $record): bool => $record->caseEntitlement?->isBroken() ?? false)
                            ->color('danger')
                            ->icon('heroicon-o-exclamation-triangle'),
                    ]),
            ]);
    }

    /**
     * Section 2: Allocation lineage.
     */
    protected function getAllocationLineageSection(): Section
    {
        return Section::make('Allocation Lineage')
            ->description('The authoritative source of this entitlement - lineage can never be modified')
            ->icon('heroicon-o-shield-check')
            ->iconColor('warning')
            ->collapsible()
            ->schema([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('allocation_id')
                            ->label('Allocation ID')
                            ->url(fn (Voucher $record): string => AllocationResource::getUrl('view', ['record' => $record->allocation_id]))
                            ->color('primary')
                            ->weight(FontWeight::Bold)
                            ->copyable(),
                        TextEntry::make('allocation.source_type')
                            ->label('Source Type')
                            ->badge()
                            ->formatStateUsing(fn (Voucher $record): string => $record->allocation?->source_type->label() ?? 'N/A')
                            ->color(fn (Voucher $record): string => $record->allocation?->source_type->color() ?? 'gray')
                            ->icon(fn (Voucher $record): ?string => $record->allocation?->source_type->icon()),
                        TextEntry::make('allocation.supply_form')
                            ->label('Supply Form')
                            ->badge()
                            ->formatStateUsing(fn (Voucher $record): string => $record->allocation?->supply_form->label() ?? 'N/A')
                            ->color(fn (Voucher $record): string => $record->allocation?->supply_form->color() ?? 'gray'),
                        TextEntry::make('allocation.serialization_required')
                            ->label('Serialization')
                            ->badge()
                            ->getStateUsing(fn (Voucher $record): string => $record->allocation?->serialization_required
                                ? 'Required'
                                : 'Not Required')
                            ->color(fn (Voucher $record): string => $record->allocation?->serialization_required
                                ? 'success'
                                : 'gray'),
                    ]),
                Section::make('Constraints Snapshot')
                    ->description('Constraints inherited from the allocation at time of issuance')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('allocation.constraint.allowed_channels')
                                    ->label('Allowed Channels')
                                    ->badge()
                                    ->getStateUsing(function (Voucher $record): string {
                                        $constraint = $record->allocation?->constraint;
                                        if ($constraint === null) {
                                            return 'All channels';
                                        }
                                        $channels = $constraint->allowed_channels;

                                        return ($channels === null || $channels === []) ? 'All channels' : implode(', ', $channels);
                                    })
                                    ->color('info'),
                                TextEntry::make('allocation.constraint.allowed_geographies')
                                    ->label('Allowed Geographies')
                                    ->badge()
                                    ->getStateUsing(function (Voucher $record): string {
                                        $constraint = $record->allocation?->constraint;
                                        if ($constraint === null) {
                                            return 'All geographies';
                                        }
                                        $geos = $constraint->allowed_geographies;

                                        return ($geos === null || $geos === []) ? 'All geographies' : implode(', ', $geos);
                                    })
                                    ->color('info'),
                                TextEntry::make('allocation.constraint.allowed_customer_types')
                                    ->label('Allowed Customer Types')
                                    ->badge()
                                    ->getStateUsing(function (Voucher $record): string {
                                        $constraint = $record->allocation?->constraint;
                                        if ($constraint === null) {
                                            return 'All customer types';
                                        }
                                        $types = $constraint->allowed_customer_types;

                                        return ($types === null || $types === []) ? 'All customer types' : implode(', ', $types);
                                    })
                                    ->color('info'),
                            ]),
                    ]),
                TextEntry::make('lineage_warning')
                    ->label('')
                    ->getStateUsing(fn (): string => 'Lineage can never be modified. This voucher can only be fulfilled with supply from the same allocation lineage. Fulfillment with bottles from a different allocation is not permitted.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->color('warning'),
            ]);
    }

    /**
     * Section 3: Lifecycle state.
     */
    protected function getLifecycleStateSection(): Section
    {
        return Section::make('Lifecycle State')
            ->description('Current state and allowed transitions')
            ->icon('heroicon-o-arrow-path')
            ->collapsible()
            ->schema([
                Grid::make(2)
                    ->schema([
                        Group::make([
                            TextEntry::make('lifecycle_state')
                                ->label('Current State')
                                ->badge()
                                ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                                ->color(fn (VoucherLifecycleState $state): string => $state->color())
                                ->icon(fn (VoucherLifecycleState $state): string => $state->icon())
                                ->size(TextEntry\TextEntrySize::Large),
                            TextEntry::make('state_description')
                                ->label('')
                                ->getStateUsing(fn (Voucher $record): string => $record->lifecycle_state->description())
                                ->color('gray'),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('allowed_transitions')
                                ->label('Allowed Transitions')
                                ->badge()
                                ->getStateUsing(function (Voucher $record): string {
                                    $transitions = $record->getAllowedTransitions();
                                    if (empty($transitions)) {
                                        return 'None (terminal state)';
                                    }

                                    return implode(', ', array_map(fn ($t) => $t->label(), $transitions));
                                })
                                ->color(fn (Voucher $record): string => $record->isTerminal() ? 'danger' : 'info'),
                            TextEntry::make('is_terminal')
                                ->label('Terminal State')
                                ->badge()
                                ->getStateUsing(fn (Voucher $record): string => $record->isTerminal() ? 'Yes' : 'No')
                                ->color(fn (Voucher $record): string => $record->isTerminal() ? 'danger' : 'gray'),
                        ])->columnSpan(1),
                    ]),
                Section::make('State Transition Diagram')
                    ->description('Visual reference for lifecycle states')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('transition_diagram')
                            ->label('')
                            ->getStateUsing(fn (): string => self::getTransitionDiagram())
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Section 4: Behavioral flags.
     */
    protected function getBehavioralFlagsSection(): Section
    {
        return Section::make('Behavioral Flags')
            ->description('Flags that control what operations are allowed on this voucher')
            ->icon('heroicon-o-flag')
            ->collapsible()
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('tradable')
                            ->label('Tradable')
                            ->badge()
                            ->getStateUsing(fn (Voucher $record): string => $record->tradable ? 'Yes' : 'No')
                            ->color(fn (Voucher $record): string => $record->tradable ? 'success' : 'danger')
                            ->icon(fn (Voucher $record): string => $record->tradable
                                ? 'heroicon-o-check-circle'
                                : 'heroicon-o-x-circle')
                            ->helperText('If enabled, this voucher can be sold on secondary markets'),
                        TextEntry::make('giftable')
                            ->label('Giftable')
                            ->badge()
                            ->getStateUsing(fn (Voucher $record): string => $record->giftable ? 'Yes' : 'No')
                            ->color(fn (Voucher $record): string => $record->giftable ? 'success' : 'danger')
                            ->icon(fn (Voucher $record): string => $record->giftable
                                ? 'heroicon-o-check-circle'
                                : 'heroicon-o-x-circle')
                            ->helperText('If enabled, this voucher can be transferred to another customer'),
                        TextEntry::make('suspended')
                            ->label('Suspended')
                            ->badge()
                            ->getStateUsing(fn (Voucher $record): string => $record->suspended ? 'Yes' : 'No')
                            ->color(fn (Voucher $record): string => $record->suspended ? 'danger' : 'success')
                            ->icon(fn (Voucher $record): string => $record->suspended
                                ? 'heroicon-o-pause-circle'
                                : 'heroicon-o-play-circle')
                            ->helperText('If suspended, all operations are blocked'),
                    ]),
                TextEntry::make('flags_info')
                    ->label('')
                    ->getStateUsing(function (Voucher $record): string {
                        if ($record->suspended) {
                            return 'This voucher is SUSPENDED. All operations (trading, gifting, redemption) are blocked until reactivated.';
                        }

                        if ($record->isTerminal()) {
                            return 'This voucher is in a terminal state. Flags cannot be modified.';
                        }

                        if (! $record->isIssued()) {
                            return 'Tradable and giftable flags can only be modified when the voucher is in Issued state.';
                        }

                        return 'Flags can be modified by authorized operators using the flag management actions.';
                    })
                    ->icon(fn (Voucher $record): string => $record->suspended
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-information-circle')
                    ->iconColor(fn (Voucher $record): string => $record->suspended ? 'danger' : 'info')
                    ->color(fn (Voucher $record): string => $record->suspended ? 'danger' : 'gray'),
            ]);
    }

    /**
     * Section 5: Transfer context.
     */
    protected function getTransferContextSection(): Section
    {
        return Section::make('Transfer Context')
            ->description('Pending transfers and external trading information')
            ->icon('heroicon-o-arrows-right-left')
            ->collapsible()
            ->schema([
                Section::make('Pending Transfers')
                    ->description(fn (Voucher $record): string => $record->hasPendingTransfer()
                        ? 'There is an active pending transfer for this voucher'
                        : 'No pending transfers')
                    ->icon(fn (Voucher $record): string => $record->hasPendingTransfer()
                        ? 'heroicon-o-clock'
                        : 'heroicon-o-check-circle')
                    ->iconColor(fn (Voucher $record): string => $record->hasPendingTransfer()
                        ? 'warning'
                        : 'success')
                    ->schema([
                        RepeatableEntry::make('pendingTransfers')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Transfer ID')
                                            ->copyable(),
                                        TextEntry::make('toCustomer.name')
                                            ->label('Recipient'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (VoucherTransfer $record): string => $record->getStatusLabel())
                                            ->color(fn (VoucherTransfer $record): string => $record->getStatusColor())
                                            ->icon(fn (VoucherTransfer $record): string => $record->getStatusIcon()),
                                        TextEntry::make('initiated_at')
                                            ->label('Initiated')
                                            ->dateTime(),
                                        TextEntry::make('expires_at')
                                            ->label('Expires')
                                            ->dateTime()
                                            ->color(fn (VoucherTransfer $record): string => $record->hasExpired()
                                                ? 'danger'
                                                : 'gray'),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Section::make('Transfer History')
                    ->description('All transfers for this voucher')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('voucherTransfers')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Transfer ID')
                                            ->copyable(),
                                        TextEntry::make('fromCustomer.name')
                                            ->label('From'),
                                        TextEntry::make('toCustomer.name')
                                            ->label('To'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (VoucherTransfer $record): string => $record->getStatusLabel())
                                            ->color(fn (VoucherTransfer $record): string => $record->getStatusColor())
                                            ->icon(fn (VoucherTransfer $record): string => $record->getStatusIcon()),
                                        TextEntry::make('initiated_at')
                                            ->label('Initiated')
                                            ->dateTime(),
                                        TextEntry::make('accepted_at')
                                            ->label('Completed')
                                            ->dateTime()
                                            ->default('—'),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                TextEntry::make('transfer_info')
                    ->label('')
                    ->getStateUsing(fn (): string => 'Transfers do not create new vouchers or consume allocation. They only change the voucher holder. Accept Transfer is done by the recipient in the customer portal, not from this admin panel.')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->color('gray'),
            ]);
    }

    /**
     * Section 6: Event history (audit trail).
     */
    protected function getEventHistorySection(): Section
    {
        return Section::make('Event History')
            ->description('Immutable audit trail of all events for this voucher')
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->schema([
                RepeatableEntry::make('auditLogs')
                    ->label('')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('event')
                                    ->label('')
                                    ->badge()
                                    ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                                    ->color(fn (AuditLog $record): string => $record->getEventColor())
                                    ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                                    ->columnSpan(1),
                                TextEntry::make('user.name')
                                    ->label('')
                                    ->default('System')
                                    ->icon('heroicon-o-user')
                                    ->columnSpan(1),
                                TextEntry::make('created_at')
                                    ->label('')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->columnSpan(1),
                                TextEntry::make('changes')
                                    ->label('')
                                    ->getStateUsing(fn (AuditLog $record): string => self::formatAuditChanges($record))
                                    ->html()
                                    ->columnSpan(1),
                            ]),
                    ]),
                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this voucher for compliance and traceability purposes. Events include issuance, lifecycle changes, flag modifications, transfers, and suspension/reactivation.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the transition diagram HTML.
     */
    protected static function getTransitionDiagram(): string
    {
        return '
        <div class="text-sm font-mono p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 rounded bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300 font-bold">ISSUED</span>
                    <span class="text-gray-500">→</span>
                    <span class="px-2 py-1 rounded bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300">LOCKED</span>
                    <span class="text-gray-500">→</span>
                    <span class="px-2 py-1 rounded bg-info-100 dark:bg-info-900 text-info-700 dark:text-info-300">REDEEMED</span>
                    <span class="text-gray-400 text-xs">(terminal)</span>
                </div>
                <div class="flex items-center gap-2 ml-8">
                    <span class="text-gray-500">↓</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 rounded bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300 font-bold">ISSUED</span>
                    <span class="text-gray-500">→</span>
                    <span class="px-2 py-1 rounded bg-danger-100 dark:bg-danger-900 text-danger-700 dark:text-danger-300">CANCELLED</span>
                    <span class="text-gray-400 text-xs">(terminal)</span>
                </div>
                <div class="flex items-center gap-2 mt-4 text-xs text-gray-500">
                    <span class="px-2 py-1 rounded bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300">LOCKED</span>
                    <span>→</span>
                    <span class="px-2 py-1 rounded bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300">ISSUED</span>
                    <span class="text-gray-400">(unlock/release)</span>
                </div>
            </div>
        </div>
        ';
    }

    /**
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];

        if ($log->event === AuditLog::EVENT_CREATED || $log->event === AuditLog::EVENT_VOUCHER_ISSUED) {
            $fieldCount = count($newValues);

            return "<span class='text-sm text-gray-500'>{$fieldCount} field(s) set</span>";
        }

        if ($log->event === AuditLog::EVENT_DELETED) {
            return "<span class='text-sm text-gray-500'>Record deleted</span>";
        }

        $changes = [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $fieldLabel = ucfirst(str_replace('_', ' ', $field));
                $oldDisplay = self::formatValue($oldValue);
                $newDisplay = self::formatValue($newValue);
                $changes[] = "<strong>{$fieldLabel}</strong>: {$oldDisplay} → {$newDisplay}";
            }
        }

        return count($changes) > 0
            ? '<span class="text-sm">'.implode('<br>', $changes).'</span>'
            : '<span class="text-sm text-gray-500">No field changes</span>';
    }

    /**
     * Format a value for display in audit logs.
     */
    protected static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">empty</em>';
        }

        if (is_array($value)) {
            return '<em class="text-gray-500">['.count($value).' items]</em>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $stringValue = (string) $value;
        if (strlen($stringValue) > 50) {
            return htmlspecialchars(substr($stringValue, 0, 47)).'...';
        }

        return htmlspecialchars($stringValue);
    }
}
