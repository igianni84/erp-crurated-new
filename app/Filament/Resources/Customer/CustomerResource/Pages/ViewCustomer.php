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
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
