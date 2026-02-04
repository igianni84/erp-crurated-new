<?php

namespace App\Filament\Resources\Customer\CustomerResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Customer\AccountStatus;
use App\Enums\Customer\ChannelScope;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
        /** @var Customer $record */
        $record = $this->record;

        return "Customer: {$record->getName()}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Customer $record */
        $record = $this->record;

        $typeBadge = $record->customer_type->label();
        $statusBadge = $record->status->label();

        return "{$typeBadge} - {$statusBadge}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Customer Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getMembershipTab(),
                        $this->getAccountsTab(),
                        $this->getAddressesTab(),
                        $this->getEligibilityTab(),
                        $this->getPaymentCreditTab(),
                        $this->getClubsTab(),
                        $this->getOperationalBlocksTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Customer identity, status, membership summary, active blocks summary.
     */
    protected function getOverviewTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;

        $accountsCount = $record->accounts()->count();
        $vouchersCount = $record->vouchers()->count();

        return Tab::make('Overview')
            ->icon('heroicon-o-user')
            ->schema([
                Section::make('Customer Identity')
                    ->description('Core customer information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Customer ID')
                                        ->copyable()
                                        ->copyMessage('Customer ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('display_name')
                                        ->label('Name')
                                        ->getStateUsing(fn (Customer $customer): string => $customer->getName())
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large),
                                    TextEntry::make('email')
                                        ->label('Email')
                                        ->icon('heroicon-o-envelope')
                                        ->copyable()
                                        ->placeholder('Not provided'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('customer_type')
                                        ->label('Customer Type')
                                        ->badge()
                                        ->formatStateUsing(fn (?CustomerType $state): string => $state?->label() ?? 'Not Set')
                                        ->color(fn (?CustomerType $state): string => $state?->color() ?? 'gray')
                                        ->icon(fn (?CustomerType $state): ?string => $state?->icon()),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (CustomerStatus $state): string => $state->label())
                                        ->color(fn (CustomerStatus $state): string => $state->color())
                                        ->icon(fn (CustomerStatus $state): string => $state->icon()),
                                    TextEntry::make('party.legal_name')
                                        ->label('Party Legal Name')
                                        ->placeholder('Not linked to Party')
                                        ->icon('heroicon-o-building-office'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Customer Since')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                    TextEntry::make('party.tax_id')
                                        ->label('Tax ID')
                                        ->placeholder('Not available')
                                        ->copyable(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Quick Summary')
                    ->description('At-a-glance metrics for this customer')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('accounts_count')
                                    ->label('Accounts')
                                    ->getStateUsing(fn (): int => $accountsCount)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-rectangle-stack'),
                                TextEntry::make('vouchers_count')
                                    ->label('Vouchers')
                                    ->getStateUsing(fn (): int => $vouchersCount)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-ticket'),
                                TextEntry::make('membership_tier')
                                    ->label('Membership Tier')
                                    ->getStateUsing(fn (): string => 'Not Set')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-star')
                                    ->helperText('Coming in US-011'),
                                TextEntry::make('active_blocks')
                                    ->label('Active Blocks')
                                    ->getStateUsing(fn (): string => 'None')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-shield-check')
                                    ->helperText('Coming in US-027'),
                            ]),
                    ]),
                $this->getVouchersSummarySection(),
            ]);
    }

    /**
     * Vouchers summary section for the Overview tab.
     */
    protected function getVouchersSummarySection(): Section
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

        return Section::make('Assets Summary')
            ->description('Vouchers and case entitlements owned by this customer')
            ->icon('heroicon-o-ticket')
            ->collapsible()
            ->collapsed($totalVouchers === 0 && $totalCaseEntitlements === 0)
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('view_all_vouchers')
                    ->label('View All Vouchers')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): string => VoucherResource::getUrl('index', [
                        'tableFilters' => [
                            'customer' => ['customer_id' => $record->id],
                        ],
                    ]))
                    ->openUrlInNewTab()
                    ->visible($totalVouchers > 0),
            ])
            ->schema([
                Grid::make(6)
                    ->schema([
                        TextEntry::make('total_vouchers')
                            ->label('Total Vouchers')
                            ->getStateUsing(fn (): int => $totalVouchers)
                            ->numeric()
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-ticket'),
                        TextEntry::make('issued_vouchers')
                            ->label('Issued')
                            ->getStateUsing(fn (): int => $issuedCount)
                            ->numeric()
                            ->color('success')
                            ->icon('heroicon-o-check-badge'),
                        TextEntry::make('locked_vouchers')
                            ->label('Locked')
                            ->getStateUsing(fn (): int => $lockedCount)
                            ->numeric()
                            ->color('warning')
                            ->icon('heroicon-o-lock-closed'),
                        TextEntry::make('redeemed_vouchers')
                            ->label('Redeemed')
                            ->getStateUsing(fn (): int => $redeemedCount)
                            ->numeric()
                            ->color('gray')
                            ->icon('heroicon-o-check-circle'),
                        TextEntry::make('total_cases')
                            ->label('Cases')
                            ->getStateUsing(fn (): int => $totalCaseEntitlements)
                            ->numeric()
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-cube'),
                        TextEntry::make('intact_cases')
                            ->label('Intact/Broken')
                            ->getStateUsing(fn (): string => "{$intactCasesCount}/{$brokenCasesCount}")
                            ->color('gray')
                            ->icon('heroicon-o-archive-box'),
                    ]),
            ]);
    }

    /**
     * Tab 2: Membership - Tier, lifecycle, decision history (placeholder).
     */
    protected function getMembershipTab(): Tab
    {
        return Tab::make('Membership')
            ->icon('heroicon-o-star')
            ->schema([
                Section::make('Membership Status')
                    ->description('Current membership tier and status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('membership_tier_placeholder')
                                    ->label('Tier')
                                    ->getStateUsing(fn (): string => 'Not Set')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-star'),
                                TextEntry::make('membership_status_placeholder')
                                    ->label('Status')
                                    ->getStateUsing(fn (): string => 'Not Applied')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-clock'),
                                TextEntry::make('membership_effective_placeholder')
                                    ->label('Effective From')
                                    ->getStateUsing(fn (): string => '—')
                                    ->placeholder('Not applicable'),
                            ]),
                    ]),
                Section::make('Membership Lifecycle')
                    ->description('Timeline of membership decisions and transitions')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('membership_lifecycle_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Membership lifecycle tracking will be implemented in US-011 through US-014.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
                Section::make('Decision History')
                    ->description('Record of membership applications and decisions')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('membership_decisions_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No membership decisions recorded. Decision history will be implemented in US-013.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 3: Accounts - List of accounts with status (placeholder).
     */
    protected function getAccountsTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;
        $accountsCount = $record->accounts()->count();

        return Tab::make('Accounts')
            ->icon('heroicon-o-rectangle-stack')
            ->badge($accountsCount > 0 ? (string) $accountsCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Customer Accounts')
                    ->description('Operational contexts for this customer. Each account can have its own channel scope and restrictions.')
                    ->schema([
                        RepeatableEntry::make('accounts')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Account ID')
                                            ->copyable()
                                            ->copyMessage('Account ID copied')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('name')
                                            ->label('Name')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('channel_scope')
                                            ->label('Channel Scope')
                                            ->badge()
                                            ->formatStateUsing(fn (ChannelScope $state): string => $state->label())
                                            ->color(fn (ChannelScope $state): string => $state->color())
                                            ->icon(fn (ChannelScope $state): string => $state->icon()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (AccountStatus $state): string => $state->label())
                                            ->color(fn (AccountStatus $state): string => $state->color())
                                            ->icon(fn (AccountStatus $state): string => $state->icon()),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No accounts found for this customer. Account management will be implemented in US-009.'),
                    ]),
                Section::make('Account Management')
                    ->description('Create and manage accounts')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('account_management_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Full account management (create, edit, suspend, delete) will be implemented in US-009.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 4: Addresses - Billing and shipping addresses (placeholder).
     */
    protected function getAddressesTab(): Tab
    {
        return Tab::make('Addresses')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Section::make('Billing Addresses')
                    ->description('Addresses used for invoicing')
                    ->schema([
                        TextEntry::make('billing_addresses_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No billing addresses configured. Address management will be implemented in US-010.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
                Section::make('Shipping Addresses')
                    ->description('Addresses used for deliveries')
                    ->schema([
                        TextEntry::make('shipping_addresses_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No shipping addresses configured. Address management will be implemented in US-010.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 5: Eligibility - Channel eligibility computed read-only (placeholder).
     */
    protected function getEligibilityTab(): Tab
    {
        return Tab::make('Eligibility')
            ->icon('heroicon-o-check-badge')
            ->schema([
                Section::make('Channel Eligibility')
                    ->description('Computed eligibility for each sales channel based on membership, blocks, and permissions')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('b2c_eligibility')
                                        ->label('B2C Channel')
                                        ->getStateUsing(fn (): string => 'Unknown')
                                        ->badge()
                                        ->color('gray')
                                        ->icon('heroicon-o-question-mark-circle'),
                                    TextEntry::make('b2c_reasons')
                                        ->label('Factors')
                                        ->getStateUsing(fn (): string => 'Eligibility engine not yet implemented')
                                        ->color('gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('b2b_eligibility')
                                        ->label('B2B Channel')
                                        ->getStateUsing(fn (): string => 'Unknown')
                                        ->badge()
                                        ->color('gray')
                                        ->icon('heroicon-o-question-mark-circle'),
                                    TextEntry::make('b2b_reasons')
                                        ->label('Factors')
                                        ->getStateUsing(fn (): string => 'Eligibility engine not yet implemented')
                                        ->color('gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('club_eligibility')
                                        ->label('Club Channel')
                                        ->getStateUsing(fn (): string => 'Unknown')
                                        ->badge()
                                        ->color('gray')
                                        ->icon('heroicon-o-question-mark-circle'),
                                    TextEntry::make('club_reasons')
                                        ->label('Factors')
                                        ->getStateUsing(fn (): string => 'Eligibility engine not yet implemented')
                                        ->color('gray'),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Eligibility Information')
                    ->description('How eligibility is computed')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('eligibility_info_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Eligibility is computed based on: membership tier & status, operational blocks, payment permissions, and club affiliations. The eligibility engine will be implemented in US-015 through US-017.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 6: Payment & Credit - Payment permissions, credit limits (placeholder).
     */
    protected function getPaymentCreditTab(): Tab
    {
        return Tab::make('Payment & Credit')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Section::make('Payment Permissions')
                    ->description('Allowed payment methods for this customer')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('card_allowed_placeholder')
                                    ->label('Card Payments')
                                    ->getStateUsing(fn (): string => 'Allowed')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-credit-card')
                                    ->helperText('Default: enabled'),
                                TextEntry::make('bank_transfer_placeholder')
                                    ->label('Bank Transfer')
                                    ->getStateUsing(fn (): string => 'Not Allowed')
                                    ->badge()
                                    ->color('danger')
                                    ->icon('heroicon-o-building-library')
                                    ->helperText('Requires Finance approval'),
                                TextEntry::make('credit_limit_placeholder')
                                    ->label('Credit Limit')
                                    ->getStateUsing(fn (): string => 'No Credit')
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-banknotes')
                                    ->helperText('No credit line configured'),
                            ]),
                    ]),
                Section::make('Payment History')
                    ->description('Changes to payment permissions')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('payment_history_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Payment permissions management will be implemented in US-018 through US-020.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 7: Clubs - Club affiliations (placeholder).
     */
    protected function getClubsTab(): Tab
    {
        return Tab::make('Clubs')
            ->icon('heroicon-o-user-group')
            ->schema([
                Section::make('Club Affiliations')
                    ->description('Club memberships and partnerships for this customer')
                    ->schema([
                        TextEntry::make('clubs_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No club affiliations found. Club management will be implemented in US-021 through US-024.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
                Section::make('Club Eligibility Impact')
                    ->description('How club affiliations affect channel eligibility')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('club_eligibility_impact_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Active club affiliations unlock access to the Club channel. Each club may provide additional benefits and exclusive access.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 8: Operational Blocks - Active and historical blocks (placeholder).
     */
    protected function getOperationalBlocksTab(): Tab
    {
        return Tab::make('Operational Blocks')
            ->icon('heroicon-o-shield-exclamation')
            ->schema([
                Section::make('Active Blocks')
                    ->description('Current operational restrictions on this customer')
                    ->schema([
                        TextEntry::make('active_blocks_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No active blocks. This customer can operate normally.')
                            ->icon('heroicon-o-shield-check')
                            ->color('success'),
                    ]),
                Section::make('Block Types')
                    ->description('Available block types that can be applied')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('block_type_payment')
                                    ->label('Payment')
                                    ->getStateUsing(fn (): string => 'Blocks all payment transactions')
                                    ->icon('heroicon-o-credit-card')
                                    ->color('gray'),
                                TextEntry::make('block_type_shipment')
                                    ->label('Shipment')
                                    ->getStateUsing(fn (): string => 'Blocks all shipments')
                                    ->icon('heroicon-o-truck')
                                    ->color('gray'),
                                TextEntry::make('block_type_redemption')
                                    ->label('Redemption')
                                    ->getStateUsing(fn (): string => 'Blocks voucher redemption')
                                    ->icon('heroicon-o-ticket')
                                    ->color('gray'),
                                TextEntry::make('block_type_trading')
                                    ->label('Trading')
                                    ->getStateUsing(fn (): string => 'Blocks voucher trading')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->color('gray'),
                                TextEntry::make('block_type_compliance')
                                    ->label('Compliance')
                                    ->getStateUsing(fn (): string => 'General compliance block')
                                    ->icon('heroicon-o-scale')
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make('Block History')
                    ->description('Previously applied and removed blocks')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('block_history_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No historical blocks found. Block management will be implemented in US-027 through US-030.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 9: Audit - Complete timeline (placeholder).
     */
    protected function getAuditTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-document-text')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Trail')
                    ->description(fn (): string => $this->getAuditFilterDescription())
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
                                        AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
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
                            ->getStateUsing(function (Customer $record): string {
                                $query = $record->auditLogs()->orderBy('created_at', 'desc');

                                if ($this->auditEventFilter) {
                                    $query->where('event', $this->auditEventFilter);
                                }

                                if ($this->auditDateFrom) {
                                    $query->whereDate('created_at', '>=', $this->auditDateFrom);
                                }

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
                                    $timestamp = $log->created_at?->format('M d, Y H:i:s') ?? 'Unknown';
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
                    ]),
                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this customer for compliance and traceability purposes.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable audit trail of all changes to this customer'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
                AuditLog::EVENT_DELETED => 'Deleted',
                AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
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
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];

        if ($log->event === AuditLog::EVENT_CREATED) {
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

    protected function getHeaderActions(): array
    {
        /** @var Customer $record */
        $record = $this->record;

        return [
            Actions\EditAction::make(),
            $this->getSuspendAction(),
            $this->getActivateAction(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
            ])->label('More')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }

    /**
     * Suspend customer action.
     */
    protected function getSuspendAction(): Actions\Action
    {
        /** @var Customer $record */
        $record = $this->record;

        return Actions\Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Suspend Customer')
            ->modalDescription('Are you sure you want to suspend this customer? They will not be able to perform any operations while suspended.')
            ->modalSubmitActionLabel('Suspend')
            ->action(function () use ($record): void {
                $record->update(['status' => CustomerStatus::Suspended]);

                \Filament\Notifications\Notification::make()
                    ->title('Customer suspended')
                    ->body('The customer has been suspended.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status === CustomerStatus::Active);
    }

    /**
     * Activate customer action.
     */
    protected function getActivateAction(): Actions\Action
    {
        /** @var Customer $record */
        $record = $this->record;

        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Activate Customer')
            ->modalDescription('Are you sure you want to activate this customer? They will be able to perform operations.')
            ->modalSubmitActionLabel('Activate')
            ->action(function () use ($record): void {
                $record->update(['status' => CustomerStatus::Active]);

                \Filament\Notifications\Notification::make()
                    ->title('Customer activated')
                    ->body('The customer has been activated.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => in_array($record->status, [CustomerStatus::Prospect, CustomerStatus::Suspended], true));
    }
}
