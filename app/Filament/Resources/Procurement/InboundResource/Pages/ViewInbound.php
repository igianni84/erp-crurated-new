<?php

namespace App\Filament\Resources\Procurement\InboundResource\Pages;

use App\Enums\Procurement\InboundPackaging;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Filament\Resources\Procurement\InboundResource;
use App\Models\AuditLog;
use App\Models\Procurement\Inbound;
use App\Services\Procurement\InboundService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

class ViewInbound extends ViewRecord
{
    protected static string $resource = InboundResource::class;

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
        /** @var Inbound $record */
        $record = $this->record;

        return "Inbound #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Inbound $record */
        $record = $this->record;

        return $record->getProductLabel().' - '.$record->status->label();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Inbound Details')
                    ->tabs([
                        $this->getPhysicalReceiptTab(),
                        $this->getSourcingContextTab(),
                        $this->getSerializationRoutingTab(),
                        $this->getDownstreamHandoffTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Physical Receipt - warehouse, date, quantity, packaging, condition notes.
     */
    protected function getPhysicalReceiptTab(): Tab
    {
        return Tab::make('Physical Receipt')
            ->icon('heroicon-o-inbox-arrow-down')
            ->schema([
                Section::make('Status & Identity')
                    ->description('Current inbound status and identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Inbound ID')
                                        ->copyable()
                                        ->copyMessage('Inbound ID copied')
                                        ->weight(FontWeight::Bold),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (InboundStatus $state): string => $state->label())
                                        ->color(fn (InboundStatus $state): string => $state->color())
                                        ->icon(fn (InboundStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Recorded At')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('ownership_flag')
                                        ->label('Ownership')
                                        ->badge()
                                        ->formatStateUsing(fn (OwnershipFlag $state): string => $state->label())
                                        ->color(fn (OwnershipFlag $state): string => $state->color())
                                        ->icon(fn (OwnershipFlag $state): string => $state->icon()),
                                ])->columnSpan(1),
                            ]),
                    ]),

