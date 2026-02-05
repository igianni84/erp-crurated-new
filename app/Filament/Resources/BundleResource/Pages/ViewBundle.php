<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Enums\Commercial\BundleStatus;
use App\Filament\Resources\BundleResource;
use App\Models\AuditLog;
use App\Models\Commercial\Bundle;
use App\Models\Commercial\PriceBook;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ViewBundle extends ViewRecord
{
    protected static string $resource = BundleResource::class;

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Bundle Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getPricingTab(),
                        $this->getComponentsTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString('tab')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab: Overview
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Bundle Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Bundle ID')
                                    ->copyable()
                                    ->fontFamily('mono'),
                                TextEntry::make('name')
                                    ->label('Name')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('bundle_sku')
                                    ->label('Bundle SKU')
                                    ->copyable()
                                    ->fontFamily('mono'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (BundleStatus $state): string => $state->label())
                                    ->color(fn (BundleStatus $state): string => $state->color())
                                    ->icon(fn (BundleStatus $state): string => $state->icon()),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Components Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('components_count_display')
                                    ->label('Unique SKUs')
                                    ->getStateUsing(fn (Bundle $record): int => $record->getComponentsCount())
                                    ->badge()
                                    ->color(fn (Bundle $record): string => $record->getComponentsCount() > 0 ? 'info' : 'gray'),
                                TextEntry::make('total_quantity_display')
                                    ->label('Total Items')
                                    ->getStateUsing(fn (Bundle $record): int => $record->getTotalComponentQuantity())
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('components_valid_display')
                                    ->label('All Components Valid')
                                    ->getStateUsing(function (Bundle $record): string {
                                        $components = $record->components()->with('sellableSku')->get();
                                        if ($components->isEmpty()) {
                                            return 'No components';
                                        }

                                        $allValid = true;
                                        foreach ($components as $component) {
                                            if (! $component->isValid()) {
                                                $allValid = false;
                                                break;
                                            }
                                        }

                                        return $allValid ? 'Yes' : 'No';
                                    })
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Yes' => 'success',
                                        'No' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('activation_status_display')
                                    ->label('Can Activate')
                                    ->getStateUsing(fn (Bundle $record): string => $record->canBeActivated() ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'gray'),
                            ]),
                    ]),

                Section::make('Component List')
                    ->schema([
                        TextEntry::make('components_list_display')
                            ->label('')
                            ->getStateUsing(function (Bundle $record): HtmlString {
                                $components = $record->components()
                                    ->with(['sellableSku.wineVariant.wineMaster', 'sellableSku.format', 'sellableSku.caseConfiguration'])
                                    ->get();

                                if ($components->isEmpty()) {
                                    return new HtmlString(
                                        '<div class="text-gray-500 dark:text-gray-400 italic">'.
                                        'No components in this bundle. Add components to enable activation.'.
                                        '</div>'
                                    );
                                }

                                $rows = [];
                                foreach ($components as $component) {
                                    $sku = $component->sellableSku;
                                    $isValid = $component->isValid();
                                    $statusIcon = $isValid
                                        ? '<span class="text-green-500">✓</span>'
                                        : '<span class="text-red-500">✗</span>';

                                    $wineVariant = $sku?->wineVariant;
                                    $wineName = 'Unknown';
                                    $vintage = null;
                                    if ($wineVariant !== null) {
                                        $wineMaster = $wineVariant->wineMaster;
                                        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                        $vintage = $wineVariant->vintage_year;
                                    }
                                    $format = $sku?->format?->volume_ml;
                                    $caseConfig = $sku?->caseConfiguration;

                                    $skuCode = $sku !== null ? $sku->sku_code : 'Unknown';
                                    $rows[] = '<tr class="border-b dark:border-gray-700">'.
                                        '<td class="py-2 font-mono text-sm">'.e($skuCode).'</td>'.
                                        '<td class="py-2">'.e($wineName).' '.e((string) $vintage).'</td>'.
                                        '<td class="py-2 text-center">'.($format !== null ? e((string) $format).'ml' : 'N/A').'</td>'.
                                        '<td class="py-2 text-center">'.($caseConfig !== null ? e((string) $caseConfig->bottles_per_case).' btl' : 'N/A').'</td>'.
                                        '<td class="py-2 text-center font-medium">'.e((string) $component->quantity).'</td>'.
                                        '<td class="py-2 text-center">'.$statusIcon.'</td>'.
                                        '</tr>';
                                }

                                return new HtmlString(
                                    '<table class="w-full text-sm">'.
                                    '<thead class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">'.
                                    '<tr>'.
                                    '<th class="py-2">SKU Code</th>'.
                                    '<th class="py-2">Wine</th>'.
                                    '<th class="py-2 text-center">Format</th>'.
                                    '<th class="py-2 text-center">Packaging</th>'.
                                    '<th class="py-2 text-center">Qty</th>'.
                                    '<th class="py-2 text-center">Valid</th>'.
                                    '</tr>'.
                                    '</thead>'.
                                    '<tbody>'.implode('', $rows).'</tbody>'.
                                    '</table>'
                                );
                            })
                            ->html(),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab: Pricing
     */
    protected function getPricingTab(): Tab
    {
        return Tab::make('Pricing')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Section::make('Pricing Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('pricing_logic')
                                    ->label('Pricing Strategy')
                                    ->badge()
                                    ->formatStateUsing(fn (Bundle $record): string => $record->getPricingLogicLabel())
                                    ->color(fn (Bundle $record): string => $record->getPricingLogicColor())
                                    ->icon(fn (Bundle $record): string => $record->getPricingLogicIcon()),
                                TextEntry::make('pricing_summary_display')
                                    ->label('Configuration')
                                    ->getStateUsing(fn (Bundle $record): string => $record->getPricingSummary()),
                            ]),
                    ]),

                Section::make('Pricing Details')
                    ->schema([
                        TextEntry::make('pricing_explanation_display')
                            ->label('')
                            ->getStateUsing(function (Bundle $record): HtmlString {
                                $logic = $record->pricing_logic;
                                $description = $logic->description();
                                $example = $logic->example();

                                $fixedPriceHtml = '';
                                if ($record->isFixedPrice() && $record->fixed_price !== null) {
                                    $fixedPriceHtml = '<div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">'.
                                        '<span class="text-sm text-green-700 dark:text-green-300">Fixed Price:</span>'.
                                        '<span class="ml-2 font-bold text-xl">€ '.number_format((float) $record->fixed_price, 2).'</span>'.
                                        '</div>';
                                }

                                $percentageHtml = '';
                                if ($record->isPercentageOffSum() && $record->percentage_off !== null) {
                                    $percentageHtml = '<div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">'.
                                        '<span class="text-sm text-amber-700 dark:text-amber-300">Discount:</span>'.
                                        '<span class="ml-2 font-bold text-xl">'.number_format((float) $record->percentage_off, 0).'% off</span>'.
                                        '</div>';
                                }

                                return new HtmlString(
                                    '<div class="space-y-4">'.
                                    '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">'.
                                    '<h4 class="font-medium text-gray-700 dark:text-gray-300">'.$logic->label().'</h4>'.
                                    '<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">'.$description.'</p>'.
                                    '<p class="mt-2 text-xs text-gray-500 dark:text-gray-500 italic">Example: '.$example.'</p>'.
                                    '</div>'.
                                    $fixedPriceHtml.
                                    $percentageHtml.
                                    '</div>'
                                );
                            })
                            ->html(),
                    ]),

                Section::make('Computed Price Preview')
                    ->schema([
                        TextEntry::make('price_preview_display')
                            ->label('')
                            ->getStateUsing(function (Bundle $record): HtmlString {
                                $components = $record->components()->with('sellableSku')->get();

                                if ($components->isEmpty()) {
                                    return new HtmlString(
                                        '<div class="text-gray-500 dark:text-gray-400 italic">'.
                                        'Add components to see price preview'.
                                        '</div>'
                                    );
                                }

                                // Get active price books to show potential prices
                                $priceBooks = PriceBook::query()
                                    ->where('status', 'active')
                                    ->orderBy('name')
                                    ->limit(5)
                                    ->get();

                                if ($priceBooks->isEmpty()) {
                                    return new HtmlString(
                                        '<div class="text-amber-600 dark:text-amber-400">'.
                                        '<p>No active Price Books found.</p>'.
                                        '<p class="text-sm mt-1">Computed prices will be available once Price Books are active.</p>'.
                                        '</div>'
                                    );
                                }

                                $rows = [];
                                foreach ($priceBooks as $priceBook) {
                                    $componentPrices = [];
                                    $hasAllPrices = true;

                                    foreach ($components as $component) {
                                        $sku = $component->sellableSku;
                                        if ($sku === null) {
                                            $hasAllPrices = false;
                                            break;
                                        }

                                        $entry = $priceBook->entries()->where('sellable_sku_id', $sku->id)->first();
                                        if ($entry === null) {
                                            $hasAllPrices = false;
                                            break;
                                        }

                                        $componentPrices[] = (float) $entry->base_price * $component->quantity;
                                    }

                                    if ($hasAllPrices) {
                                        $sumPrice = array_sum($componentPrices);
                                        $finalPrice = $record->calculateBundlePrice($sumPrice);
                                        $savings = $sumPrice - $finalPrice;
                                        $savingsPercent = $sumPrice > 0 ? ($savings / $sumPrice) * 100 : 0;

                                        $savingsHtml = $savings > 0
                                            ? '<span class="text-green-600 dark:text-green-400 text-sm">(Save € '.number_format($savings, 2).' / '.number_format($savingsPercent, 0).'%)</span>'
                                            : '';

                                        $rows[] = '<tr class="border-b dark:border-gray-700">'.
                                            '<td class="py-2">'.e($priceBook->name).'</td>'.
                                            '<td class="py-2 text-center">'.$priceBook->currency.'</td>'.
                                            '<td class="py-2 text-right">€ '.number_format($sumPrice, 2).'</td>'.
                                            '<td class="py-2 text-right font-bold">€ '.number_format($finalPrice, 2).' '.$savingsHtml.'</td>'.
                                            '</tr>';
                                    } else {
                                        $rows[] = '<tr class="border-b dark:border-gray-700 text-gray-400">'.
                                            '<td class="py-2">'.e($priceBook->name).'</td>'.
                                            '<td class="py-2 text-center">'.$priceBook->currency.'</td>'.
                                            '<td class="py-2 text-center" colspan="2">Missing component prices</td>'.
                                            '</tr>';
                                    }
                                }

                                return new HtmlString(
                                    '<table class="w-full text-sm">'.
                                    '<thead class="text-left text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">'.
                                    '<tr>'.
                                    '<th class="py-2">Price Book</th>'.
                                    '<th class="py-2 text-center">Currency</th>'.
                                    '<th class="py-2 text-right">Sum of Components</th>'.
                                    '<th class="py-2 text-right">Bundle Price</th>'.
                                    '</tr>'.
                                    '</thead>'.
                                    '<tbody>'.implode('', $rows).'</tbody>'.
                                    '</table>'
                                );
                            })
                            ->html(),
                    ])
                    ->collapsible()
                    ->description('Preview of bundle prices based on active Price Books'),
            ]);
    }

    /**
     * Tab: Components (managed via RelationManager)
     */
    protected function getComponentsTab(): Tab
    {
        /** @var Bundle $record */
        $record = $this->getRecord();

        return Tab::make('Manage Components')
            ->icon('heroicon-o-cube')
            ->badge(fn (): ?int => $record->getComponentsCount() > 0 ? $record->getComponentsCount() : null)
            ->schema([
                Section::make()
                    ->schema([
                        TextEntry::make('components_info_display')
                            ->label('')
                            ->getStateUsing(function () use ($record): HtmlString {
                                if ($record->isEditable()) {
                                    return new HtmlString(
                                        '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 mb-4">'.
                                        '<div class="flex items-start gap-3">'.
                                        '<svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">'.
                                        '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>'.
                                        '</svg>'.
                                        '<div>'.
                                        '<h4 class="font-medium text-blue-800 dark:text-blue-200">Edit Components</h4>'.
                                        '<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">'.
                                        'This bundle is in Draft status. You can add, remove, or modify components below. '.
                                        'Use the table to manage bundle components.'.
                                        '</p>'.
                                        '</div>'.
                                        '</div>'.
                                        '</div>'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 mb-4">'.
                                    '<div class="flex items-start gap-3">'.
                                    '<svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">'.
                                    '<path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>'.
                                    '</svg>'.
                                    '<div>'.
                                    '<h4 class="font-medium text-gray-700 dark:text-gray-300">Components Locked</h4>'.
                                    '<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">'.
                                    'This bundle is no longer in Draft status. Components cannot be modified. '.
                                    'To change components, deactivate the bundle first or create a new bundle.'.
                                    '</p>'.
                                    '</div>'.
                                    '</div>'.
                                    '</div>'
                                );
                            })
                            ->html(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Bundle $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible($record->isEditable()),

            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Bundle')
                ->modalDescription(function () use ($record): string {
                    $componentsCount = $record->getComponentsCount();

                    return "Are you sure you want to activate this bundle?\n\n".
                        "• Name: {$record->name}\n".
                        "• Components: {$componentsCount}\n".
                        "• Pricing: {$record->getPricingSummary()}\n\n".
                        'After activation, components cannot be modified.';
                })
                ->modalSubmitActionLabel('Activate')
                ->visible($record->isDraft() && $record->hasComponents())
                ->disabled(! $record->canBeActivated())
                ->action(function () use ($record): void {
                    // Validate all components
                    $components = $record->components()->with('sellableSku')->get();
                    $invalidComponents = [];

                    foreach ($components as $component) {
                        $errors = $component->validate();
                        if (! empty($errors)) {
                            $invalidComponents[] = $component->getSkuCode().': '.implode(', ', $errors);
                        }
                    }

                    if (! empty($invalidComponents)) {
                        Notification::make()
                            ->title('Cannot Activate')
                            ->body('Some components have validation errors: '.implode('; ', $invalidComponents))
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->status = BundleStatus::Active;
                    $record->save();

                    Notification::make()
                        ->title('Bundle Activated')
                        ->body('The bundle "'.$record->name.'" is now active.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Deactivate Bundle')
                ->modalDescription('Are you sure you want to deactivate this bundle? The bundle will become inactive and cannot be used in offers.')
                ->modalSubmitActionLabel('Deactivate')
                ->visible($record->canBeDeactivated())
                ->action(function () use ($record): void {
                    $record->status = BundleStatus::Inactive;
                    $record->save();

                    Notification::make()
                        ->title('Bundle Deactivated')
                        ->body('The bundle "'.$record->name.'" is now inactive.')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Reactivate Bundle')
                ->modalDescription('Are you sure you want to reactivate this bundle?')
                ->modalSubmitActionLabel('Reactivate')
                ->visible($record->isInactive())
                ->action(function () use ($record): void {
                    // Check if all components are still valid
                    $components = $record->components()->with('sellableSku')->get();
                    $invalidComponents = [];

                    foreach ($components as $component) {
                        $errors = $component->validate();
                        if (! empty($errors)) {
                            $invalidComponents[] = $component->getSkuCode().': '.implode(', ', $errors);
                        }
                    }

                    if (! empty($invalidComponents)) {
                        Notification::make()
                            ->title('Cannot Reactivate')
                            ->body('Some components have validation errors: '.implode('; ', $invalidComponents))
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->status = BundleStatus::Active;
                    $record->save();

                    Notification::make()
                        ->title('Bundle Reactivated')
                        ->body('The bundle "'.$record->name.'" is active again.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->visible($record->isDraft()),
        ];
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
                            ->getStateUsing(function (Bundle $record): string {
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
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this bundle for compliance and traceability purposes. Events include creation, updates, and status changes.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes made to this bundle'];

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
