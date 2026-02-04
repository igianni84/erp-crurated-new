<?php

namespace App\Filament\Resources\Customer\CustomerResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Customer\AccountStatus;
use App\Enums\Customer\AddressType;
use App\Enums\Customer\ChannelScope;
use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\MembershipStatus;
use App\Enums\Customer\MembershipTier;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Models\AuditLog;
use App\Models\Customer\Account;
use App\Models\Customer\Address;
use App\Models\Customer\Customer;
use App\Models\Customer\Membership;
use App\Models\Customer\PaymentPermission;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                                    ->getStateUsing(fn (Customer $customer): string => $customer->getMembershipTier()?->label() ?? 'Not Set')
                                    ->badge()
                                    ->color(fn (Customer $customer): string => $customer->getMembershipTier()?->color() ?? 'gray')
                                    ->icon(fn (Customer $customer): string => $customer->getMembershipTier()?->icon() ?? 'heroicon-o-star'),
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
     * Tab 2: Membership - Tier, lifecycle, decision history with workflow actions.
     */
    protected function getMembershipTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;
        $membership = $record->membership;
        $membershipsCount = $record->memberships()->count();

        return Tab::make('Membership')
            ->icon('heroicon-o-star')
            ->badge($membership !== null ? $membership->status->label() : null)
            ->badgeColor($membership !== null ? $membership->status->color() : 'gray')
            ->schema([
                Section::make('Current Membership')
                    ->description('Current membership tier, status, and effective dates')
                    ->headerActions($this->getMembershipActions())
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('membership.tier')
                                    ->label('Tier')
                                    ->badge()
                                    ->formatStateUsing(fn (?MembershipTier $state): string => $state?->label() ?? 'Not Set')
                                    ->color(fn (?MembershipTier $state): string => $state?->color() ?? 'gray')
                                    ->icon(fn (?MembershipTier $state): ?string => $state?->icon())
                                    ->helperText(fn (?MembershipTier $state): ?string => $state?->description()),
                                TextEntry::make('membership.status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?MembershipStatus $state): string => $state?->label() ?? 'No Membership')
                                    ->color(fn (?MembershipStatus $state): string => $state?->color() ?? 'gray')
                                    ->icon(fn (?MembershipStatus $state): ?string => $state?->icon()),
                                TextEntry::make('membership.effective_from')
                                    ->label('Effective From')
                                    ->dateTime()
                                    ->placeholder('Not set'),
                                TextEntry::make('membership.effective_to')
                                    ->label('Effective Until')
                                    ->dateTime()
                                    ->placeholder('No expiry'),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('membership.decision_notes')
                                    ->label('Latest Decision Notes')
                                    ->placeholder('No decision notes')
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (): bool => $membership !== null && $membership->decision_notes !== null),
                    ]),
                Section::make('Membership Lifecycle')
                    ->description('Visual timeline of membership status transitions')
                    ->collapsible()
                    ->schema([
                        $this->getMembershipLifecycleTimeline(),
                    ]),
                Section::make('Decision History'.($membershipsCount > 1 ? " ({$membershipsCount})" : ''))
                    ->description('Complete record of all membership decisions and status changes')
                    ->collapsible()
                    ->collapsed($membershipsCount <= 1)
                    ->schema([
                        $this->getMembershipDecisionHistory(),
                    ]),
            ]);
    }

    /**
     * Get the membership workflow actions based on current state.
     *
     * @return array<\Filament\Infolists\Components\Actions\Action>
     */
    protected function getMembershipActions(): array
    {
        /** @var Customer $record */
        $record = $this->record;
        $membership = $record->membership;

        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $canApproveMemberships = $user?->canApproveMemberships() ?? false;

        $actions = [];

        // Apply for Membership - only if no membership exists or previous was rejected
        if ($membership === null || $membership->status === MembershipStatus::Rejected) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('apply_membership')
                ->label('Apply for Membership')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    Select::make('tier')
                        ->label('Membership Tier')
                        ->options(collect(MembershipTier::cases())
                            ->mapWithKeys(fn (MembershipTier $tier): array => [
                                $tier->value => $tier->label().' - '.$tier->description(),
                            ])
                            ->toArray())
                        ->required()
                        ->native(false)
                        ->helperText('Select the tier to apply for'),
                ])
                ->modalHeading('Apply for Membership')
                ->modalDescription('Start a membership application for this customer. The application will be created in "Applied" status.')
                ->modalSubmitActionLabel('Submit Application')
                ->action(function (array $data) use ($record): void {
                    $record->memberships()->create([
                        'tier' => $data['tier'],
                        'status' => MembershipStatus::Applied,
                    ]);

                    Notification::make()
                        ->title('Membership application submitted')
                        ->body('The membership application has been created successfully.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        // Submit for Review - only if status is Applied and transition is valid
        if ($membership !== null && $membership->canTransitionTo(MembershipStatus::UnderReview)) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('submit_review')
                ->label('Submit for Review')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Submit for Review')
                ->modalDescription('Submit this membership application for review. A manager will need to approve or reject it.')
                ->modalSubmitActionLabel('Submit for Review')
                ->action(function () use ($membership): void {
                    try {
                        $membership->submitForReview();

                        Notification::make()
                            ->title('Submitted for review')
                            ->body('The membership application has been submitted for review.')
                            ->success()
                            ->send();
                    } catch (\App\Exceptions\InvalidMembershipTransitionException $e) {
                        Notification::make()
                            ->title('Transition failed')
                            ->body($e->getUserMessage())
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        // Approve - only if transition is valid and user has Manager role or higher
        if ($membership !== null && $membership->canTransitionTo(MembershipStatus::Approved) && $membership->status === MembershipStatus::UnderReview) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('approve_membership')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->disabled(! $canApproveMemberships)
                ->tooltip(! $canApproveMemberships ? 'Manager role or higher required to approve memberships' : null)
                ->form([
                    Textarea::make('decision_notes')
                        ->label('Decision Notes (Optional)')
                        ->placeholder('Add any notes about this approval...')
                        ->rows(3),
                ])
                ->modalHeading('Approve Membership')
                ->modalDescription('Approve this membership application. The effective date will be set to now.')
                ->modalSubmitActionLabel('Approve Membership')
                ->action(function (array $data) use ($membership, $canApproveMemberships): void {
                    if (! $canApproveMemberships) {
                        Notification::make()
                            ->title('Unauthorized')
                            ->body('You must have Manager role or higher to approve memberships.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $membership->approve($data['decision_notes'] ?? null);

                        Notification::make()
                            ->title('Membership approved')
                            ->body('The membership has been approved and is now active.')
                            ->success()
                            ->send();
                    } catch (\App\Exceptions\InvalidMembershipTransitionException $e) {
                        Notification::make()
                            ->title('Transition failed')
                            ->body($e->getUserMessage())
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        // Reject - only if transition is valid and user has Manager role or higher
        if ($membership !== null && $membership->canTransitionTo(MembershipStatus::Rejected)) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('reject_membership')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->disabled(! $canApproveMemberships)
                ->tooltip(! $canApproveMemberships ? 'Manager role or higher required to reject memberships' : null)
                ->form([
                    Textarea::make('decision_notes')
                        ->label('Rejection Reason')
                        ->placeholder('Explain why this membership application is being rejected...')
                        ->required()
                        ->rows(3)
                        ->helperText('Required: Please provide a clear reason for rejection.'),
                ])
                ->modalHeading('Reject Membership')
                ->modalDescription('Reject this membership application. A reason must be provided.')
                ->modalSubmitActionLabel('Reject Membership')
                ->action(function (array $data) use ($membership, $canApproveMemberships): void {
                    if (! $canApproveMemberships) {
                        Notification::make()
                            ->title('Unauthorized')
                            ->body('You must have Manager role or higher to reject memberships.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $membership->reject($data['decision_notes']);

                        Notification::make()
                            ->title('Membership rejected')
                            ->body('The membership application has been rejected.')
                            ->warning()
                            ->send();
                    } catch (\App\Exceptions\InvalidMembershipTransitionException $e) {
                        Notification::make()
                            ->title('Transition failed')
                            ->body($e->getUserMessage())
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        // Suspend - only if transition is valid
        if ($membership !== null && $membership->canTransitionTo(MembershipStatus::Suspended)) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('suspend_membership')
                ->label('Suspend')
                ->icon('heroicon-o-pause-circle')
                ->color('danger')
                ->form([
                    Textarea::make('decision_notes')
                        ->label('Suspension Reason')
                        ->placeholder('Explain why this membership is being suspended...')
                        ->required()
                        ->rows(3)
                        ->helperText('Required: Please provide a clear reason for suspension.'),
                ])
                ->modalHeading('Suspend Membership')
                ->modalDescription('Suspend this active membership. The customer will lose access to membership benefits. A reason must be provided.')
                ->modalSubmitActionLabel('Suspend Membership')
                ->action(function (array $data) use ($membership): void {
                    try {
                        $membership->suspend($data['decision_notes']);

                        Notification::make()
                            ->title('Membership suspended')
                            ->body('The membership has been suspended.')
                            ->warning()
                            ->send();
                    } catch (\App\Exceptions\InvalidMembershipTransitionException $e) {
                        Notification::make()
                            ->title('Transition failed')
                            ->body($e->getUserMessage())
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        // Reactivate - only if transition is valid (Suspended -> Approved)
        if ($membership !== null && $membership->status === MembershipStatus::Suspended && $membership->canTransitionTo(MembershipStatus::Approved)) {
            $actions[] = \Filament\Infolists\Components\Actions\Action::make('reactivate_membership')
                ->label('Reactivate')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->form([
                    Textarea::make('decision_notes')
                        ->label('Reactivation Notes (Optional)')
                        ->placeholder('Add any notes about this reactivation...')
                        ->rows(3),
                ])
                ->modalHeading('Reactivate Membership')
                ->modalDescription('Reactivate this suspended membership. The customer will regain access to membership benefits.')
                ->modalSubmitActionLabel('Reactivate Membership')
                ->action(function (array $data) use ($membership): void {
                    try {
                        $membership->reactivate($data['decision_notes'] ?? null);

                        Notification::make()
                            ->title('Membership reactivated')
                            ->body('The membership has been reactivated and is now active.')
                            ->success()
                            ->send();
                    } catch (\App\Exceptions\InvalidMembershipTransitionException $e) {
                        Notification::make()
                            ->title('Transition failed')
                            ->body($e->getUserMessage())
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['membership', 'memberships']);
                });
        }

        return $actions;
    }

    /**
     * Get the membership lifecycle timeline visualization.
     */
    protected function getMembershipLifecycleTimeline(): TextEntry
    {
        return TextEntry::make('membership_lifecycle')
            ->label('')
            ->getStateUsing(function (Customer $record): string {
                $membership = $record->membership;

                if ($membership === null) {
                    return '<div class="text-gray-500 text-sm py-4">No membership exists. Use "Apply for Membership" to start the process.</div>';
                }

                // Define the workflow stages
                $stages = [
                    ['status' => MembershipStatus::Applied, 'label' => 'Applied'],
                    ['status' => MembershipStatus::UnderReview, 'label' => 'Under Review'],
                    ['status' => MembershipStatus::Approved, 'label' => 'Approved'],
                ];

                $currentStatus = $membership->status;
                $html = '<div class="flex items-center justify-between py-4">';

                foreach ($stages as $index => $stage) {
                    $isCompleted = $this->isStageCompleted($currentStatus, $stage['status']);
                    $isCurrent = $currentStatus === $stage['status'];
                    $isRejected = $currentStatus === MembershipStatus::Rejected && $stage['status'] === MembershipStatus::UnderReview;
                    $isSuspended = $currentStatus === MembershipStatus::Suspended && $stage['status'] === MembershipStatus::Approved;

                    // Determine colors
                    if ($isCurrent && $currentStatus === MembershipStatus::Rejected) {
                        $bgColor = 'bg-red-500';
                        $textColor = 'text-white';
                        $label = 'Rejected';
                    } elseif ($isCurrent && $currentStatus === MembershipStatus::Suspended) {
                        $bgColor = 'bg-gray-500';
                        $textColor = 'text-white';
                        $label = 'Suspended';
                    } elseif ($isCompleted || $isCurrent) {
                        $bgColor = match ($stage['status']) {
                            MembershipStatus::Applied => 'bg-blue-500',
                            MembershipStatus::UnderReview => 'bg-yellow-500',
                            MembershipStatus::Approved => 'bg-green-500',
                        };
                        $textColor = 'text-white';
                        $label = $stage['label'];
                    } else {
                        $bgColor = 'bg-gray-200 dark:bg-gray-700';
                        $textColor = 'text-gray-500 dark:text-gray-400';
                        $label = $stage['label'];
                    }

                    $html .= '<div class="flex flex-col items-center">';
                    $html .= "<div class=\"w-10 h-10 rounded-full {$bgColor} {$textColor} flex items-center justify-center font-semibold text-sm\">";
                    $html .= ($index + 1);
                    $html .= '</div>';
                    $html .= "<span class=\"text-xs mt-1 {$textColor} font-medium\">{$label}</span>";
                    $html .= '</div>';

                    // Add connector line between stages
                    if ($index < count($stages) - 1) {
                        $lineColor = $isCompleted ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700';
                        $html .= "<div class=\"flex-1 h-1 mx-2 {$lineColor} rounded\"></div>";
                    }
                }

                $html .= '</div>';

                // Add current status description
                $statusDescription = match ($currentStatus) {
                    MembershipStatus::Applied => 'Membership application has been submitted. Waiting to be submitted for review.',
                    MembershipStatus::UnderReview => 'Membership application is under review. A manager will approve or reject it.',
                    MembershipStatus::Approved => 'Membership is active. The customer has full membership benefits.',
                    MembershipStatus::Rejected => 'Membership application was rejected. A new application can be submitted.',
                    MembershipStatus::Suspended => 'Membership is suspended. It can be reactivated by a manager.',
                };

                $html .= "<div class=\"text-sm text-gray-600 dark:text-gray-400 mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded\">{$statusDescription}</div>";

                return $html;
            })
            ->html()
            ->columnSpanFull();
    }

    /**
     * Check if a stage is completed based on current status.
     */
    protected function isStageCompleted(MembershipStatus $current, MembershipStatus $stage): bool
    {
        // Special cases
        if ($current === MembershipStatus::Rejected) {
            return $stage === MembershipStatus::Applied;
        }

        if ($current === MembershipStatus::Suspended) {
            // Suspended means it was approved before, so all stages are complete
            return true;
        }

        // At this point, $current is one of: Applied, UnderReview, Approved
        $currentOrder = match ($current) {
            MembershipStatus::Applied => 1,
            MembershipStatus::UnderReview => 2,
            MembershipStatus::Approved => 3,
        };

        // $stage is only passed from the timeline: Applied, UnderReview, Approved
        $stageOrder = match ($stage) {
            MembershipStatus::Applied => 1,
            MembershipStatus::UnderReview => 2,
            MembershipStatus::Approved => 3,
            // These are never passed to this method, but must be handled for exhaustiveness
            MembershipStatus::Rejected, MembershipStatus::Suspended => 0,
        };

        return $stageOrder < $currentOrder;
    }

    /**
     * Get the membership decision history.
     */
    protected function getMembershipDecisionHistory(): TextEntry
    {
        return TextEntry::make('membership_history')
            ->label('')
            ->getStateUsing(function (Customer $record): string {
                $memberships = $record->memberships()->orderBy('created_at', 'desc')->get();

                if ($memberships->isEmpty()) {
                    return '<div class="text-gray-500 text-sm py-4">No membership history found.</div>';
                }

                $html = '<div class="space-y-3">';

                foreach ($memberships as $membership) {
                    /** @var Membership $membership */
                    $tierColor = $membership->tier->color();
                    $tierLabel = $membership->tier->label();
                    $statusColor = $membership->status->color();
                    $statusLabel = $membership->status->label();
                    $createdAt = $membership->created_at->format('M d, Y H:i');
                    $effectiveFrom = $membership->effective_from !== null ? $membership->effective_from->format('M d, Y H:i') : 'Not set';
                    $effectiveTo = $membership->effective_to !== null ? $membership->effective_to->format('M d, Y H:i') : 'No expiry';
                    $notes = $membership->decision_notes ?? 'No notes';

                    $tierBadgeClass = match ($tierColor) {
                        'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                        'primary' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    };

                    $statusBadgeClass = match ($statusColor) {
                        'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                        'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    };

                    $notesHtml = htmlspecialchars($notes);

                    $html .= <<<HTML
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$tierBadgeClass}">
                                    {$tierLabel}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$statusBadgeClass}">
                                    {$statusLabel}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Created: {$createdAt}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm mb-2">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Effective From:</span>
                                <span class="ml-1 font-medium">{$effectiveFrom}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Effective Until:</span>
                                <span class="ml-1 font-medium">{$effectiveTo}</span>
                            </div>
                        </div>
                        <div class="text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Notes:</span>
                            <p class="mt-1 text-gray-700 dark:text-gray-300">{$notesHtml}</p>
                        </div>
                    </div>
                    HTML;
                }

                $html .= '</div>';

                return $html;
            })
            ->html()
            ->columnSpanFull();
    }

    /**
     * Tab 3: Accounts - Full CRUD for customer accounts.
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
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('create_account')
                            ->label('Create Account')
                            ->icon('heroicon-o-plus-circle')
                            ->color('primary')
                            ->form([
                                TextInput::make('name')
                                    ->label('Account Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('A descriptive name for this account'),
                                Select::make('channel_scope')
                                    ->label('Channel Scope')
                                    ->options(collect(ChannelScope::cases())
                                        ->mapWithKeys(fn (ChannelScope $scope): array => [
                                            $scope->value => $scope->label(),
                                        ])
                                        ->toArray())
                                    ->required()
                                    ->native(false)
                                    ->helperText('The operational channel for this account'),
                            ])
                            ->modalHeading('Create New Account')
                            ->modalDescription('Create a new operational account for this customer.')
                            ->modalSubmitActionLabel('Create Account')
                            ->action(function (array $data) use ($record): void {
                                $account = $record->accounts()->create([
                                    'name' => $data['name'],
                                    'channel_scope' => $data['channel_scope'],
                                    'status' => AccountStatus::Active,
                                ]);

                                Notification::make()
                                    ->title('Account created')
                                    ->body("Account \"{$account->name}\" has been created successfully.")
                                    ->success()
                                    ->send();

                                $this->refreshFormData(['accounts']);
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('accounts')
                            ->label('')
                            ->schema([
                                Grid::make(6)
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
                                        \Filament\Infolists\Components\Actions::make([
                                            \Filament\Infolists\Components\Actions\Action::make('edit_account')
                                                ->label('Edit')
                                                ->icon('heroicon-o-pencil-square')
                                                ->color('gray')
                                                ->size('sm')
                                                ->form(fn (Account $account): array => [
                                                    TextInput::make('name')
                                                        ->label('Account Name')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->default($account->name),
                                                    Select::make('channel_scope')
                                                        ->label('Channel Scope')
                                                        ->options(collect(ChannelScope::cases())
                                                            ->mapWithKeys(fn (ChannelScope $scope): array => [
                                                                $scope->value => $scope->label(),
                                                            ])
                                                            ->toArray())
                                                        ->required()
                                                        ->native(false)
                                                        ->default($account->channel_scope->value),
                                                ])
                                                ->modalHeading('Edit Account')
                                                ->modalDescription(fn (Account $account): string => "Edit account: {$account->name}")
                                                ->modalSubmitActionLabel('Save Changes')
                                                ->action(function (array $data, Account $account): void {
                                                    $account->update([
                                                        'name' => $data['name'],
                                                        'channel_scope' => $data['channel_scope'],
                                                    ]);

                                                    Notification::make()
                                                        ->title('Account updated')
                                                        ->body("Account \"{$account->name}\" has been updated.")
                                                        ->success()
                                                        ->send();

                                                    $this->refreshFormData(['accounts']);
                                                }),
                                            \Filament\Infolists\Components\Actions\Action::make('suspend_account')
                                                ->label('Suspend')
                                                ->icon('heroicon-o-pause-circle')
                                                ->color('warning')
                                                ->size('sm')
                                                ->requiresConfirmation()
                                                ->modalHeading('Suspend Account')
                                                ->modalDescription(fn (Account $account): string => "Are you sure you want to suspend account \"{$account->name}\"? This will prevent any operations on this account.")
                                                ->modalSubmitActionLabel('Suspend')
                                                ->visible(fn (Account $account): bool => $account->status === AccountStatus::Active)
                                                ->action(function (Account $account): void {
                                                    $account->update(['status' => AccountStatus::Suspended]);

                                                    Notification::make()
                                                        ->title('Account suspended')
                                                        ->body("Account \"{$account->name}\" has been suspended.")
                                                        ->success()
                                                        ->send();

                                                    $this->refreshFormData(['accounts']);
                                                }),
                                            \Filament\Infolists\Components\Actions\Action::make('activate_account')
                                                ->label('Activate')
                                                ->icon('heroicon-o-check-circle')
                                                ->color('success')
                                                ->size('sm')
                                                ->requiresConfirmation()
                                                ->modalHeading('Activate Account')
                                                ->modalDescription(fn (Account $account): string => "Are you sure you want to activate account \"{$account->name}\"?")
                                                ->modalSubmitActionLabel('Activate')
                                                ->visible(fn (Account $account): bool => $account->status === AccountStatus::Suspended)
                                                ->action(function (Account $account): void {
                                                    $account->update(['status' => AccountStatus::Active]);

                                                    Notification::make()
                                                        ->title('Account activated')
                                                        ->body("Account \"{$account->name}\" has been activated.")
                                                        ->success()
                                                        ->send();

                                                    $this->refreshFormData(['accounts']);
                                                }),
                                            \Filament\Infolists\Components\Actions\Action::make('delete_account')
                                                ->label('Delete')
                                                ->icon('heroicon-o-trash')
                                                ->color('danger')
                                                ->size('sm')
                                                ->requiresConfirmation()
                                                ->modalHeading('Delete Account')
                                                ->modalDescription(fn (Account $account): string => "Are you sure you want to delete account \"{$account->name}\"? This action cannot be undone.")
                                                ->modalSubmitActionLabel('Delete')
                                                ->action(function (Account $account): void {
                                                    $accountName = $account->name;
                                                    $account->delete();

                                                    Notification::make()
                                                        ->title('Account deleted')
                                                        ->body("Account \"{$accountName}\" has been deleted.")
                                                        ->success()
                                                        ->send();

                                                    $this->refreshFormData(['accounts']);
                                                }),
                                        ])->alignEnd(),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No accounts found for this customer. Use the "Create Account" button to add one.'),
                    ]),
            ]);
    }

    /**
     * Tab 4: Addresses - Full CRUD for billing and shipping addresses.
     */
    protected function getAddressesTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;
        $addressesCount = $record->addresses()->count();

        return Tab::make('Addresses')
            ->icon('heroicon-o-map-pin')
            ->badge($addressesCount > 0 ? (string) $addressesCount : null)
            ->badgeColor('info')
            ->schema([
                $this->getBillingAddressesSection(),
                $this->getShippingAddressesSection(),
            ]);
    }

    /**
     * Get the billing addresses section.
     */
    protected function getBillingAddressesSection(): Section
    {
        /** @var Customer $record */
        $record = $this->record;
        $billingCount = $record->billingAddresses()->count();

        return Section::make('Billing Addresses')
            ->description('Addresses used for invoicing')
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('add_billing_address')
                    ->label('Add Billing Address')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form($this->getAddressFormFields())
                    ->modalHeading('Add Billing Address')
                    ->modalDescription('Add a new billing address for this customer.')
                    ->modalSubmitActionLabel('Add Address')
                    ->action(function (array $data) use ($record): void {
                        $this->createAddress($record, AddressType::Billing, $data);
                    }),
            ])
            ->schema([
                RepeatableEntry::make('billingAddresses')
                    ->label('')
                    ->schema($this->getAddressEntrySchema(AddressType::Billing))
                    ->columns(1)
                    ->placeholder('No billing addresses configured. Use the "Add Billing Address" button to add one.'),
            ]);
    }

    /**
     * Get the shipping addresses section.
     */
    protected function getShippingAddressesSection(): Section
    {
        /** @var Customer $record */
        $record = $this->record;
        $shippingCount = $record->shippingAddresses()->count();

        return Section::make('Shipping Addresses')
            ->description('Addresses used for deliveries')
            ->headerActions([
                \Filament\Infolists\Components\Actions\Action::make('add_shipping_address')
                    ->label('Add Shipping Address')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form($this->getAddressFormFields())
                    ->modalHeading('Add Shipping Address')
                    ->modalDescription('Add a new shipping address for this customer.')
                    ->modalSubmitActionLabel('Add Address')
                    ->action(function (array $data) use ($record): void {
                        $this->createAddress($record, AddressType::Shipping, $data);
                    }),
            ])
            ->schema([
                RepeatableEntry::make('shippingAddresses')
                    ->label('')
                    ->schema($this->getAddressEntrySchema(AddressType::Shipping))
                    ->columns(1)
                    ->placeholder('No shipping addresses configured. Use the "Add Shipping Address" button to add one.'),
            ]);
    }

    /**
     * Get the form fields for address creation/editing.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getAddressFormFields(): array
    {
        return [
            TextInput::make('line_1')
                ->label('Address Line 1')
                ->required()
                ->maxLength(255)
                ->helperText('Street address, P.O. box, company name'),
            TextInput::make('line_2')
                ->label('Address Line 2')
                ->maxLength(255)
                ->helperText('Apartment, suite, unit, building, floor, etc.'),
            \Filament\Forms\Components\Grid::make(2)
                ->schema([
                    TextInput::make('city')
                        ->label('City')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('state')
                        ->label('State/Province/Region')
                        ->maxLength(255),
                ]),
            \Filament\Forms\Components\Grid::make(2)
                ->schema([
                    TextInput::make('postal_code')
                        ->label('Postal Code')
                        ->required()
                        ->maxLength(20),
                    TextInput::make('country')
                        ->label('Country')
                        ->required()
                        ->maxLength(255),
                ]),
            \Filament\Forms\Components\Toggle::make('is_default')
                ->label('Set as default address')
                ->helperText('This address will be used by default for this address type'),
        ];
    }

    /**
     * Get the schema for displaying an address entry.
     *
     * @return array<\Filament\Infolists\Components\Component>
     */
    protected function getAddressEntrySchema(AddressType $addressType): array
    {
        return [
            Grid::make(6)
                ->schema([
                    Group::make([
                        TextEntry::make('formatted_address')
                            ->label('Address')
                            ->getStateUsing(fn (Address $address): string => $address->getFormattedAddress())
                            ->weight(FontWeight::Bold),
                    ])->columnSpan(2),
                    TextEntry::make('city')
                        ->label('City'),
                    TextEntry::make('country')
                        ->label('Country'),
                    TextEntry::make('is_default')
                        ->label('Default')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Default' : '—')
                        ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                        ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-star' : null),
                    \Filament\Infolists\Components\Actions::make([
                        \Filament\Infolists\Components\Actions\Action::make('edit_address')
                            ->label('Edit')
                            ->icon('heroicon-o-pencil-square')
                            ->color('gray')
                            ->size('sm')
                            ->form(fn (Address $address): array => [
                                TextInput::make('line_1')
                                    ->label('Address Line 1')
                                    ->required()
                                    ->maxLength(255)
                                    ->default($address->line_1),
                                TextInput::make('line_2')
                                    ->label('Address Line 2')
                                    ->maxLength(255)
                                    ->default($address->line_2),
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        TextInput::make('city')
                                            ->label('City')
                                            ->required()
                                            ->maxLength(255)
                                            ->default($address->city),
                                        TextInput::make('state')
                                            ->label('State/Province/Region')
                                            ->maxLength(255)
                                            ->default($address->state),
                                    ]),
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        TextInput::make('postal_code')
                                            ->label('Postal Code')
                                            ->required()
                                            ->maxLength(20)
                                            ->default($address->postal_code),
                                        TextInput::make('country')
                                            ->label('Country')
                                            ->required()
                                            ->maxLength(255)
                                            ->default($address->country),
                                    ]),
                            ])
                            ->modalHeading('Edit Address')
                            ->modalDescription(fn (Address $address): string => "Edit {$address->getTypeLabel()} address: {$address->getOneLine()}")
                            ->modalSubmitActionLabel('Save Changes')
                            ->action(function (array $data, Address $address): void {
                                $address->update([
                                    'line_1' => $data['line_1'],
                                    'line_2' => $data['line_2'],
                                    'city' => $data['city'],
                                    'state' => $data['state'],
                                    'postal_code' => $data['postal_code'],
                                    'country' => $data['country'],
                                ]);

                                Notification::make()
                                    ->title('Address updated')
                                    ->body('The address has been updated successfully.')
                                    ->success()
                                    ->send();

                                $this->refreshFormData(['billingAddresses', 'shippingAddresses']);
                            }),
                        \Filament\Infolists\Components\Actions\Action::make('set_default')
                            ->label('Set Default')
                            ->icon('heroicon-o-star')
                            ->color('warning')
                            ->size('sm')
                            ->requiresConfirmation()
                            ->modalHeading('Set as Default Address')
                            ->modalDescription(fn (Address $address): string => "Are you sure you want to set this as the default {$address->getTypeLabel()} address?")
                            ->modalSubmitActionLabel('Set Default')
                            ->visible(fn (Address $address): bool => ! $address->is_default)
                            ->action(function (Address $address): void {
                                $address->setAsDefault();

                                Notification::make()
                                    ->title('Default address set')
                                    ->body("This address is now the default {$address->getTypeLabel()} address.")
                                    ->success()
                                    ->send();

                                $this->refreshFormData(['billingAddresses', 'shippingAddresses']);
                            }),
                        \Filament\Infolists\Components\Actions\Action::make('delete_address')
                            ->label('Delete')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->size('sm')
                            ->requiresConfirmation()
                            ->modalHeading('Delete Address')
                            ->modalDescription(fn (Address $address): string => "Are you sure you want to delete this {$address->getTypeLabel()} address? This action cannot be undone.")
                            ->modalSubmitActionLabel('Delete')
                            ->action(function (Address $address): void {
                                $addressType = $address->getTypeLabel();
                                $address->delete();

                                Notification::make()
                                    ->title('Address deleted')
                                    ->body("The {$addressType} address has been deleted.")
                                    ->success()
                                    ->send();

                                $this->refreshFormData(['billingAddresses', 'shippingAddresses']);
                            }),
                    ])->alignEnd(),
                ]),
        ];
    }

    /**
     * Create a new address for the customer.
     */
    protected function createAddress(Customer $customer, AddressType $type, array $data): void
    {
        $isDefault = $data['is_default'] ?? false;

        // If this is set as default, unset other defaults of the same type
        if ($isDefault) {
            Address::query()
                ->where('addressable_type', Customer::class)
                ->where('addressable_id', $customer->id)
                ->where('type', $type)
                ->update(['is_default' => false]);
        }

        // If this is the first address of this type, make it default
        $existingCount = $customer->addresses()->where('type', $type)->count();
        if ($existingCount === 0) {
            $isDefault = true;
        }

        $customer->addresses()->create([
            'type' => $type,
            'line_1' => $data['line_1'],
            'line_2' => $data['line_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'is_default' => $isDefault,
        ]);

        Notification::make()
            ->title('Address added')
            ->body("A new {$type->label()} address has been added.")
            ->success()
            ->send();

        $this->refreshFormData(['billingAddresses', 'shippingAddresses']);
    }

    /**
     * Tab 5: Eligibility - Channel eligibility computed read-only with full explanation.
     */
    protected function getEligibilityTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;

        $eligibilityEngine = new \App\Services\Customer\EligibilityEngine;
        $detailedEligibility = $eligibilityEngine->getDetailedEligibility($record);

        // Count how many channels are eligible
        $eligibleCount = count(array_filter($detailedEligibility, fn ($data) => $data['eligible']));
        $totalCount = count($detailedEligibility);

        return Tab::make('Eligibility')
            ->icon('heroicon-o-check-badge')
            ->badge("{$eligibleCount}/{$totalCount}")
            ->badgeColor($eligibleCount === $totalCount ? 'success' : ($eligibleCount > 0 ? 'warning' : 'danger'))
            ->schema([
                Section::make('Channel Eligibility')
                    ->description('Real-time computed eligibility for each sales channel. This is read-only and reflects current state.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('refresh_eligibility')
                            ->label('Refresh')
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->action(function (): void {
                                Notification::make()
                                    ->title('Eligibility recalculated')
                                    ->body('The eligibility status has been refreshed.')
                                    ->success()
                                    ->send();

                                $this->refreshFormData([]);
                            }),
                    ])
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                $this->getChannelEligibilityCard(ChannelScope::B2C, $detailedEligibility['b2c']),
                                $this->getChannelEligibilityCard(ChannelScope::B2B, $detailedEligibility['b2b']),
                                $this->getChannelEligibilityCard(ChannelScope::Club, $detailedEligibility['club']),
                            ]),
                    ]),
                Section::make('Channel Requirements')
                    ->description('The rules that determine eligibility for each channel')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                $this->getChannelRequirementsCard(ChannelScope::B2C),
                                $this->getChannelRequirementsCard(ChannelScope::B2B),
                                $this->getChannelRequirementsCard(ChannelScope::Club),
                            ]),
                    ]),
                Section::make('How to Resolve Issues')
                    ->description('Quick links to resolve eligibility problems')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('resolve_issues_info')
                            ->label('')
                            ->getStateUsing(fn (): string => $this->getResolveIssuesHtml($detailedEligibility))
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Get a card displaying eligibility status and factors for a channel.
     */
    protected function getChannelEligibilityCard(ChannelScope $channel, array $eligibilityData): Group
    {
        $isEligible = $eligibilityData['eligible'];
        $positiveReasons = $eligibilityData['reasons']['positive'];
        $negativeReasons = $eligibilityData['reasons']['negative'];

        return Group::make([
            TextEntry::make("{$channel->value}_eligibility_status")
                ->label($channel->label().' Channel')
                ->getStateUsing(fn (): string => $isEligible ? 'Eligible' : 'Not Eligible')
                ->badge()
                ->color($isEligible ? 'success' : 'danger')
                ->icon($isEligible ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            TextEntry::make("{$channel->value}_description")
                ->label('')
                ->getStateUsing(fn (): string => $channel->description())
                ->color('gray')
                ->size(TextEntry\TextEntrySize::Small),
            TextEntry::make("{$channel->value}_factors")
                ->label('Factors')
                ->getStateUsing(fn (): string => $this->renderFactorsHtml($positiveReasons, $negativeReasons))
                ->html(),
        ])->columnSpan(1);
    }

    /**
     * Render HTML for eligibility factors.
     *
     * @param  array<string>  $positiveReasons
     * @param  array<string>  $negativeReasons
     */
    protected function renderFactorsHtml(array $positiveReasons, array $negativeReasons): string
    {
        $html = '<div class="space-y-2">';

        // Negative factors first (more important to show what's blocking)
        foreach ($negativeReasons as $reason) {
            $escapedReason = htmlspecialchars($reason);
            $html .= '<div class="flex items-start gap-2 text-sm">';
            $html .= '<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 flex-shrink-0">';
            $html .= '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
            $html .= '</span>';
            $html .= "<span class=\"text-red-700 dark:text-red-300\">{$escapedReason}</span>";
            $html .= '</div>';
        }

        // Positive factors
        foreach ($positiveReasons as $reason) {
            $escapedReason = htmlspecialchars($reason);
            $html .= '<div class="flex items-start gap-2 text-sm">';
            $html .= '<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400 flex-shrink-0">';
            $html .= '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            $html .= '</span>';
            $html .= "<span class=\"text-green-700 dark:text-green-300\">{$escapedReason}</span>";
            $html .= '</div>';
        }

        if (empty($positiveReasons) && empty($negativeReasons)) {
            $html .= '<div class="text-gray-500 text-sm">No factors evaluated</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get a card displaying requirements for a channel.
     */
    protected function getChannelRequirementsCard(ChannelScope $channel): Group
    {
        $requirements = $channel->getEligibilityRequirements();

        return Group::make([
            TextEntry::make("{$channel->value}_requirements_title")
                ->label($channel->label().' Requirements')
                ->getStateUsing(fn (): string => '')
                ->badge()
                ->color($channel->color())
                ->icon($channel->icon())
                ->formatStateUsing(fn (): string => $channel->label()),
            TextEntry::make("{$channel->value}_requirements_list")
                ->label('')
                ->getStateUsing(fn (): string => $this->renderRequirementsHtml($requirements))
                ->html(),
        ])->columnSpan(1);
    }

    /**
     * Render HTML for channel requirements.
     *
     * @param  array<string>  $requirements
     */
    protected function renderRequirementsHtml(array $requirements): string
    {
        $html = '<ul class="list-disc list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">';

        foreach ($requirements as $requirement) {
            $escapedReq = htmlspecialchars($requirement);
            $html .= "<li>{$escapedReq}</li>";
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Get HTML for resolve issues section with links to relevant tabs.
     *
     * @param  array<string, array{eligible: bool, reasons: array{positive: array<string>, negative: array<string>}}>  $eligibilityData
     */
    protected function getResolveIssuesHtml(array $eligibilityData): string
    {
        $issues = [];

        foreach ($eligibilityData as $channelValue => $data) {
            if (! $data['eligible']) {
                foreach ($data['reasons']['negative'] as $reason) {
                    $issues[] = [
                        'channel' => ChannelScope::from($channelValue)->label(),
                        'reason' => $reason,
                        'tab' => $this->getRelevantTabForIssue($reason),
                    ];
                }
            }
        }

        if (empty($issues)) {
            return '<div class="flex items-center gap-2 text-green-600 dark:text-green-400">'.
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'.
                '<span>All channels are fully eligible! No issues to resolve.</span>'.
                '</div>';
        }

        $html = '<div class="space-y-3">';

        foreach ($issues as $issue) {
            $channelEscaped = htmlspecialchars($issue['channel']);
            $reasonEscaped = htmlspecialchars($issue['reason']);
            $tabInfo = $issue['tab'];

            $html .= '<div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">';
            $html .= '<div class="flex-shrink-0">';
            $html .= '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">';
            $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
            $html .= '</span>';
            $html .= '</div>';
            $html .= '<div class="flex-1">';
            $html .= "<div class=\"font-medium text-gray-900 dark:text-gray-100\">{$channelEscaped} Channel</div>";
            $html .= "<div class=\"text-sm text-gray-600 dark:text-gray-400\">{$reasonEscaped}</div>";

            if ($tabInfo) {
                $tabNameEscaped = htmlspecialchars($tabInfo['name']);
                $tabDescEscaped = htmlspecialchars($tabInfo['description']);
                $html .= '<div class="mt-2">';
                $html .= "<span class=\"text-xs text-primary-600 dark:text-primary-400 font-medium\">→ Go to {$tabNameEscaped} tab: {$tabDescEscaped}</span>";
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get the relevant tab information for resolving an issue.
     *
     * @return array{name: string, description: string}|null
     */
    protected function getRelevantTabForIssue(string $reason): ?array
    {
        // Map issue keywords to tabs
        $lowerReason = strtolower($reason);

        if (str_contains($lowerReason, 'membership') || str_contains($lowerReason, 'tier') || str_contains($lowerReason, 'approved')) {
            return [
                'name' => 'Membership',
                'description' => 'Apply for or update membership status',
            ];
        }

        if (str_contains($lowerReason, 'payment') || str_contains($lowerReason, 'card')) {
            return [
                'name' => 'Payment & Credit',
                'description' => 'Review payment permissions',
            ];
        }

        if (str_contains($lowerReason, 'credit')) {
            return [
                'name' => 'Payment & Credit',
                'description' => 'Request credit approval',
            ];
        }

        if (str_contains($lowerReason, 'block')) {
            return [
                'name' => 'Operational Blocks',
                'description' => 'Review and remove active blocks',
            ];
        }

        if (str_contains($lowerReason, 'club') || str_contains($lowerReason, 'affiliation')) {
            return [
                'name' => 'Clubs',
                'description' => 'Join a club to enable Club channel access',
            ];
        }

        if (str_contains($lowerReason, 'customer type') || str_contains($lowerReason, 'b2b')) {
            return [
                'name' => 'Overview',
                'description' => 'Customer type cannot be changed directly',
            ];
        }

        if (str_contains($lowerReason, 'account')) {
            return [
                'name' => 'Accounts',
                'description' => 'Review account status and settings',
            ];
        }

        return null;
    }

    /**
     * Tab 6: Payment & Credit - Payment permissions and credit limits.
     */
    protected function getPaymentCreditTab(): Tab
    {
        /** @var Customer $customer */
        $customer = $this->record;
        $paymentPermission = $customer->paymentPermission;

        // Build section header actions for editing permissions
        $sectionActions = [];

        // Only show edit action if user can manage payment permissions
        $currentUser = auth()->user();
        $canManagePayments = $currentUser?->canManagePaymentPermissions() ?? false;

        if ($canManagePayments) {
            $sectionActions[] = \Filament\Infolists\Components\Actions\Action::make('editPaymentPermissions')
                ->label('Edit Permissions')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->modalHeading('Edit Payment Permissions')
                ->modalDescription('Modify payment methods and credit limit for this customer. Changes are logged automatically.')
                ->form([
                    Toggle::make('card_allowed')
                        ->label('Card Payments Allowed')
                        ->helperText('Enable or disable card payments for this customer.')
                        ->default($paymentPermission !== null ? $paymentPermission->card_allowed : true),
                    Toggle::make('bank_transfer_allowed')
                        ->label('Bank Transfer Allowed')
                        ->helperText('Enable or disable bank transfer payments. Requires Finance approval.')
                        ->default($paymentPermission !== null ? $paymentPermission->bank_transfer_allowed : false),
                    TextInput::make('credit_limit')
                        ->label('Credit Limit (EUR)')
                        ->helperText('Maximum credit amount. Leave empty for no credit (cash/card only).')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->prefix('€')
                        ->placeholder('No credit')
                        ->default($paymentPermission?->credit_limit),
                ])
                ->action(function (array $data) use ($customer): void {
                    $paymentPermission = $customer->paymentPermission;

                    if (! $paymentPermission) {
                        // Create a new payment permission record if none exists
                        $paymentPermission = $customer->paymentPermission()->create([
                            'card_allowed' => $data['card_allowed'],
                            'bank_transfer_allowed' => $data['bank_transfer_allowed'],
                            'credit_limit' => $data['credit_limit'] !== '' ? $data['credit_limit'] : null,
                        ]);
                    } else {
                        // Update existing payment permission
                        $paymentPermission->update([
                            'card_allowed' => $data['card_allowed'],
                            'bank_transfer_allowed' => $data['bank_transfer_allowed'],
                            'credit_limit' => $data['credit_limit'] !== '' ? $data['credit_limit'] : null,
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title('Payment Permissions Updated')
                        ->body('Payment permissions have been updated successfully.')
                        ->send();

                    $this->refreshFormData(['paymentPermission']);
                });
        }

        // Determine display values
        $cardAllowed = $paymentPermission !== null ? $paymentPermission->card_allowed : true;
        $bankTransferAllowed = $paymentPermission !== null ? $paymentPermission->bank_transfer_allowed : false;
        $creditLimit = $paymentPermission?->credit_limit;

        return Tab::make('Payment & Credit')
            ->icon('heroicon-o-credit-card')
            ->badge(fn (): ?string => ! $cardAllowed ? '!' : null)
            ->badgeColor('danger')
            ->schema([
                Section::make('Payment Permissions')
                    ->description('Allowed payment methods for this customer')
                    ->headerActions($sectionActions)
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('paymentPermission.card_allowed')
                                    ->label('Card Payments')
                                    ->getStateUsing(fn (): string => $cardAllowed ? 'Allowed' : 'Blocked')
                                    ->badge()
                                    ->color(fn (): string => $cardAllowed ? 'success' : 'danger')
                                    ->icon(fn (): string => $cardAllowed ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                    ->helperText('Default payment method. If blocked, customer cannot make payments.'),
                                TextEntry::make('paymentPermission.bank_transfer_allowed')
                                    ->label('Bank Transfer')
                                    ->getStateUsing(fn (): string => $bankTransferAllowed ? 'Authorized' : 'Not Authorized')
                                    ->badge()
                                    ->color(fn (): string => $bankTransferAllowed ? 'success' : 'gray')
                                    ->icon(fn (): string => $bankTransferAllowed ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle')
                                    ->helperText($paymentPermission?->getBankTransferExplanation() ?? 'Bank transfers not authorized. Requires Finance approval.'),
                                TextEntry::make('paymentPermission.credit_limit')
                                    ->label('Credit Limit')
                                    ->getStateUsing(function () use ($creditLimit): string {
                                        if ($creditLimit === null) {
                                            return 'No Credit';
                                        }

                                        return '€'.number_format((float) $creditLimit, 2);
                                    })
                                    ->badge()
                                    ->color(fn (): string => $creditLimit !== null ? 'success' : 'gray')
                                    ->icon(fn (): string => $creditLimit !== null ? 'heroicon-o-banknotes' : 'heroicon-o-minus-circle')
                                    ->helperText($paymentPermission?->getCreditLimitExplanation() ?? 'No credit approved. Customer must pay by card or cash.'),
                            ]),
                    ]),
                Section::make('Permission Details')
                    ->description('Additional information about payment permissions')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payment_eligibility_impact')
                                    ->label('Impact on Eligibility')
                                    ->getStateUsing(function () use ($cardAllowed, $creditLimit): string {
                                        $impacts = [];

                                        if (! $cardAllowed) {
                                            $impacts[] = '❌ Payment blocked: Customer cannot access any sales channel (B2C, B2B, Club)';
                                        }

                                        if ($creditLimit === null) {
                                            $impacts[] = '⚠️ No credit: Customer cannot access B2B channel (requires credit approval)';
                                        } else {
                                            $impacts[] = '✓ Credit approved: Customer eligible for B2B channel';
                                        }

                                        if ($cardAllowed && $creditLimit !== null) {
                                            $impacts[] = '✓ All payment methods available';
                                        }

                                        return implode("\n", $impacts);
                                    })
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('payment_last_modified')
                                    ->label('Last Modified')
                                    ->getStateUsing(function () use ($paymentPermission): string {
                                        if (! $paymentPermission) {
                                            return 'Not yet configured';
                                        }

                                        $updatedAt = $paymentPermission->updated_at->format('d M Y, H:i');
                                        $updatedBy = $paymentPermission->updater->name ?? 'System';

                                        return "{$updatedAt} by {$updatedBy}";
                                    })
                                    ->icon('heroicon-o-clock'),
                                TextEntry::make('payment_created')
                                    ->label('Created')
                                    ->getStateUsing(function () use ($paymentPermission): string {
                                        if (! $paymentPermission) {
                                            return 'Not yet configured';
                                        }

                                        $createdAt = $paymentPermission->created_at->format('d M Y, H:i');
                                        $createdBy = $paymentPermission->creator->name ?? 'System';

                                        return "{$createdAt} by {$createdBy}";
                                    })
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),
                Section::make('Modification History')
                    ->description('Audit log of all changes to payment permissions')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        $this->getPaymentPermissionHistoryEntry($paymentPermission),
                    ]),
            ]);
    }

    /**
     * Get the payment permission history entry for the audit section.
     */
    protected function getPaymentPermissionHistoryEntry(?PaymentPermission $paymentPermission): TextEntry
    {
        return TextEntry::make('payment_history')
            ->label('')
            ->getStateUsing(function () use ($paymentPermission): string {
                if (! $paymentPermission) {
                    return '<div class="text-gray-500 text-sm py-4 text-center">No payment permissions configured yet. History will appear once permissions are set.</div>';
                }

                // Get audit logs for this payment permission
                $auditLogs = AuditLog::where('auditable_type', PaymentPermission::class)
                    ->where('auditable_id', $paymentPermission->id)
                    ->with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();

                if ($auditLogs->isEmpty()) {
                    return '<div class="text-gray-500 text-sm py-4 text-center">No modification history available.</div>';
                }

                $html = '<div class="space-y-3">';

                foreach ($auditLogs as $log) {
                    $date = $log->created_at?->format('d M Y, H:i') ?? 'Unknown';
                    $user = htmlspecialchars($log->user->name ?? 'System');
                    $event = htmlspecialchars($log->getEventLabel());
                    $eventColor = $log->getEventColor();
                    $eventIcon = $log->getEventIcon();

                    // Build changes description
                    $changes = $this->formatPaymentPermissionChanges($log->old_values ?? [], $log->new_values ?? []);

                    $html .= <<<HTML
                    <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-{$eventColor}-100 dark:bg-{$eventColor}-900">
                                <x-heroicon-o-{$this->extractIconName($eventIcon)} class="w-4 h-4 text-{$eventColor}-600 dark:text-{$eventColor}-400" />
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{$event}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{$date}</span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">by {$user}</div>
                            {$changes}
                        </div>
                    </div>
                    HTML;
                }

                $html .= '</div>';

                return $html;
            })
            ->html()
            ->columnSpanFull();
    }

    /**
     * Format payment permission changes for display.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function formatPaymentPermissionChanges(array $oldValues, array $newValues): string
    {
        $changes = [];

        $fieldLabels = [
            'card_allowed' => 'Card Payments',
            'bank_transfer_allowed' => 'Bank Transfer',
            'credit_limit' => 'Credit Limit',
        ];

        foreach ($fieldLabels as $field => $label) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            // Skip if both values are the same or both are null
            if ($oldValue === $newValue) {
                continue;
            }

            // Format values for display
            if ($field === 'credit_limit') {
                $oldDisplay = $oldValue !== null ? '€'.number_format((float) $oldValue, 2) : 'No Credit';
                $newDisplay = $newValue !== null ? '€'.number_format((float) $newValue, 2) : 'No Credit';
            } else {
                $oldDisplay = $oldValue ? 'Enabled' : 'Disabled';
                $newDisplay = $newValue ? 'Enabled' : 'Disabled';
            }

            $changes[] = "<span class=\"font-medium\">{$label}</span>: {$oldDisplay} → {$newDisplay}";
        }

        if (empty($changes)) {
            return '';
        }

        return '<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">'.implode(' | ', $changes).'</div>';
    }

    /**
     * Extract the icon name from a Heroicon identifier.
     */
    protected function extractIconName(string $heroicon): string
    {
        // Convert "heroicon-o-credit-card" to "credit-card"
        return preg_replace('/^heroicon-[os]-/', '', $heroicon) ?? 'document';
    }

    /**
     * Tab 7: Clubs - Club affiliations management.
     */
    protected function getClubsTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;
        $affiliationsCount = $record->clubAffiliations()->count();
        $effectiveCount = $record->effectiveClubAffiliations()->count();

        // Get available clubs for the add affiliation form (exclude already affiliated clubs)
        $affiliatedClubIds = $record->clubAffiliations()->pluck('club_id')->toArray();
        $availableClubs = \App\Models\Customer\Club::query()
            ->whereNotIn('id', $affiliatedClubIds)
            ->where('status', \App\Enums\Customer\ClubStatus::Active)
            ->orderBy('partner_name')
            ->pluck('partner_name', 'id')
            ->toArray();

        return Tab::make('Clubs')
            ->icon('heroicon-o-user-group')
            ->badge($affiliationsCount > 0 ? (string) $affiliationsCount : null)
            ->badgeColor($effectiveCount > 0 ? 'success' : 'gray')
            ->schema([
                Section::make('Club Affiliations')
                    ->description('Club memberships and partnerships for this customer. Effective affiliations unlock Club channel access.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('add_affiliation')
                            ->label('Add Affiliation')
                            ->icon('heroicon-o-plus-circle')
                            ->color('primary')
                            ->visible(count($availableClubs) > 0)
                            ->form([
                                Select::make('club_id')
                                    ->label('Club')
                                    ->options($availableClubs)
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->helperText('Select a club to affiliate this customer with'),
                                DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->helperText('When the affiliation becomes active'),
                            ])
                            ->modalHeading('Add Club Affiliation')
                            ->modalDescription('Create a new club affiliation for this customer.')
                            ->modalSubmitActionLabel('Add Affiliation')
                            ->action(function (array $data) use ($record): void {
                                $club = \App\Models\Customer\Club::find($data['club_id']);

                                $record->clubAffiliations()->create([
                                    'club_id' => $data['club_id'],
                                    'affiliation_status' => \App\Enums\Customer\AffiliationStatus::Active,
                                    'start_date' => $data['start_date'],
                                    'end_date' => null,
                                ]);

                                Notification::make()
                                    ->title('Club affiliation added')
                                    ->body("Customer is now affiliated with club \"{$club->partner_name}\".")
                                    ->success()
                                    ->send();

                                $this->refreshFormData(['clubAffiliations']);
                            }),
                    ])
                    ->schema([
                        $this->getClubAffiliationsRepeatableEntry($record),
                    ]),
                Section::make('Club Eligibility Impact')
                    ->description('How club affiliations affect channel eligibility')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('club_eligibility_summary')
                            ->label('')
                            ->getStateUsing(function () use ($effectiveCount): string {
                                if ($effectiveCount > 0) {
                                    return '✓ This customer has '.$effectiveCount.' effective club affiliation(s), which unlocks access to the Club sales channel.';
                                }

                                return '✗ No effective club affiliations. To access the Club channel, the customer needs at least one active affiliation that has started and not ended.';
                            })
                            ->icon(fn () => $effectiveCount > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->color(fn () => $effectiveCount > 0 ? 'success' : 'warning'),
                        TextEntry::make('club_eligibility_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Note: Some membership tiers (Legacy, Invitation Only) automatically grant Club channel access regardless of club affiliations.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Build the club affiliations repeatable entry with CRUD actions.
     */
    protected function getClubAffiliationsRepeatableEntry(Customer $record): RepeatableEntry|TextEntry
    {
        $affiliations = $record->clubAffiliations()->with('club')->get();

        if ($affiliations->isEmpty()) {
            return TextEntry::make('no_affiliations')
                ->label('')
                ->getStateUsing(fn (): string => 'No club affiliations found. Use "Add Affiliation" to create one.')
                ->icon('heroicon-o-information-circle')
                ->color('gray');
        }

        return RepeatableEntry::make('clubAffiliations')
            ->label('')
            ->schema([
                Grid::make(7)
                    ->schema([
                        TextEntry::make('club.partner_name')
                            ->label('Club')
                            ->weight(FontWeight::Bold)
                            ->url(fn (\App\Models\Customer\CustomerClub $affiliation): string => \App\Filament\Resources\Customer\ClubResource::getUrl('view', ['record' => $affiliation->club_id])),
                        TextEntry::make('affiliation_status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (\App\Enums\Customer\AffiliationStatus $state): string => $state->label())
                            ->color(fn (\App\Enums\Customer\AffiliationStatus $state): string => $state->color())
                            ->icon(fn (\App\Enums\Customer\AffiliationStatus $state): string => $state->icon()),
                        TextEntry::make('start_date')
                            ->label('Start Date')
                            ->date(),
                        TextEntry::make('end_date')
                            ->label('End Date')
                            ->date()
                            ->placeholder('No end date'),
                        TextEntry::make('effective_indicator')
                            ->label('Eligibility')
                            ->getStateUsing(fn (\App\Models\Customer\CustomerClub $affiliation): string => $affiliation->isEffective() ? 'Effective' : 'Not Effective')
                            ->badge()
                            ->color(fn (\App\Models\Customer\CustomerClub $affiliation): string => $affiliation->isEffective() ? 'success' : 'gray')
                            ->icon(fn (\App\Models\Customer\CustomerClub $affiliation): string => $affiliation->isEffective() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->tooltip(fn (\App\Models\Customer\CustomerClub $affiliation): string => $affiliation->isEffective()
                                ? 'This affiliation is active, has started, and has not ended - it grants Club channel access.'
                                : 'This affiliation is not effective (suspended, not started, or ended) - it does not grant Club channel access.'),
                        TextEntry::make('club.status')
                            ->label('Club Status')
                            ->badge()
                            ->formatStateUsing(fn (\App\Enums\Customer\ClubStatus $state): string => $state->label())
                            ->color(fn (\App\Enums\Customer\ClubStatus $state): string => $state->color())
                            ->icon(fn (\App\Enums\Customer\ClubStatus $state): string => $state->icon()),
                        \Filament\Infolists\Components\Actions::make([
                            \Filament\Infolists\Components\Actions\Action::make('edit_affiliation')
                                ->label('Edit')
                                ->icon('heroicon-o-pencil-square')
                                ->color('gray')
                                ->size('sm')
                                ->form(fn (\App\Models\Customer\CustomerClub $affiliation): array => [
                                    Select::make('affiliation_status')
                                        ->label('Affiliation Status')
                                        ->options(collect(\App\Enums\Customer\AffiliationStatus::cases())
                                            ->mapWithKeys(fn (\App\Enums\Customer\AffiliationStatus $status): array => [
                                                $status->value => $status->label(),
                                            ])
                                            ->toArray())
                                        ->required()
                                        ->native(false)
                                        ->default($affiliation->affiliation_status->value)
                                        ->helperText('Active affiliations contribute to Club channel eligibility'),
                                    DatePicker::make('end_date')
                                        ->label('End Date')
                                        ->native(false)
                                        ->default($affiliation->end_date)
                                        ->helperText('Leave empty for ongoing affiliation, or set to end the affiliation'),
                                ])
                                ->modalHeading('Edit Club Affiliation')
                                ->modalDescription(fn (\App\Models\Customer\CustomerClub $affiliation): string => "Edit affiliation with club: {$affiliation->club->partner_name}")
                                ->modalSubmitActionLabel('Save Changes')
                                ->action(function (array $data, \App\Models\Customer\CustomerClub $affiliation): void {
                                    $affiliation->update([
                                        'affiliation_status' => $data['affiliation_status'],
                                        'end_date' => $data['end_date'],
                                    ]);

                                    Notification::make()
                                        ->title('Affiliation updated')
                                        ->body("Affiliation with \"{$affiliation->club->partner_name}\" has been updated.")
                                        ->success()
                                        ->send();

                                    $this->refreshFormData(['clubAffiliations']);
                                }),
                            \Filament\Infolists\Components\Actions\Action::make('suspend_affiliation')
                                ->label('Suspend')
                                ->icon('heroicon-o-pause-circle')
                                ->color('warning')
                                ->size('sm')
                                ->requiresConfirmation()
                                ->modalHeading('Suspend Affiliation')
                                ->modalDescription(fn (\App\Models\Customer\CustomerClub $affiliation): string => "Are you sure you want to suspend the affiliation with \"{$affiliation->club->partner_name}\"? This will remove Club channel eligibility from this affiliation.")
                                ->modalSubmitActionLabel('Suspend')
                                ->visible(fn (\App\Models\Customer\CustomerClub $affiliation): bool => $affiliation->isActive() && ! $affiliation->hasEnded())
                                ->action(function (\App\Models\Customer\CustomerClub $affiliation): void {
                                    $affiliation->update(['affiliation_status' => \App\Enums\Customer\AffiliationStatus::Suspended]);

                                    Notification::make()
                                        ->title('Affiliation suspended')
                                        ->body("Affiliation with \"{$affiliation->club->partner_name}\" has been suspended.")
                                        ->success()
                                        ->send();

                                    $this->refreshFormData(['clubAffiliations']);
                                }),
                            \Filament\Infolists\Components\Actions\Action::make('reactivate_affiliation')
                                ->label('Reactivate')
                                ->icon('heroicon-o-play-circle')
                                ->color('success')
                                ->size('sm')
                                ->requiresConfirmation()
                                ->modalHeading('Reactivate Affiliation')
                                ->modalDescription(fn (\App\Models\Customer\CustomerClub $affiliation): string => "Are you sure you want to reactivate the affiliation with \"{$affiliation->club->partner_name}\"?")
                                ->modalSubmitActionLabel('Reactivate')
                                ->visible(fn (\App\Models\Customer\CustomerClub $affiliation): bool => $affiliation->isSuspended() && ! $affiliation->hasEnded())
                                ->action(function (\App\Models\Customer\CustomerClub $affiliation): void {
                                    $affiliation->update(['affiliation_status' => \App\Enums\Customer\AffiliationStatus::Active]);

                                    Notification::make()
                                        ->title('Affiliation reactivated')
                                        ->body("Affiliation with \"{$affiliation->club->partner_name}\" has been reactivated.")
                                        ->success()
                                        ->send();

                                    $this->refreshFormData(['clubAffiliations']);
                                }),
                            \Filament\Infolists\Components\Actions\Action::make('end_affiliation')
                                ->label('End')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->size('sm')
                                ->requiresConfirmation()
                                ->modalHeading('End Affiliation')
                                ->modalDescription(fn (\App\Models\Customer\CustomerClub $affiliation): string => "Are you sure you want to end the affiliation with \"{$affiliation->club->partner_name}\"? This will set the end date to today.")
                                ->modalSubmitActionLabel('End Affiliation')
                                ->visible(fn (\App\Models\Customer\CustomerClub $affiliation): bool => ! $affiliation->hasEnded())
                                ->action(function (\App\Models\Customer\CustomerClub $affiliation): void {
                                    $affiliation->update(['end_date' => now()]);

                                    Notification::make()
                                        ->title('Affiliation ended')
                                        ->body("Affiliation with \"{$affiliation->club->partner_name}\" has been ended.")
                                        ->success()
                                        ->send();

                                    $this->refreshFormData(['clubAffiliations']);
                                }),
                        ])->alignEnd(),
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

        $hasBillingAddress = $record->hasBillingAddress();

        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Activate Customer')
            ->modalDescription(function () use ($hasBillingAddress): string {
                if (! $hasBillingAddress) {
                    return 'This customer requires at least one billing address before they can be activated. Please add a billing address in the Addresses tab first.';
                }

                return 'Are you sure you want to activate this customer? They will be able to perform operations.';
            })
            ->modalSubmitActionLabel($hasBillingAddress ? 'Activate' : 'Go to Addresses')
            ->action(function () use ($record, $hasBillingAddress): void {
                if (! $hasBillingAddress) {
                    Notification::make()
                        ->title('Cannot activate customer')
                        ->body('Please add at least one billing address before activating this customer.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['status' => CustomerStatus::Active]);

                Notification::make()
                    ->title('Customer activated')
                    ->body('The customer has been activated.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => in_array($record->status, [CustomerStatus::Prospect, CustomerStatus::Suspended], true));
    }
}
