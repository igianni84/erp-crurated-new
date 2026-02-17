<?php

namespace App\Filament\Resources\Finance\SubscriptionResource\Pages;

use App\Enums\Finance\BillingCycle;
use App\Enums\Finance\SubscriptionPlanType;
use App\Enums\Finance\SubscriptionStatus;
use App\Filament\Resources\Finance\SubscriptionResource;
use App\Models\AuditLog;
use App\Models\Finance\Invoice;
use App\Models\Finance\Subscription;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Subscription $record */
        $record = $this->record;

        return 'Subscription: '.$record->plan_name;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                $this->getHeaderSection(),
                Tabs::make('Subscription Details')
                    ->tabs([
                        $this->getPlanDetailsTab(),
                        $this->getBillingTab(),
                        $this->getInvoicesTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Header section with subscription_id, customer, plan_name, status.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make('Subscription Overview')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('id')
                                ->label('Subscription ID')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->copyable()
                                ->copyMessage('Subscription ID copied'),
                            TextEntry::make('plan_name')
                                ->label('Plan Name')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->url(fn (Subscription $record): ?string => $record->customer
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('customer.email')
                                ->label('Customer Email')
                                ->copyable()
                                ->copyMessage('Email copied'),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                                ->color(fn (SubscriptionStatus $state): string => $state->color())
                                ->icon(fn (SubscriptionStatus $state): string => $state->icon()),
                            TextEntry::make('plan_type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn (SubscriptionPlanType $state): string => $state->label())
                                ->color(fn (SubscriptionPlanType $state): string => $state->color())
                                ->icon(fn (SubscriptionPlanType $state): string => $state->icon()),
                        ])->columnSpan(1),

                        Group::make([
                            TextEntry::make('amount')
                                ->label('Amount')
                                ->money(fn (Subscription $record): string => $record->currency)
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                            TextEntry::make('billing_cycle')
                                ->label('Billing Cycle')
                                ->badge()
                                ->formatStateUsing(fn (BillingCycle $state): string => $state->label())
                                ->color(fn (BillingCycle $state): string => $state->color()),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Tab 1: Plan Details - type, billing_cycle, amount, started_at.
     */
    protected function getPlanDetailsTab(): Tab
    {
        return Tab::make('Plan Details')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Subscription Plan Information')
                    ->description('Details about the subscription plan and terms')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('plan_type')
                                        ->label('Plan Type')
                                        ->badge()
                                        ->formatStateUsing(fn (SubscriptionPlanType $state): string => $state->label())
                                        ->color(fn (SubscriptionPlanType $state): string => $state->color())
                                        ->icon(fn (SubscriptionPlanType $state): string => $state->icon())
                                        ->helperText(fn (Subscription $record): string => $record->isMembership()
                                            ? 'Generates INV0 (Membership Service) invoices'
                                            : 'Generates INV4 (Service Events) invoices'),
                                    TextEntry::make('plan_name')
                                        ->label('Plan Name'),
                                ]),

                                Group::make([
                                    TextEntry::make('billing_cycle')
                                        ->label('Billing Cycle')
                                        ->badge()
                                        ->formatStateUsing(fn (BillingCycle $state): string => $state->label())
                                        ->color(fn (BillingCycle $state): string => $state->color())
                                        ->icon(fn (BillingCycle $state): string => $state->icon())
                                        ->helperText(fn (Subscription $record): string => 'Every '.$record->getBillingCycleMonths().' month(s)'),
                                    TextEntry::make('amount')
                                        ->label('Amount per Cycle')
                                        ->money(fn (Subscription $record): string => $record->currency),
                                ]),

                                Group::make([
                                    TextEntry::make('started_at')
                                        ->label('Started At')
                                        ->date()
                                        ->helperText(fn (Subscription $record): string => 'Active for '.$record->started_at->diffForHumans()),
                                    TextEntry::make('cancelled_at')
                                        ->label('Cancelled At')
                                        ->date()
                                        ->placeholder('Not cancelled')
                                        ->visible(fn (Subscription $record): bool => $record->cancelled_at !== null),
                                    TextEntry::make('cancellation_reason')
                                        ->label('Cancellation Reason')
                                        ->placeholder('N/A')
                                        ->visible(fn (Subscription $record): bool => $record->cancellation_reason !== null),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Billing - next_billing_date, payment_method (Stripe).
     */
    protected function getBillingTab(): Tab
    {
        return Tab::make('Billing')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make('Billing Information')
                    ->description('Payment schedule and method details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('next_billing_date')
                                        ->label('Next Billing Date')
                                        ->date()
                                        ->weight(FontWeight::Bold)
                                        ->size(TextSize::Large)
                                        ->color(fn (Subscription $record): ?string => $record->isOverdueForBilling() ? 'danger' : ($record->isDueForBilling() ? 'warning' : null))
                                        ->helperText(fn (Subscription $record): string => $record->isOverdueForBilling()
                                            ? 'Overdue - billing should have occurred'
                                            : ($record->isDueForBilling()
                                                ? 'Due today!'
                                                : 'Next billing in '.$record->next_billing_date->diffForHumans())),
                                    TextEntry::make('billing_status')
                                        ->label('Billing Status')
                                        ->getStateUsing(fn (Subscription $record): string => $record->allowsBilling() ? 'Active' : 'Blocked')
                                        ->badge()
                                        ->color(fn (Subscription $record): string => $record->allowsBilling() ? 'success' : 'danger')
                                        ->icon(fn (Subscription $record): string => $record->allowsBilling()
                                            ? 'heroicon-o-check-circle'
                                            : 'heroicon-o-x-circle'),
                                ]),

                                Group::make([
                                    TextEntry::make('stripe_subscription_id')
                                        ->label('Stripe Subscription ID')
                                        ->copyable()
                                        ->copyMessage('Stripe ID copied')
                                        ->placeholder('Not linked to Stripe')
                                        ->helperText(fn (Subscription $record): string => $record->hasStripeSubscription()
                                            ? 'Payments processed via Stripe'
                                            : 'Manual billing - no Stripe integration'),
                                    TextEntry::make('currency')
                                        ->label('Billing Currency')
                                        ->badge()
                                        ->color('gray'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Invoices - list of INV0 generated from this subscription.
     */
    protected function getInvoicesTab(): Tab
    {
        /** @var Subscription $record */
        $record = $this->record;
        $invoiceCount = $record->invoices()->count();

        return Tab::make('Invoices')
            ->icon('heroicon-o-document-text')
            ->badge($invoiceCount > 0 ? (string) $invoiceCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Generated Invoices')
                    ->description('Invoices created from this subscription billing')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('invoice_number')
                                            ->label('Invoice #')
                                            ->weight(FontWeight::Bold)
                                            ->placeholder('Draft')
                                            ->url(fn (Invoice $invoice): string => route('filament.admin.resources.finance.invoices.view', ['record' => $invoice]))
                                            ->color('primary'),
                                        TextEntry::make('invoice_type')
                                            ->label('Type')
                                            ->badge()
                                            ->formatStateUsing(fn (Invoice $invoice): string => $invoice->invoice_type->code())
                                            ->color(fn (Invoice $invoice): string => $invoice->invoice_type->color()),
                                        TextEntry::make('total_amount')
                                            ->label('Amount')
                                            ->money(fn (Invoice $invoice): string => $invoice->currency),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (Invoice $invoice): string => $invoice->status->label())
                                            ->color(fn (Invoice $invoice): string => $invoice->status->color()),
                                        TextEntry::make('issued_at')
                                            ->label('Issued')
                                            ->date()
                                            ->placeholder('Not issued'),
                                        TextEntry::make('due_date')
                                            ->label('Due')
                                            ->date()
                                            ->placeholder('N/A')
                                            ->color(fn (Invoice $invoice): ?string => $invoice->isOverdue() ? 'danger' : null),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No invoices generated yet'),
                    ]),
            ]);
    }

    /**
     * Tab 4: Audit - timeline of changes.
     */
    protected function getAuditTab(): Tab
    {
        /** @var Subscription $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-clock')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Timeline')
                    ->description('Immutable record of all changes to this subscription')
                    ->schema([
                        RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Timestamp')
                                            ->dateTime()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('event')
                                            ->label('Event')
                                            ->badge()
                                            ->formatStateUsing(fn (AuditLog $log): string => $log->getEventLabel())
                                            ->color(fn (AuditLog $log): string => $log->getEventColor())
                                            ->icon(fn (AuditLog $log): string => $log->getEventIcon()),
                                        TextEntry::make('user.name')
                                            ->label('User')
                                            ->placeholder('System'),
                                        TextEntry::make('changes')
                                            ->label('Changes')
                                            ->getStateUsing(fn (AuditLog $log): string => $this->formatAuditChanges($log))
                                            ->html()
                                            ->columnSpan(4),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No audit records available'),
                    ]),
            ]);
    }

    /**
     * Format audit changes for display.
     */
    protected function formatAuditChanges(AuditLog $log): string
    {
        if ($log->event === 'created') {
            return '<span class="text-success-600">Subscription created</span>';
        }

        /** @var array<string, mixed> $oldValues */
        $oldValues = $log->old_values ?? [];
        /** @var array<string, mixed> $newValues */
        $newValues = $log->new_values ?? [];

        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? '<em>null</em>';
            $changes[] = "<strong>{$key}</strong>: {$oldValue} → {$newValue}";
        }

        return count($changes) > 0 ? implode('<br>', $changes) : 'No field changes recorded';
    }

    /**
     * Get header actions for subscription management.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getSuspendAction(),
            $this->getResumeAction(),
            $this->getCancelAction(),
        ];
    }

    /**
     * Suspend subscription action.
     */
    protected function getSuspendAction(): Action
    {
        /** @var Subscription $subscription */
        $subscription = $this->record;

        return Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('warning')
            ->visible(fn (): bool => $subscription->canTransitionTo(SubscriptionStatus::Suspended))
            ->requiresConfirmation()
            ->modalHeading('Suspend Subscription')
            ->modalDescription('Suspending this subscription will stop billing and block customer access. The subscription can be resumed later.')
            ->schema([
                Textarea::make('reason')
                    ->label('Suspension Reason')
                    ->required()
                    ->maxLength(500)
                    ->placeholder('Enter reason for suspension...'),
            ])
            ->action(function (array $data): void {
                /** @var Subscription $subscription */
                $subscription = $this->record;
                $subscription->status = SubscriptionStatus::Suspended;
                $subscription->metadata = array_merge($subscription->metadata ?? [], [
                    'suspension_reason' => $data['reason'],
                    'suspended_at' => now()->toIso8601String(),
                    'suspended_by' => auth()->id(),
                ]);
                $subscription->save();

                Notification::make()
                    ->title('Subscription Suspended')
                    ->body('The subscription has been suspended. Billing is now paused.')
                    ->warning()
                    ->send();
            });
    }

    /**
     * Resume subscription action.
     */
    protected function getResumeAction(): Action
    {
        /** @var Subscription $subscription */
        $subscription = $this->record;

        return Action::make('resume')
            ->label('Resume')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->visible(fn (): bool => $subscription->canTransitionTo(SubscriptionStatus::Active))
            ->requiresConfirmation()
            ->modalHeading('Resume Subscription')
            ->modalDescription('Resuming this subscription will restart billing and restore customer access.')
            ->action(function (): void {
                /** @var Subscription $subscription */
                $subscription = $this->record;
                $subscription->status = SubscriptionStatus::Active;
                $subscription->metadata = array_merge($subscription->metadata ?? [], [
                    'resumed_at' => now()->toIso8601String(),
                    'resumed_by' => auth()->id(),
                ]);
                $subscription->save();

                Notification::make()
                    ->title('Subscription Resumed')
                    ->body('The subscription has been resumed. Billing will continue as scheduled.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Cancel subscription action.
     */
    protected function getCancelAction(): Action
    {
        /** @var Subscription $subscription */
        $subscription = $this->record;

        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (): bool => $subscription->canTransitionTo(SubscriptionStatus::Cancelled))
            ->requiresConfirmation()
            ->modalHeading('Cancel Subscription')
            ->modalDescription('WARNING: Cancelling this subscription is permanent and cannot be undone. The customer will lose access and no further billing will occur.')
            ->modalSubmitActionLabel('Yes, Cancel Subscription')
            ->schema([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->required()
                    ->maxLength(500)
                    ->placeholder('Enter reason for cancellation...'),
            ])
            ->action(function (array $data): void {
                /** @var Subscription $subscription */
                $subscription = $this->record;
                $subscription->status = SubscriptionStatus::Cancelled;
                $subscription->cancellation_reason = $data['reason'];
                $subscription->cancelled_at = now();
                $subscription->save();

                Notification::make()
                    ->title('Subscription Cancelled')
                    ->body('The subscription has been cancelled. No further billing will occur.')
                    ->danger()
                    ->send();
            });
    }
}
