<?php

namespace App\Filament\Resources\Procurement\PurchaseOrderResource\Pages;

use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\PurchaseOrderResource;
use App\Models\AuditLog;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\PurchaseOrder;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

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
        /** @var PurchaseOrder $record */
        $record = $this->record;

        return "PO #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var PurchaseOrder $record */
        $record = $this->record;

        return $record->getProductLabel().' - '.$record->status->label();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Purchase Order Details')
                    ->tabs([
                        $this->getCommercialTermsTab(),
                        $this->getLinkedIntentTab(),
                        $this->getDeliveryExpectationsTab(),
                        $this->getInboundMatchingTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Commercial Terms - supplier, product, quantity, pricing, incoterms, ownership flag.
     */
    protected function getCommercialTermsTab(): Tab
    {
        return Tab::make('Commercial Terms')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Section::make('Status & Identity')
                    ->description('Current PO status and identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('PO ID')
                                        ->copyable()
                                        ->copyMessage('PO ID copied')
                                        ->weight(FontWeight::Bold),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->label())
                                        ->color(fn (PurchaseOrderStatus $state): string => $state->color())
                                        ->icon(fn (PurchaseOrderStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(2),
                            ]),
                    ]),

                Section::make('Supplier')
                    ->description('Supplier party information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('supplier.legal_name')
                                    ->label('Supplier')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->placeholder('No supplier assigned'),
                                TextEntry::make('supplier.party_type')
                                    ->label('Party Type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                    ->color('gray'),
                                TextEntry::make('supplier.jurisdiction')
                                    ->label('Jurisdiction')
                                    ->placeholder('Not specified'),
                            ]),
                    ]),

                Section::make('Product')
                    ->description('Wine and format information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('product_label')
                                    ->label('Product')
                                    ->getStateUsing(fn (PurchaseOrder $record): string => $record->getProductLabel())
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
                                    ->suffix(fn (PurchaseOrder $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                            ]),
                    ]),

                Section::make('Pricing')
                    ->description('Commercial pricing terms')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->money(fn (PurchaseOrder $record): string => $record->currency ?? 'EUR'),
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('total_cost')
                                    ->label('Total Cost')
                                    ->getStateUsing(fn (PurchaseOrder $record): string => number_format($record->getTotalCost(), 2))
                                    ->prefix(fn (PurchaseOrder $record): string => $record->currency.' ')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('incoterms')
                                    ->label('Incoterms')
                                    ->placeholder('Not specified')
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),

                Section::make('Ownership')
                    ->description('Ownership transfer terms')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ownership_transfer')
                                    ->label('Ownership Transfer')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes - Ownership transfers on delivery' : 'No - No ownership transfer')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                TextEntry::make('ownership_explanation')
                                    ->label('Implication')
                                    ->getStateUsing(fn (PurchaseOrder $record): string => $record->hasOwnershipTransfer()
                                        ? 'The wine will become our inventory asset upon delivery'
                                        : 'Ownership remains with the supplier (consignment/custody arrangement)')
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Linked Intent - read-only link back, quantity coverage vs intent.
     */
    protected function getLinkedIntentTab(): Tab
    {
        return Tab::make('Linked Intent')
            ->icon('heroicon-o-clipboard-document-list')
            ->schema([
                Section::make('Source Procurement Intent')
                    ->description('The intent that initiated this purchase order')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('procurementIntent.id')
                                    ->label('Intent ID')
                                    ->copyable()
                                    ->copyMessage('Intent ID copied')
                                    ->weight(FontWeight::Bold)
                                    ->url(fn (PurchaseOrder $record): ?string => $record->procurementIntent
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
                            ]),
                    ]),

                Section::make('Quantity Coverage')
                    ->description('Compare PO quantity against intent demand')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('procurementIntent.quantity')
                                    ->label('Intent Quantity')
                                    ->numeric()
                                    ->suffix(fn (PurchaseOrder $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                                TextEntry::make('quantity')
                                    ->label('PO Quantity')
                                    ->numeric()
                                    ->suffix(fn (PurchaseOrder $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                                TextEntry::make('coverage_percentage')
                                    ->label('Coverage')
                                    ->getStateUsing(function (PurchaseOrder $record): string {
                                        $intentQty = $record->procurementIntent->quantity ?? 0;
                                        if ($intentQty === 0) {
                                            return 'N/A';
                                        }
                                        $percentage = ($record->quantity / $intentQty) * 100;

                                        return number_format($percentage, 1).'%';
                                    })
                                    ->badge()
                                    ->color(function (PurchaseOrder $record): string {
                                        $intentQty = $record->procurementIntent->quantity ?? 0;
                                        if ($intentQty === 0) {
                                            return 'gray';
                                        }
                                        $percentage = ($record->quantity / $intentQty) * 100;

                                        return match (true) {
                                            $percentage >= 100 => 'success',
                                            $percentage >= 80 => 'warning',
                                            default => 'danger',
                                        };
                                    }),
                                TextEntry::make('coverage_status')
                                    ->label('Status')
                                    ->getStateUsing(function (PurchaseOrder $record): string {
                                        $intentQty = $record->procurementIntent->quantity ?? 0;
                                        if ($intentQty === 0) {
                                            return 'No intent quantity';
                                        }
                                        $diff = $record->quantity - $intentQty;

                                        return match (true) {
                                            $diff === 0 => 'Exact match',
                                            $diff > 0 => 'Over-ordered by '.abs($diff),
                                            default => 'Under-ordered by '.abs($diff),
                                        };
                                    })
                                    ->color(function (PurchaseOrder $record): string {
                                        $intentQty = $record->procurementIntent->quantity ?? 0;
                                        if ($intentQty === 0) {
                                            return 'gray';
                                        }
                                        $diff = $record->quantity - $intentQty;

                                        return match (true) {
                                            $diff === 0 => 'success',
                                            $diff > 0 => 'warning',
                                            default => 'danger',
                                        };
                                    }),
                            ]),
                    ]),

                Section::make('Sourcing Context')
                    ->description('Sourcing model from the intent')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('procurementIntent.sourcing_model')
                                    ->label('Sourcing Model')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                    ->color('info'),
                                TextEntry::make('procurementIntent.rationale')
                                    ->label('Intent Rationale')
                                    ->placeholder('No rationale provided')
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Delivery Expectations - delivery window, destination warehouse, serialization routing note.
     */
    protected function getDeliveryExpectationsTab(): Tab
    {
        return Tab::make('Delivery')
            ->icon('heroicon-o-truck')
            ->schema([
                Section::make('Delivery Window')
                    ->description('Expected delivery dates')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('expected_delivery_start')
                                    ->label('Delivery Start')
                                    ->date()
                                    ->placeholder('Not specified'),
                                TextEntry::make('expected_delivery_end')
                                    ->label('Delivery End')
                                    ->date()
                                    ->placeholder('Not specified'),
                                TextEntry::make('delivery_status')
                                    ->label('Delivery Status')
                                    ->getStateUsing(function (PurchaseOrder $record): string {
                                        if ($record->isClosed()) {
                                            return 'Closed';
                                        }
                                        if ($record->isDeliveryOverdue()) {
                                            return 'Overdue';
                                        }
                                        if ($record->expected_delivery_end === null) {
                                            return 'No deadline';
                                        }
                                        $daysUntil = now()->diffInDays($record->expected_delivery_end, false);

                                        return match (true) {
                                            $daysUntil <= 0 => 'Due today',
                                            $daysUntil <= 7 => "Due in {$daysUntil} days",
                                            $daysUntil <= 30 => "Due in {$daysUntil} days",
                                            default => "Due in {$daysUntil} days",
                                        };
                                    })
                                    ->badge()
                                    ->color(function (PurchaseOrder $record): string {
                                        if ($record->isClosed()) {
                                            return 'gray';
                                        }
                                        if ($record->isDeliveryOverdue()) {
                                            return 'danger';
                                        }
                                        if ($record->expected_delivery_end === null) {
                                            return 'gray';
                                        }
                                        $daysUntil = now()->diffInDays($record->expected_delivery_end, false);

                                        return match (true) {
                                            $daysUntil <= 0 => 'danger',
                                            $daysUntil <= 7 => 'warning',
                                            default => 'success',
                                        };
                                    })
                                    ->icon(function (PurchaseOrder $record): string {
                                        if ($record->isDeliveryOverdue()) {
                                            return 'heroicon-o-exclamation-triangle';
                                        }

                                        return 'heroicon-o-clock';
                                    }),
                            ]),
                    ]),

                Section::make('Destination')
                    ->description('Delivery destination and routing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('destination_warehouse')
                                    ->label('Destination Warehouse')
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

                Section::make('Serialization Routing')
                    ->description('Special instructions for serialization')
                    ->schema([
                        TextEntry::make('serialization_routing_note')
                            ->label('Routing Note')
                            ->placeholder('No special routing instructions')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Inbound Matching - linked inbound batches, variance flags.
     */
    protected function getInboundMatchingTab(): Tab
    {
        return Tab::make('Inbound Matching')
            ->icon('heroicon-o-arrow-down-on-square')
            ->badge(fn (PurchaseOrder $record): ?string => $record->inbounds->count() > 0 ? (string) $record->inbounds->count() : null)
            ->schema([
                Section::make('Variance Summary')
                    ->description('Compare PO quantity with received inbound quantities')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('PO Quantity')
                                    ->numeric()
                                    ->suffix(fn (PurchaseOrder $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                                TextEntry::make('inbound_quantity')
                                    ->label('Received Quantity')
                                    ->getStateUsing(fn (PurchaseOrder $record): int => $record->inbounds->sum('quantity'))
                                    ->numeric()
                                    ->suffix(fn (PurchaseOrder $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles'),
                                TextEntry::make('variance')
                                    ->label('Variance')
                                    ->getStateUsing(function (PurchaseOrder $record): string {
                                        $inboundQty = $record->inbounds->sum('quantity');
                                        $variance = $inboundQty - $record->quantity;

                                        return $variance >= 0 ? "+{$variance}" : (string) $variance;
                                    })
                                    ->badge()
                                    ->color(function (PurchaseOrder $record): string {
                                        $inboundQty = $record->inbounds->sum('quantity');
                                        $variance = $inboundQty - $record->quantity;

                                        return match (true) {
                                            $variance === 0 => 'success',
                                            $variance > 0 => 'warning',
                                            default => 'danger',
                                        };
                                    }),
                                TextEntry::make('variance_status')
                                    ->label('Status')
                                    ->getStateUsing(function (PurchaseOrder $record): string {
                                        $inboundQty = $record->inbounds->sum('quantity');
                                        $variance = $inboundQty - $record->quantity;

                                        if ($record->quantity === 0) {
                                            return 'No PO quantity';
                                        }

                                        $variancePercent = abs($variance / $record->quantity) * 100;

                                        return match (true) {
                                            $variance === 0 => 'Exact Match',
                                            $variance > 0 && $variancePercent > 10 => 'Over Delivery (>10%)',
                                            $variance > 0 => 'Over Delivery',
                                            $variancePercent > 10 => 'Short Delivery (>10%)',
                                            default => 'Short Delivery',
                                        };
                                    })
                                    ->badge()
                                    ->icon(function (PurchaseOrder $record): string {
                                        $inboundQty = $record->inbounds->sum('quantity');
                                        $variance = $inboundQty - $record->quantity;

                                        return match (true) {
                                            $variance === 0 => 'heroicon-o-check-circle',
                                            $variance > 0 => 'heroicon-o-arrow-up-circle',
                                            default => 'heroicon-o-arrow-down-circle',
                                        };
                                    })
                                    ->color(function (PurchaseOrder $record): string {
                                        $inboundQty = $record->inbounds->sum('quantity');
                                        $variance = $inboundQty - $record->quantity;

                                        if ($record->quantity === 0) {
                                            return 'gray';
                                        }

                                        $variancePercent = abs($variance / $record->quantity) * 100;

                                        return match (true) {
                                            $variance === 0 => 'success',
                                            $variancePercent > 10 => 'danger',
                                            default => 'warning',
                                        };
                                    }),
                            ]),
                    ]),

                Section::make('Linked Inbound Batches')
                    ->description('Physical receipts linked to this PO')
                    ->schema([
                        RepeatableEntry::make('inbounds')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Inbound ID')
                                            ->limit(8)
                                            ->tooltip(fn (Inbound $record): string => $record->id)
                                            ->url(fn (Inbound $record): string => route('filament.admin.resources.procurement.inbounds.view', ['record' => $record->id]))
                                            ->openUrlInNewTab()
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->badge()
                                            ->color('info'),
                                        TextEntry::make('warehouse')
                                            ->label('Warehouse')
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'main_warehouse' => 'Main',
                                                'secondary_warehouse' => 'Secondary',
                                                'bonded_warehouse' => 'Bonded',
                                                'third_party_storage' => '3rd Party',
                                                default => $state ?? 'Unknown',
                                            }),
                                        TextEntry::make('received_date')
                                            ->label('Received')
                                            ->date(),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (InboundStatus $state): string => $state->label())
                                            ->color(fn (InboundStatus $state): string => $state->color()),
                                        TextEntry::make('ownership_flag')
                                            ->label('Ownership')
                                            ->badge()
                                            ->formatStateUsing(fn ($state): string => is_object($state) && method_exists($state, 'label') ? $state->label() : (string) $state)
                                            ->color(fn ($state): string => is_object($state) && method_exists($state, 'color') ? $state->color() : 'gray'),
                                    ]),
                            ])
                            ->contained(false)
                            ->placeholder('No inbound batches linked to this PO yet'),
                    ]),
            ]);
    }

    /**
     * Tab 5: Audit - approval trail, status changes, timeline.
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
                            ->getStateUsing(function (PurchaseOrder $record): array {
                                $query = $record->auditLogs()->orderBy('created_at', 'desc');

                                if ($this->auditEventFilter !== null && $this->auditEventFilter !== '') {
                                    $query->where('event_type', $this->auditEventFilter);
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
                                        TextEntry::make('event_type')
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
                                        TextEntry::make('changes')
                                            ->label('Changes')
                                            ->formatStateUsing(function ($state): string {
                                                if (empty($state)) {
                                                    return '-';
                                                }

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
                                                    foreach ($state as $field => $change) {
                                                        if (is_array($change)) {
                                                            $old = $change['old'] ?? '—';
                                                            $new = $change['new'] ?? '—';
                                                            $parts[] = "{$field}: {$old} → {$new}";
                                                        } else {
                                                            $parts[] = "{$field}: {$change}";
                                                        }
                                                    }

                                                    return implode(', ', $parts);
                                                }

                                                return '-';
                                            }),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->placeholder('-'),
                                    ]),
                            ])
                            ->contained(false)
                            ->placeholder('No audit records found'),
                    ]),
            ]);
    }

    /**
     * Get the header actions for the view page.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Mark as Sent (Draft → Sent)
            Actions\Action::make('mark_sent')
                ->label('Mark as Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Mark PO as Sent')
                ->modalDescription('This will mark the PO as sent to the supplier. This indicates the PO has been communicated to the supplier.')
                ->modalSubmitActionLabel('Mark as Sent')
                ->visible(fn (PurchaseOrder $record): bool => $record->isDraft())
                ->action(function (PurchaseOrder $record): void {
                    if (! $record->status->canTransitionTo(PurchaseOrderStatus::Sent)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid transition')
                            ->body('Cannot transition from '.$record->status->label().' to Sent')
                            ->send();

                        return;
                    }

                    $oldStatus = $record->status->value;
                    $record->status = PurchaseOrderStatus::Sent;
                    $record->save();

                    // Create audit log
                    AuditLog::create([
                        'auditable_type' => PurchaseOrder::class,
                        'auditable_id' => $record->id,
                        'user_id' => auth()->id(),
                        'event_type' => AuditLog::EVENT_STATUS_CHANGE,
                        'changes' => [
                            'status' => [
                                'old' => $oldStatus,
                                'new' => PurchaseOrderStatus::Sent->value,
                            ],
                        ],
                        'notes' => 'PO marked as sent to supplier',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('PO marked as Sent')
                        ->body('The purchase order has been marked as sent to the supplier.')
                        ->send();
                }),

            // Confirm (Sent → Confirmed)
            Actions\Action::make('confirm')
                ->label('Confirm')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm PO')
                ->modalDescription('This will mark the PO as confirmed by the supplier. This indicates the supplier has acknowledged and accepted the order.')
                ->modalSubmitActionLabel('Confirm PO')
                ->visible(fn (PurchaseOrder $record): bool => $record->isSent())
                ->action(function (PurchaseOrder $record): void {
                    if (! $record->status->canTransitionTo(PurchaseOrderStatus::Confirmed)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid transition')
                            ->body('Cannot transition from '.$record->status->label().' to Confirmed')
                            ->send();

                        return;
                    }

                    $oldStatus = $record->status->value;
                    $record->status = PurchaseOrderStatus::Confirmed;
                    $record->save();

                    // Create audit log
                    AuditLog::create([
                        'auditable_type' => PurchaseOrder::class,
                        'auditable_id' => $record->id,
                        'user_id' => auth()->id(),
                        'event_type' => AuditLog::EVENT_STATUS_CHANGE,
                        'changes' => [
                            'status' => [
                                'old' => $oldStatus,
                                'new' => PurchaseOrderStatus::Confirmed->value,
                            ],
                        ],
                        'notes' => 'PO confirmed by supplier',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('PO confirmed')
                        ->body('The purchase order has been confirmed by the supplier.')
                        ->send();
                }),

            // Close (Confirmed → Closed)
            Actions\Action::make('close')
                ->label('Close PO')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Close PO')
                ->modalDescription('This will close the purchase order. Make sure all expected deliveries have been received.')
                ->modalSubmitActionLabel('Close PO')
                ->form([
                    Textarea::make('variance_notes')
                        ->label('Variance Notes')
                        ->placeholder('Add any notes about quantity variances or delivery issues (optional)')
                        ->rows(3),
                ])
                ->visible(fn (PurchaseOrder $record): bool => $record->isConfirmed())
                ->action(function (PurchaseOrder $record, array $data): void {
                    if (! $record->status->canTransitionTo(PurchaseOrderStatus::Closed)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid transition')
                            ->body('Cannot transition from '.$record->status->label().' to Closed')
                            ->send();

                        return;
                    }

                    $oldStatus = $record->status->value;
                    $record->status = PurchaseOrderStatus::Closed;
                    $record->save();

                    // Create audit log
                    $notes = 'PO closed';
                    if (! empty($data['variance_notes'])) {
                        $notes .= ': '.$data['variance_notes'];
                    }

                    AuditLog::create([
                        'auditable_type' => PurchaseOrder::class,
                        'auditable_id' => $record->id,
                        'user_id' => auth()->id(),
                        'event_type' => AuditLog::EVENT_STATUS_CHANGE,
                        'changes' => [
                            'status' => [
                                'old' => $oldStatus,
                                'new' => PurchaseOrderStatus::Closed->value,
                            ],
                        ],
                        'notes' => $notes,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('PO closed')
                        ->body('The purchase order has been closed.')
                        ->send();
                }),

            // Link Inbound (available when confirmed, not closed)
            Actions\Action::make('link_inbound')
                ->label('Link Inbound')
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('info')
                ->url(fn (PurchaseOrder $record): string => route('filament.admin.resources.procurement.inbounds.create', [
                    'purchase_order_id' => $record->id,
                ]))
                ->visible(fn (PurchaseOrder $record): bool => $record->isConfirmed() || $record->isSent()),
        ];
    }
}