                Section::make('Receiving Location')
                    ->description('Where goods were physically received')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('warehouse')
                                    ->label('Warehouse')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'main_warehouse' => 'Main Warehouse',
                                        'secondary_warehouse' => 'Secondary Warehouse',
                                        'bonded_warehouse' => 'Bonded Warehouse',
                                        'third_party_storage' => 'Third Party Storage',
                                        default => $state ?? 'Unknown',
                                    })
                                    ->color('gray')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('received_date')
                                    ->label('Received Date')
                                    ->date()
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),

                Section::make('Product & Quantity')
                    ->description('What was received')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('product_label')
                                    ->label('Product')
                                    ->getStateUsing(fn (Inbound $record): string => $record->getProductLabel())
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),
                                TextEntry::make('product_reference_type')
                                    ->label('Product Type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => $state === 'sellable_skus' ? 'Bottle SKU' : 'Liquid Product')
                                    ->color(fn (string $state): string => $state === 'sellable_skus' ? 'info' : 'warning')
                                    ->icon(fn (string $state): string => $state === 'sellable_skus' ? 'heroicon-o-cube' : 'heroicon-o-beaker'),
                                TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->badge()
                                    ->color('info')
                                    ->suffix(fn (Inbound $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                                TextEntry::make('packaging')
                                    ->label('Packaging')
                                    ->badge()
                                    ->formatStateUsing(fn (InboundPackaging $state): string => $state->label())
                                    ->color(fn (InboundPackaging $state): string => $state->color())
                                    ->icon(fn (InboundPackaging $state): string => $state->icon()),
                            ]),
                    ]),

                Section::make('Condition Notes')
                    ->description('Notes about the physical condition of goods')
                    ->schema([
                        TextEntry::make('condition_notes')
                            ->label('')
                            ->placeholder('No condition notes recorded')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 2: Sourcing Context - linked intent(s), linked PO, ownership status.
     */
    protected function getSourcingContextTab(): Tab
    {
        return Tab::make('Sourcing Context')
            ->icon('heroicon-o-link')
            ->badge(fn (Inbound $record): ?string => $record->isUnlinked() ? '!' : null)
            ->badgeColor('warning')
            ->schema([
                Section::make('Ownership Status')
                    ->description('Explicit ownership clarification (required before hand-off)')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('ownership_flag')
                                    ->label('Ownership Flag')
                                    ->badge()
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->formatStateUsing(fn (OwnershipFlag $state): string => $state->label())
                                    ->color(fn (OwnershipFlag $state): string => $state->color())
                                    ->icon(fn (OwnershipFlag $state): string => $state->icon()),
                                TextEntry::make('ownership_explanation')
                                    ->label('Explanation')
                                    ->getStateUsing(fn (Inbound $record): string => match ($record->ownership_flag) {
                                        OwnershipFlag::Owned => 'We own these goods. They are our inventory asset.',
                                        OwnershipFlag::InCustody => 'We hold but do not own these goods. Ownership remains with another party.',
                                        OwnershipFlag::Pending => 'Ownership status must be clarified before completion or hand-off.',
                                    })
                                    ->columnSpan(2),
                            ]),

                        // Warning for pending ownership
                        TextEntry::make('ownership_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => '⚠️ Ownership must be clarified before this inbound can be completed or handed off to Module B.')
                            ->color('danger')
                            ->weight(FontWeight::Bold)
                            ->visible(fn (Inbound $record): bool => $record->hasOwnershipPending()),
                    ])
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('update_ownership')
                            ->label('Update Ownership')
                            ->icon('heroicon-o-pencil-square')
                            ->form([
                                Select::make('ownership_flag')
                                    ->label('Ownership Flag')
                                    ->options(collect(OwnershipFlag::cases())
                                        ->mapWithKeys(fn (OwnershipFlag $flag) => [$flag->value => $flag->label()])
                                        ->toArray())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(function (Inbound $record, array $data): void {
                                try {
                                    $newFlag = OwnershipFlag::from($data['ownership_flag']);
                                    app(InboundService::class)->updateOwnershipFlag($record, $newFlag);

                                    Notification::make()
                                        ->success()
                                        ->title('Ownership updated')
                                        ->body("Ownership flag changed to {$newFlag->label()}")
                                        ->send();
                                } catch (\InvalidArgumentException $e) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Failed to update ownership')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            })
                            ->visible(fn (Inbound $record): bool => ! $record->handed_to_module_b),
                    ]),

                Section::make('Linked Purchase Order')
                    ->description('Source PO for this inbound')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('purchaseOrder.id')
                                    ->label('PO ID')
                                    ->limit(8)
                                    ->tooltip(fn (Inbound $record): ?string => $record->purchase_order_id)
                                    ->url(fn (Inbound $record): ?string => $record->purchase_order_id !== null
                                        ? route('filament.admin.resources.procurement.purchase-orders.view', ['record' => $record->purchase_order_id])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->placeholder('Not linked'),
                                TextEntry::make('purchaseOrder.supplier.legal_name')
                                    ->label('Supplier')
                                    ->placeholder('—'),
                                TextEntry::make('purchaseOrder.quantity')
                                    ->label('PO Quantity')
                                    ->numeric()
                                    ->placeholder('—'),
                                TextEntry::make('purchaseOrder.status')
                                    ->label('PO Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): ?string => is_object($state) && method_exists($state, 'label') ? $state->label() : null)
                                    ->color(fn ($state): string => is_object($state) && method_exists($state, 'color') ? $state->color() : 'gray')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->visible(fn (Inbound $record): bool => $record->purchase_order_id !== null),

                Section::make('Linked Procurement Intent')
                    ->description('Source demand that triggered this inbound')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('procurementIntent.id')
                                    ->label('Intent ID')
                                    ->limit(8)
                                    ->tooltip(fn (Inbound $record): ?string => $record->procurement_intent_id)
                                    ->url(fn (Inbound $record): ?string => $record->procurement_intent_id !== null
                                        ? route('filament.admin.resources.procurement.procurement-intents.view', ['record' => $record->procurement_intent_id])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->placeholder('Not linked'),
                                TextEntry::make('procurementIntent.quantity')
                                    ->label('Intent Quantity')
                                    ->numeric()
                                    ->placeholder('—'),
                                TextEntry::make('procurementIntent.trigger_type')
                                    ->label('Trigger Type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): ?string => is_object($state) && method_exists($state, 'label') ? $state->label() : null)
                                    ->color(fn ($state): string => is_object($state) && method_exists($state, 'color') ? $state->color() : 'gray')
                                    ->placeholder('—'),
                                TextEntry::make('procurementIntent.status')
                                    ->label('Intent Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): ?string => is_object($state) && method_exists($state, 'label') ? $state->label() : null)
                                    ->color(fn ($state): string => is_object($state) && method_exists($state, 'color') ? $state->color() : 'gray')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->visible(fn (Inbound $record): bool => $record->procurement_intent_id !== null),

                // Unlinked warning section
                Section::make('Unlinked Inbound')
                    ->description('This inbound is not linked to a Procurement Intent')
                    ->schema([
                        TextEntry::make('unlinked_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => '⚠️ This inbound has no linked Procurement Intent. Unlinked inbounds require manual validation before hand-off to inventory.')
                            ->color('warning')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('unlinked_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'To link this inbound to an intent, it must be done through a Purchase Order that is already linked to an intent.'),
                    ])
                    ->visible(fn (Inbound $record): bool => $record->isUnlinked()),
            ]);
    }

    /**
     * Tab 3: Serialization Routing - authorized location, current rule, blockers.
     */
    protected function getSerializationRoutingTab(): Tab
    {
        return Tab::make('Serialization Routing')
            ->icon('heroicon-o-qr-code')
            ->schema([
                Section::make('Serialization Requirements')
                    ->description('Whether this inbound requires bottle serialization')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('serialization_required')
                                    ->label('Serialization Required')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn (bool $state): string => $state ? 'info' : 'gray')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-qr-code' : 'heroicon-o-minus'),
                                TextEntry::make('serialization_explanation')
                                    ->label('Explanation')
                                    ->getStateUsing(fn (Inbound $record): string => $record->serialization_required
                                        ? 'Each bottle must receive a unique serial number before entering inventory.'
                                        : 'Bottles will not be individually serialized for this inbound.'),
                            ]),
                    ]),

                Section::make('Authorized Location')
                    ->description('Where serialization will be performed')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('serialization_location_authorized')
                                    ->label('Authorized Location')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'main_warehouse' => 'Main Warehouse',
                                        'secondary_warehouse' => 'Secondary Warehouse',
                                        'bonded_warehouse' => 'Bonded Warehouse',
                                        'third_party_storage' => 'Third Party Storage',
                                        'france_facility' => 'France Facility',
                                        'uk_facility' => 'UK Facility',
                                        'origin_winery' => 'Origin Winery',
                                        null => 'Not specified',
                                        default => $state,
                                    })
                                    ->color(fn (?string $state): string => $state !== null ? 'success' : 'warning')
                                    ->placeholder('Not specified'),
                                TextEntry::make('location_status')
                                    ->label('Status')
                                    ->getStateUsing(fn (Inbound $record): string => $record->hasValidSerializationRouting()
                                        ? '✓ Valid routing configuration'
                                        : '⚠️ Missing serialization location')
                                    ->color(fn (Inbound $record): string => $record->hasValidSerializationRouting() ? 'success' : 'warning'),
                            ]),

                        // Blocker warning if serialization required but no location
                        TextEntry::make('routing_blocker')
                            ->label('')
                            ->getStateUsing(fn (): string => '🚫 BLOCKER: Serialization is required but no authorized location is set. This inbound cannot be routed until a location is specified.')
                            ->color('danger')
                            ->weight(FontWeight::Bold)
                            ->visible(fn (Inbound $record): bool => $record->serialization_required && $record->serialization_location_authorized === null),
                    ])
                    ->visible(fn (Inbound $record): bool => $record->serialization_required),

                Section::make('Routing Rule')
                    ->description('Special instructions for serialization routing')
                    ->schema([
                        TextEntry::make('serialization_routing_rule')
                            ->label('')
                            ->placeholder('No special routing rules defined')
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Inbound $record): bool => $record->serialization_required),
            ]);
    }

    /**
     * Tab 4: Downstream Hand-off - sent to Module B, serialized quantity.
     */
    protected function getDownstreamHandoffTab(): Tab
    {
        return Tab::make('Downstream Hand-off')
            ->icon('heroicon-o-arrow-right-circle')
            ->badge(fn (Inbound $record): ?string => $record->handed_to_module_b ? '✓' : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Module B Hand-off Status')
                    ->description('Whether this inbound has been handed off to inventory management')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('handed_to_module_b')
                                    ->label('Handed Off')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),
                                TextEntry::make('handed_to_module_b_at')
                                    ->label('Hand-off Timestamp')
                                    ->dateTime()
                                    ->placeholder('Not yet handed off'),
                                TextEntry::make('handoff_status_summary')
                                    ->label('Status')
                                    ->getStateUsing(fn (Inbound $record): string => $record->handed_to_module_b
                                        ? '✓ Successfully handed off to Module B'
                                        : ($record->canHandOffToModuleB()
                                            ? '⏳ Ready for hand-off'
                                            : '⚠️ Not ready for hand-off')),
                            ]),
                    ]),

                Section::make('Hand-off Prerequisites')
                    ->description('Requirements that must be met before hand-off')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('prereq_status')
                                    ->label('Status')
                                    ->getStateUsing(fn (Inbound $record): string => $record->status->label())
                                    ->badge()
                                    ->color(fn (Inbound $record): string => $record->status === InboundStatus::Completed ? 'success' : 'warning')
                                    ->icon(fn (Inbound $record): string => $record->status === InboundStatus::Completed ? 'heroicon-o-check' : 'heroicon-o-clock')
                                    ->helperText('Must be Completed'),
                                TextEntry::make('prereq_ownership')
                                    ->label('Ownership Clarity')
                                    ->getStateUsing(fn (Inbound $record): string => $record->ownership_flag->label())
                                    ->badge()
                                    ->color(fn (Inbound $record): string => $record->hasOwnershipClarity() ? 'success' : 'danger')
                                    ->icon(fn (Inbound $record): string => $record->hasOwnershipClarity() ? 'heroicon-o-check' : 'heroicon-o-x-mark')
                                    ->helperText('Must not be Pending'),
                            ]),

                        // Checklist summary
                        TextEntry::make('checklist_summary')
                            ->label('Checklist')
                            ->getStateUsing(function (Inbound $record): string {
                                $checks = [];
                                $checks[] = ($record->status === InboundStatus::Completed ? '✓' : '○').' Status = Completed';
                                $checks[] = ($record->hasOwnershipClarity() ? '✓' : '○').' Ownership clarified (not Pending)';
                                $checks[] = ($record->hasValidSerializationRouting() ? '✓' : '○').' Valid serialization routing';
                                $checks[] = (! $record->handed_to_module_b ? '✓' : '○').' Not already handed off';

                                return implode("\n", $checks);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Inbound $record): bool => ! $record->handed_to_module_b),

                Section::make('Serialization Info')
                    ->description('Serialization data after hand-off')
                    ->schema([
                        TextEntry::make('serialized_quantity_placeholder')
                            ->label('Serialized Quantity')
                            ->getStateUsing(fn (Inbound $record): string => $record->handed_to_module_b
                                ? "{$record->quantity} bottles (awaiting Module B data)"
                                : 'Not yet available')
                            ->placeholder('Available after hand-off and serialization'),
                        TextEntry::make('module_b_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'ℹ️ Detailed serialization data will be available from Module B once the inbound is processed and bottles are serialized.')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - WMS event references, manual adjustments, status changes.
     */
    protected function getAuditTab(): Tab
    {
        return Tab::make('Audit')
            ->icon('heroicon-o-document-magnifying-glass')
            ->schema([
                Section::make('Audit Trail')
                    ->description('Immutable record of all changes')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('filter_audit')
                            ->label('Filter')
                            ->icon('heroicon-o-funnel')
                            ->form([
                                Select::make('event_type')
                                    ->label('Event Type')
                                    ->options([
                                        '' => 'All Events',
                                        AuditLog::EVENT_CREATED => 'Created',
                                        AuditLog::EVENT_UPDATED => 'Updated',
                                        AuditLog::EVENT_STATUS_CHANGE => 'Status Change',
                                        AuditLog::EVENT_FLAG_CHANGE => 'Flag Change',
                                    ])
                                    ->default(''),
                                DatePicker::make('date_from')
                                    ->label('From Date'),
                                DatePicker::make('date_until')
                                    ->label('Until Date'),
                            ])
                            ->action(function (array $data): void {
                                $this->auditEventFilter = $data['event_type'] ?? null;
                                $this->auditDateFrom = $data['date_from'] ?? null;
                                $this->auditDateUntil = $data['date_until'] ?? null;
                            }),
                        \Filament\Infolists\Components\Actions\Action::make('clear_filter')
                            ->label('Clear')
                            ->icon('heroicon-o-x-mark')
                            ->action(function (): void {
                                $this->auditEventFilter = null;
                                $this->auditDateFrom = null;
                                $this->auditDateUntil = null;
                            })
                            ->visible(fn (): bool => $this->auditEventFilter !== null
                                || $this->auditDateFrom !== null
                                || $this->auditDateUntil !== null),
                    ])
                    ->schema([
                        RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->getStateUsing(function (Inbound $record): array {
                                $query = $record->auditLogs()->orderBy('created_at', 'desc');

                                if ($this->auditEventFilter !== null && $this->auditEventFilter !== '') {
                                    $query->where('event', $this->auditEventFilter);
                                }

                                if ($this->auditDateFrom !== null) {
                                    $query->whereDate('created_at', '>=', $this->auditDateFrom);
                                }

                                if ($this->auditDateUntil !== null) {
                                    $query->whereDate('created_at', '<=', $this->auditDateUntil);
                                }

                                return $query->with('user')->get()->toArray();
                            })
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Date/Time')
                                            ->formatStateUsing(fn ($state): string => $state instanceof \Carbon\Carbon
                                                ? $state->format('Y-m-d H:i:s')
                                                : (is_string($state) ? $state : 'Unknown')),
                                        TextEntry::make('event')
                                            ->label('Event')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                AuditLog::EVENT_CREATED => 'success',
                                                AuditLog::EVENT_STATUS_CHANGE => 'warning',
                                                AuditLog::EVENT_UPDATED => 'info',
                                                AuditLog::EVENT_FLAG_CHANGE => 'info',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('user.name')
                                            ->label('User')
                                            ->placeholder('System'),
                                        TextEntry::make('old_values')
                                            ->label('Old Values')
                                            ->formatStateUsing(function ($state): string {
                                                if (empty($state)) {
                                                    return '-';
                                                }

                                                return self::formatAuditValues($state);
                                            }),
                                        TextEntry::make('new_values')
                                            ->label('New Values')
                                            ->formatStateUsing(function ($state): string {
                                                if (empty($state)) {
                                                    return '-';
                                                }

                                                return self::formatAuditValues($state);
                                            }),
                                    ]),
                            ])
                            ->contained(false)
                            ->placeholder('No audit records found'),
                    ]),

                Section::make('Key Events')
                    ->description('Summary of important lifecycle events')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('recorded_event')
                                    ->label('Recorded')
                                    ->getStateUsing(fn (Inbound $record): string => $record->created_at->format('Y-m-d H:i'))
                                    ->icon('heroicon-o-inbox-arrow-down'),
                                TextEntry::make('routed_event')
                                    ->label('Routed')
                                    ->getStateUsing(fn (Inbound $record): string => $record->status !== InboundStatus::Recorded
                                        ? 'Yes' : 'Not yet')
                                    ->icon(fn (Inbound $record): string => $record->status !== InboundStatus::Recorded
                                        ? 'heroicon-o-check' : 'heroicon-o-clock'),
                                TextEntry::make('completed_event')
                                    ->label('Completed')
                                    ->getStateUsing(fn (Inbound $record): string => $record->status === InboundStatus::Completed
                                        ? 'Yes' : 'Not yet')
                                    ->icon(fn (Inbound $record): string => $record->status === InboundStatus::Completed
                                        ? 'heroicon-o-check' : 'heroicon-o-clock'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Format audit values for display.
     *
     * @param  mixed  $state
     */
    protected static function formatAuditValues($state): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (is_array($decoded)) {
                $state = $decoded;
            } else {
                return $state;
            }
        }

        if (is_array($state)) {
            $parts = [];
            foreach ($state as $field => $value) {
                if (is_array($value)) {
                    $parts[] = "{$field}: ".json_encode($value);
                } elseif (is_bool($value)) {
                    $parts[] = "{$field}: ".($value ? 'true' : 'false');
                } else {
                    $parts[] = "{$field}: {$value}";
                }
            }

            return implode(', ', $parts);
        }

        return '-';
    }

    /**
     * Get the header actions for the view page.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Route (Recorded → Routed)
            Actions\Action::make('route')
                ->label('Route')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Route Inbound')
                ->modalDescription(function (Inbound $record): string {
                    $inboundService = app(InboundService::class);
                    $authorizedLocations = $inboundService->getAuthorizedSerializationLocations($record);

                    if ($authorizedLocations !== null && count($authorizedLocations) > 0) {
                        $locationList = implode(', ', $authorizedLocations);

                        return "⚠️ Supplier config restricts serialization to: {$locationList}. "
                            .'Selecting a location not in this list will be blocked.';
                    }

                    return 'Assign a serialization location and route this inbound for processing.';
                })
                ->modalSubmitActionLabel('Route Inbound')
                ->form([
                    Select::make('serialization_location')
                        ->label('Serialization Location')
                        ->options(function (Inbound $record): array {
                            $inboundService = app(InboundService::class);
                            $authorizedLocations = $inboundService->getAuthorizedSerializationLocations($record);

                            $allLocations = [
                                'main_warehouse' => 'Main Warehouse',
                                'secondary_warehouse' => 'Secondary Warehouse',
                                'bonded_warehouse' => 'Bonded Warehouse',
                                'third_party_storage' => 'Third Party Storage',
                                'france_facility' => 'France Facility',
                                'uk_facility' => 'UK Facility',
                                'origin_winery' => 'Origin Winery',
                            ];

                            if ($authorizedLocations !== null && count($authorizedLocations) > 0) {
                                // Filter to only authorized locations and mark others as disabled
                                $options = [];
                                foreach ($allLocations as $value => $label) {
                                    if (in_array($value, $authorizedLocations, true)) {
                                        $options[$value] = $label.' ✓';
                                    } else {
                                        $options[$value] = $label.' (Not authorized)';
                                    }
                                }

                                return $options;
                            }

                            return $allLocations;
                        })
                        ->required()
                        ->native(false)
                        ->default(fn (Inbound $record) => $record->serialization_location_authorized)
                        ->helperText(function (Inbound $record): string {
                            $inboundService = app(InboundService::class);
                            $authorizedLocations = $inboundService->getAuthorizedSerializationLocations($record);

                            if ($authorizedLocations !== null && count($authorizedLocations) > 0) {
                                return 'Locations marked with ✓ are authorized per supplier config.';
                            }

                            return 'Where serialization will be performed';
                        })
                        ->live()
                        ->afterStateUpdated(function (string $state, callable $set, Inbound $record): void {
                            $inboundService = app(InboundService::class);
                            $checkResult = $inboundService->checkSerializationLocationAuthorization($record, $state);

                            if (! $checkResult['authorized']) {
                                $set('location_warning', $checkResult['message']);
                            } else {
                                $set('location_warning', null);
                            }
                        }),
                    \Filament\Forms\Components\Placeholder::make('location_warning_display')
                        ->label('')
                        ->content(fn ($get): ?\Illuminate\Support\HtmlString => $get('location_warning') !== null
                            ? new \Illuminate\Support\HtmlString(
                                '<div class="p-3 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">'
                                .'<p class="text-red-700 dark:text-red-300 font-medium text-sm">'
                                .'🚫 '.$get('location_warning')
                                .'</p></div>'
                            )
                            : null
                        )
                        ->hidden(fn ($get): bool => $get('location_warning') === null),
                    \Filament\Forms\Components\Hidden::make('location_warning'),
                ])
                ->visible(fn (Inbound $record): bool => $record->isRecorded())
                ->action(function (Inbound $record, array $data): void {
                    try {
                        app(InboundService::class)->route($record, $data['serialization_location']);

                        Notification::make()
                            ->success()
                            ->title('Inbound routed')
                            ->body('The inbound has been routed to '.$data['serialization_location'])
                            ->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to route inbound')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // Complete (Routed → Completed)
            Actions\Action::make('complete')
                ->label('Complete')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Complete Inbound')
                ->modalDescription(function (Inbound $record): string {
                    if ($record->hasOwnershipPending()) {
                        return '⚠️ WARNING: Ownership is still pending. You must clarify ownership before completing this inbound.';
                    }

                    return 'Mark this inbound as completed. This confirms that all receiving processes are finished.';
                })
                ->modalSubmitActionLabel('Complete Inbound')
                ->visible(fn (Inbound $record): bool => $record->isRouted())
                ->disabled(fn (Inbound $record): bool => $record->hasOwnershipPending())
                ->action(function (Inbound $record): void {
                    try {
                        app(InboundService::class)->complete($record);

                        Notification::make()
                            ->success()
                            ->title('Inbound completed')
                            ->body('The inbound has been marked as completed.')
                            ->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to complete inbound')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // Hand-off to Module B
            Actions\Action::make('handoff')
                ->label('Hand-off to Module B')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hand-off to Module B')
                ->modalDescription(function (Inbound $record): string {
                    $warnings = [];

                    if ($record->isUnlinked()) {
                        $warnings[] = '⚠️ This is an unlinked inbound (no Procurement Intent). Manual validation is recommended.';
                    }

                    $base = 'This will hand off the inbound to Module B for inventory management. This action cannot be reversed.';

                    if (count($warnings) > 0) {
                        return implode("\n\n", $warnings)."\n\n".$base;
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Confirm Hand-off')
                ->visible(fn (Inbound $record): bool => $record->isCompleted() && ! $record->handed_to_module_b)
                ->disabled(fn (Inbound $record): bool => ! $record->canHandOffToModuleB())
                ->action(function (Inbound $record): void {
                    try {
                        app(InboundService::class)->handOffToModuleB($record);

                        Notification::make()
                            ->success()
                            ->title('Hand-off successful')
                            ->body('The inbound has been handed off to Module B.')
                            ->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to hand off inbound')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // View linked Intent
            Actions\Action::make('view_intent')
                ->label('View Intent')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn (Inbound $record): ?string => $record->procurement_intent_id !== null
                    ? route('filament.admin.resources.procurement.procurement-intents.view', ['record' => $record->procurement_intent_id])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (Inbound $record): bool => $record->procurement_intent_id !== null),

            // View linked PO
            Actions\Action::make('view_po')
                ->label('View PO')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (Inbound $record): ?string => $record->purchase_order_id !== null
                    ? route('filament.admin.resources.procurement.purchase-orders.view', ['record' => $record->purchase_order_id])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (Inbound $record): bool => $record->purchase_order_id !== null),

            // Delete action
            Actions\DeleteAction::make()
                ->visible(fn (Inbound $record): bool => ! $record->handed_to_module_b),

            // Restore action (for soft deleted)
            Actions\RestoreAction::make(),
        ];
    }
}
