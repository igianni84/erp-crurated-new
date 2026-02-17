<?php

namespace App\Filament\Resources\ChannelResource\Pages;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Filament\Resources\ChannelResource;
use App\Models\AuditLog;
use App\Models\Commercial\Channel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
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

class ViewChannel extends ViewRecord
{
    protected static string $resource = ChannelResource::class;

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
        /** @var Channel $record */
        $record = $this->record;

        return "Channel: {$record->name}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Channel Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getPriceBooksTab(),
                        $this->getOffersTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Channel configuration details.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Channel Information')
                    ->description('Core channel configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Channel ID')
                                        ->copyable()
                                        ->copyMessage('Channel ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextSize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('channel_type')
                                        ->label('Channel Type')
                                        ->badge()
                                        ->formatStateUsing(fn (ChannelType $state): string => $state->label())
                                        ->color(fn (ChannelType $state): string => $state->color())
                                        ->icon(fn (ChannelType $state): string => $state->icon()),
                                    TextEntry::make('default_currency')
                                        ->label('Default Currency')
                                        ->weight(FontWeight::Medium),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (ChannelStatus $state): string => $state->label())
                                        ->color(fn (ChannelStatus $state): string => $state->color())
                                        ->icon(fn (ChannelStatus $state): string => $state->icon()),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Allowed Commercial Models')
                    ->description('Commercial models permitted for this channel')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('voucher_based_status')
                                    ->label('Voucher Based')
                                    ->badge()
                                    ->getStateUsing(fn (Channel $record): string => $record->allowsVoucherBased() ? 'Enabled' : 'Disabled')
                                    ->color(fn (Channel $record): string => $record->allowsVoucherBased() ? 'success' : 'gray')
                                    ->icon(fn (Channel $record): string => $record->allowsVoucherBased() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                TextEntry::make('sell_through_status')
                                    ->label('Sell Through')
                                    ->badge()
                                    ->getStateUsing(fn (Channel $record): string => $record->allowsSellThrough() ? 'Enabled' : 'Disabled')
                                    ->color(fn (Channel $record): string => $record->allowsSellThrough() ? 'success' : 'gray')
                                    ->icon(fn (Channel $record): string => $record->allowsSellThrough() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                            ]),
                    ]),
                Section::make('Channel Configuration')
                    ->description('Channels are stable sales contexts that rarely change')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('info')
                    ->schema([
                        TextEntry::make('configuration_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Channels define the context for commercial operations. They determine which Price Books and Offers apply to sales transactions. Changes to channel configuration should be made carefully as they affect all associated pricing and offers.')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 2: Price Books - List of Price Books applicable to this channel.
     */
    protected function getPriceBooksTab(): Tab
    {
        return Tab::make('Price Books')
            ->icon('heroicon-o-book-open')
            ->schema([
                Section::make('Associated Price Books')
                    ->description('Price Books that apply to this channel')
                    ->schema([
                        TextEntry::make('price_books_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Price Books associated with this channel will be displayed here once the Price Book functionality is implemented (US-009+). Price Books define base prices for Sellable SKUs within specific markets and currencies.')
                            ->html()
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),
                Section::make('Price Book Information')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('price_book_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Price Books are channel-specific or channel-agnostic pricing containers. A Price Book can be associated with a specific channel (limiting its applicability) or be general-purpose. Only one active Price Book can exist for a given market+channel+currency combination.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 3: Offers - List of active Offers on this channel.
     */
    protected function getOffersTab(): Tab
    {
        return Tab::make('Offers')
            ->icon('heroicon-o-tag')
            ->schema([
                Section::make('Active Offers')
                    ->description('Offers currently active on this channel')
                    ->schema([
                        TextEntry::make('offers_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Offers active on this channel will be displayed here once the Offer functionality is implemented (US-033+). Offers activate sellability for Sellable SKUs, combining a Price Book price with optional benefits (discounts, promotions).')
                            ->html()
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),
                Section::make('Offer Information')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('offer_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Offers are the activation mechanism for selling Sellable SKUs. Each Offer is specific to one Sellable SKU and one Channel. Offers reference Price Books for base pricing and can apply additional benefits like discounts. Only active Offers make products available for sale.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Audit - Immutable timeline of events.
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
                                $this->auditEventFilter = $data['event_type'] ?? null;
                                $this->auditDateFrom = $data['date_from'] ?? null;
                                $this->auditDateUntil = $data['date_until'] ?? null;
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
                            ->getStateUsing(function (Channel $record): string {
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
                    ]),
                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this channel for compliance and traceability purposes. Events include creation, updates, and status changes.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes made to this channel'];

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
            EditAction::make(),
        ];
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
