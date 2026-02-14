<?php

namespace App\Filament\Resources\Allocation\VoucherResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Services\Allocation\VoucherService;
use App\Services\Allocation\VoucherTransferService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    /**
     * Filter for audit log event type.
     */
    public ?string $auditEventFilter = null;

    /**
     * Filter for audit log date from.
     */
    public ?string $auditDateFrom = null;

    /**
     * Filter for audit log date until.
     */
    public ?string $auditDateUntil = null;

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

    /**
     * Get the header actions for the view page.
     *
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getInitiateTransferAction(),
            $this->getCancelTransferAction(),
            $this->getToggleTradableAction(),
            $this->getToggleGiftableAction(),
            $this->getSuspendAction(),
            $this->getReactivateAction(),
        ];
    }

    /**
     * Toggle tradable flag action.
     */
    protected function getToggleTradableAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        $currentValue = $record->tradable;
        $newValue = ! $currentValue;
        $actionLabel = $newValue ? 'Enable Trading' : 'Disable Trading';
        $confirmMessage = $newValue
            ? 'Are you sure you want to enable trading for this voucher? This will allow the voucher to be sold on secondary markets.'
            : 'Are you sure you want to disable trading for this voucher? This will prevent the voucher from being sold on secondary markets.';

        return Action::make('toggleTradable')
            ->label($actionLabel)
            ->icon($currentValue ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color($currentValue ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading($actionLabel)
            ->modalDescription($confirmMessage)
            ->modalSubmitActionLabel('Confirm')
            ->action(function () use ($record, $newValue): void {
                try {
                    /** @var VoucherService $service */
                    $service = app(VoucherService::class);
                    $service->setTradable($record, $newValue);

                    Notification::make()
                        ->title($newValue ? 'Trading enabled' : 'Trading disabled')
                        ->body("Tradable flag has been set to '".($newValue ? 'Yes' : 'No')."'.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['tradable']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot modify tradable flag')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher is issued, not suspended, user has permission
            ->visible(function () use ($record): bool {
                if ($record->suspended) {
                    return false;
                }
                if (! $record->isIssued()) {
                    return false;
                }

                return true;
            })
            ->authorize('setTradable', $record);
    }

    /**
     * Toggle giftable flag action.
     */
    protected function getToggleGiftableAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        $currentValue = $record->giftable;
        $newValue = ! $currentValue;
        $actionLabel = $newValue ? 'Enable Gifting' : 'Disable Gifting';
        $confirmMessage = $newValue
            ? 'Are you sure you want to enable gifting for this voucher? This will allow the voucher to be transferred to another customer.'
            : 'Are you sure you want to disable gifting for this voucher? This will prevent the voucher from being transferred to another customer.';

        return Action::make('toggleGiftable')
            ->label($actionLabel)
            ->icon($currentValue ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color($currentValue ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading($actionLabel)
            ->modalDescription($confirmMessage)
            ->modalSubmitActionLabel('Confirm')
            ->action(function () use ($record, $newValue): void {
                try {
                    /** @var VoucherService $service */
                    $service = app(VoucherService::class);
                    $service->setGiftable($record, $newValue);

                    Notification::make()
                        ->title($newValue ? 'Gifting enabled' : 'Gifting disabled')
                        ->body("Giftable flag has been set to '".($newValue ? 'Yes' : 'No')."'.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['giftable']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot modify giftable flag')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher is issued, not suspended, user has permission
            ->visible(function () use ($record): bool {
                if ($record->suspended) {
                    return false;
                }
                if (! $record->isIssued()) {
                    return false;
                }

                return true;
            })
            ->authorize('setGiftable', $record);
    }

    /**
     * Suspend voucher action.
     */
    protected function getSuspendAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        return Action::make('suspend')
            ->label('Suspend Voucher')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspend Voucher')
            ->modalDescription(
                'Are you sure you want to suspend this voucher? '
                .'Suspended vouchers cannot be traded, transferred, redeemed, or have their flags modified until reactivated. '
                .'This action can be undone by reactivating the voucher.'
            )
            ->modalSubmitActionLabel('Suspend')
            ->action(function () use ($record): void {
                try {
                    /** @var VoucherService $service */
                    $service = app(VoucherService::class);
                    $service->suspend($record);

                    Notification::make()
                        ->title('Voucher suspended')
                        ->body('The voucher has been suspended. All operations are now blocked until it is reactivated.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['suspended']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot suspend voucher')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher is not suspended, not terminal, user has permission
            ->visible(function () use ($record): bool {
                if ($record->suspended) {
                    return false;
                }
                if ($record->isTerminal()) {
                    return false;
                }

                return true;
            })
            ->authorize('suspend', $record);
    }

    /**
     * Reactivate (unsuspend) voucher action.
     */
    protected function getReactivateAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        return Action::make('reactivate')
            ->label('Reactivate Voucher')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Reactivate Voucher')
            ->modalDescription(
                'Are you sure you want to reactivate this voucher? '
                .'This will remove the suspension and allow normal operations (trading, gifting, redemption) to resume.'
            )
            ->modalSubmitActionLabel('Reactivate')
            ->action(function () use ($record): void {
                try {
                    /** @var VoucherService $service */
                    $service = app(VoucherService::class);
                    $service->reactivate($record);

                    Notification::make()
                        ->title('Voucher reactivated')
                        ->body('The voucher has been reactivated. Normal operations can now resume.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['suspended']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot reactivate voucher')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher is suspended, not terminal, user has permission
            ->visible(function () use ($record): bool {
                if (! $record->suspended) {
                    return false;
                }
                if ($record->isTerminal()) {
                    return false;
                }

                return true;
            })
            ->authorize('reactivate', $record);
    }

    /**
     * Initiate transfer action.
     */
    protected function getInitiateTransferAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        return Action::make('initiateTransfer')
            ->label('Initiate Transfer')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->form([
                Placeholder::make('info')
                    ->label('')
                    ->content('Transfers do not create new vouchers or consume allocation. They only change the voucher holder. The recipient will need to accept the transfer in the customer portal.'),
                Select::make('to_customer_id')
                    ->label('Recipient Customer')
                    ->placeholder('Search for a customer...')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) use ($record): array {
                        return Customer::query()
                            ->where('id', '!=', $record->customer_id)
                            ->where(function ($query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Customer $customer): array => [
                                $customer->id => "{$customer->name} ({$customer->email})",
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $customer = Customer::find($value);

                        return $customer ? "{$customer->name} ({$customer->email})" : null;
                    })
                    ->required()
                    ->helperText('The customer who will receive this voucher'),
                DatePicker::make('expires_at')
                    ->label('Transfer Expires On')
                    ->minDate(now()->addDay())
                    ->default(now()->addWeeks(2))
                    ->required()
                    ->helperText('If the recipient does not accept by this date, the transfer will expire automatically'),
            ])
            ->modalHeading('Initiate Voucher Transfer')
            ->modalDescription(
                'You are about to initiate a transfer of this voucher to another customer. '
                .'The recipient will need to accept the transfer before it is completed.'
            )
            ->modalSubmitActionLabel('Initiate Transfer')
            ->action(function (array $data) use ($record): void {
                try {
                    /** @var VoucherTransferService $service */
                    $service = app(VoucherTransferService::class);

                    $toCustomer = Customer::findOrFail($data['to_customer_id']);
                    $expiresAt = Carbon::parse($data['expires_at'])->endOfDay();

                    $transfer = $service->initiateTransfer($record, $toCustomer, $expiresAt);

                    Notification::make()
                        ->title('Transfer initiated')
                        ->body("Transfer to {$toCustomer->name} has been initiated. The recipient must accept the transfer before {$expiresAt->format('Y-m-d')}.")
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot initiate transfer')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher is issued, not suspended, not in pending transfer, giftable
            ->visible(function () use ($record): bool {
                if (! $record->isIssued()) {
                    return false;
                }
                if ($record->suspended) {
                    return false;
                }
                if ($record->hasPendingTransfer()) {
                    return false;
                }
                if (! $record->giftable) {
                    return false;
                }

                return true;
            })
            ->authorize('initiateTransfer', $record);
    }

    /**
     * Cancel pending transfer action.
     */
    protected function getCancelTransferAction(): Action
    {
        /** @var Voucher $record */
        $record = $this->record;

        $pendingTransfer = $record->getPendingTransfer();

        return Action::make('cancelTransfer')
            ->label('Cancel Transfer')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Pending Transfer')
            ->modalDescription(function () use ($pendingTransfer): string {
                if (! $pendingTransfer) {
                    return 'Cancel the pending transfer for this voucher?';
                }

                $toCustomer = $pendingTransfer->toCustomer;
                $recipientName = $toCustomer ? $toCustomer->name : 'Unknown';

                return "Are you sure you want to cancel the pending transfer to {$recipientName}? "
                    .'This action cannot be undone. The voucher will remain with the current holder.';
            })
            ->modalSubmitActionLabel('Cancel Transfer')
            ->action(function () use ($record): void {
                try {
                    /** @var VoucherTransferService $service */
                    $service = app(VoucherTransferService::class);

                    $pendingTransfer = $record->getPendingTransfer();
                    if (! $pendingTransfer) {
                        throw new \InvalidArgumentException('No pending transfer found.');
                    }

                    $service->cancelTransfer($pendingTransfer);

                    Notification::make()
                        ->title('Transfer cancelled')
                        ->body('The pending transfer has been cancelled. The voucher remains with the current holder.')
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Cannot cancel transfer')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            // Only visible when: voucher has a pending transfer
            ->visible(fn (): bool => $record->hasPendingTransfer())
            ->authorize('cancelTransfer', $record);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Header: Lifecycle state banner
                $this->getHeaderSection(),

                // Quarantine warning (if applicable)
                $this->getQuarantineWarningSection(),

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
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
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
     * Quarantine warning section (only displayed if voucher requires attention).
     */
    protected function getQuarantineWarningSection(): Section
    {
        return Section::make()
            ->schema([
                TextEntry::make('quarantine_warning_banner')
                    ->label('')
                    ->getStateUsing(fn (): string => 'ANOMALOUS VOUCHER - REQUIRES MANUAL ATTENTION')
                    ->size(TextEntry\TextEntrySize::Large)
                    ->weight(FontWeight::Bold)
                    ->color('danger')
                    ->icon('heroicon-o-exclamation-triangle'),
                TextEntry::make('quarantine_reason')
                    ->label('Reason')
                    ->getStateUsing(fn (Voucher $record): string => $record->getAttentionReason() ?? 'Unknown anomaly')
                    ->weight(FontWeight::Medium)
                    ->color('danger'),
                TextEntry::make('quarantine_explanation')
                    ->label('')
                    ->getStateUsing(fn (): string => 'This voucher has been flagged as anomalous and is outside the normal scope of operations. '
                        .'Manual intervention is required to resolve the issue before this voucher can participate in normal operations '
                        .'(transfers, fulfillment, etc.). Contact your administrator or data team to investigate and resolve.')
                    ->color('danger'),
                Section::make('Detected Anomalies')
                    ->description('All issues detected with this voucher')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('anomalies_list')
                            ->label('')
                            ->getStateUsing(function (Voucher $record): string {
                                $anomalies = $record->getDetectedAnomalies();
                                if (empty($anomalies)) {
                                    return 'No anomalies detected (voucher may have been manually flagged)';
                                }

                                return '• '.implode("\n• ", $anomalies);
                            })
                            ->html(false),
                    ]),
            ])
            ->extraAttributes([
                'class' => 'border-l-4 border-l-danger-500 bg-danger-50 dark:bg-danger-900/10',
            ])
            ->visible(fn (Voucher $record): bool => $record->isQuarantined());
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
                // CRITICAL LINEAGE WARNING - Most prominent element
                Section::make('')
                    ->schema([
                        TextEntry::make('lineage_critical_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => '⛔ NOT FULFILLABLE OUTSIDE LINEAGE')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('danger'),
                        TextEntry::make('lineage_constraint_message')
                            ->label('')
                            ->getStateUsing(fn (Voucher $record): string => $record->getLineageConstraintMessage())
                            ->color('danger'),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-danger-50 dark:bg-danger-950 border-2 border-danger-500 rounded-lg p-4',
                    ]),
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
                Section::make('Lineage Enforcement')
                    ->description('Understanding lineage constraints')
                    ->collapsed()
                    ->collapsible()
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextEntry::make('lineage_explanation')
                            ->label('')
                            ->getStateUsing(fn (): string => '**Allocation lineage is immutable and strictly enforced:**

• **allocation_id cannot be changed** - The allocation_id field is locked at voucher creation time and cannot be modified by any user or system process.

• **Fulfillment validation** - Module C (Fulfillment) must validate that physical bottles belong to the same allocation before assigning them to this voucher.

• **Provenance guarantee** - This ensures complete traceability from allocation to voucher to physical bottle.

• **Constraint inheritance** - Commercial constraints from the allocation are enforced throughout the voucher lifecycle.

Attempting to fulfill this voucher with bottles from a different allocation will result in a system error.')
                            ->markdown()
                            ->html(),
                    ]),
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
                TextEntry::make('suspension_reason')
                    ->label('Suspension Reason')
                    ->getStateUsing(fn (Voucher $record): string => $record->getSuspensionReason())
                    ->visible(fn (Voucher $record): bool => $record->suspended)
                    ->badge()
                    ->color(fn (Voucher $record): string => $record->isSuspendedForTrading() ? 'warning' : 'danger')
                    ->icon(fn (Voucher $record): string => $record->isSuspendedForTrading()
                        ? 'heroicon-o-currency-dollar'
                        : 'heroicon-o-pause-circle'),
                TextEntry::make('external_trading_reference')
                    ->label('External Trading Reference')
                    ->visible(fn (Voucher $record): bool => $record->isSuspendedForTrading())
                    ->copyable()
                    ->badge()
                    ->color('warning'),
                TextEntry::make('flags_info')
                    ->label('')
                    ->getStateUsing(function (Voucher $record): string {
                        if ($record->isSuspendedForTrading()) {
                            return 'This voucher is SUSPENDED FOR EXTERNAL TRADING. All operations are blocked until trading is completed or the voucher is manually reactivated. The external trading platform will notify the system when the trade is complete.';
                        }

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
                    ->iconColor(fn (Voucher $record): string => $record->suspended
                        ? ($record->isSuspendedForTrading() ? 'warning' : 'danger')
                        : 'info')
                    ->color(fn (Voucher $record): string => $record->suspended
                        ? ($record->isSuspendedForTrading() ? 'warning' : 'danger')
                        : 'gray'),
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
                    ->description(function (Voucher $record): string {
                        if (! $record->hasPendingTransfer()) {
                            return 'No pending transfers';
                        }

                        if ($record->hasPendingTransferBlockedByLock()) {
                            return 'BLOCKED: Transfer acceptance blocked while voucher is locked';
                        }

                        return 'There is an active pending transfer for this voucher';
                    })
                    ->icon(function (Voucher $record): string {
                        if ($record->hasPendingTransferBlockedByLock()) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        return $record->hasPendingTransfer()
                            ? 'heroicon-o-clock'
                            : 'heroicon-o-check-circle';
                    })
                    ->iconColor(function (Voucher $record): string {
                        if ($record->hasPendingTransferBlockedByLock()) {
                            return 'danger';
                        }

                        return $record->hasPendingTransfer() ? 'warning' : 'success';
                    })
                    ->schema([
                        // Warning banner for blocked transfer due to lock
                        TextEntry::make('transfer_blocked_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => '⚠️ LOCKED DURING TRANSFER - ACCEPTANCE BLOCKED')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('danger')
                            ->visible(fn (Voucher $record): bool => $record->hasPendingTransferBlockedByLock()),
                        TextEntry::make('transfer_blocked_explanation')
                            ->label('')
                            ->getStateUsing(fn (Voucher $record): string => 'This voucher was locked for fulfillment while a transfer was pending. '
                                .'The transfer cannot be accepted until the voucher is unlocked. '
                                .'The transfer can still be cancelled. '
                                .(($lockedAt = $record->getLockedAtTimestamp())
                                    ? "Locked at: {$lockedAt->format('Y-m-d H:i:s')}"
                                    : ''))
                            ->color('danger')
                            ->visible(fn (Voucher $record): bool => $record->hasPendingTransferBlockedByLock()),
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
                                // Show blocked status for this specific transfer
                                TextEntry::make('acceptance_blocked_reason')
                                    ->label('Acceptance Status')
                                    ->getStateUsing(fn (VoucherTransfer $record): string => $record->getAcceptanceBlockedReason() ?? 'Can be accepted')
                                    ->badge()
                                    ->color(fn (VoucherTransfer $record): string => $record->isAcceptanceBlockedByLock() ? 'danger' : ($record->canCurrentlyBeAccepted() ? 'success' : 'warning'))
                                    ->icon(fn (VoucherTransfer $record): string => $record->isAcceptanceBlockedByLock() ? 'heroicon-o-lock-closed' : ($record->canCurrentlyBeAccepted() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')),
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
                                            ->placeholder('—'),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Section::make('External Trading')
                    ->description(fn (Voucher $record): string => $record->isSuspendedForTrading()
                        ? 'This voucher is currently suspended for external trading'
                        : 'External trading information')
                    ->icon(fn (Voucher $record): string => $record->isSuspendedForTrading()
                        ? 'heroicon-o-currency-dollar'
                        : 'heroicon-o-banknotes')
                    ->iconColor(fn (Voucher $record): string => $record->isSuspendedForTrading()
                        ? 'warning'
                        : 'gray')
                    ->collapsed(fn (Voucher $record): bool => ! $record->isSuspendedForTrading())
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('trading_status')
                                    ->label('Trading Status')
                                    ->badge()
                                    ->getStateUsing(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                        ? 'Suspended for Trading'
                                        : 'Not in External Trading')
                                    ->color(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                        ? 'warning'
                                        : 'gray')
                                    ->icon(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                        ? 'heroicon-o-pause-circle'
                                        : 'heroicon-o-check-circle'),
                                TextEntry::make('external_trading_reference')
                                    ->label('Trading Reference')
                                    ->copyable()
                                    ->weight(FontWeight::Bold)
                                    ->visible(fn (Voucher $record): bool => $record->isSuspendedForTrading()),
                            ]),
                        TextEntry::make('trading_info')
                            ->label('')
                            ->getStateUsing(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                ? 'This voucher is currently suspended while being traded on an external platform. '
                                  .'All operations (redemption, transfers, flag modifications) are blocked. '
                                  .'When the external trade is completed, the system will be notified via API callback '
                                  .'and the voucher will be transferred to the new owner automatically.'
                                : 'External trading allows vouchers to be sold on secondary markets. '
                                  .'When a voucher is listed for external trading, it becomes suspended to prevent '
                                  .'duplicate sales or modifications during the trading process.')
                            ->icon(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                ? 'heroicon-o-exclamation-triangle'
                                : 'heroicon-o-information-circle')
                            ->iconColor(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                ? 'warning'
                                : 'info')
                            ->color(fn (Voucher $record): string => $record->isSuspendedForTrading()
                                ? 'warning'
                                : 'gray'),
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
            ->description(fn (): string => $this->getAuditFilterDescription())
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('filter_audit')
                    ->label('Filter')
                    ->icon('heroicon-o-funnel')
                    ->form([
                        Select::make('event_type')
                            ->label('Event Type')
                            ->placeholder('All events')
                            ->options([
                                AuditLog::EVENT_CREATED => 'Created',
                                AuditLog::EVENT_UPDATED => 'Updated',
                                AuditLog::EVENT_DELETED => 'Deleted',
                                AuditLog::EVENT_VOUCHER_ISSUED => 'Voucher Issued',
                                AuditLog::EVENT_LIFECYCLE_CHANGE => 'Lifecycle Changed',
                                AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
                                AuditLog::EVENT_VOUCHER_SUSPENDED => 'Voucher Suspended',
                                AuditLog::EVENT_VOUCHER_REACTIVATED => 'Voucher Reactivated',
                                AuditLog::EVENT_TRANSFER_INITIATED => 'Transfer Initiated',
                                AuditLog::EVENT_TRANSFER_ACCEPTED => 'Transfer Accepted',
                                AuditLog::EVENT_TRANSFER_CANCELLED => 'Transfer Cancelled',
                                AuditLog::EVENT_TRANSFER_EXPIRED => 'Transfer Expired',
                                AuditLog::EVENT_TRADING_SUSPENDED => 'Suspended for Trading',
                                AuditLog::EVENT_TRADING_COMPLETED => 'Trading Completed',
                                AuditLog::EVENT_VOUCHER_QUARANTINED => 'Voucher Quarantined',
                                AuditLog::EVENT_VOUCHER_UNQUARANTINED => 'Voucher Unquarantined',
                            ])
                            ->default($this->auditEventFilter),
                        DatePicker::make('date_from')
                            ->label('From Date')
                            ->default($this->auditDateFrom),
                        DatePicker::make('date_until')
                            ->label('Until Date')
                            ->default($this->auditDateUntil),
                    ])
                    ->action(function (array $data): void {
                        $this->auditEventFilter = $data['event_type'] ?? null;
                        $this->auditDateFrom = $data['date_from'] ?? null;
                        $this->auditDateUntil = $data['date_until'] ?? null;
                    }),
                \Filament\Infolists\Components\Actions\Action::make('clear_filters')
                    ->label('Clear Filters')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (): bool => $this->auditEventFilter !== null || $this->auditDateFrom !== null || $this->auditDateUntil !== null)
                    ->action(function (): void {
                        $this->auditEventFilter = null;
                        $this->auditDateFrom = null;
                        $this->auditDateUntil = null;
                    }),
            ])
            ->schema([
                TextEntry::make('audit_logs_list')
                    ->label('')
                    ->getStateUsing(function (Voucher $record): string {
                        $query = $record->auditLogs()->orderBy('created_at', 'desc');

                        // Apply event type filter
                        if ($this->auditEventFilter) {
                            $query->where('event', $this->auditEventFilter);
                        }

                        // Apply date from filter
                        if ($this->auditDateFrom) {
                            $query->whereDate('created_at', '>=', $this->auditDateFrom);
                        }

                        // Apply date until filter
                        if ($this->auditDateUntil) {
                            $query->whereDate('created_at', '<=', $this->auditDateUntil);
                        }

                        $logs = $query->get();

                        if ($logs->isEmpty()) {
                            return '<div class="text-gray-500 text-sm py-4">No audit logs found matching the current filters.</div>';
                        }

                        $html = '<div class="space-y-3">';
                        foreach ($logs as $log) {
                            /** @var AuditLog $log */
                            $eventColor = $log->getEventColor();
                            $eventLabel = $log->getEventLabel();
                            $user = $log->user;
                            $userName = $user !== null ? $user->name : 'System';
                            $timestamp = $log->created_at->format('M d, Y H:i:s');
                            $changes = self::formatAuditChanges($log);

                            $colorClass = match ($eventColor) {
                                'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'danger' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                            };

                            $html .= <<<HTML
                            <div class="flex items-start gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colorClass}">
                                        {$eventLabel}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            {$userName}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            {$timestamp}
                                        </span>
                                    </div>
                                    <div class="text-sm">{$changes}</div>
                                </div>
                            </div>
                            HTML;
                        }
                        $html .= '</div>';

                        return $html;
                    })
                    ->html()
                    ->columnSpanFull(),
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
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable audit trail of all events for this voucher'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
                AuditLog::EVENT_DELETED => 'Deleted',
                AuditLog::EVENT_VOUCHER_ISSUED => 'Voucher Issued',
                AuditLog::EVENT_LIFECYCLE_CHANGE => 'Lifecycle Changed',
                AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
                AuditLog::EVENT_VOUCHER_SUSPENDED => 'Voucher Suspended',
                AuditLog::EVENT_VOUCHER_REACTIVATED => 'Voucher Reactivated',
                AuditLog::EVENT_TRANSFER_INITIATED => 'Transfer Initiated',
                AuditLog::EVENT_TRANSFER_ACCEPTED => 'Transfer Accepted',
                AuditLog::EVENT_TRANSFER_CANCELLED => 'Transfer Cancelled',
                AuditLog::EVENT_TRANSFER_EXPIRED => 'Transfer Expired',
                AuditLog::EVENT_TRADING_SUSPENDED => 'Suspended for Trading',
                AuditLog::EVENT_TRADING_COMPLETED => 'Trading Completed',
                AuditLog::EVENT_VOUCHER_QUARANTINED => 'Voucher Quarantined',
                AuditLog::EVENT_VOUCHER_UNQUARANTINED => 'Voucher Unquarantined',
                default => $this->auditEventFilter,
            };
            $filters[] = "Event: {$eventLabel}";
        }
        if ($this->auditDateFrom) {
            $filters[] = "From: {$this->auditDateFrom}";
        }
        if ($this->auditDateUntil) {
            $filters[] = "Until: {$this->auditDateUntil}";
        }

        if (! empty($filters)) {
            $parts[] = 'Filters: '.implode(', ', $filters);
        }

        return implode(' | ', $parts);
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
