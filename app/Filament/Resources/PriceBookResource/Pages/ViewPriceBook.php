<?php

namespace App\Filament\Resources\PriceBookResource\Pages;

use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PriceSource;
use App\Filament\Resources\PriceBookResource;
use App\Models\AuditLog;
use App\Models\Commercial\PriceBook;
use App\Services\Commercial\PriceBookService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use InvalidArgumentException;

class ViewPriceBook extends ViewRecord
{
    protected static string $resource = PriceBookResource::class;

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
        /** @var PriceBook $record */
        $record = $this->record;

        return "Price Book: {$record->name}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Price Book Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getPricesTab(),
                        $this->getScopeTab(),
                        $this->getLifecycleTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Summary, status, validity, coverage stats.
     */
    protected function getOverviewTab(): Tab
    {
        /** @var PriceBook $record */
        $record = $this->record;

        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Price Book Information')
                    ->description('Core price book configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Price Book ID')
                                        ->copyable()
                                        ->copyMessage('ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextSize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('market')
                                        ->label('Market')
                                        ->badge()
                                        ->color('info'),
                                    TextEntry::make('currency')
                                        ->label('Currency')
                                        ->badge()
                                        ->color('gray'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (PriceBookStatus $state): string => $state->label())
                                        ->color(fn (PriceBookStatus $state): string => $state->color())
                                        ->icon(fn (PriceBookStatus $state): string => $state->icon()),
                                    TextEntry::make('channel.name')
                                        ->label('Channel')
                                        ->placeholder('All Channels'),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Validity Period')
                    ->description('When this price book is effective')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('valid_from')
                                    ->label('Valid From')
                                    ->date()
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('valid_to')
                                    ->label('Valid To')
                                    ->date()
                                    ->placeholder('Indefinite')
                                    ->color(fn (PriceBook $record): ?string => $record->isExpiringSoon() ? 'warning' : null)
                                    ->weight(fn (PriceBook $record): ?FontWeight => $record->isExpiringSoon() ? FontWeight::Bold : null),
                                TextEntry::make('validity_status')
                                    ->label('Validity Status')
                                    ->getStateUsing(function (PriceBook $record): string {
                                        if ($record->isWithinValidityPeriod()) {
                                            if ($record->isExpiringSoon()) {
                                                $daysLeft = (int) now()->diffInDays($record->valid_to, false);

                                                return "Expiring in {$daysLeft} days";
                                            }

                                            return 'Active period';
                                        }

                                        if ($record->valid_from->isFuture()) {
                                            return 'Not yet started';
                                        }

                                        return 'Expired';
                                    })
                                    ->badge()
                                    ->color(function (PriceBook $record): string {
                                        if ($record->isWithinValidityPeriod()) {
                                            return $record->isExpiringSoon() ? 'warning' : 'success';
                                        }

                                        if ($record->valid_from->isFuture()) {
                                            return 'info';
                                        }

                                        return 'danger';
                                    }),
                            ]),
                    ]),

                Section::make('Coverage Statistics')
                    ->description('Price entries and coverage information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('entries_count')
                                    ->label('Total Entries')
                                    ->getStateUsing(fn (PriceBook $record): int => $record->entries()->count())
                                    ->badge()
                                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                                    ->icon(fn (int $state): string => $state === 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'),
                                TextEntry::make('manual_entries_count')
                                    ->label('Manual Prices')
                                    ->getStateUsing(fn (PriceBook $record): int => $record->entries()->where('source', PriceSource::Manual->value)->count())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('policy_entries_count')
                                    ->label('Policy Generated')
                                    ->getStateUsing(fn (PriceBook $record): int => $record->entries()->where('source', PriceSource::PolicyGenerated->value)->count())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Linked Entities')
                    ->description('Policies and offers referencing this price book')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('linked_policies_placeholder')
                            ->label('Linked Pricing Policies')
                            ->getStateUsing(fn (): string => 'Pricing policies targeting this price book will be displayed here once implemented (US-020+).')
                            ->color('gray'),
                        TextEntry::make('linked_offers_placeholder')
                            ->label('Linked Offers')
                            ->getStateUsing(fn (): string => 'Offers using this price book will be displayed here once implemented (US-033+).')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 2: Prices - Grid of price entries.
     */
    protected function getPricesTab(): Tab
    {
        /** @var PriceBook $record */
        $record = $this->record;
        $entriesCount = $record->entries()->count();

        return Tab::make('Prices')
            ->icon('heroicon-o-currency-euro')
            ->badge($entriesCount)
            ->badgeColor($entriesCount === 0 ? 'danger' : 'success')
            ->schema([
                Section::make('Price Entries')
                    ->description(fn (): string => $record->isEditable()
                        ? 'Price entries for this price book (editable in draft status)'
                        : 'Price entries for this price book (read-only - price book is not in draft status)')
                    ->schema([
                        TextEntry::make('prices_info')
                            ->label('')
                            ->getStateUsing(function (PriceBook $record): string {
                                $count = $record->entries()->count();
                                if ($count === 0) {
                                    return 'No price entries yet. Add prices using the Edit page or by running a Pricing Policy.';
                                }

                                return "This price book contains {$count} price entries.";
                            })
                            ->color(fn (PriceBook $record): string => $record->entries()->count() === 0 ? 'warning' : 'success')
                            ->columnSpanFull(),

                        RepeatableEntry::make('entries')
                            ->label('')
                            ->schema([
                                TextEntry::make('sellableSku.id')
                                    ->label('SKU ID')
                                    ->copyable()
                                    ->size(TextSize::Small),
                                TextEntry::make('base_price')
                                    ->label('Base Price')
                                    ->money(fn (): string => $this->getRecord() instanceof PriceBook ? $this->getRecord()->currency : 'EUR')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('source')
                                    ->label('Source')
                                    ->badge()
                                    ->formatStateUsing(fn (PriceSource $state): string => $state->label())
                                    ->color(fn (PriceSource $state): string => $state->color())
                                    ->icon(fn (PriceSource $state): string => $state->icon()),
                            ])
                            ->columns(3)
                            ->visible(fn (PriceBook $record): bool => $record->entries()->count() > 0),
                    ]),

                Section::make('Price Management')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('price_management_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Price entries can be added manually via the Edit page, imported from CSV, cloned from another Price Book, or generated by a Pricing Policy. Manual changes set the source to "Manual", while policy-generated prices track their source policy for traceability.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 3: Scope & Applicability - Market, channel, currency, priority rules.
     */
    protected function getScopeTab(): Tab
    {
        return Tab::make('Scope & Applicability')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Section::make('Geographic Scope')
                    ->description('Market and currency configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('market')
                                    ->label('Market')
                                    ->badge()
                                    ->color('info')
                                    ->size(TextSize::Large),
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge()
                                    ->color('gray')
                                    ->size(TextSize::Large),
                            ]),
                    ]),

                Section::make('Channel Applicability')
                    ->description('Which sales channels use this price book')
                    ->schema([
                        TextEntry::make('channel.name')
                            ->label('Assigned Channel')
                            ->placeholder('All Channels')
                            ->badge()
                            ->color(fn (PriceBook $record): string => $record->channel_id === null ? 'info' : 'success'),
                        TextEntry::make('channel_applicability_info')
                            ->label('')
                            ->getStateUsing(function (PriceBook $record): string {
                                if ($record->channel_id === null) {
                                    return '**Channel-agnostic:** This price book applies to all sales channels unless a channel-specific price book exists for the same market and currency.';
                                }

                                return '**Channel-specific:** This price book only applies to the assigned channel. It takes priority over channel-agnostic price books for the same market and currency.';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Priority Rules')
                    ->description('How price book selection works')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('priority_rules_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-2">
                                    <p><strong>Price Book Selection Priority:</strong></p>
                                    <ol class="list-decimal list-inside space-y-1">
                                        <li>Channel-specific price book for the exact market + currency + channel combination</li>
                                        <li>Channel-agnostic price book for the market + currency (if no channel-specific exists)</li>
                                    </ol>
                                    <p class="text-sm text-gray-500 mt-2">Only one active price book can exist for each unique combination. Activating a new price book may require archiving the existing one.</p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Lifecycle - Activation history, approval info, expiration.
     */
    protected function getLifecycleTab(): Tab
    {
        return Tab::make('Lifecycle')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Section::make('Current Status')
                    ->description('Current lifecycle state of this price book')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (PriceBookStatus $state): string => $state->label())
                                    ->color(fn (PriceBookStatus $state): string => $state->color())
                                    ->icon(fn (PriceBookStatus $state): string => $state->icon())
                                    ->size(TextSize::Large),
                                TextEntry::make('status_description')
                                    ->label('Status Description')
                                    ->getStateUsing(function (PriceBook $record): string {
                                        return match ($record->status) {
                                            PriceBookStatus::Draft => 'This price book is in draft status and not used for pricing. Edit prices and activate when ready.',
                                            PriceBookStatus::Active => 'This price book is active and used for pricing. Prices are read-only.',
                                            PriceBookStatus::Expired => 'This price book has expired and is no longer used for pricing.',
                                            PriceBookStatus::Archived => 'This price book is archived and preserved for historical reference.',
                                        };
                                    }),
                            ]),
                    ]),

                Section::make('Approval Information')
                    ->description('Approval details for this price book')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->dateTime()
                                    ->placeholder('Not approved yet'),
                                TextEntry::make('approver.name')
                                    ->label('Approved By')
                                    ->placeholder('Not approved yet'),
                            ]),
                        TextEntry::make('approval_info')
                            ->label('')
                            ->getStateUsing(function (PriceBook $record): string {
                                if ($record->isApproved()) {
                                    return 'This price book has been approved and activated.';
                                }

                                if ($record->isDraft()) {
                                    return 'This price book requires approval before activation. Use the "Activate" action to submit for approval.';
                                }

                                return 'Approval information not applicable for this status.';
                            })
                            ->color(fn (PriceBook $record): string => $record->isApproved() ? 'success' : 'warning')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status Transitions')
                    ->description('Valid state transitions for this price book')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('transitions_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-2">
                                    <p><strong>Status Transition Rules:</strong></p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li><strong>Draft → Active:</strong> Requires approval and at least one price entry</li>
                                        <li><strong>Active → Expired:</strong> Automatic when valid_to date passes, or manual</li>
                                        <li><strong>Expired → Archived:</strong> Manual action for long-term storage</li>
                                        <li><strong>Active → Archived:</strong> Direct archival (marks as no longer in use)</li>
                                    </ul>
                                    <p class="text-sm text-gray-500 mt-2">Once activated, prices become read-only. To modify prices, create a new version.</p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Editable State')
                    ->schema([
                        TextEntry::make('editable_status')
                            ->label('Editable')
                            ->getStateUsing(fn (PriceBook $record): string => $record->isEditable() ? 'Yes - Draft status allows editing' : 'No - Only draft price books can be edited')
                            ->badge()
                            ->color(fn (PriceBook $record): string => $record->isEditable() ? 'success' : 'gray')
                            ->icon(fn (PriceBook $record): string => $record->isEditable() ? 'heroicon-o-pencil' : 'heroicon-o-lock-closed'),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - Complete modification timeline.
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Audit History')
                    ->description(fn (): string => $this->getAuditFilterDescription())
                    ->headerActions([
                        Action::make('filter_audit')
                            ->label('Filter')
                            ->icon('heroicon-o-funnel')
                            ->schema([
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
                                $this->auditEventFilter = isset($data['event_type']) && is_string($data['event_type']) ? $data['event_type'] : null;
                                $this->auditDateFrom = isset($data['date_from']) && is_string($data['date_from']) ? $data['date_from'] : null;
                                $this->auditDateUntil = isset($data['date_until']) && is_string($data['date_until']) ? $data['date_until'] : null;
                            }),
                        Action::make('clear_filters')
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
                            ->getStateUsing(function (PriceBook $record): string {
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
                    ]),

                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this price book for compliance and traceability purposes.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Complete modification history'];

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

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (PriceBook $record): bool => $record->isEditable()),

            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (PriceBook $record): bool => $record->canBeActivated() && auth()->user()?->canApprovePriceBooks())
                ->requiresConfirmation()
                ->modalHeading('Activate Price Book')
                ->modalDescription(function (PriceBook $record): string {
                    $entriesCount = $record->entries()->count();
                    $messages = [];

                    if ($entriesCount === 0) {
                        return 'Cannot activate: This price book has no price entries. Add at least one price entry before activating.';
                    }

                    $messages[] = "This will activate the price book with {$entriesCount} price entries.";
                    $messages[] = 'Prices will become read-only after activation.';

                    // Check for overlapping active price books using model method
                    $overlappingBooks = $record->findOverlappingActivePriceBooks();

                    if ($overlappingBooks->isNotEmpty()) {
                        $messages[] = '';
                        $messages[] = '⚠️ WARNING: Active price book(s) already exist for this market/channel/currency:';
                        foreach ($overlappingBooks as $overlapping) {
                            $messages[] = "• \"{$overlapping->name}\" (valid from {$overlapping->valid_from->format('Y-m-d')})";
                        }
                        $messages[] = '';
                        $messages[] = 'Activating this price book will automatically expire the overlapping one(s).';
                    }

                    return implode("\n", $messages);
                })
                ->action(function (PriceBook $record): void {
                    $user = auth()->user();
                    if ($user === null) {
                        Notification::make()
                            ->danger()
                            ->title('Activation failed')
                            ->body('User not authenticated.')
                            ->send();

                        return;
                    }

                    try {
                        $service = app(PriceBookService::class);
                        $overlappingCount = $record->findOverlappingActivePriceBooks()->count();
                        $service->activate($record, $user);

                        $message = "The price book \"{$record->name}\" is now active.";
                        if ($overlappingCount > 0) {
                            $message .= " {$overlappingCount} overlapping price book(s) have been expired.";
                        }

                        Notification::make()
                            ->success()
                            ->title('Price Book activated')
                            ->body($message)
                            ->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Activation failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('activate_disabled')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->disabled()
                ->visible(fn (PriceBook $record): bool => $record->canBeActivated() && ! auth()->user()?->canApprovePriceBooks())
                ->tooltip('You need Manager role or higher to approve price books'),

            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn (PriceBook $record): bool => $record->canBeArchived())
                ->requiresConfirmation()
                ->modalHeading('Archive Price Book')
                ->modalDescription('This will archive the price book. It will no longer be used for pricing but will be preserved for historical reference.')
                ->action(function (PriceBook $record): void {
                    try {
                        $service = app(PriceBookService::class);
                        $service->archive($record);

                        Notification::make()
                            ->success()
                            ->title('Price Book archived')
                            ->body("The price book \"{$record->name}\" has been archived.")
                            ->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Archive failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        /** @var array<string, mixed> $oldValues */
        $oldValues = $log->old_values ?? [];
        /** @var array<string, mixed> $newValues */
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
                $fieldLabel = ucfirst(str_replace('_', ' ', (string) $field));
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

        if (is_string($value) || is_int($value) || is_float($value)) {
            $stringValue = (string) $value;
            if (strlen($stringValue) > 50) {
                return htmlspecialchars(substr($stringValue, 0, 47)).'...';
            }

            return htmlspecialchars($stringValue);
        }

        return '<em class="text-gray-400">[complex value]</em>';
    }
}
