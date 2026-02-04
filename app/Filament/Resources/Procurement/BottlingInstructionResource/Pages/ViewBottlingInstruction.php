<?php

namespace App\Filament\Resources\Procurement\BottlingInstructionResource\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Filament\Resources\Procurement\BottlingInstructionResource;
use App\Models\AuditLog;
use App\Models\Procurement\BottlingInstruction;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewBottlingInstruction extends ViewRecord
{
    protected static string $resource = BottlingInstructionResource::class;

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
        /** @var BottlingInstruction $record */
        $record = $this->record;

        return "Bottling Instruction #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var BottlingInstruction $record */
        $record = $this->record;

        return $record->getProductLabel().' - '.$record->status->label();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Bottling Instruction Details')
                    ->tabs([
                        $this->getBottlingRulesTab(),
                        $this->getCustomerPreferencesTab(),
                        $this->getAllocationVoucherLinkageTab(),
                        $this->getPersonalisationFlagsTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Bottling Rules - allowed formats, case configurations, default rule, delivery location.
     */
    protected function getBottlingRulesTab(): Tab
    {
        return Tab::make('Bottling Rules')
            ->icon('heroicon-o-beaker')
            ->schema([
                Section::make('Status & Identity')
                    ->description('Current instruction status and identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Instruction ID')
                                        ->copyable()
                                        ->copyMessage('Instruction ID copied')
                                        ->weight(FontWeight::Bold),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (BottlingInstructionStatus $state): string => $state->label())
                                        ->color(fn (BottlingInstructionStatus $state): string => $state->color())
                                        ->icon(fn (BottlingInstructionStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('preference_status')
                                        ->label('Preference Status')
                                        ->badge()
                                        ->formatStateUsing(fn (BottlingPreferenceStatus $state): string => $state->label())
                                        ->color(fn (BottlingPreferenceStatus $state): string => $state->color())
                                        ->icon(fn (BottlingPreferenceStatus $state): string => $state->icon()),
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

                Section::make('Product')
                    ->description('Liquid product information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('product_label')
                                    ->label('Liquid Product')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => $record->getProductLabel())
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),
                                TextEntry::make('bottle_equivalents')
                                    ->label('Bottle Equivalents')
                                    ->numeric()
                                    ->badge()
                                    ->color('info')
                                    ->suffix(' bottles'),
                                TextEntry::make('liquidProduct.wineVariant.wineMaster.name')
                                    ->label('Wine')
                                    ->placeholder('Unknown'),
                            ]),
                    ]),

                Section::make('Allowed Formats')
                    ->description('Bottle formats permitted for this instruction')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('allowed_formats')
                                    ->label('Allowed Bottle Formats')
                                    ->badge()
                                    ->formatStateUsing(fn (array $state): string => implode(', ', $state))
                                    ->color('info')
                                    ->placeholder('None specified'),
                                TextEntry::make('allowed_case_configurations')
                                    ->label('Allowed Case Configurations')
                                    ->badge()
                                    ->formatStateUsing(fn (array $state): string => implode(', ', array_map(fn ($c) => $c.' bottles/case', $state)))
                                    ->color('info')
                                    ->placeholder('None specified'),
                            ]),
                    ]),

                Section::make('Default Bottling Rule')
                    ->description('Rule applied automatically if customer preferences are not received by deadline')
                    ->schema([
                        TextEntry::make('default_bottling_rule')
                            ->label('Default Rule')
                            ->placeholder('No default rule specified')
                            ->columnSpanFull(),
                        TextEntry::make('rule_explanation')
                            ->label('')
                            ->getStateUsing(fn (BottlingInstruction $record): string => $record->default_bottling_rule
                                ? 'This rule will be applied automatically to any vouchers without customer preferences after the deadline.'
                                : 'No default rule configured. Manual intervention will be required if preferences are not received.')
                            ->color('gray')
                            ->icon('heroicon-o-information-circle'),
                    ]),

                Section::make('Delivery Location')
                    ->description('Where the bottled product should be delivered')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('delivery_location')
                                    ->label('Delivery Location')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'main_warehouse' => 'Main Warehouse',
                                        'secondary_warehouse' => 'Secondary Warehouse',
                                        'bonded_warehouse' => 'Bonded Warehouse',
                                        'third_party_storage' => 'Third Party Storage',
                                        default => $state ?? 'Not specified',
                                    })
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-building-office-2'),
                                TextEntry::make('procurementIntent.preferred_inbound_location')
                                    ->label('Intent Preferred Location')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'main_warehouse' => 'Main Warehouse',
                                        'secondary_warehouse' => 'Secondary Warehouse',
                                        'bonded_warehouse' => 'Bonded Warehouse',
                                        'third_party_storage' => 'Third Party Storage',
                                        default => $state ?? 'Not specified',
                                    })
                                    ->placeholder('Not specified'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Customer Preferences - voucher count, preferences collected, missing preferences, countdown to deadline.
     */
    protected function getCustomerPreferencesTab(): Tab
    {
        return Tab::make('Customer Preferences')
            ->icon('heroicon-o-user-group')
            ->badge(fn (BottlingInstruction $record): ?string => $record->preference_status->isCollecting() ? '!' : null)
            ->badgeColor('warning')
            ->schema([
                Section::make('Deadline Countdown')
                    ->description('Time remaining for customer preference collection')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('bottling_deadline')
                                    ->label('Deadline')
                                    ->date()
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('days_until_deadline')
                                    ->label('Days Remaining')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => (string) $record->getDaysUntilDeadline())
                                    ->badge()
                                    ->suffix(' days')
                                    ->color(fn (BottlingInstruction $record): string => match ($record->getDeadlineUrgency()) {
                                        'critical' => 'danger',
                                        'warning' => 'warning',
                                        default => 'success',
                                    })
                                    ->icon(fn (BottlingInstruction $record): string => match ($record->getDeadlineUrgency()) {
                                        'critical' => 'heroicon-o-exclamation-triangle',
                                        'warning' => 'heroicon-o-clock',
                                        default => 'heroicon-o-check-circle',
                                    }),
                                TextEntry::make('deadline_status')
                                    ->label('Status')
                                    ->getStateUsing(function (BottlingInstruction $record): string {
                                        if ($record->isDeadlinePassed()) {
                                            return 'Deadline passed';
                                        }
                                        $days = $record->getDaysUntilDeadline();
                                        if ($days === 0) {
                                            return 'Due today';
                                        }
                                        if ($days < 14) {
                                            return 'Urgent - action required';
                                        }
                                        if ($days < 30) {
                                            return 'Approaching deadline';
                                        }

                                        return 'On track';
                                    })
                                    ->badge()
                                    ->color(fn (BottlingInstruction $record): string => match ($record->getDeadlineUrgency()) {
                                        'critical' => 'danger',
                                        'warning' => 'warning',
                                        default => 'success',
                                    }),
                            ]),
                    ]),

                Section::make('Preference Collection Progress')
                    ->description('Status of customer bottling preferences')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('preference_status_display')
                                    ->label('Collection Status')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => $record->preference_status->label())
                                    ->badge()
                                    ->color(fn (BottlingInstruction $record): string => $record->preference_status->color())
                                    ->icon(fn (BottlingInstruction $record): string => $record->preference_status->icon()),
                                TextEntry::make('voucher_count')
                                    ->label('Total Vouchers')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => (string) $record->bottle_equivalents)
                                    ->suffix(' linked')
                                    ->helperText('Based on bottle equivalents'),
                                TextEntry::make('preferences_collected')
                                    ->label('Preferences Collected')
                                    ->getStateUsing(function (BottlingInstruction $record): string {
                                        // Placeholder - actual collection would come from voucher preferences
                                        return match ($record->preference_status) {
                                            BottlingPreferenceStatus::Complete, BottlingPreferenceStatus::Defaulted => (string) $record->bottle_equivalents,
                                            BottlingPreferenceStatus::Partial => (string) (int) ($record->bottle_equivalents * 0.5),
                                            default => '0',
                                        };
                                    })
                                    ->suffix(' collected'),
                                TextEntry::make('preferences_missing')
                                    ->label('Missing Preferences')
                                    ->getStateUsing(function (BottlingInstruction $record): string {
                                        // Placeholder - actual collection would come from voucher preferences
                                        return match ($record->preference_status) {
                                            BottlingPreferenceStatus::Complete, BottlingPreferenceStatus::Defaulted => '0',
                                            BottlingPreferenceStatus::Partial => (string) (int) ($record->bottle_equivalents * 0.5),
                                            default => (string) $record->bottle_equivalents,
                                        };
                                    })
                                    ->suffix(' pending')
                                    ->color(fn (BottlingInstruction $record): string => $record->preference_status->isCollecting() ? 'warning' : 'gray'),
                            ]),
                    ]),

                Section::make('Preference Collection Status')
                    ->description('Current state of preference collection')
                    ->schema([
                        TextEntry::make('collection_explanation')
                            ->label('')
                            ->getStateUsing(function (BottlingInstruction $record): string {
                                return match ($record->preference_status) {
                                    BottlingPreferenceStatus::Pending => 'No customer preferences have been collected yet. Customers should be contacted to provide their bottling preferences before the deadline.',
                                    BottlingPreferenceStatus::Partial => 'Some customer preferences have been collected. Continue collecting preferences from remaining customers before the deadline.',
                                    BottlingPreferenceStatus::Complete => 'All customer preferences have been collected. The instruction is ready for execution.',
                                    BottlingPreferenceStatus::Defaulted => 'The deadline has passed and default bottling rules have been applied to vouchers without preferences.',
                                };
                            })
                            ->icon(fn (BottlingInstruction $record): string => $record->preference_status->icon())
                            ->iconColor(fn (BottlingInstruction $record): string => $record->preference_status->color()),
                        TextEntry::make('customer_portal_link')
                            ->label('Customer Portal')
                            ->getStateUsing(fn (): string => 'Customer preferences are collected via the external customer portal. Contact the customer success team for portal access links.')
                            ->color('gray')
                            ->icon('heroicon-o-globe-alt'),
                    ]),

                Section::make('Defaults Application')
                    ->description('Information about automatic defaults')
                    ->visible(fn (BottlingInstruction $record): bool => $record->hasDefaultsApplied())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('defaults_applied_at')
                                    ->label('Defaults Applied At')
                                    ->dateTime(),
                                TextEntry::make('default_note')
                                    ->label('')
                                    ->getStateUsing(fn (): string => 'Default bottling rules were applied automatically because some customer preferences were not received by the deadline.')
                                    ->color('danger')
                                    ->icon('heroicon-o-exclamation-circle'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Allocation/Voucher Linkage - source allocations, voucher batches (read-only).
     */
    protected function getAllocationVoucherLinkageTab(): Tab
    {
        return Tab::make('Allocation & Voucher Linkage')
            ->icon('heroicon-o-link')
            ->schema([
                Section::make('Source Procurement Intent')
                    ->description('The intent that initiated this bottling instruction')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('procurementIntent.id')
                                    ->label('Intent ID')
                                    ->copyable()
                                    ->copyMessage('Intent ID copied')
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (BottlingInstruction $record): ?string => $record->procurementIntent
                                        ? route('filament.admin.resources.procurement.procurement-intents.view', ['record' => $record->procurementIntent->id])
                                        : null)
                                    ->openUrlInNewTab(),
                                TextEntry::make('procurementIntent.status')
                                    ->label('Intent Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                    ->color(fn ($state): string => is_object($state) && method_exists($state, 'color') ? $state->color() : 'gray'),
                                TextEntry::make('procurementIntent.trigger_type')
                                    ->label('Trigger Type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                    ->color('info'),
                                TextEntry::make('procurementIntent.sourcing_model')
                                    ->label('Sourcing Model')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                    ->color('info'),
                            ]),
                    ]),

                Section::make('Source Allocations')
                    ->description('Allocations linked to this instruction (read-only from Module A)')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        TextEntry::make('allocation_info')
                            ->label('')
                            ->getStateUsing(function (BottlingInstruction $record): string {
                                // Try to extract allocation info from intent rationale
                                $rationale = $record->procurementIntent->rationale ?? '';
                                if (preg_match('/Allocation ID: ([a-f0-9-]+)/i', $rationale, $matches)) {
                                    return 'Source Allocation: '.$matches[1];
                                }

                                return 'Allocation information is managed in Module A. The source allocation determines the voucher lineage for this bottling instruction.';
                            })
                            ->icon('heroicon-o-information-circle')
                            ->iconColor('info'),
                        TextEntry::make('allocation_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Allocations are read-only from Module A (Allocations & Vouchers). Changes to allocations must be made in Module A.')
                            ->color('gray'),
                    ]),

                Section::make('Voucher Batch(es)')
                    ->description('Vouchers linked to this instruction (read-only from Module A)')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        TextEntry::make('voucher_info')
                            ->label('')
                            ->getStateUsing(function (BottlingInstruction $record): string {
                                // Try to extract voucher info from intent rationale
                                $rationale = $record->procurementIntent->rationale ?? '';
                                if (preg_match('/Voucher ID: ([a-f0-9-]+)/i', $rationale, $matches)) {
                                    return 'Linked Voucher: '.$matches[1];
                                }

                                return 'Voucher information is managed in Module A. The bottle_equivalents field ('.$record->bottle_equivalents.') represents the total voucher count linked to this instruction.';
                            })
                            ->icon('heroicon-o-ticket')
                            ->iconColor('info'),
                        TextEntry::make('voucher_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Vouchers are read-only from Module A (Allocations & Vouchers). Each voucher represents a customer entitlement that requires bottling preferences. Changes to vouchers must be made in Module A.')
                            ->color('gray'),
                    ]),

                Section::make('Module A Integration')
                    ->description('Integration with the Allocation & Voucher module')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('module_a_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This tab shows read-only linkages to Module A (Allocations & Vouchers). '
                                .'The data here is informational and cannot be modified directly. '
                                .'Module A is the authoritative source for allocation and voucher information. '
                                .'Bottling preferences collected here will be associated with the linked vouchers.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('warning'),
                    ]),
            ]);
    }

    /**
     * Tab 4: Personalisation Flags - personalised bottling, early binding, binding instruction preview.
     */
    protected function getPersonalisationFlagsTab(): Tab
    {
        return Tab::make('Personalisation')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Section::make('Personalisation Settings')
                    ->description('Configuration for personalised bottling')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('personalised_bottling_required')
                                    ->label('Personalised Bottling Required')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                TextEntry::make('personalised_explanation')
                                    ->label('Implication')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => $record->personalised_bottling_required
                                        ? 'Each bottle may have unique personalisation (e.g., custom labels, engravings). Individual customer preferences will be applied during bottling.'
                                        : 'Standard bottling without individual personalisation. All bottles will be identical.')
                                    ->color('gray'),
                            ]),
                    ]),

                Section::make('Early Binding Configuration')
                    ->description('Voucher-bottle binding timing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('early_binding_required')
                                    ->label('Early Binding Required')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-link' : 'heroicon-o-link-slash'),
                                TextEntry::make('binding_explanation')
                                    ->label('Implication')
                                    ->getStateUsing(fn (BottlingInstruction $record): string => $record->early_binding_required
                                        ? 'Voucher-to-bottle binding happens BEFORE bottling. Specific bottles will be assigned to vouchers before the bottling process begins.'
                                        : 'Voucher-to-bottle binding happens AFTER bottling. Bottles are assigned to vouchers post-production based on availability.')
                                    ->color('gray'),
                            ]),
                    ]),

                Section::make('Binding Instruction Preview')
                    ->description('Summary of how binding will occur')
                    ->schema([
                        TextEntry::make('binding_preview')
                            ->label('')
                            ->getStateUsing(function (BottlingInstruction $record): string {
                                $parts = [];

                                // Binding timing
                                if ($record->early_binding_required) {
                                    $parts[] = '• **Binding Timing**: Early (before bottling)';
                                    $parts[] = '• Vouchers will be assigned to specific bottles BEFORE production';
                                    $parts[] = '• This ensures traceability from voucher to physical bottle';
                                } else {
                                    $parts[] = '• **Binding Timing**: Late (after bottling)';
                                    $parts[] = '• Bottles will be assigned to vouchers AFTER production';
                                    $parts[] = '• This provides flexibility in fulfillment';
                                }

                                // Personalisation
                                if ($record->personalised_bottling_required) {
                                    $parts[] = '• **Personalisation**: Individual customer preferences will be applied';
                                    $parts[] = '• Each bottle may have unique characteristics based on voucher holder preferences';
                                } else {
                                    $parts[] = '• **Personalisation**: Standard bottling (no individual customisation)';
                                }

                                // Formats
                                if (! empty($record->allowed_formats)) {
                                    $parts[] = '• **Allowed Formats**: '.implode(', ', $record->allowed_formats);
                                }

                                // Default rule
                                if ($record->default_bottling_rule) {
                                    $parts[] = '• **Default Rule**: Will be applied to vouchers without preferences after deadline';
                                }

                                return implode("\n", $parts);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Process Flow')
                    ->description('Expected bottling process based on current configuration')
                    ->schema([
                        TextEntry::make('process_flow')
                            ->label('')
                            ->getStateUsing(function (BottlingInstruction $record): string {
                                $steps = [];

                                $steps[] = '1. **Preference Collection** - Customers submit bottling preferences via portal';

                                if ($record->early_binding_required) {
                                    $steps[] = '2. **Early Binding** - Assign vouchers to specific bottle allocations';
                                    $steps[] = '3. **Bottling** - Produce bottles with bound voucher requirements';
                                } else {
                                    $steps[] = '2. **Bottling** - Produce bottles according to format specifications';
                                    $steps[] = '3. **Late Binding** - Assign produced bottles to vouchers';
                                }

                                if ($record->personalised_bottling_required) {
                                    $steps[] = '4. **Personalisation** - Apply individual customer preferences';
                                }

                                $steps[] = ($record->personalised_bottling_required ? '5' : '4').'. **Quality Check** - Verify bottles meet specifications';
                                $steps[] = ($record->personalised_bottling_required ? '6' : '5').'. **Handoff** - Transfer to inventory/fulfillment';

                                return implode("\n", $steps);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - deadline enforcement events, default application event, status changes.
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-document-magnifying-glass')
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
                                        AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                                        AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
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
                            ->getStateUsing(function (BottlingInstruction $record): string {
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

                Section::make('Key Events')
                    ->description('Important lifecycle events for this instruction')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                TextEntry::make('defaults_applied_at')
                                    ->label('Defaults Applied')
                                    ->dateTime()
                                    ->placeholder('Not applied')
                                    ->color(fn (BottlingInstruction $record): string => $record->hasDefaultsApplied() ? 'danger' : 'gray'),
                            ]),
                    ]),

                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. '
                                .'They provide a complete history of all changes to this bottling instruction for compliance and traceability purposes. '
                                .'Key events tracked include: creation, activation, preference status updates, default rule application, and execution.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('gray'),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
                AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                AuditLog::EVENT_FLAG_CHANGE => 'Flag Changed',
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
     * Get the header actions for the view page.
     *
     * @return array<Actions\Action|Actions\ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        /** @var BottlingInstruction $record */
        $record = $this->record;

        return [
            // Activate action (Draft → Active)
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Bottling Instruction')
                ->modalDescription('Are you sure you want to activate this bottling instruction? Once active, customer preference collection will begin and the deadline countdown starts.')
                ->visible(fn (): bool => $record->isDraft())
                ->action(function () use ($record): void {
                    if (! $record->status->canTransitionTo(BottlingInstructionStatus::Active)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid transition')
                            ->body('Cannot transition from '.$record->status->label().' to Active')
                            ->send();

                        return;
                    }

                    $oldStatus = $record->status->value;
                    $record->status = BottlingInstructionStatus::Active;
                    $record->save();

                    // Create audit log
                    AuditLog::create([
                        'auditable_type' => BottlingInstruction::class,
                        'auditable_id' => $record->id,
                        'user_id' => auth()->id(),
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => $oldStatus],
                        'new_values' => ['status' => BottlingInstructionStatus::Active->value],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Instruction Activated')
                        ->body("Bottling Instruction #{$record->id} has been activated. Preference collection is now open.")
                        ->send();

                    $this->redirect(BottlingInstructionResource::getUrl('view', ['record' => $record]));
                }),

            // Mark Executed action (Active → Executed)
            Actions\Action::make('mark_executed')
                ->label('Mark Executed')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark Instruction as Executed')
                ->modalDescription(function () use ($record): string {
                    $message = 'This will mark the bottling instruction as executed, indicating bottling has been completed.';

                    if ($record->preference_status->isCollecting()) {
                        $message .= "\n\n⚠️ Warning: Preference collection is still in progress ({$record->preference_status->label()}). Consider waiting for preferences to be complete or applying defaults first.";
                    }

                    return $message;
                })
                ->visible(fn (): bool => $record->isActive())
                ->action(function () use ($record): void {
                    if (! $record->status->canTransitionTo(BottlingInstructionStatus::Executed)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid transition')
                            ->body('Cannot transition from '.$record->status->label().' to Executed')
                            ->send();

                        return;
                    }

                    $oldStatus = $record->status->value;
                    $record->status = BottlingInstructionStatus::Executed;
                    $record->save();

                    // Create audit log
                    AuditLog::create([
                        'auditable_type' => BottlingInstruction::class,
                        'auditable_id' => $record->id,
                        'user_id' => auth()->id(),
                        'event' => AuditLog::EVENT_STATUS_CHANGE,
                        'old_values' => ['status' => $oldStatus],
                        'new_values' => ['status' => BottlingInstructionStatus::Executed->value],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Instruction Executed')
                        ->body("Bottling Instruction #{$record->id} has been marked as executed.")
                        ->send();

                    $this->redirect(BottlingInstructionResource::getUrl('view', ['record' => $record]));
                }),

            // View linked intent action
            Actions\Action::make('view_intent')
                ->label('View Intent')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn (): ?string => $record->procurementIntent
                    ? route('filament.admin.resources.procurement.procurement-intents.view', ['record' => $record->procurementIntent->id])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $record->procurementIntent !== null),

            // More actions (delete, restore)
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
            ])->label('More')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
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
