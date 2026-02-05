<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\ProcurementIntentStatus;
use App\Enums\Procurement\ProcurementTriggerType;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\SourcingModel;
use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Models\AuditLog;
use App\Models\Procurement\BottlingInstruction;
use App\Models\Procurement\Inbound;
use App\Models\Procurement\ProcurementIntent;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Procurement\ProcurementIntentService;
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

class ViewProcurementIntent extends ViewRecord
{
    protected static string $resource = ProcurementIntentResource::class;

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
        /** @var ProcurementIntent $record */
        $record = $this->record;

        return "Intent #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var ProcurementIntent $record */
        $record = $this->record;

        return $record->getProductLabel().' - '.$record->status->label();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Procurement Intent Details')
                    ->tabs([
                        $this->getSummaryTab(),
                        $this->getDownstreamExecutionTab(),
                        $this->getAllocationVoucherContextTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Summary - Demand source, rationale, quantities, sourcing model, status, approvals.
     */
    protected function getSummaryTab(): Tab
    {
        return Tab::make('Summary')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Status & Identity')
                    ->description('Current intent status and identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Intent ID')
                                        ->copyable()
                                        ->copyMessage('Intent ID copied')
                                        ->weight(FontWeight::Bold),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (ProcurementIntentStatus $state): string => $state->label())
                                        ->color(fn (ProcurementIntentStatus $state): string => $state->color())
                                        ->icon(fn (ProcurementIntentStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('approved_at')
                                        ->label('Approved At')
                                        ->dateTime()
                                        ->placeholder('Not approved yet')
                                        ->visible(fn (ProcurementIntent $record): bool => $record->approved_at !== null),
                                    TextEntry::make('approver.name')
                                        ->label('Approved By')
                                        ->placeholder('—')
                                        ->visible(fn (ProcurementIntent $record): bool => $record->approved_by !== null),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Product')
                    ->description('Wine and format information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('product_label')
                                    ->label('Product')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => $record->getProductLabel())
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
                                    ->suffix(fn (ProcurementIntent $record): string => $record->isForLiquidProduct() ? ' bottle-equivalents' : ' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->badge()
                                    ->color('success'),
                            ]),
                    ]),
                Section::make('Demand Source')
                    ->description('What triggered this procurement intent')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('trigger_type')
                                    ->label('Trigger Type')
                                    ->badge()
                                    ->formatStateUsing(fn (ProcurementTriggerType $state): string => $state->label())
                                    ->color(fn (ProcurementTriggerType $state): string => $state->color())
                                    ->icon(fn (ProcurementTriggerType $state): string => $state->icon()),
                                TextEntry::make('trigger_explanation')
                                    ->label('Trigger Explanation')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => match ($record->trigger_type) {
                                        ProcurementTriggerType::VoucherDriven => 'Created from a voucher sale - sourcing to fulfill customer entitlement',
                                        ProcurementTriggerType::AllocationDriven => 'Created from allocation demand - pre-emptive sourcing based on projected sales',
                                        ProcurementTriggerType::Strategic => 'Manual strategic decision - speculative or discretionary procurement',
                                        ProcurementTriggerType::Contractual => 'Contractual commitment - bound by existing agreement',
                                    })
                                    ->columnSpan(2),
                            ]),
                    ]),
                Section::make('Sourcing Model')
                    ->description('How the product will be sourced')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('sourcing_model')
                                    ->label('Sourcing Model')
                                    ->badge()
                                    ->formatStateUsing(fn (SourcingModel $state): string => $state->label())
                                    ->color(fn (SourcingModel $state): string => $state->color())
                                    ->icon(fn (SourcingModel $state): string => $state->icon()),
                                TextEntry::make('sourcing_explanation')
                                    ->label('Sourcing Explanation')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => match ($record->sourcing_model) {
                                        SourcingModel::Purchase => 'Ownership transfers to us on delivery',
                                        SourcingModel::PassiveConsignment => 'We hold custody but do not own the product',
                                        SourcingModel::ThirdPartyCustody => 'Product remains with third party - no ownership or custody',
                                    })
                                    ->columnSpan(2),
                            ]),
                    ]),
                Section::make('Delivery Preferences')
                    ->description('Where the product should be delivered')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('preferred_inbound_location')
                                    ->label('Preferred Inbound Location')
                                    ->placeholder('Not specified')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('rationale')
                                    ->label('Rationale / Notes')
                                    ->placeholder('No rationale provided')
                                    ->columnSpan(1),
                            ]),
                    ]),
                Section::make('Approval Information')
                    ->description('Approval status and details')
                    ->visible(fn (ProcurementIntent $record): bool => $record->status !== ProcurementIntentStatus::Draft)
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->dateTime()
                                    ->placeholder('Not approved yet'),
                                TextEntry::make('approver.name')
                                    ->label('Approved By')
                                    ->placeholder('—'),
                                TextEntry::make('approval_note')
                                    ->label('Status')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => match ($record->status) {
                                        ProcurementIntentStatus::Draft => 'Awaiting approval',
                                        ProcurementIntentStatus::Approved => 'Approved - ready for execution',
                                        ProcurementIntentStatus::Executed => 'In execution - procurement activities underway',
                                        ProcurementIntentStatus::Closed => 'Closed - all activities completed',
                                    })
                                    ->badge()
                                    ->color(fn (ProcurementIntent $record): string => $record->status->color()),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Downstream Execution - Linked POs, Bottling Instructions, Inbound batches.
     */
    protected function getDownstreamExecutionTab(): Tab
    {
        return Tab::make('Downstream Execution')
            ->icon('heroicon-o-arrow-down-circle')
            ->badge(fn (ProcurementIntent $record): ?string => ($record->purchaseOrders()->count()
                + $record->bottlingInstructions()->count()
                + $record->inbounds()->count()) > 0
                ? (string) ($record->purchaseOrders()->count()
                    + $record->bottlingInstructions()->count()
                    + $record->inbounds()->count())
                : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Linked Purchase Orders')
                    ->description(fn (ProcurementIntent $record): string => $record->purchaseOrders()->count() > 0
                        ? $record->purchaseOrders()->count().' Purchase Order(s) linked'
                        : 'No Purchase Orders linked yet')
                    ->icon('heroicon-o-document-text')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('create_po')
                            ->label('Create PO')
                            ->icon('heroicon-o-plus')
                            ->color('primary')
                            ->visible(fn (ProcurementIntent $record): bool => $record->canCreateLinkedObjects())
                            ->url(fn (ProcurementIntent $record): string => route('filament.admin.resources.procurement/purchase-orders.create', [
                                'procurement_intent_id' => $record->id,
                            ])),
                    ])
                    ->schema([
                        RepeatableEntry::make('purchaseOrders')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('PO ID')
                                            ->copyable()
                                            ->limit(8)
                                            ->tooltip(fn (PurchaseOrder $record): string => $record->id),
                                        TextEntry::make('supplier.name')
                                            ->label('Supplier')
                                            ->placeholder('—'),
                                        TextEntry::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->suffix(' units'),
                                        TextEntry::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->money(fn (PurchaseOrder $record): string => $record->currency ?? 'EUR'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (PurchaseOrder $record): string => $record->status->label())
                                            ->color(fn (PurchaseOrder $record): string => $record->status->color())
                                            ->icon(fn (PurchaseOrder $record): string => $record->status->icon()),
                                        TextEntry::make('expected_delivery_start')
                                            ->label('Delivery Window')
                                            ->getStateUsing(fn (PurchaseOrder $record): string => $record->expected_delivery_start
                                                ? ($record->expected_delivery_start->format('Y-m-d').
                                                    ($record->expected_delivery_end ? ' - '.$record->expected_delivery_end->format('Y-m-d') : ''))
                                                : 'Not set'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (ProcurementIntent $record): bool => $record->purchaseOrders()->count() > 0),
                        TextEntry::make('no_pos')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No Purchase Orders have been created for this intent yet. Purchase Orders are created when sourcing contracts are established with suppliers.')
                            ->color('gray')
                            ->visible(fn (ProcurementIntent $record): bool => $record->purchaseOrders()->count() === 0),
                    ]),
                Section::make('Linked Bottling Instructions')
                    ->description(fn (ProcurementIntent $record): string => $record->bottlingInstructions()->count() > 0
                        ? $record->bottlingInstructions()->count().' Bottling Instruction(s) linked'
                        : 'No Bottling Instructions linked yet')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn (ProcurementIntent $record): bool => $record->isForLiquidProduct() || $record->bottlingInstructions()->count() > 0)
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('create_bottling_instruction')
                            ->label('Create Bottling Instruction')
                            ->icon('heroicon-o-plus')
                            ->color('primary')
                            ->visible(fn (ProcurementIntent $record): bool => $record->canCreateLinkedObjects() && $record->isForLiquidProduct())
                            ->url(fn (ProcurementIntent $record): string => route('filament.admin.resources.procurement/bottling-instructions.create', [
                                'procurement_intent_id' => $record->id,
                            ])),
                    ])
                    ->schema([
                        RepeatableEntry::make('bottlingInstructions')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Instruction ID')
                                            ->copyable()
                                            ->limit(8)
                                            ->tooltip(fn (BottlingInstruction $record): string => $record->id),
                                        TextEntry::make('bottle_equivalents')
                                            ->label('Bottle Equivalents')
                                            ->numeric(),
                                        TextEntry::make('bottling_deadline')
                                            ->label('Deadline')
                                            ->date()
                                            ->color(fn (BottlingInstruction $record): string => $record->getDeadlineUrgency() === 'urgent' ? 'danger' : ($record->getDeadlineUrgency() === 'warning' ? 'warning' : 'gray')),
                                        TextEntry::make('preference_status')
                                            ->label('Preference Status')
                                            ->badge()
                                            ->formatStateUsing(fn (BottlingInstruction $record): string => $record->preference_status->label())
                                            ->color(fn (BottlingInstruction $record): string => $record->preference_status->color()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (BottlingInstruction $record): string => $record->status->label())
                                            ->color(fn (BottlingInstruction $record): string => $record->status->color())
                                            ->icon(fn (BottlingInstruction $record): string => $record->status->icon()),
                                        TextEntry::make('delivery_location')
                                            ->label('Delivery Location')
                                            ->placeholder('Not set'),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (ProcurementIntent $record): bool => $record->bottlingInstructions()->count() > 0),
                        TextEntry::make('no_bottling')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No Bottling Instructions have been created for this intent yet. Bottling Instructions manage post-sale bottling decisions for liquid products.')
                            ->color('gray')
                            ->visible(fn (ProcurementIntent $record): bool => $record->bottlingInstructions()->count() === 0),
                    ]),
                Section::make('Linked Inbound Batches')
                    ->description(fn (ProcurementIntent $record): string => $record->inbounds()->count() > 0
                        ? $record->inbounds()->count().' Inbound batch(es) linked'
                        : 'No Inbound batches linked yet')
                    ->icon('heroicon-o-truck')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('link_inbound')
                            ->label('Link Inbound')
                            ->icon('heroicon-o-link')
                            ->color('primary')
                            ->visible(fn (ProcurementIntent $record): bool => $record->canCreateLinkedObjects())
                            ->url(fn (ProcurementIntent $record): string => route('filament.admin.resources.procurement/inbounds.create', [
                                'procurement_intent_id' => $record->id,
                            ])),
                    ])
                    ->schema([
                        RepeatableEntry::make('inbounds')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Inbound ID')
                                            ->copyable()
                                            ->limit(8)
                                            ->tooltip(fn (Inbound $record): string => $record->id),
                                        TextEntry::make('warehouse')
                                            ->label('Warehouse'),
                                        TextEntry::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->suffix(' units'),
                                        TextEntry::make('received_date')
                                            ->label('Received')
                                            ->date(),
                                        TextEntry::make('ownership_flag')
                                            ->label('Ownership')
                                            ->badge()
                                            ->formatStateUsing(fn (Inbound $record): string => $record->ownership_flag->label())
                                            ->color(fn (Inbound $record): string => $record->ownership_flag->color())
                                            ->icon(fn (Inbound $record): string => $record->ownership_flag->icon()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (Inbound $record): string => $record->status->label())
                                            ->color(fn (Inbound $record): string => $record->status->color())
                                            ->icon(fn (Inbound $record): string => $record->status->icon()),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (ProcurementIntent $record): bool => $record->inbounds()->count() > 0),
                        TextEntry::make('no_inbounds')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No Inbound batches have been linked to this intent yet. Inbound records physical arrival of goods - it does NOT imply ownership.')
                            ->color('gray')
                            ->visible(fn (ProcurementIntent $record): bool => $record->inbounds()->count() === 0),
                    ]),
                Section::make('Execution Summary')
                    ->description('Overview of downstream execution progress')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('po_count')
                                    ->label('Purchase Orders')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => $record->purchaseOrders()->count().' total')
                                    ->helperText(fn (ProcurementIntent $record): string => $record->purchaseOrders()->where('status', PurchaseOrderStatus::Closed)->count().' closed'),
                                TextEntry::make('bottling_count')
                                    ->label('Bottling Instructions')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => $record->bottlingInstructions()->count().' total')
                                    ->helperText(fn (ProcurementIntent $record): string => $record->bottlingInstructions()->where('status', BottlingInstructionStatus::Executed)->count().' executed'),
                                TextEntry::make('inbound_count')
                                    ->label('Inbound Batches')
                                    ->getStateUsing(fn (ProcurementIntent $record): string => $record->inbounds()->count().' total')
                                    ->helperText(fn (ProcurementIntent $record): string => $record->inbounds()->where('status', InboundStatus::Completed)->count().' completed'),
                                TextEntry::make('closure_readiness')
                                    ->label('Closure Readiness')
                                    ->getStateUsing(function (ProcurementIntent $record): string {
                                        $service = app(ProcurementIntentService::class);
                                        $validation = $service->canClose($record);

                                        return $validation['can_close'] ? 'Ready to close' : count($validation['pending_items']).' pending items';
                                    })
                                    ->badge()
                                    ->color(function (ProcurementIntent $record): string {
                                        $service = app(ProcurementIntentService::class);
                                        $validation = $service->canClose($record);

                                        return $validation['can_close'] ? 'success' : 'warning';
                                    }),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 3: Allocation & Voucher Context - Read-only context from Module A.
     */
    protected function getAllocationVoucherContextTab(): Tab
    {
        return Tab::make('Allocation & Voucher Context')
            ->icon('heroicon-o-link')
            ->schema([
                Section::make('Source Context')
                    ->description('Information about what triggered this procurement intent')
                    ->schema([
                        TextEntry::make('trigger_context')
                            ->label('')
                            ->getStateUsing(function (ProcurementIntent $record): string {
                                if ($record->trigger_type === ProcurementTriggerType::VoucherDriven) {
                                    return 'This intent was created from a voucher sale. The allocation and voucher information below shows the demand source.';
                                }
                                if ($record->trigger_type === ProcurementTriggerType::AllocationDriven) {
                                    return 'This intent was created from allocation demand. The allocation information below shows the projected demand source.';
                                }
                                if ($record->trigger_type === ProcurementTriggerType::Strategic) {
                                    return 'This intent was created manually as a strategic procurement decision. No direct allocation or voucher linkage.';
                                }

                                return 'This intent was created from a contractual commitment.';
                            })
                            ->icon(fn (ProcurementIntent $record): string => match ($record->trigger_type) {
                                ProcurementTriggerType::VoucherDriven => 'heroicon-o-ticket',
                                ProcurementTriggerType::AllocationDriven => 'heroicon-o-clipboard-document-list',
                                ProcurementTriggerType::Strategic => 'heroicon-o-light-bulb',
                                ProcurementTriggerType::Contractual => 'heroicon-o-document-check',
                            })
                            ->iconColor(fn (ProcurementIntent $record): string => $record->trigger_type->color()),
                    ]),
                Section::make('Allocation Information')
                    ->description('Allocation(s) driving this intent (read-only)')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->visible(fn (ProcurementIntent $record): bool => $record->trigger_type === ProcurementTriggerType::VoucherDriven
                        || $record->trigger_type === ProcurementTriggerType::AllocationDriven)
                    ->schema([
                        TextEntry::make('allocation_info')
                            ->label('')
                            ->getStateUsing(function (ProcurementIntent $record): string {
                                // Extract allocation ID from rationale if available
                                $rationale = $record->rationale ?? '';
                                if (preg_match('/Allocation ID: ([a-f0-9-]+)/i', $rationale, $matches)) {
                                    return 'Linked Allocation ID: '.$matches[1];
                                }

                                return 'Allocation information not available in rationale. Check audit logs for creation context.';
                            })
                            ->copyable(),
                        TextEntry::make('allocation_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Allocation details are read-only from Module A. The allocation determines the lineage and constraints for fulfillment.')
                            ->color('gray')
                            ->icon('heroicon-o-information-circle'),
                    ]),
                Section::make('Voucher Information')
                    ->description('Voucher(s) driving this intent (read-only)')
                    ->icon('heroicon-o-ticket')
                    ->visible(fn (ProcurementIntent $record): bool => $record->trigger_type === ProcurementTriggerType::VoucherDriven)
                    ->schema([
                        TextEntry::make('voucher_info')
                            ->label('')
                            ->getStateUsing(function (ProcurementIntent $record): string {
                                // Extract voucher ID from rationale if available
                                $rationale = $record->rationale ?? '';
                                if (preg_match('/Voucher ID: ([a-f0-9-]+)/i', $rationale, $matches)) {
                                    return 'Linked Voucher ID: '.$matches[1];
                                }

                                return 'Voucher information not available in rationale. Check audit logs for creation context.';
                            })
                            ->copyable(),
                        TextEntry::make('voucher_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Voucher details are read-only from Module A. Each voucher represents a customer entitlement that must be fulfilled.')
                            ->color('gray')
                            ->icon('heroicon-o-information-circle'),
                    ]),
                Section::make('Strategic Context')
                    ->description('Manual procurement decision context')
                    ->icon('heroicon-o-light-bulb')
                    ->visible(fn (ProcurementIntent $record): bool => $record->trigger_type === ProcurementTriggerType::Strategic
                        || $record->trigger_type === ProcurementTriggerType::Contractual)
                    ->schema([
                        TextEntry::make('rationale')
                            ->label('Rationale')
                            ->placeholder('No rationale provided')
                            ->columnSpanFull(),
                        TextEntry::make('strategic_note')
                            ->label('')
                            ->getStateUsing(fn (ProcurementIntent $record): string => $record->trigger_type === ProcurementTriggerType::Strategic
                                ? 'Strategic intents are created manually without direct allocation or voucher linkage. They represent speculative or discretionary procurement decisions.'
                                : 'Contractual intents are created from existing supplier agreements. They represent committed procurement obligations.')
                            ->color('gray')
                            ->icon('heroicon-o-information-circle'),
                    ]),
                Section::make('Module A Integration')
                    ->description('Read-only context from the Allocation & Voucher module')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('module_a_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This tab shows the demand source from Module A (Allocations & Vouchers). '
                                .'The data here is read-only as Module A is the authoritative source for allocation and voucher information. '
                                .'Changes to allocations or vouchers must be made in Module A and will be reflected here automatically.')
                            ->icon('heroicon-o-shield-check')
                            ->iconColor('warning'),
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
                            ->getStateUsing(function (ProcurementIntent $record): string {
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
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this procurement intent for compliance and traceability purposes. Events include creation, approval, status changes, and linked object additions.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes made to this procurement intent'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
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
     * Get the header actions for the view page.
     *
     * @return array<Actions\Action|Actions\ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        /** @var ProcurementIntent $record */
        $record = $this->record;

        return [
            // Approve action (Draft → Approved)
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Procurement Intent')
                ->modalDescription('Are you sure you want to approve this procurement intent? Once approved, linked objects (POs, Bottling Instructions, Inbounds) can be created.')
                ->visible(fn (): bool => $record->isDraft())
                ->action(function () use ($record): void {
                    try {
                        $service = app(ProcurementIntentService::class);
                        $service->approve($record);

                        Notification::make()
                            ->title('Intent Approved')
                            ->body("Procurement Intent #{$record->id} has been approved.")
                            ->success()
                            ->send();

                        $this->redirect(ProcurementIntentResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Approval Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Mark Executed action (Approved → Executed)
            Actions\Action::make('mark_executed')
                ->label('Mark Executed')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Mark Intent as Executed')
                ->modalDescription('Are you sure you want to mark this intent as executed? This indicates that procurement activities have begun.')
                ->visible(fn (): bool => $record->isApproved())
                ->action(function () use ($record): void {
                    try {
                        $service = app(ProcurementIntentService::class);
                        $service->markExecuted($record);

                        Notification::make()
                            ->title('Intent Executed')
                            ->body("Procurement Intent #{$record->id} has been marked as executed.")
                            ->success()
                            ->send();

                        $this->redirect(ProcurementIntentResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Execution Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Close action (Executed → Closed)
            Actions\Action::make('close')
                ->label('Close')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Close Procurement Intent')
                ->modalDescription(function () use ($record): string {
                    $service = app(ProcurementIntentService::class);
                    $validation = $service->canClose($record);

                    if ($validation['can_close']) {
                        return 'Are you sure you want to close this procurement intent? Closed intents cannot be reopened.';
                    }

                    return 'Cannot close this intent. The following items are still pending: '.implode(', ', $validation['pending_items']);
                })
                ->visible(fn (): bool => $record->isExecuted())
                ->disabled(function () use ($record): bool {
                    $service = app(ProcurementIntentService::class);
                    $validation = $service->canClose($record);

                    return ! $validation['can_close'];
                })
                ->action(function () use ($record): void {
                    try {
                        $service = app(ProcurementIntentService::class);
                        $service->close($record);

                        Notification::make()
                            ->title('Intent Closed')
                            ->body("Procurement Intent #{$record->id} has been closed.")
                            ->success()
                            ->send();

                        $this->redirect(ProcurementIntentResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Close Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Action group for secondary actions
            Actions\ActionGroup::make([
                Actions\Action::make('create_po')
                    ->label('Create Purchase Order')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn (): bool => $record->canCreateLinkedObjects())
                    ->url(fn (): string => route('filament.admin.resources.procurement/purchase-orders.create', [
                        'procurement_intent_id' => $record->id,
                    ])),
                Actions\Action::make('create_bottling_instruction')
                    ->label('Create Bottling Instruction')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn (): bool => $record->canCreateLinkedObjects() && $record->isForLiquidProduct())
                    ->url(fn (): string => route('filament.admin.resources.procurement/bottling-instructions.create', [
                        'procurement_intent_id' => $record->id,
                    ])),
                Actions\Action::make('link_inbound')
                    ->label('Link Inbound')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (): bool => $record->canCreateLinkedObjects())
                    ->url(fn (): string => route('filament.admin.resources.procurement/inbounds.create', [
                        'procurement_intent_id' => $record->id,
                    ])),
            ])->label('Create Linked Object')
                ->icon('heroicon-o-plus-circle')
                ->button()
                ->visible(fn (): bool => $record->canCreateLinkedObjects()),

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
