<?php

namespace App\Filament\Resources\PricingPolicyResource\Pages;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\ExecutionType;
use App\Enums\Commercial\PolicyScopeType;
use App\Enums\Commercial\PricingPolicyInputSource;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Enums\Commercial\PricingPolicyType;
use App\Filament\Resources\PricingPolicyResource;
use App\Models\AuditLog;
use App\Models\Commercial\PricingPolicy;
use App\Models\Commercial\PricingPolicyExecution;
use App\Services\Commercial\PricingPolicyService;
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

class ViewPricingPolicy extends ViewRecord
{
    protected static string $resource = PricingPolicyResource::class;

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
        /** @var PricingPolicy $record */
        $record = $this->record;

        return "Pricing Policy: {$record->name}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Pricing Policy Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getLogicTab(),
                        $this->getScopeTab(),
                        $this->getExecutionTab(),
                        $this->getExecutionHistoryTab(),
                        $this->getImpactPreviewTab(),
                        $this->getLifecycleTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Summary, status, last execution result.
     */
    protected function getOverviewTab(): Tab
    {
        /** @var PricingPolicy $record */
        $record = $this->record;
        $latestExecution = $record->latestExecution();

        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Policy Information')
                    ->description('Core pricing policy configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Policy ID')
                                        ->copyable()
                                        ->copyMessage('ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextSize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('policy_type')
                                        ->label('Policy Type')
                                        ->badge()
                                        ->formatStateUsing(fn (PricingPolicyType $state): string => $state->label())
                                        ->color(fn (PricingPolicyType $state): string => $state->color())
                                        ->icon(fn (PricingPolicyType $state): string => $state->icon()),
                                    TextEntry::make('input_source')
                                        ->label('Input Source')
                                        ->badge()
                                        ->formatStateUsing(fn (PricingPolicyInputSource $state): string => $state->label())
                                        ->color(fn (PricingPolicyInputSource $state): string => $state->color()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (PricingPolicyStatus $state): string => $state->label())
                                        ->color(fn (PricingPolicyStatus $state): string => $state->color())
                                        ->icon(fn (PricingPolicyStatus $state): string => $state->icon()),
                                    TextEntry::make('execution_cadence')
                                        ->label('Execution Cadence')
                                        ->badge()
                                        ->formatStateUsing(fn (ExecutionCadence $state): string => $state->label())
                                        ->color(fn (ExecutionCadence $state): string => $state->color())
                                        ->icon(fn (ExecutionCadence $state): string => $state->icon()),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Target Price Book')
                    ->description('The price book that receives generated prices')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('targetPriceBook.name')
                                    ->label('Price Book Name')
                                    ->placeholder('No target price book assigned')
                                    ->weight(FontWeight::Medium)
                                    ->url(fn (PricingPolicy $record): ?string => $record->targetPriceBook
                                        ? route('filament.admin.resources.price-books.view', ['record' => $record->targetPriceBook->id])
                                        : null),
                                TextEntry::make('targetPriceBook.market')
                                    ->label('Market')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('-'),
                                TextEntry::make('targetPriceBook.currency')
                                    ->label('Currency')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->visible(fn (PricingPolicy $record): bool => $record->target_price_book_id !== null),

                Section::make('Formula Summary')
                    ->description('Plain-language description of pricing logic')
                    ->schema([
                        TextEntry::make('logic_description')
                            ->label('')
                            ->getStateUsing(fn (PricingPolicy $record): string => $record->getLogicDescription())
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('primary'),
                    ]),

                Section::make('Last Execution')
                    ->description('Most recent policy execution result')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('last_execution_status')
                                    ->label('Status')
                                    ->getStateUsing(fn (): ?string => $latestExecution?->status?->label())
                                    ->badge()
                                    ->color(fn (): string => $latestExecution?->status?->color() ?? 'gray')
                                    ->icon(fn (): string => $latestExecution?->status?->icon() ?? 'heroicon-o-minus')
                                    ->placeholder('Never executed'),
                                TextEntry::make('last_execution_type')
                                    ->label('Type')
                                    ->getStateUsing(fn (): ?string => $latestExecution?->execution_type?->label())
                                    ->badge()
                                    ->color(fn (): string => $latestExecution?->execution_type?->color() ?? 'gray')
                                    ->placeholder('-'),
                                TextEntry::make('last_executed_at')
                                    ->label('Executed At')
                                    ->dateTime()
                                    ->placeholder('Never'),
                                TextEntry::make('last_execution_result')
                                    ->label('Result')
                                    ->getStateUsing(function () use ($latestExecution): string {
                                        if ($latestExecution === null) {
                                            return '-';
                                        }

                                        return "{$latestExecution->prices_generated} prices, {$latestExecution->errors_count} errors";
                                    })
                                    ->color(fn () => $latestExecution?->hasErrors() ? 'warning' : 'success'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(fn (): bool => $latestExecution === null),

                Section::make('Statistics')
                    ->description('Execution history summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_executions')
                                    ->label('Total Executions')
                                    ->getStateUsing(fn (PricingPolicy $record): int => $record->executions()->count())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('successful_executions')
                                    ->label('Successful')
                                    ->getStateUsing(fn (PricingPolicy $record): int => $record->successfulExecutionsCount())
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('failed_executions')
                                    ->label('Failed')
                                    ->getStateUsing(fn (PricingPolicy $record): int => $record->failedExecutionsCount())
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->failedExecutionsCount() > 0 ? 'danger' : 'gray'),
                                TextEntry::make('generated_entries_count')
                                    ->label('Generated Prices')
                                    ->getStateUsing(fn (PricingPolicy $record): int => $record->generatedEntries()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 2: Logic - Inputs, calculations, rounding, formula preview.
     */
    protected function getLogicTab(): Tab
    {
        return Tab::make('Logic')
            ->icon('heroicon-o-calculator')
            ->schema([
                Section::make('Policy Type & Input')
                    ->description('What type of pricing logic is applied')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('policy_type')
                                    ->label('Policy Type')
                                    ->badge()
                                    ->formatStateUsing(fn (PricingPolicyType $state): string => $state->label())
                                    ->color(fn (PricingPolicyType $state): string => $state->color())
                                    ->icon(fn (PricingPolicyType $state): string => $state->icon())
                                    ->size(TextSize::Large),
                                TextEntry::make('input_source')
                                    ->label('Input Source')
                                    ->badge()
                                    ->formatStateUsing(fn (PricingPolicyInputSource $state): string => $state->label())
                                    ->color(fn (PricingPolicyInputSource $state): string => $state->color())
                                    ->size(TextSize::Large),
                            ]),
                        TextEntry::make('policy_type_description')
                            ->label('')
                            ->getStateUsing(fn (PricingPolicy $record): string => self::getPolicyTypeDescription($record->policy_type))
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Calculation Parameters')
                    ->description('Specific values used in price calculation')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('margin_percentage')
                                    ->label('Margin Percentage')
                                    ->getStateUsing(fn (PricingPolicy $record): ?string => $record->getMarginPercentage() !== null ? $record->getMarginPercentage().'%' : null)
                                    ->placeholder('Not set')
                                    ->badge()
                                    ->color('success')
                                    ->visible(fn (PricingPolicy $record): bool => $record->getMarginPercentage() !== null),
                                TextEntry::make('markup_value')
                                    ->label('Markup/Adjustment Value')
                                    ->getStateUsing(fn (PricingPolicy $record): ?string => $record->getMarkupValue() !== null ? $record->getMarkupValue().'%' : null)
                                    ->placeholder('Not set')
                                    ->badge()
                                    ->color('info')
                                    ->visible(fn (PricingPolicy $record): bool => $record->getMarkupValue() !== null),
                                TextEntry::make('rounding_rule')
                                    ->label('Rounding Rule')
                                    ->getStateUsing(fn (PricingPolicy $record): ?string => $record->getRoundingRule())
                                    ->placeholder('No rounding')
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn (PricingPolicy $record): bool => $record->getRoundingRule() !== null),
                            ]),
                    ]),

                Section::make('Tiered Logic')
                    ->description('Different calculations for different categories or price ranges')
                    ->schema([
                        TextEntry::make('tiered_logic_display')
                            ->label('')
                            ->getStateUsing(function (PricingPolicy $record): string {
                                $tieredLogic = $record->getTieredLogic();
                                if (empty($tieredLogic)) {
                                    return 'No tiered logic configured - uniform pricing applies to all SKUs.';
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($tieredLogic as $tier) {
                                    if (is_array($tier)) {
                                        $category = $tier['category'] ?? $tier['range'] ?? 'Tier';
                                        $value = $tier['margin'] ?? $tier['adjustment'] ?? $tier['value'] ?? '?';
                                        $html .= "<div class='p-2 bg-gray-100 dark:bg-gray-800 rounded'><strong>{$category}</strong>: {$value}%</div>";
                                    }
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn (PricingPolicy $record): bool => empty($record->getTieredLogic())),

                Section::make('Complete Logic Definition')
                    ->description('Raw configuration data (advanced)')
                    ->schema([
                        TextEntry::make('logic_definition_json')
                            ->label('')
                            ->getStateUsing(fn (PricingPolicy $record): string => json_encode($record->logic_definition, JSON_PRETTY_PRINT) ?: '{}')
                            ->copyable()
                            ->copyMessage('Logic definition copied')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Formula Preview')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('formula_preview')
                            ->label('')
                            ->getStateUsing(fn (PricingPolicy $record): string => $record->getLogicDescription())
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 3: Scope - Resolved SKUs, markets, channels.
     */
    protected function getScopeTab(): Tab
    {
        /** @var PricingPolicy $record */
        $record = $this->record;
        $scope = $record->scope;

        return Tab::make('Scope')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Section::make('Scope Definition')
                    ->description('What SKUs this policy applies to')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('scope_type')
                                    ->label('Scope Type')
                                    ->getStateUsing(fn (): ?string => $scope?->scope_type?->label())
                                    ->badge()
                                    ->color(fn (): string => $scope?->scope_type?->color() ?? 'gray')
                                    ->icon(fn (): string => $scope?->scope_type?->icon() ?? 'heroicon-o-minus')
                                    ->placeholder('No scope defined')
                                    ->size(TextSize::Large),
                                TextEntry::make('scope_reference')
                                    ->label('Scope Reference')
                                    ->getStateUsing(fn (): ?string => $scope?->scope_reference)
                                    ->placeholder('Not applicable')
                                    ->visible(fn (): bool => $scope !== null && $scope->scope_type !== PolicyScopeType::All),
                            ]),
                        TextEntry::make('scope_description')
                            ->label('')
                            ->getStateUsing(fn (): string => $scope?->getScopeDescription() ?? 'No scope defined for this policy.')
                            ->columnSpanFull()
                            ->color(fn (): string => $scope !== null ? 'success' : 'warning'),
                    ]),

                Section::make('Market Restrictions')
                    ->description('Geographic markets where this policy applies')
                    ->schema([
                        TextEntry::make('markets')
                            ->label('Allowed Markets')
                            ->getStateUsing(fn (): string => $scope?->getMarketsDisplayString() ?? 'All markets')
                            ->badge()
                            ->color(fn (): string => $scope?->hasMarketRestrictions() ? 'info' : 'gray'),
                        TextEntry::make('markets_count')
                            ->label('Markets Count')
                            ->getStateUsing(fn (): string => $scope?->hasMarketRestrictions()
                                ? $scope->getMarketsCount().' market(s)'
                                : 'No restrictions')
                            ->color('gray'),
                    ])
                    ->columns(2),

                Section::make('Channel Restrictions')
                    ->description('Sales channels where this policy applies')
                    ->schema([
                        TextEntry::make('channels')
                            ->label('Allowed Channels')
                            ->getStateUsing(function () use ($scope): string {
                                if ($scope === null || ! $scope->hasChannelRestrictions()) {
                                    return 'All channels';
                                }
                                $channels = $scope->channels ?? [];

                                return implode(', ', $channels);
                            })
                            ->badge()
                            ->color(fn (): string => $scope?->hasChannelRestrictions() ? 'info' : 'gray'),
                        TextEntry::make('channels_count')
                            ->label('Channels Count')
                            ->getStateUsing(fn (): string => $scope?->hasChannelRestrictions()
                                ? $scope->getChannelsCount().' channel(s)'
                                : 'No restrictions')
                            ->color('gray'),
                    ])
                    ->columns(2),

                Section::make('Scope Resolution')
                    ->description('SKUs affected by this policy')
                    ->schema([
                        TextEntry::make('scope_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'SKU resolution is performed at execution time. The actual count of affected SKUs depends on current allocations and inventory availability. Use the "Dry Run" action in the Execution tab to see the current resolved scope.')
                            ->color('gray')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Tab 4: Execution - Manual run button, scheduling info, dry run button.
     */
    protected function getExecutionTab(): Tab
    {
        /** @var PricingPolicy $record */
        $record = $this->record;

        return Tab::make('Execution')
            ->icon('heroicon-o-play')
            ->schema([
                Section::make('Execution Status')
                    ->description('Current execution readiness')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('can_execute')
                                    ->label('Can Execute')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canBeExecuted() ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canBeExecuted() ? 'success' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canBeExecuted() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                TextEntry::make('can_dry_run')
                                    ->label('Can Dry Run')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canDryRun() ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canDryRun() ? 'success' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canDryRun() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                TextEntry::make('status')
                                    ->label('Policy Status')
                                    ->badge()
                                    ->formatStateUsing(fn (PricingPolicyStatus $state): string => $state->label())
                                    ->color(fn (PricingPolicyStatus $state): string => $state->color())
                                    ->icon(fn (PricingPolicyStatus $state): string => $state->icon()),
                            ]),
                        TextEntry::make('execution_requirement')
                            ->label('')
                            ->getStateUsing(function (PricingPolicy $record): string {
                                if ($record->canBeExecuted()) {
                                    return '✅ This policy is active and ready for execution.';
                                }
                                if ($record->isDraft()) {
                                    return '⚠️ This policy is in Draft status. Activate it to enable execution.';
                                }
                                if ($record->isPaused()) {
                                    return '⚠️ This policy is Paused. Resume it to enable execution.';
                                }
                                if ($record->isArchived()) {
                                    return '❌ This policy is Archived. Archived policies cannot be executed.';
                                }

                                return 'Execution status unknown.';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Execution Cadence')
                    ->description('How and when this policy runs')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('execution_cadence')
                                    ->label('Cadence Type')
                                    ->badge()
                                    ->formatStateUsing(fn (ExecutionCadence $state): string => $state->label())
                                    ->color(fn (ExecutionCadence $state): string => $state->color())
                                    ->icon(fn (ExecutionCadence $state): string => $state->icon())
                                    ->size(TextSize::Large),
                                TextEntry::make('cadence_description')
                                    ->label('Description')
                                    ->getStateUsing(fn (PricingPolicy $record): string => self::getCadenceDescription($record))
                                    ->html(),
                            ]),
                    ]),

                Section::make('Schedule Configuration')
                    ->description('Scheduling details for automated execution')
                    ->schema([
                        TextEntry::make('schedule_info')
                            ->label('')
                            ->getStateUsing(function (PricingPolicy $record): string {
                                if (! $record->isScheduled()) {
                                    return 'This policy is not scheduled for automatic execution.';
                                }

                                $schedule = $record->logic_definition['schedule'] ?? [];
                                if (empty($schedule)) {
                                    return 'Scheduled execution is enabled but schedule details are not configured.';
                                }

                                $frequency = $schedule['frequency'] ?? 'unknown';
                                $time = $schedule['time'] ?? '00:00';
                                $dayOfWeek = $schedule['day_of_week'] ?? null;
                                $dayOfMonth = $schedule['day_of_month'] ?? null;

                                $html = '<div class="space-y-1">';
                                $html .= "<p><strong>Frequency:</strong> {$frequency}</p>";
                                $html .= "<p><strong>Time:</strong> {$time}</p>";
                                if ($dayOfWeek) {
                                    $html .= "<p><strong>Day of Week:</strong> {$dayOfWeek}</p>";
                                }
                                if ($dayOfMonth) {
                                    $html .= "<p><strong>Day of Month:</strong> {$dayOfMonth}</p>";
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (PricingPolicy $record): bool => $record->isScheduled()),

                Section::make('Event Triggers')
                    ->description('Events that trigger automatic execution')
                    ->schema([
                        TextEntry::make('event_triggers')
                            ->label('')
                            ->getStateUsing(function (PricingPolicy $record): string {
                                if (! $record->isEventTriggered()) {
                                    return 'This policy is not triggered by events.';
                                }

                                $triggers = $record->logic_definition['event_triggers'] ?? [];
                                if (empty($triggers)) {
                                    return 'Event-triggered execution is enabled but no triggers are configured.';
                                }

                                $triggerLabels = [
                                    'cost_change' => 'Cost Change - Executes when product costs are updated',
                                    'emp_update' => 'EMP Update - Executes when market prices are updated',
                                    'fx_change' => 'FX Rate Change - Executes when exchange rates change',
                                ];

                                $html = '<div class="space-y-2">';
                                foreach ($triggers as $trigger) {
                                    $label = $triggerLabels[$trigger] ?? ucfirst(str_replace('_', ' ', $trigger));
                                    $html .= "<div class='p-2 bg-blue-50 dark:bg-blue-900/20 rounded flex items-center gap-2'>";
                                    $html .= "<span class='text-blue-600 dark:text-blue-400'>⚡</span>";
                                    $html .= "<span>{$label}</span>";
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (PricingPolicy $record): bool => $record->isEventTriggered()),

                Section::make('Execution Actions')
                    ->description('Run this policy manually or preview results')
                    ->icon('heroicon-o-bolt')
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('execution_actions_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-3">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">
                                        <p class="font-medium">🔍 Dry Run</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Preview the results without writing to the Price Book. Available for draft, active, and paused policies.</p>
                                    </div>
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded">
                                        <p class="font-medium">▶️ Execute Now</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Run the policy and write generated prices to the target Price Book. Only available for active policies.</p>
                                    </div>
                                    <p class="text-sm text-gray-500">Use the header actions above to run these commands.</p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Execution History')
                    ->description('Recent policy executions')
                    ->schema([
                        RepeatableEntry::make('executions')
                            ->label('')
                            ->schema([
                                TextEntry::make('executed_at')
                                    ->label('Executed')
                                    ->dateTime()
                                    ->size(TextSize::Small),
                                TextEntry::make('execution_type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn (ExecutionType $state): string => $state->label())
                                    ->color(fn (ExecutionType $state): string => $state->color()),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (ExecutionStatus $state): string => $state->label())
                                    ->color(fn (ExecutionStatus $state): string => $state->color()),
                                TextEntry::make('prices_generated')
                                    ->label('Prices')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('errors_count')
                                    ->label('Errors')
                                    ->badge()
                                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                            ])
                            ->columns(5)
                            ->getStateUsing(fn (PricingPolicy $record) => $record->executions()->latest('executed_at')->limit(5)->get()),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 5: Execution History - Complete history of all policy executions.
     */
    protected function getExecutionHistoryTab(): Tab
    {
        /** @var PricingPolicy $record */
        $record = $this->record;
        $executionCount = $record->executions()->count();

        return Tab::make('Execution History')
            ->icon('heroicon-o-queue-list')
            ->badge($executionCount > 0 ? (string) $executionCount : null)
            ->schema([
                Section::make('All Executions')
                    ->description(fn (): string => "Complete execution history - {$executionCount} total executions")
                    ->icon('heroicon-o-clock')
                    ->schema([
                        RepeatableEntry::make('all_executions')
                            ->label('')
                            ->schema([
                                Grid::make(7)
                                    ->schema([
                                        TextEntry::make('executed_at')
                                            ->label('Date/Time')
                                            ->dateTime('M j, Y H:i:s')
                                            ->size(TextSize::Small),
                                        TextEntry::make('execution_type')
                                            ->label('Type')
                                            ->badge()
                                            ->formatStateUsing(fn (ExecutionType $state): string => $state->label())
                                            ->color(fn (ExecutionType $state): string => $state->color())
                                            ->icon(fn (ExecutionType $state): string => $state->icon()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (ExecutionStatus $state): string => $state->label())
                                            ->color(fn (ExecutionStatus $state): string => $state->color())
                                            ->icon(fn (ExecutionStatus $state): string => $state->icon()),
                                        TextEntry::make('skus_processed')
                                            ->label('SKUs Processed')
                                            ->badge()
                                            ->color('info'),
                                        TextEntry::make('prices_generated')
                                            ->label('Prices Generated')
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('errors_count')
                                            ->label('Errors')
                                            ->badge()
                                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                                        TextEntry::make('success_rate')
                                            ->label('Success Rate')
                                            ->getStateUsing(fn (PricingPolicyExecution $execution): string => $execution->getSuccessRate().'%')
                                            ->badge()
                                            ->color(fn (PricingPolicyExecution $execution): string => match (true) {
                                                $execution->getSuccessRate() >= 100 => 'success',
                                                $execution->getSuccessRate() >= 80 => 'warning',
                                                default => 'danger',
                                            }),
                                    ]),
                                TextEntry::make('log_summary_full')
                                    ->label('Execution Log')
                                    ->getStateUsing(fn (PricingPolicyExecution $execution): string => $execution->log_summary ?? 'No execution log available')
                                    ->columnSpanFull()
                                    ->prose()
                                    ->markdown(),
                            ])
                            ->getStateUsing(fn (PricingPolicy $policyRecord) => $policyRecord->executions()->latest('executed_at')->get())
                            ->contained(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Target Price Book')
                    ->description('View prices generated by this policy')
                    ->icon('heroicon-o-book-open')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('targetPriceBook.name')
                                    ->label('Price Book Name')
                                    ->placeholder('No target price book assigned')
                                    ->weight(FontWeight::Medium)
                                    ->url(fn (PricingPolicy $policyRecord): ?string => $policyRecord->targetPriceBook
                                        ? route('filament.admin.resources.price-books.view', ['record' => $policyRecord->targetPriceBook->id])
                                        : null)
                                    ->color('primary')
                                    ->icon('heroicon-o-arrow-top-right-on-square'),
                                TextEntry::make('targetPriceBook.market')
                                    ->label('Market')
                                    ->placeholder('—'),
                                TextEntry::make('targetPriceBook.currency')
                                    ->label('Currency')
                                    ->placeholder('—'),
                            ]),
                        TextEntry::make('price_book_link_info')
                            ->label('')
                            ->getStateUsing(fn (PricingPolicy $policyRecord): string => $policyRecord->targetPriceBook !== null
                                ? '<div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800"><p class="text-sm text-blue-700 dark:text-blue-300"><strong>Tip:</strong> Click the Price Book name above to view all prices generated by this policy. In the Prices tab, you can filter by source to see only policy-generated prices.</p></div>'
                                : '<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"><p class="text-sm text-yellow-700 dark:text-yellow-300"><strong>Note:</strong> No target Price Book is assigned to this policy. Generated prices will not be stored until a target is configured.</p></div>')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Execution Statistics')
                    ->description('Summary of execution performance')
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_executions')
                                    ->label('Total Executions')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->count())
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('successful_executions')
                                    ->label('Successful')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('status', ExecutionStatus::Success->value)->count())
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('partial_executions')
                                    ->label('Partial')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('status', ExecutionStatus::Partial->value)->count())
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('failed_executions')
                                    ->label('Failed')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('status', ExecutionStatus::Failed->value)->count())
                                    ->badge()
                                    ->color('danger'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('manual_executions')
                                    ->label('Manual')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('execution_type', ExecutionType::Manual->value)->count())
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('scheduled_executions')
                                    ->label('Scheduled')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('execution_type', ExecutionType::Scheduled->value)->count())
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('dry_run_executions')
                                    ->label('Dry Runs')
                                    ->getStateUsing(fn (PricingPolicy $policyRecord): int => $policyRecord->executions()->where('execution_type', ExecutionType::DryRun->value)->count())
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * Tab 6: Impact Preview - Old price vs new price, EMP delta, warnings.
     */
    protected function getImpactPreviewTab(): Tab
    {
        return Tab::make('Impact Preview')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Section::make('Preview Information')
                    ->description('Impact analysis for price changes')
                    ->schema([
                        TextEntry::make('impact_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-4">
                                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                        <h3 class="font-medium text-blue-800 dark:text-blue-200 mb-2">📊 Impact Preview</h3>
                                        <p class="text-sm text-blue-700 dark:text-blue-300">
                                            Impact preview shows the projected changes before execution. Use the "Dry Run" action to generate a preview of:
                                        </p>
                                        <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300 mt-2">
                                            <li>Current prices vs. new calculated prices</li>
                                            <li>Price differences (amount and percentage)</li>
                                            <li>Comparison with EMP (Estimated Market Price)</li>
                                            <li>SKUs affected by this policy</li>
                                        </ul>
                                    </div>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Price Comparison (Placeholder)')
                    ->description('Comparison of current vs projected prices')
                    ->schema([
                        TextEntry::make('price_comparison_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current Price</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">New Price</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">vs EMP</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                                    Run a "Dry Run" to populate this preview with actual data.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Warnings & Alerts')
                    ->description('Potential issues identified during preview')
                    ->schema([
                        TextEntry::make('warnings_placeholder')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-2">
                                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">
                                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                            <strong>ℹ️ Note:</strong> Warnings will appear here after running a Dry Run. Common warnings include:
                                        </p>
                                        <ul class="list-disc list-inside text-sm text-yellow-700 dark:text-yellow-300 mt-2">
                                            <li>Prices significantly different from EMP (>15% deviation)</li>
                                            <li>SKUs without cost data (for cost-plus policies)</li>
                                            <li>Missing reference price book entries</li>
                                            <li>Currency conversion issues</li>
                                        </ul>
                                    </div>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 6: Lifecycle - Status transitions, activation history.
     */
    protected function getLifecycleTab(): Tab
    {
        return Tab::make('Lifecycle')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Section::make('Current Status')
                    ->description('Current lifecycle state of this pricing policy')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (PricingPolicyStatus $state): string => $state->label())
                                    ->color(fn (PricingPolicyStatus $state): string => $state->color())
                                    ->icon(fn (PricingPolicyStatus $state): string => $state->icon())
                                    ->size(TextSize::Large),
                                TextEntry::make('status_description')
                                    ->label('Status Description')
                                    ->getStateUsing(function (PricingPolicy $record): string {
                                        return match ($record->status) {
                                            PricingPolicyStatus::Draft => 'This policy is in draft status and cannot be executed. Activate it when ready.',
                                            PricingPolicyStatus::Active => 'This policy is active and can be executed manually or automatically based on cadence settings.',
                                            PricingPolicyStatus::Paused => 'This policy is paused. Automatic executions are suspended but the policy can be resumed.',
                                            PricingPolicyStatus::Archived => 'This policy is archived and preserved for historical reference. It cannot be executed.',
                                        };
                                    }),
                            ]),
                    ]),

                Section::make('Available Transitions')
                    ->description('Actions available based on current status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('can_activate')
                                    ->label('Can Activate')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canBeActivated() ? 'Yes - Draft → Active' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canBeActivated() ? 'success' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canBeActivated() ? 'heroicon-o-check' : 'heroicon-o-x-mark'),
                                TextEntry::make('can_pause')
                                    ->label('Can Pause')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canBePaused() ? 'Yes - Active → Paused' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canBePaused() ? 'warning' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canBePaused() ? 'heroicon-o-pause' : 'heroicon-o-x-mark'),
                                TextEntry::make('can_resume')
                                    ->label('Can Resume')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canBeResumed() ? 'Yes - Paused → Active' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canBeResumed() ? 'success' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canBeResumed() ? 'heroicon-o-play' : 'heroicon-o-x-mark'),
                                TextEntry::make('can_archive')
                                    ->label('Can Archive')
                                    ->getStateUsing(fn (PricingPolicy $record): string => $record->canBeArchived() ? 'Yes - Active/Paused → Archived' : 'No')
                                    ->badge()
                                    ->color(fn (PricingPolicy $record): string => $record->canBeArchived() ? 'danger' : 'gray')
                                    ->icon(fn (PricingPolicy $record): string => $record->canBeArchived() ? 'heroicon-o-archive-box' : 'heroicon-o-x-mark'),
                            ]),
                    ]),

                Section::make('Status Transition Rules')
                    ->description('Valid state transitions for pricing policies')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('transitions_info')
                            ->label('')
                            ->getStateUsing(fn (): string => '
                                <div class="space-y-2">
                                    <p><strong>Status Transition Rules:</strong></p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li><strong>Draft → Active:</strong> Enables policy execution</li>
                                        <li><strong>Active → Paused:</strong> Temporarily suspends automatic execution</li>
                                        <li><strong>Paused → Active:</strong> Resumes normal operation</li>
                                        <li><strong>Active/Paused → Archived:</strong> Permanently disables the policy</li>
                                    </ul>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <strong>Note:</strong> Archived policies cannot be reactivated. Create a new policy by cloning if needed.
                                    </p>
                                </div>
                            ')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Editable State')
                    ->schema([
                        TextEntry::make('editable_status')
                            ->label('Editable')
                            ->getStateUsing(fn (PricingPolicy $record): string => $record->isEditable() ? 'Yes - Draft status allows editing' : 'No - Only draft policies can be edited')
                            ->badge()
                            ->color(fn (PricingPolicy $record): string => $record->isEditable() ? 'success' : 'gray')
                            ->icon(fn (PricingPolicy $record): string => $record->isEditable() ? 'heroicon-o-pencil' : 'heroicon-o-lock-closed'),
                    ]),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                TextEntry::make('last_executed_at')
                                    ->label('Last Executed')
                                    ->dateTime()
                                    ->placeholder('Never'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Tab 7: Audit - Immutable log changes and executions.
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
                            ->getStateUsing(function (PricingPolicy $record): string {
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

                Section::make('Execution Logs')
                    ->description('History of policy executions')
                    ->schema([
                        RepeatableEntry::make('executions')
                            ->label('')
                            ->schema([
                                TextEntry::make('executed_at')
                                    ->label('Date')
                                    ->dateTime(),
                                TextEntry::make('execution_type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn (ExecutionType $state): string => $state->label())
                                    ->color(fn (ExecutionType $state): string => $state->color())
                                    ->icon(fn (ExecutionType $state): string => $state->icon()),
                                TextEntry::make('status')
                                    ->label('Result')
                                    ->badge()
                                    ->formatStateUsing(fn (ExecutionStatus $state): string => $state->label())
                                    ->color(fn (ExecutionStatus $state): string => $state->color())
                                    ->icon(fn (ExecutionStatus $state): string => $state->icon()),
                                TextEntry::make('skus_processed')
                                    ->label('SKUs'),
                                TextEntry::make('prices_generated')
                                    ->label('Prices'),
                                TextEntry::make('errors_count')
                                    ->label('Errors')
                                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                                TextEntry::make('log_summary')
                                    ->label('Summary')
                                    ->placeholder('No summary')
                                    ->limit(100)
                                    ->tooltip(fn (PricingPolicyExecution $record): ?string => $record->log_summary),
                            ])
                            ->columns(7)
                            ->getStateUsing(fn (PricingPolicy $record) => $record->executions()->latest('executed_at')->get()),
                    ])
                    ->collapsible(),

                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs and execution logs are immutable and cannot be modified or deleted. They provide a complete history of all changes and executions for this pricing policy, ensuring compliance and traceability.')
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
                ->visible(fn (PricingPolicy $record): bool => $record->isEditable()),

            Action::make('dry_run')
                ->label('Dry Run')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->visible(fn (PricingPolicy $record): bool => $record->canDryRun())
                ->requiresConfirmation()
                ->modalHeading('Dry Run Preview')
                ->modalDescription(function (PricingPolicy $record): string {
                    $service = app(PricingPolicyService::class);
                    $skuCount = $service->resolveScope($record)->count();
                    $targetPriceBook = $record->targetPriceBook;
                    $targetName = $targetPriceBook !== null ? $targetPriceBook->name : 'None';

                    return "This will simulate the policy execution without writing any prices.\n\n"
                        ."• Target Price Book: {$targetName}\n"
                        ."• SKUs in scope: {$skuCount}\n\n"
                        .'The preview will show what prices would be generated.';
                })
                ->action(function (PricingPolicy $record): void {
                    $service = app(PricingPolicyService::class);

                    try {
                        $result = $service->execute($record, isDryRun: true);

                        if ($result->pricesGenerated === 0 && $result->skusProcessed === 0) {
                            Notification::make()
                                ->warning()
                                ->title('No SKUs in Scope')
                                ->body('No SKUs matched the policy scope. Check the scope configuration.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        $priceChanges = $result->getPriceChanges();
                        $changesPreview = '';
                        $displayCount = min(5, count($priceChanges));

                        for ($i = 0; $i < $displayCount; $i++) {
                            $change = $priceChanges[$i];
                            $currentPrice = $change['current_price'] ?? 'N/A';
                            $changeIndicator = '';
                            if ($change['change_percent'] !== null) {
                                $sign = $change['change_percent'] >= 0 ? '+' : '';
                                $changeIndicator = " ({$sign}{$change['change_percent']}%)";
                            }
                            $changesPreview .= "• {$change['sku_code']}: {$currentPrice} → {$change['new_price']}{$changeIndicator}\n";
                        }

                        if (count($priceChanges) > 5) {
                            $remaining = count($priceChanges) - 5;
                            $changesPreview .= "... and {$remaining} more\n";
                        }

                        $statusColor = $result->isSuccess() ? 'success' : ($result->isPartial() ? 'warning' : 'danger');

                        Notification::make()
                            ->color($statusColor)
                            ->title('Dry Run Complete')
                            ->body(
                                "**Results:**\n"
                                ."• SKUs processed: {$result->skusProcessed}\n"
                                ."• Prices calculated: {$result->pricesGenerated}\n"
                                ."• Errors: {$result->errorsCount}\n\n"
                                ."**Price Preview:**\n{$changesPreview}\n"
                                .'No prices were written to the Price Book.'
                            )
                            ->persistent()
                            ->send();

                        if ($result->hasErrors()) {
                            $errorPreview = '';
                            $errorDisplayCount = min(3, count($result->errors));
                            for ($i = 0; $i < $errorDisplayCount; $i++) {
                                $error = $result->errors[$i];
                                $errorPreview .= "• {$error['sku_code']}: {$error['error']}\n";
                            }
                            if (count($result->errors) > 3) {
                                $remaining = count($result->errors) - 3;
                                $errorPreview .= "... and {$remaining} more errors\n";
                            }

                            Notification::make()
                                ->warning()
                                ->title('Dry Run Errors')
                                ->body("Some SKUs could not be processed:\n{$errorPreview}")
                                ->persistent()
                                ->send();
                        }
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Dry Run Failed')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('execute')
                ->label('Execute Now')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (PricingPolicy $record): bool => $record->canBeExecuted())
                ->requiresConfirmation()
                ->modalHeading('Execute Policy')
                ->modalDescription(function (PricingPolicy $record): string {
                    $service = app(PricingPolicyService::class);
                    $skuCount = $service->resolveScope($record)->count();
                    $targetPriceBook = $record->targetPriceBook;
                    $targetName = $targetPriceBook !== null ? $targetPriceBook->name : 'None';

                    return "⚠️ **This action will write prices to the Price Book.**\n\n"
                        ."• Target Price Book: {$targetName}\n"
                        ."• SKUs in scope: {$skuCount}\n\n"
                        ."Prices with source 'policy_generated' will be updated or created.\n"
                        .'Consider running a Dry Run first to preview changes.';
                })
                ->action(function (PricingPolicy $record): void {
                    $service = app(PricingPolicyService::class);

                    try {
                        $result = $service->execute($record, isDryRun: false);

                        if ($result->pricesGenerated === 0 && $result->skusProcessed === 0) {
                            Notification::make()
                                ->warning()
                                ->title('No Prices Generated')
                                ->body('No SKUs matched the policy scope. Check the scope configuration.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        $statusColor = $result->isSuccess() ? 'success' : ($result->isPartial() ? 'warning' : 'danger');
                        $statusTitle = $result->isSuccess() ? 'Execution Complete' : ($result->isPartial() ? 'Execution Completed with Warnings' : 'Execution Failed');

                        Notification::make()
                            ->color($statusColor)
                            ->title($statusTitle)
                            ->body(
                                "**Results:**\n"
                                ."• SKUs processed: {$result->skusProcessed}\n"
                                ."• Prices written: {$result->pricesGenerated}\n"
                                ."• Errors: {$result->errorsCount}\n\n"
                                ."Target Price Book: {$record->targetPriceBook?->name}\n"
                                .'Execution logged for audit purposes.'
                            )
                            ->persistent()
                            ->send();

                        if ($result->hasErrors()) {
                            $errorPreview = '';
                            $errorDisplayCount = min(3, count($result->errors));
                            for ($i = 0; $i < $errorDisplayCount; $i++) {
                                $error = $result->errors[$i];
                                $errorPreview .= "• {$error['sku_code']}: {$error['error']}\n";
                            }
                            if (count($result->errors) > 3) {
                                $remaining = count($result->errors) - 3;
                                $errorPreview .= "... and {$remaining} more errors\n";
                            }

                            Notification::make()
                                ->warning()
                                ->title('Execution Errors')
                                ->body("Some SKUs could not be processed:\n{$errorPreview}")
                                ->persistent()
                                ->send();
                        }
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Execution Failed')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (PricingPolicy $record): bool => $record->canBeActivated())
                ->requiresConfirmation()
                ->modalHeading('Activate Policy')
                ->modalDescription('This will activate the policy, enabling it for execution based on its cadence settings.')
                ->action(function (PricingPolicy $record): void {
                    $record->update(['status' => PricingPolicyStatus::Active]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => PricingPolicyStatus::Draft->value],
                        'new_values' => ['status' => PricingPolicyStatus::Active->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Policy activated')
                        ->body("The policy \"{$record->name}\" is now active and can be executed.")
                        ->send();
                }),

            Action::make('pause')
                ->label('Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (PricingPolicy $record): bool => $record->canBePaused())
                ->requiresConfirmation()
                ->modalHeading('Pause Policy')
                ->modalDescription('This will pause the policy. Scheduled and event-triggered executions will be suspended.')
                ->action(function (PricingPolicy $record): void {
                    $record->update(['status' => PricingPolicyStatus::Paused]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => PricingPolicyStatus::Active->value],
                        'new_values' => ['status' => PricingPolicyStatus::Paused->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Policy paused')
                        ->body("The policy \"{$record->name}\" has been paused. Resume it to enable execution.")
                        ->send();
                }),

            Action::make('resume')
                ->label('Resume')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (PricingPolicy $record): bool => $record->canBeResumed())
                ->requiresConfirmation()
                ->modalHeading('Resume Policy')
                ->modalDescription('This will resume the policy, re-enabling scheduled and event-triggered executions.')
                ->action(function (PricingPolicy $record): void {
                    $record->update(['status' => PricingPolicyStatus::Active]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => PricingPolicyStatus::Paused->value],
                        'new_values' => ['status' => PricingPolicyStatus::Active->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Policy resumed')
                        ->body("The policy \"{$record->name}\" is now active again.")
                        ->send();
                }),

            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn (PricingPolicy $record): bool => $record->canBeArchived())
                ->requiresConfirmation()
                ->modalHeading('Archive Policy')
                ->modalDescription('This will archive the policy. Archived policies cannot be executed or reactivated. This action is permanent.')
                ->action(function (PricingPolicy $record): void {
                    $oldStatus = $record->status;
                    $record->update(['status' => PricingPolicyStatus::Archived]);
                    $record->auditLogs()->create([
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => $oldStatus->value],
                        'new_values' => ['status' => PricingPolicyStatus::Archived->value],
                        'user_id' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Policy archived')
                        ->body("The policy \"{$record->name}\" has been archived.")
                        ->send();
                }),
        ];
    }

    /**
     * Get description for a policy type.
     */
    protected static function getPolicyTypeDescription(PricingPolicyType $type): string
    {
        return match ($type) {
            PricingPolicyType::CostPlusMargin => '<div class="p-3 bg-green-50 dark:bg-green-900/20 rounded"><strong>Cost + Margin:</strong> Calculates prices by adding a margin percentage or fixed amount to the product cost.</div>',
            PricingPolicyType::ReferencePriceBook => '<div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded"><strong>Reference Price Book:</strong> Uses prices from another Price Book as a base, then applies adjustments.</div>',
            PricingPolicyType::IndexBased => '<div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded"><strong>Index-Based:</strong> Derives prices from external indices like EMP (Estimated Market Price) or FX rates.</div>',
            PricingPolicyType::FixedAdjustment => '<div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded"><strong>Fixed Adjustment:</strong> Applies a fixed percentage or amount adjustment to existing prices.</div>',
            PricingPolicyType::Rounding => '<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded"><strong>Rounding:</strong> Normalizes prices to specific endings (e.g., .99, .95) for psychological pricing.</div>',
        };
    }

    /**
     * Get description for execution cadence.
     */
    protected static function getCadenceDescription(PricingPolicy $record): string
    {
        return match ($record->execution_cadence) {
            ExecutionCadence::Manual => 'This policy runs only when manually triggered. Use the "Execute Now" action to run it.',
            ExecutionCadence::Scheduled => 'This policy runs automatically on a schedule. Check the Schedule Configuration section for details.',
            ExecutionCadence::EventTriggered => 'This policy runs automatically when specific events occur. Check the Event Triggers section for configured triggers.',
        };
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
