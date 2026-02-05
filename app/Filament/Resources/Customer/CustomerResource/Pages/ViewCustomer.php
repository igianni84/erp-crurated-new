<?php

namespace App\Filament\Resources\Customer\CustomerResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Enums\Finance\SubscriptionStatus;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Filament\Resources\Allocation\CaseEntitlementResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Filament\Resources\Fulfillment\ShipmentResource;
use App\Filament\Resources\Fulfillment\ShippingOrderResource;
use App\Models\Allocation\CaseEntitlement;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\StorageBillingPeriod;
use App\Models\Finance\Subscription;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingOrder;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Customer $record */
        $record = $this->record;

        return "Customer: {$record->name}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var Customer $record */
        $record = $this->record;
        $warningsSection = $this->getFinancialWarningsSection($record);

        $schema = [];

        // Add warnings section at the top if there are warnings
        if ($warningsSection !== null) {
            $schema[] = $warningsSection;
        }

        $schema[] = Tabs::make('Customer Details')
            ->tabs([
                $this->getOverviewTab(),
                $this->getVouchersTab(),
                $this->getShippingOrdersTab(),
            ])
            ->persistTabInQueryString()
            ->columnSpanFull();

        return $infolist->schema($schema);
    }

    /**
     * Get the Financial Warnings section if there are active warnings.
     * Shows prominent warnings for suspended subscriptions and blocked storage billing due to overdue payments.
     */
    protected function getFinancialWarningsSection(Customer $record): ?Section
    {
        // Get suspended subscriptions with overdue invoices
        $suspendedSubscriptions = $record->subscriptions()
            ->where('status', SubscriptionStatus::Suspended)
            ->get();

        // Get overdue INV0 invoices
        $overdueInvoices = $record->invoices()
            ->where('invoice_type', InvoiceType::MembershipService)
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->get();

        // Get blocked storage billing periods
        $blockedStoragePeriods = StorageBillingPeriod::blockedForCustomer($record->id)
            ->with(['invoice', 'location'])
            ->get();

        // Get overdue INV3 invoices (at risk of custody block)
        $overdueStorageInvoices = $record->invoices()
            ->where('invoice_type', InvoiceType::StorageFee)
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->get();

        // No warnings if nothing to show
        if ($suspendedSubscriptions->isEmpty() && $overdueInvoices->isEmpty()
            && $blockedStoragePeriods->isEmpty() && $overdueStorageInvoices->isEmpty()) {
            return null;
        }

        $warningItems = [];

        // Add blocked storage warnings (custody blocked - most severe for storage)
        foreach ($blockedStoragePeriods as $period) {
            /** @var StorageBillingPeriod $period */
            $invoice = $period->invoice;
            $location = $period->location;

            $warningItems[] = Grid::make(5)
                ->schema([
                    TextEntry::make('storage_blocked_'.$period->id)
                        ->label('Custody Blocked')
                        ->getStateUsing(fn (): string => 'Storage Billing')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('storage_period_'.$period->id)
                        ->label('Period')
                        ->getStateUsing(fn (): string => $period->getPeriodLabel())
                        ->color('gray'),
                    TextEntry::make('storage_location_'.$period->id)
                        ->label('Location')
                        ->getStateUsing(fn (): string => $location !== null ? $location->name : 'All Locations')
                        ->color('gray'),
                    TextEntry::make('storage_invoice_'.$period->id)
                        ->label('Invoice')
                        ->getStateUsing(fn (): string => $invoice !== null ? ($invoice->invoice_number ?? 'Draft') : 'N/A')
                        ->url(fn (): ?string => $invoice !== null ? InvoiceResource::getUrl('view', ['record' => $invoice]) : null)
                        ->openUrlInNewTab()
                        ->color('primary'),
                    TextEntry::make('storage_action_'.$period->id)
                        ->label('Resolution')
                        ->getStateUsing(fn (): string => $period->getResolutionInstructions() ?? 'Pay outstanding invoice')
                        ->color('gray'),
                ]);
        }

        // Add suspended subscription warnings
        foreach ($suspendedSubscriptions as $subscription) {
            /** @var Subscription $subscription */
            $warningItems[] = Grid::make(4)
                ->schema([
                    TextEntry::make('subscription_warning_'.$subscription->id)
                        ->label('Subscription Suspended')
                        ->getStateUsing(fn (): string => $subscription->plan_name)
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('subscription_status_'.$subscription->id)
                        ->label('Status')
                        ->getStateUsing(fn (): string => 'Suspended')
                        ->badge()
                        ->color('danger'),
                    TextEntry::make('subscription_reason_'.$subscription->id)
                        ->label('Reason')
                        ->getStateUsing(fn (): string => 'Overdue INV0 payment')
                        ->color('gray'),
                    TextEntry::make('subscription_action_'.$subscription->id)
                        ->label('Resolution')
                        ->getStateUsing(fn (): string => 'Pay outstanding invoice to resume')
                        ->color('gray'),
                ]);
        }

        // Add overdue INV0 invoice warnings
        foreach ($overdueInvoices as $invoice) {
            /** @var Invoice $invoice */
            $daysOverdue = $invoice->getDaysOverdue() ?? 0;
            $thresholdDays = (int) config('finance.subscription_overdue_suspension_days', 14);
            $daysUntilSuspension = max(0, $thresholdDays - $daysOverdue);

            $warningItems[] = Grid::make(5)
                ->schema([
                    TextEntry::make('invoice_warning_'.$invoice->id)
                        ->label('Overdue Invoice (INV0)')
                        ->getStateUsing(fn (): string => $invoice->invoice_number ?? 'Draft')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->weight(FontWeight::Bold)
                        ->url(fn (): string => InvoiceResource::getUrl('view', ['record' => $invoice]))
                        ->openUrlInNewTab(),
                    TextEntry::make('invoice_amount_'.$invoice->id)
                        ->label('Amount Due')
                        ->getStateUsing(fn (): string => $invoice->currency.' '.number_format((float) $invoice->getOutstandingAmount(), 2))
                        ->color('danger')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('invoice_due_date_'.$invoice->id)
                        ->label('Due Date')
                        ->getStateUsing(fn (): string => $invoice->due_date !== null ? $invoice->due_date->format('Y-m-d') : 'N/A')
                        ->color('danger'),
                    TextEntry::make('invoice_overdue_'.$invoice->id)
                        ->label('Days Overdue')
                        ->getStateUsing(fn (): string => $daysOverdue.' days')
                        ->badge()
                        ->color($daysOverdue >= $thresholdDays ? 'danger' : 'warning'),
                    TextEntry::make('invoice_suspension_'.$invoice->id)
                        ->label('Suspension')
                        ->getStateUsing(function () use ($daysOverdue, $thresholdDays, $daysUntilSuspension): string {
                            if ($daysOverdue >= $thresholdDays) {
                                return 'Eligible for suspension';
                            }

                            return "In {$daysUntilSuspension} days";
                        })
                        ->badge()
                        ->color($daysOverdue >= $thresholdDays ? 'danger' : 'warning'),
                ]);
        }

        // Add overdue INV3 invoice warnings (at risk of custody block)
        $storageBlockThresholdDays = (int) config('finance.storage_overdue_block_days', 30);
        foreach ($overdueStorageInvoices as $invoice) {
            /** @var Invoice $invoice */
            $daysOverdue = $invoice->getDaysOverdue() ?? 0;
            $daysUntilBlock = max(0, $storageBlockThresholdDays - $daysOverdue);

            // Skip if already blocked (handled above via blocked periods)
            $period = $invoice->getStorageBillingPeriod();
            if ($period !== null && $period->isBlocked()) {
                continue;
            }

            $warningItems[] = Grid::make(5)
                ->schema([
                    TextEntry::make('storage_invoice_warning_'.$invoice->id)
                        ->label('Overdue Invoice (INV3)')
                        ->getStateUsing(fn (): string => $invoice->invoice_number ?? 'Draft')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->weight(FontWeight::Bold)
                        ->url(fn (): string => InvoiceResource::getUrl('view', ['record' => $invoice]))
                        ->openUrlInNewTab(),
                    TextEntry::make('storage_invoice_amount_'.$invoice->id)
                        ->label('Amount Due')
                        ->getStateUsing(fn (): string => $invoice->currency.' '.number_format((float) $invoice->getOutstandingAmount(), 2))
                        ->color('danger')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('storage_invoice_due_date_'.$invoice->id)
                        ->label('Due Date')
                        ->getStateUsing(fn (): string => $invoice->due_date !== null ? $invoice->due_date->format('Y-m-d') : 'N/A')
                        ->color('danger'),
                    TextEntry::make('storage_invoice_overdue_'.$invoice->id)
                        ->label('Days Overdue')
                        ->getStateUsing(fn (): string => $daysOverdue.' days')
                        ->badge()
                        ->color($daysOverdue >= $storageBlockThresholdDays ? 'danger' : 'warning'),
                    TextEntry::make('storage_invoice_block_'.$invoice->id)
                        ->label('Custody Block')
                        ->getStateUsing(function () use ($daysOverdue, $storageBlockThresholdDays, $daysUntilBlock): string {
                            if ($daysOverdue >= $storageBlockThresholdDays) {
                                return 'Eligible for block';
                            }

                            return "In {$daysUntilBlock} days";
                        })
                        ->badge()
                        ->color($daysOverdue >= $storageBlockThresholdDays ? 'danger' : 'warning'),
                ]);
        }

        return Section::make('Financial Warnings')
            ->description('Action required: This customer has financial issues that need attention.')
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->collapsed(false)
            ->collapsible(false)
            ->extraAttributes([
                'class' => 'bg-danger-50 dark:bg-danger-950 border-danger-300 dark:border-danger-700',
            ])
            ->schema($warningItems)
            ->columnSpanFull();
    }

    /**
     * Tab 1: Overview - Customer information and status.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-user')
            ->schema([
                Section::make('Customer Information')
                    ->description('Basic customer details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Customer ID')
                                        ->copyable()
                                        ->copyMessage('Customer ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('email')
                                        ->label('Email')
                                        ->icon('heroicon-o-envelope')
                                        ->copyable(),
                                ])->columnSpan(2),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn (Customer $record): string => match ($record->status) {
                                            Customer::STATUS_ACTIVE => 'success',
                                            Customer::STATUS_SUSPENDED => 'warning',
                                            Customer::STATUS_CLOSED => 'danger',
                                            default => 'gray',
                                        })
                                        ->icon(fn (Customer $record): string => match ($record->status) {
                                            Customer::STATUS_ACTIVE => 'heroicon-o-check-circle',
                                            Customer::STATUS_SUSPENDED => 'heroicon-o-pause-circle',
                                            Customer::STATUS_CLOSED => 'heroicon-o-x-circle',
                                            default => 'heroicon-o-question-mark-circle',
                                        })
                                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                    TextEntry::make('created_at')
                                        ->label('Customer Since')
                                        ->date(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Vouchers - Customer's vouchers with filters and summary.
     */
    protected function getVouchersTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;

        $totalVouchers = $record->vouchers()->count();
        $issuedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Issued)->count();
        $lockedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Locked)->count();
        $redeemedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Redeemed)->count();

        // Case entitlements counts
        $totalCaseEntitlements = $record->caseEntitlements()->count();
        $intactCasesCount = $record->caseEntitlements()->where('status', CaseEntitlementStatus::Intact)->count();
        $brokenCasesCount = $record->caseEntitlements()->where('status', CaseEntitlementStatus::Broken)->count();

        return Tab::make('Vouchers')
            ->icon('heroicon-o-ticket')
            ->badge($totalVouchers > 0 ? (string) $totalVouchers : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Voucher Summary')
                    ->description('Overview of customer\'s voucher portfolio')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_vouchers')
                                    ->label('Total Vouchers')
                                    ->getStateUsing(fn (): int => $totalVouchers)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-ticket'),
                                TextEntry::make('issued_vouchers')
                                    ->label('Issued')
                                    ->getStateUsing(fn (): int => $issuedCount)
                                    ->numeric()
                                    ->color('success')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-check-badge')
                                    ->helperText('Active and available'),
                                TextEntry::make('locked_vouchers')
                                    ->label('Locked')
                                    ->getStateUsing(fn (): int => $lockedCount)
                                    ->numeric()
                                    ->color('warning')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-lock-closed')
                                    ->helperText('Pending fulfillment'),
                                TextEntry::make('redeemed_vouchers')
                                    ->label('Redeemed')
                                    ->getStateUsing(fn (): int => $redeemedCount)
                                    ->numeric()
                                    ->color('gray')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-check-circle')
                                    ->helperText('Fulfilled'),
                            ]),
                    ]),
                Section::make('Customer Vouchers')
                    ->description('All vouchers owned by this customer. Click on a voucher ID to view details.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_all_vouchers')
                            ->label('View All in Vouchers List')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (): string => VoucherResource::getUrl('index', [
                                'tableFilters' => [
                                    'customer' => ['customer_id' => $record->id],
                                ],
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        $this->getVoucherList(),
                    ]),
                $this->getCaseEntitlementsSection($record, $totalCaseEntitlements, $intactCasesCount, $brokenCasesCount),
            ]);
    }

    /**
     * Tab 3: Shipping Orders - Customer's shipping orders with filters and summary.
     */
    protected function getShippingOrdersTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;

        $totalSOs = $record->shippingOrders()->count();
        $pendingSOs = $record->shippingOrders()->whereIn('status', [
            ShippingOrderStatus::Draft->value,
            ShippingOrderStatus::Planned->value,
            ShippingOrderStatus::Picking->value,
        ])->count();
        $shippedSOs = $record->shippingOrders()->whereIn('status', [
            ShippingOrderStatus::Shipped->value,
            ShippingOrderStatus::Completed->value,
        ])->count();

        return Tab::make('Shipping Orders')
            ->icon('heroicon-o-truck')
            ->badge($totalSOs > 0 ? (string) $totalSOs : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Shipping Order Summary')
                    ->description('Overview of customer\'s shipping orders')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_sos')
                                    ->label('Total SOs')
                                    ->getStateUsing(fn (): int => $totalSOs)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-document-text'),
                                TextEntry::make('pending_sos')
                                    ->label('Pending')
                                    ->getStateUsing(fn (): int => $pendingSOs)
                                    ->numeric()
                                    ->color('warning')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-clock')
                                    ->helperText('Draft, Planned, Picking'),
                                TextEntry::make('shipped_sos')
                                    ->label('Shipped/Completed')
                                    ->getStateUsing(fn (): int => $shippedSOs)
                                    ->numeric()
                                    ->color('success')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-check-circle')
                                    ->helperText('Shipped or Completed'),
                            ]),
                    ]),
                Section::make('Customer Shipping Orders')
                    ->description('All shipping orders for this customer. Click on an SO ID to view details.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_all_sos')
                            ->label('View All in Shipping Orders List')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (): string => ShippingOrderResource::getUrl('index', [
                                'tableFilters' => [
                                    'customer_id' => ['value' => $record->id],
                                ],
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        $this->getShippingOrderList(),
                    ]),
                $this->getShipmentsSection($record),
            ]);
    }

    /**
     * Get the Shipment History section for the Shipping Orders tab.
     */
    protected function getShipmentsSection(Customer $record): Section
    {
        $totalShipments = $record->shipments()->count();

        return Section::make('Shipment History')
            ->description('All shipments for this customer. Click on a shipment ID to view details.')
            ->icon('heroicon-o-paper-airplane')
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('view_all_shipments')
                    ->label('View All in Shipments List')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): string => ShipmentResource::getUrl('index'))
                    ->openUrlInNewTab()
                    ->visible($totalShipments > 0),
            ])
            ->collapsed($totalShipments === 0)
            ->collapsible()
            ->schema([
                $this->getShipmentList(),
            ]);
    }

    /**
     * Get the shipping order list component as a RepeatableEntry.
     */
    protected function getShippingOrderList(): RepeatableEntry
    {
        return RepeatableEntry::make('shippingOrders')
            ->label('')
            ->schema([
                Grid::make(5)
                    ->schema([
                        TextEntry::make('id')
                            ->label('SO ID')
                            ->copyable()
                            ->copyMessage('SO ID copied')
                            ->url(fn (ShippingOrder $so): string => ShippingOrderResource::getUrl('view', ['record' => $so]))
                            ->color('primary')
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn (string $state): string => \Illuminate\Support\Str::limit($state, 8, '...')),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ShippingOrderStatus $state): string => $state->label())
                            ->color(fn (ShippingOrderStatus $state): string => $state->color())
                            ->icon(fn (ShippingOrderStatus $state): string => $state->icon()),
                        TextEntry::make('lines_count')
                            ->label('Vouchers')
                            ->getStateUsing(fn (ShippingOrder $so): int => $so->lines()->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('shipped_at')
                            ->label('Shipped')
                            ->dateTime()
                            ->placeholder('Not shipped'),
                    ]),
            ])
            ->columns(1)
            ->placeholder('No shipping orders found for this customer.');
    }

    /**
     * Get the shipment list component as a RepeatableEntry.
     */
    protected function getShipmentList(): RepeatableEntry
    {
        return RepeatableEntry::make('shipments')
            ->label('')
            ->schema([
                Grid::make(5)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Shipment ID')
                            ->copyable()
                            ->copyMessage('Shipment ID copied')
                            ->url(fn (Shipment $shipment): string => ShipmentResource::getUrl('view', ['record' => $shipment]))
                            ->color('primary')
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn (string $state): string => \Illuminate\Support\Str::limit($state, 8, '...')),
                        TextEntry::make('carrier')
                            ->label('Carrier')
                            ->placeholder('N/A'),
                        TextEntry::make('tracking_number')
                            ->label('Tracking')
                            ->copyable()
                            ->copyMessage('Tracking number copied')
                            ->placeholder('N/A'),
                        TextEntry::make('shipped_at')
                            ->label('Shipped')
                            ->dateTime()
                            ->placeholder('Not shipped'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (ShipmentStatus $state): string => $state->label())
                            ->color(fn (ShipmentStatus $state): string => $state->color())
                            ->icon(fn (ShipmentStatus $state): string => $state->icon()),
                    ]),
            ])
            ->columns(1)
            ->placeholder('No shipments found for this customer.');
    }

    /**
     * Get the Case Entitlements section for the Vouchers tab.
     */
    protected function getCaseEntitlementsSection(Customer $record, int $totalCaseEntitlements, int $intactCasesCount, int $brokenCasesCount): Section
    {
        return Section::make('Case Entitlements')
            ->description('Cases (multi-bottle packages) owned by this customer. Click on an entitlement ID to view details.')
            ->icon('heroicon-o-cube')
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('view_all_cases')
                    ->label('View All in Cases List')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): string => CaseEntitlementResource::getUrl('index', [
                        'tableFilters' => [
                            'customer' => ['customer_id' => $record->id],
                        ],
                    ]))
                    ->openUrlInNewTab()
                    ->visible($totalCaseEntitlements > 0),
            ])
            ->collapsed($totalCaseEntitlements === 0)
            ->collapsible()
            ->schema([
                // Case Entitlements Summary
                Grid::make(3)
                    ->schema([
                        TextEntry::make('total_cases')
                            ->label('Total Cases')
                            ->getStateUsing(fn (): int => $totalCaseEntitlements)
                            ->numeric()
                            ->weight(FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon('heroicon-o-cube'),
                        TextEntry::make('intact_cases')
                            ->label('Intact')
                            ->getStateUsing(fn (): int => $intactCasesCount)
                            ->numeric()
                            ->color('success')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon('heroicon-o-check-circle')
                            ->helperText('Complete and unmodified'),
                        TextEntry::make('broken_cases')
                            ->label('Broken')
                            ->getStateUsing(fn (): int => $brokenCasesCount)
                            ->numeric()
                            ->color('danger')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->icon('heroicon-o-x-circle')
                            ->helperText('Individual vouchers modified'),
                    ]),

                // Case Entitlements List
                RepeatableEntry::make('caseEntitlements')
                    ->label('')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Entitlement ID')
                                    ->copyable()
                                    ->copyMessage('Entitlement ID copied')
                                    ->url(fn (CaseEntitlement $caseEntitlement): string => CaseEntitlementResource::getUrl('view', ['record' => $caseEntitlement]))
                                    ->color('primary')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('sellable_sku')
                                    ->label('Sellable SKU')
                                    ->getStateUsing(fn (CaseEntitlement $caseEntitlement): string => $caseEntitlement->sellableSku->sku_code ?? 'N/A'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (CaseEntitlementStatus $state): string => $state->label())
                                    ->color(fn (CaseEntitlementStatus $state): string => $state->color())
                                    ->icon(fn (CaseEntitlementStatus $state): string => $state->icon()),
                                TextEntry::make('vouchers_count')
                                    ->label('Vouchers')
                                    ->getStateUsing(fn (CaseEntitlement $caseEntitlement): int => $caseEntitlement->vouchers()->count())
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ])
                    ->columns(1)
                    ->placeholder('No case entitlements found for this customer.'),
            ]);
    }

    /**
     * Get the voucher list component as a RepeatableEntry.
     */
    protected function getVoucherList(): RepeatableEntry
    {
        return RepeatableEntry::make('vouchers')
            ->label('')
            ->schema([
                Grid::make(6)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Voucher ID')
                            ->copyable()
                            ->copyMessage('Voucher ID copied')
                            ->url(fn (Voucher $voucher): string => VoucherResource::getUrl('view', ['record' => $voucher]))
                            ->color('primary')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('bottle_sku')
                            ->label('Bottle SKU')
                            ->getStateUsing(fn (Voucher $voucher): string => $voucher->getBottleSkuLabel()),
                        TextEntry::make('lifecycle_state')
                            ->label('State')
                            ->badge()
                            ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                            ->color(fn (VoucherLifecycleState $state): string => $state->color())
                            ->icon(fn (VoucherLifecycleState $state): string => $state->icon()),
                        TextEntry::make('flags')
                            ->label('Flags')
                            ->getStateUsing(function (Voucher $voucher): string {
                                $flags = [];
                                if ($voucher->suspended) {
                                    $flags[] = 'Suspended';
                                }
                                if ($voucher->tradable) {
                                    $flags[] = 'Tradable';
                                }
                                if ($voucher->giftable) {
                                    $flags[] = 'Giftable';
                                }

                                return count($flags) > 0 ? implode(', ', $flags) : '—';
                            })
                            ->badge()
                            ->color(fn (Voucher $voucher): string => $voucher->suspended ? 'danger' : 'gray'),
                        TextEntry::make('allocation_id')
                            ->label('Allocation')
                            ->url(fn (Voucher $voucher): string => route('filament.admin.resources.allocations.view', ['record' => $voucher->allocation_id]))
                            ->color('primary'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),
            ])
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
            ])->label('More')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }
}
