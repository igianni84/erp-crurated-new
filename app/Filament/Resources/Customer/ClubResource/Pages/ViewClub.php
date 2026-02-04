<?php

namespace App\Filament\Resources\Customer\ClubResource\Pages;

use App\Enums\Customer\ClubStatus;
use App\Filament\Resources\Customer\ClubResource;
use App\Models\AuditLog;
use App\Models\Customer\Club;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewClub extends ViewRecord
{
    protected static string $resource = ClubResource::class;

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
        /** @var Club $record */
        $record = $this->record;

        return "Club: {$record->partner_name}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Club $record */
        $record = $this->record;

        return $record->status->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            $this->getActivateAction(),
            $this->getSuspendAction(),
            $this->getEndAction(),
        ];
    }

    /**
     * Activate club action.
     */
    protected function getActivateAction(): Actions\Action
    {
        /** @var Club $record */
        $record = $this->record;

        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Activate Club')
            ->modalDescription('Are you sure you want to activate this club?')
            ->modalSubmitActionLabel('Activate')
            ->action(function () use ($record): void {
                $record->update(['status' => ClubStatus::Active]);

                Notification::make()
                    ->title('Club activated')
                    ->body('The club has been activated successfully.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status === ClubStatus::Suspended);
    }

    /**
     * Suspend club action.
     */
    protected function getSuspendAction(): Actions\Action
    {
        /** @var Club $record */
        $record = $this->record;

        return Actions\Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Suspend Club')
            ->modalDescription('Are you sure you want to suspend this club? Members will not be able to use club benefits.')
            ->modalSubmitActionLabel('Suspend')
            ->action(function () use ($record): void {
                $record->update(['status' => ClubStatus::Suspended]);

                Notification::make()
                    ->title('Club suspended')
                    ->body('The club has been suspended.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status === ClubStatus::Active);
    }

    /**
     * End club action.
     */
    protected function getEndAction(): Actions\Action
    {
        /** @var Club $record */
        $record = $this->record;

        return Actions\Action::make('end')
            ->label('End Club')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('End Club')
            ->modalDescription('Are you sure you want to end this club? This action cannot be undone easily.')
            ->modalSubmitActionLabel('End Club')
            ->action(function () use ($record): void {
                $record->update(['status' => ClubStatus::Ended]);

                Notification::make()
                    ->title('Club ended')
                    ->body('The club has been marked as ended.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status !== ClubStatus::Ended);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Club Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getMembersTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab Overview: identity summary, status, branding metadata.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-identification')
            ->schema([
                Section::make('Club Information')
                    ->description('Basic club information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Club ID')
                                        ->copyable()
                                        ->copyMessage('Club ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('partner_name')
                                        ->label('Partner Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (ClubStatus $state): string => $state->label())
                                        ->color(fn (ClubStatus $state): string => $state->color())
                                        ->icon(fn (ClubStatus $state): string => $state->icon()),
                                    TextEntry::make('members_count')
                                        ->label('Active Members')
                                        ->getStateUsing(fn (Club $record): int => $record->getActiveMembersCount())
                                        ->badge()
                                        ->color('info'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Branding Metadata')
                    ->description('Custom branding configuration for this club')
                    ->collapsible()
                    ->schema([
                        KeyValueEntry::make('branding_metadata')
                            ->label('')
                            ->placeholder('No branding metadata configured'),
                    ]),
            ]);
    }

    /**
     * Tab Members: list of club members.
     */
    protected function getMembersTab(): Tab
    {
        /** @var Club $record */
        $record = $this->record;
        $membersCount = $record->getActiveMembersCount();

        return Tab::make('Members')
            ->icon('heroicon-o-users')
            ->badge($membersCount > 0 ? (string) $membersCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Club Members')
                    ->description('Customers affiliated with this club')
                    ->schema([
                        TextEntry::make('members_list')
                            ->label('')
                            ->getStateUsing(function (Club $record): string {
                                $affiliations = $record->activeCustomerAffiliations()
                                    ->with('customer')
                                    ->get();

                                if ($affiliations->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No active members in this club.</div>';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($affiliations as $affiliation) {
                                    $customer = $affiliation->customer;
                                    $customerName = $customer !== null ? htmlspecialchars($customer->getName()) : 'Unknown Customer';
                                    $startDate = $affiliation->start_date->format('M d, Y');
                                    $statusColor = $affiliation->isEffective()
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    $statusLabel = $affiliation->isEffective() ? 'Effective' : 'Not Effective';

                                    $html .= <<<HTML
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div>
                                            <span class="font-medium">{$customerName}</span>
                                            <span class="text-sm text-gray-500 ml-2">Since: {$startDate}</span>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$statusColor}">
                                            {$statusLabel}
                                        </span>
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
     * Tab Audit: timeline read-only di tutte le modifiche.
     */
    protected function getAuditTab(): Tab
    {
        /** @var Club $record */
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
                            ->getStateUsing(function (Club $record): string {
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
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this club for compliance and traceability purposes.')
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
        $parts = ['Immutable audit trail of all changes to this club'];

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
}
