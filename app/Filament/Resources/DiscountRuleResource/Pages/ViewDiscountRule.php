<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource;
use App\Models\AuditLog;
use App\Models\Commercial\DiscountRule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

class ViewDiscountRule extends ViewRecord
{
    protected static string $resource = DiscountRuleResource::class;

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

    protected function getHeaderActions(): array
    {
        /** @var DiscountRule $record */
        $record = $this->getRecord();

        return [
            EditAction::make()
                ->visible(fn (): bool => $record->canBeEdited()),
            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Discount Rule')
                ->modalDescription('Are you sure you want to activate this discount rule?')
                ->visible(fn (): bool => $record->isInactive())
                ->action(function () use ($record): void {
                    $record->status = DiscountRuleStatus::Active;
                    $record->save();

                    Notification::make()
                        ->title('Discount rule activated')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Deactivate Discount Rule')
                ->modalDescription(fn (): string => $record->hasActiveOffersUsing()
                    ? 'Warning: This rule is used by active Offers. Deactivating it may affect pricing.'
                    : 'Are you sure you want to deactivate this discount rule?')
                ->visible(fn (): bool => $record->isActive() && $record->canBeDeactivated())
                ->action(function () use ($record): void {
                    $record->status = DiscountRuleStatus::Inactive;
                    $record->save();

                    Notification::make()
                        ->title('Discount rule deactivated')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $record->canBeDeleted())
                ->before(function (DeleteAction $action) use ($record): void {
                    if ($record->isReferencedByAnyOffer()) {
                        Notification::make()
                            ->title('Cannot delete')
                            ->body('This rule is referenced by Offers and cannot be deleted.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Tabs::make('Discount Rule Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getLogicTab(),
                        $this->getUsageTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString('tab')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab: Overview - Basic rule information.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Rule Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Name')
                                    ->weight('bold')
                                    ->size(TextSize::Large),
                                TextEntry::make('rule_type')
                                    ->label('Rule Type')
                                    ->badge()
                                    ->formatStateUsing(fn (DiscountRuleType $state): string => $state->label())
                                    ->color(fn (DiscountRuleType $state): string => $state->color())
                                    ->icon(fn (DiscountRuleType $state): string => $state->icon()),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (DiscountRuleStatus $state): string => $state->label())
                                    ->color(fn (DiscountRuleStatus $state): string => $state->color())
                                    ->icon(fn (DiscountRuleStatus $state): string => $state->icon()),
                                TextEntry::make('summary')
                                    ->label('Summary')
                                    ->getStateUsing(fn (DiscountRule $record): string => $record->getSummary())
                                    ->icon('heroicon-o-document-text'),
                            ]),
                    ]),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    /**
     * Tab: Logic - Discount calculation details.
     */
    protected function getLogicTab(): Tab
    {
        return Tab::make('Logic')
            ->icon('heroicon-o-calculator')
            ->schema([
                Section::make('Logic Configuration')
                    ->description('The discount calculation logic for this rule.')
                    ->schema([
                        TextEntry::make('rule_type_description')
                            ->label('How it works')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->rule_type->description())
                            ->columnSpanFull(),

                        // Percentage/Fixed Amount value
                        TextEntry::make('discount_value')
                            ->label(fn (DiscountRule $record): string => $record->isPercentage() ? 'Discount Percentage' : 'Discount Amount')
                            ->getStateUsing(function (DiscountRule $record): string {
                                $value = $record->getValue();
                                if ($value === null) {
                                    return 'Not configured';
                                }

                                return $record->isPercentage()
                                    ? number_format($value, 1).'%'
                                    : '€'.number_format($value, 2);
                            })
                            ->visible(fn (DiscountRule $record): bool => $record->isPercentage() || $record->isFixedAmount())
                            ->weight('bold')
                            ->size(TextSize::Large)
                            ->color('success'),

                        // Tiered logic display
                        TextEntry::make('tiers_display')
                            ->label('Price Tiers')
                            ->getStateUsing(fn (DiscountRule $record): HtmlString => $this->formatTiersDisplay($record))
                            ->html()
                            ->visible(fn (DiscountRule $record): bool => $record->isTiered())
                            ->columnSpanFull(),

                        // Volume-based thresholds display
                        TextEntry::make('thresholds_display')
                            ->label('Quantity Thresholds')
                            ->getStateUsing(fn (DiscountRule $record): HtmlString => $this->formatThresholdsDisplay($record))
                            ->html()
                            ->visible(fn (DiscountRule $record): bool => $record->isVolumeBased())
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab: Usage - Offers using this rule.
     */
    protected function getUsageTab(): Tab
    {
        return Tab::make('Usage')
            ->icon('heroicon-o-link')
            ->badge(fn (DiscountRule $record): ?int => $record->getOffersUsingCount() > 0 ? $record->getOffersUsingCount() : null)
            ->schema([
                Section::make('Usage')
                    ->description('Offers using this discount rule.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('offers_using_count')
                                    ->label('Total Offers Using')
                                    ->getStateUsing(fn (DiscountRule $record): string => (string) $record->getOffersUsingCount())
                                    ->badge()
                                    ->color(fn (DiscountRule $record): string => $record->getOffersUsingCount() > 0 ? 'info' : 'gray'),
                                TextEntry::make('active_offers_using_count')
                                    ->label('Active Offers Using')
                                    ->getStateUsing(fn (DiscountRule $record): string => (string) $record->getActiveOffersUsingCount())
                                    ->badge()
                                    ->color(fn (DiscountRule $record): string => $record->getActiveOffersUsingCount() > 0 ? 'success' : 'gray'),
                            ]),
                        TextEntry::make('editability')
                            ->label('Edit Status')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->canBeEdited()
                                ? 'This rule can be edited'
                                : 'This rule cannot be edited (active Offers are using it)')
                            ->icon(fn (DiscountRule $record): string => $record->canBeEdited()
                                ? 'heroicon-o-pencil-square'
                                : 'heroicon-o-lock-closed')
                            ->color(fn (DiscountRule $record): string => $record->canBeEdited() ? 'success' : 'warning')
                            ->columnSpanFull(),
                        TextEntry::make('deletability')
                            ->label('Delete Status')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->canBeDeleted()
                                ? 'This rule can be deleted'
                                : 'This rule cannot be deleted (referenced by Offers)')
                            ->icon(fn (DiscountRule $record): string => $record->canBeDeleted()
                                ? 'heroicon-o-trash'
                                : 'heroicon-o-shield-exclamation')
                            ->color(fn (DiscountRule $record): string => $record->canBeDeleted() ? 'danger' : 'warning')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab: Audit - Immutable timeline of events.
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
                            ->getStateUsing(function (DiscountRule $record): string {
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
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this discount rule for compliance and traceability purposes. Events include creation, updates, and status changes.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes made to this discount rule'];

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

    /**
     * Format tiered logic for display.
     */
    private function formatTiersDisplay(DiscountRule $record): HtmlString
    {
        $tiers = $record->getTiers();

        if (empty($tiers)) {
            return new HtmlString('<span class="text-gray-500">No tiers configured</span>');
        }

        $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tier</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price Range</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($tiers as $i => $tier) {
            $minVal = $tier['min'] ?? null;
            $maxVal = $tier['max'] ?? null;
            $min = ($minVal !== null) ? '€'.number_format((float) $minVal, 2) : '€0.00';
            $max = ($maxVal !== null) ? '€'.number_format((float) $maxVal, 2) : '∞';
            $value = number_format((float) ($tier['value'] ?? 0), 1).'%';

            $html .= '<tr>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Tier '.($i + 1).'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">'.$min.' - '.$max.'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm"><span class="inline-flex items-center rounded-full bg-success-100 dark:bg-success-900/20 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:text-success-200">'.$value.' off</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Format volume-based thresholds for display.
     */
    private function formatThresholdsDisplay(DiscountRule $record): HtmlString
    {
        $thresholds = $record->getThresholds();

        if (empty($thresholds)) {
            return new HtmlString('<span class="text-gray-500">No thresholds configured</span>');
        }

        $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Threshold</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Minimum Quantity</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount Amount</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($thresholds as $i => $threshold) {
            $minQty = (int) $threshold['min_qty'];
            $value = '€'.number_format((float) $threshold['value'], 2);

            $html .= '<tr>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Threshold '.($i + 1).'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">≥ '.$minQty.' units</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm"><span class="inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-900/20 px-2.5 py-0.5 text-xs font-medium text-warning-800 dark:text-warning-200">'.$value.' off</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return new HtmlString($html);
    }
}
