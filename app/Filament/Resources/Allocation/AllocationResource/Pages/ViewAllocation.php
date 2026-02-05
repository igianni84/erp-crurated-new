<?php

namespace App\Filament\Resources\Allocation\AllocationResource\Pages;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\ReservationStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\TemporaryReservation;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use App\Services\Allocation\AllocationService;
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

class ViewAllocation extends ViewRecord
{
    protected static string $resource = AllocationResource::class;

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
        /** @var Allocation $record */
        $record = $this->record;

        return "Allocation #{$record->id} - {$record->getBottleSkuLabel()}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Allocation Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getConstraintsTab(),
                        $this->getCapacityTab(),
                        $this->getReservationsTab(),
                        $this->getVouchersTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Read-only control panel with status, quantities, availability window.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Status & Identity')
                    ->description('Current allocation status and identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Allocation ID')
                                        ->copyable()
                                        ->copyMessage('Allocation ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('uuid')
                                        ->label('UUID')
                                        ->copyable()
                                        ->copyMessage('UUID copied'),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (AllocationStatus $state): string => $state->label())
                                        ->color(fn (AllocationStatus $state): string => $state->color())
                                        ->icon(fn (AllocationStatus $state): string => $state->icon()),
                                    TextEntry::make('source_type')
                                        ->label('Source Type')
                                        ->badge()
                                        ->formatStateUsing(fn (AllocationSourceType $state): string => $state->label())
                                        ->color(fn (AllocationSourceType $state): string => $state->color())
                                        ->icon(fn (AllocationSourceType $state): string => $state->icon()),
                                    TextEntry::make('supply_form')
                                        ->label('Supply Form')
                                        ->badge()
                                        ->formatStateUsing(fn (AllocationSupplyForm $state): string => $state->label())
                                        ->color(fn (AllocationSupplyForm $state): string => $state->color())
                                        ->icon(fn (AllocationSupplyForm $state): string => $state->icon()),
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
                                    TextEntry::make('serialization_required')
                                        ->label('Serialization')
                                        ->badge()
                                        ->getStateUsing(fn (Allocation $record): string => $record->serialization_required ? 'Required' : 'Not Required')
                                        ->color(fn (Allocation $record): string => $record->serialization_required ? 'success' : 'gray')
                                        ->icon(fn (Allocation $record): string => $record->serialization_required ? 'heroicon-o-finger-print' : 'heroicon-o-minus-circle'),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Bottle SKU')
                    ->description('Wine, vintage, and format information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('wineVariant.wineMaster.name')
                                    ->label('Wine')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('wineVariant.wineMaster.producer')
                                    ->label('Producer'),
                                TextEntry::make('wineVariant.vintage_year')
                                    ->label('Vintage'),
                                TextEntry::make('format.name')
                                    ->label('Format'),
                                TextEntry::make('format.volume_ml')
                                    ->label('Volume')
                                    ->suffix(' ml'),
                                TextEntry::make('bottle_sku_label')
                                    ->label('Full Bottle SKU')
                                    ->getStateUsing(fn (Allocation $record): string => $record->getBottleSkuLabel())
                                    ->copyable()
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),
                Section::make('Quantities')
                    ->description('Allocation capacity and consumption')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_quantity')
                                    ->label('Total Quantity')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('sold_quantity')
                                    ->label('Sold Quantity')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color('warning'),
                                TextEntry::make('remaining_quantity')
                                    ->label('Remaining Quantity')
                                    ->getStateUsing(fn (Allocation $record): int => $record->remaining_quantity)
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color(fn (Allocation $record): string => $record->isNearExhaustion() ? 'danger' : 'success')
                                    ->weight(fn (Allocation $record): FontWeight => $record->isNearExhaustion() ? FontWeight::Bold : FontWeight::Medium)
                                    ->icon(fn (Allocation $record): ?string => $record->isNearExhaustion() ? 'heroicon-o-exclamation-triangle' : null),
                                TextEntry::make('available_quantity')
                                    ->label('Available (excl. reservations)')
                                    ->getStateUsing(function (Allocation $record): int {
                                        $service = app(AllocationService::class);

                                        return $service->getRemainingAvailable($record);
                                    })
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color('info'),
                            ]),
                    ]),
                Section::make('Availability Window')
                    ->description('Expected availability period')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('expected_availability_start')
                                    ->label('Start Date')
                                    ->date()
                                    ->default('Not specified'),
                                TextEntry::make('expected_availability_end')
                                    ->label('End Date')
                                    ->date()
                                    ->default('Not specified'),
                                TextEntry::make('availability_window')
                                    ->label('Window Summary')
                                    ->getStateUsing(fn (Allocation $record): string => $record->getAvailabilityWindowLabel())
                                    ->weight(FontWeight::Medium),
                            ]),
                    ]),
                Section::make('Lineage Rule')
                    ->description('Allocation lineage is authoritative and immutable')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('lineage_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This allocation defines the lineage for all vouchers issued from it. Vouchers inherit the allocation\'s constraints and must be fulfilled with supply from the same lineage. Lineage cannot be modified after voucher issuance.')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tab 2: Constraints - Commercial constraints, editable only in Draft.
     */
    protected function getConstraintsTab(): Tab
    {
        return Tab::make('Constraints')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Section::make('Commercial Constraints')
                    ->description(fn (Allocation $record): string => $record->isDraft()
                        ? 'These constraints are editable while in Draft status'
                        : 'Constraints are locked after activation')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('edit_constraints')
                            ->label('Edit Constraints')
                            ->icon('heroicon-o-pencil')
                            ->url(fn (Allocation $record): string => AllocationResource::getUrl('edit', ['record' => $record]))
                            ->visible(fn (Allocation $record): bool => $record->isDraft()),
                    ])
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('constraint.allowed_channels')
                                    ->label('Allowed Channels')
                                    ->badge()
                                    ->separator(', ')
                                    ->getStateUsing(function (Allocation $record): string {
                                        $constraint = $record->constraint;
                                        if ($constraint === null) {
                                            return 'All channels';
                                        }
                                        $channels = $constraint->allowed_channels;

                                        return ($channels === null || $channels === []) ? 'All channels' : implode(', ', $channels);
                                    })
                                    ->color('info'),
                                TextEntry::make('constraint.allowed_geographies')
                                    ->label('Allowed Geographies')
                                    ->badge()
                                    ->separator(', ')
                                    ->getStateUsing(function (Allocation $record): string {
                                        $constraint = $record->constraint;
                                        if ($constraint === null) {
                                            return 'All geographies';
                                        }
                                        $geos = $constraint->allowed_geographies;

                                        return ($geos === null || $geos === []) ? 'All geographies' : implode(', ', $geos);
                                    })
                                    ->color('info'),
                                TextEntry::make('constraint.allowed_customer_types')
                                    ->label('Allowed Customer Types')
                                    ->badge()
                                    ->separator(', ')
                                    ->getStateUsing(function (Allocation $record): string {
                                        $constraint = $record->constraint;
                                        if ($constraint === null) {
                                            return 'All customer types';
                                        }
                                        $types = $constraint->allowed_customer_types;

                                        return ($types === null || $types === []) ? 'All customer types' : implode(', ', $types);
                                    })
                                    ->color('info'),
                            ]),
                    ]),
                Section::make('Advanced Constraints')
                    ->description('Composition and fungibility rules')
                    ->collapsible()
                    ->collapsed(fn (Allocation $record): bool => $record->constraint === null
                        || ($record->constraint->composition_constraint_group === null && ! $record->constraint->fungibility_exception))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('constraint.composition_constraint_group')
                                    ->label('Composition Constraint Group')
                                    ->default('None')
                                    ->helperText('Used for vertical cases or themed selections'),
                                TextEntry::make('constraint.fungibility_exception')
                                    ->label('Fungibility Exception')
                                    ->badge()
                                    ->getStateUsing(function (Allocation $record): string {
                                        $constraint = $record->constraint;
                                        if ($constraint === null) {
                                            return 'No';
                                        }

                                        return $constraint->fungibility_exception ? 'Yes' : 'No';
                                    })
                                    ->color(fn (Allocation $record): string => ($record->constraint !== null && $record->constraint->fungibility_exception) ? 'warning' : 'gray')
                                    ->helperText('If yes, bottles are not interchangeable'),
                            ]),
                    ]),
                Section::make('Liquid Allocation Constraints')
                    ->description('Additional constraints for liquid supply allocations')
                    ->icon('heroicon-o-beaker')
                    ->visible(fn (Allocation $record): bool => $record->isLiquid())
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('liquidConstraint.allowed_bottling_formats')
                                    ->label('Allowed Bottling Formats')
                                    ->badge()
                                    ->separator(', ')
                                    ->getStateUsing(function (Allocation $record): string {
                                        $lc = $record->liquidConstraint;
                                        if ($lc === null) {
                                            return 'All formats';
                                        }
                                        $formats = $lc->allowed_bottling_formats;

                                        return ($formats === null || $formats === []) ? 'All formats' : implode(', ', $formats);
                                    })
                                    ->color('info'),
                                TextEntry::make('liquidConstraint.allowed_case_configurations')
                                    ->label('Allowed Case Configurations')
                                    ->badge()
                                    ->separator(', ')
                                    ->getStateUsing(function (Allocation $record): string {
                                        $lc = $record->liquidConstraint;
                                        if ($lc === null) {
                                            return 'All configurations';
                                        }
                                        $configs = $lc->allowed_case_configurations;

                                        return ($configs === null || $configs === []) ? 'All configurations' : implode(', ', $configs);
                                    })
                                    ->color('info'),
                                TextEntry::make('liquidConstraint.bottling_confirmation_deadline')
                                    ->label('Bottling Confirmation Deadline')
                                    ->date()
                                    ->default('Not set')
                                    ->color(function (Allocation $record): string {
                                        $lc = $record->liquidConstraint;
                                        if ($lc === null || $lc->bottling_confirmation_deadline === null) {
                                            return 'gray';
                                        }

                                        return $lc->isBottlingDeadlinePassed() ? 'danger' : 'success';
                                    }),
                            ]),
                    ]),
                Section::make('Constraint Summary')
                    ->schema([
                        TextEntry::make('constraint_summary')
                            ->label('')
                            ->getStateUsing(function (Allocation $record): string {
                                $constraint = $record->constraint;

                                return $constraint !== null ? $constraint->getSummary() : 'No constraints defined';
                            })
                            ->weight(FontWeight::Medium),
                    ]),
            ]);
    }

    /**
     * Tab 3: Capacity & Consumption - Breakdown by sellable SKU, channel, time.
     */
    protected function getCapacityTab(): Tab
    {
        return Tab::make('Capacity & Consumption')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Section::make('Capacity Overview')
                    ->description('Allocation capacity breakdown')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_quantity')
                                    ->label('Total Capacity')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('sold_quantity')
                                    ->label('Consumed')
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color('warning')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('remaining_quantity')
                                    ->label('Remaining')
                                    ->getStateUsing(fn (Allocation $record): int => $record->remaining_quantity)
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color(fn (Allocation $record): string => $record->isNearExhaustion() ? 'danger' : 'success')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('consumption_percentage')
                                    ->label('Utilization')
                                    ->getStateUsing(function (Allocation $record): string {
                                        if ($record->total_quantity === 0) {
                                            return '0%';
                                        }

                                        return round(($record->sold_quantity / $record->total_quantity) * 100, 1).'%';
                                    })
                                    ->badge()
                                    ->color(function (Allocation $record): string {
                                        if ($record->total_quantity === 0) {
                                            return 'gray';
                                        }
                                        $pct = ($record->sold_quantity / $record->total_quantity) * 100;
                                        if ($pct >= 90) {
                                            return 'danger';
                                        }
                                        if ($pct >= 70) {
                                            return 'warning';
                                        }

                                        return 'success';
                                    })
                                    ->size(TextEntry\TextEntrySize::Large),
                            ]),
                    ]),
                Section::make('Active Reservations Impact')
                    ->description('Temporary holds affecting available quantity')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('active_reservations_count')
                                    ->label('Active Reservations')
                                    ->getStateUsing(fn (Allocation $record): int => $record->activeReservations()->count())
                                    ->numeric()
                                    ->suffix(' reservations'),
                                TextEntry::make('reserved_quantity')
                                    ->label('Reserved Quantity')
                                    ->getStateUsing(fn (Allocation $record): int => (int) $record->activeReservations()->sum('quantity'))
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color('warning'),
                                TextEntry::make('available_for_sale')
                                    ->label('Available for Sale')
                                    ->getStateUsing(function (Allocation $record): int {
                                        $service = app(AllocationService::class);

                                        return $service->getRemainingAvailable($record);
                                    })
                                    ->numeric()
                                    ->suffix(' bottles')
                                    ->color('success')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),
                Section::make('Consumption Breakdown')
                    ->description('Note: Detailed consumption by Sellable SKU, channel, and time will be available when Voucher tracking is implemented (US-015+)')
                    ->icon('heroicon-o-clock')
                    ->iconColor('gray')
                    ->schema([
                        TextEntry::make('consumption_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Consumption breakdown by Sellable SKU, sales channel, and time period will be populated once vouchers are issued from this allocation. This provides full traceability of how the allocation capacity was utilized.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 4: Reservations - List of temporary reservations with status.
     */
    protected function getReservationsTab(): Tab
    {
        return Tab::make('Reservations')
            ->icon('heroicon-o-clock')
            ->badge(fn (Allocation $record): ?string => $record->activeReservations()->count() > 0
                ? (string) $record->activeReservations()->count()
                : null)
            ->badgeColor('warning')
            ->schema([
                Section::make('Temporary Reservations')
                    ->description('Reservations temporarily block quantity but do not consume the allocation')
                    ->schema([
                        RepeatableEntry::make('temporaryReservations')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Reservation ID')
                                            ->copyable(),
                                        TextEntry::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->suffix(' bottles'),
                                        TextEntry::make('context_type')
                                            ->label('Context')
                                            ->badge()
                                            ->formatStateUsing(fn (TemporaryReservation $record): string => $record->getContextTypeLabel())
                                            ->color(fn (TemporaryReservation $record): string => $record->context_type->color()),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (TemporaryReservation $record): string => $record->getStatusLabel())
                                            ->color(fn (TemporaryReservation $record): string => $record->getStatusColor())
                                            ->icon(fn (TemporaryReservation $record): string => $record->status->icon()),
                                        TextEntry::make('expires_at')
                                            ->label('Expires At')
                                            ->dateTime()
                                            ->color(fn (TemporaryReservation $record): string => $record->hasExpired() && $record->isActive() ? 'danger' : 'gray'),
                                        TextEntry::make('context_reference')
                                            ->label('Reference')
                                            ->default('—')
                                            ->copyable(),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
                Section::make('Reservation Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_reservations')
                                    ->label('Total Reservations')
                                    ->getStateUsing(fn (Allocation $record): int => $record->temporaryReservations()->count())
                                    ->numeric(),
                                TextEntry::make('active_reservations')
                                    ->label('Active')
                                    ->getStateUsing(fn (Allocation $record): int => $record->temporaryReservations()->where('status', ReservationStatus::Active)->count())
                                    ->numeric()
                                    ->color('warning'),
                                TextEntry::make('expired_reservations')
                                    ->label('Expired')
                                    ->getStateUsing(fn (Allocation $record): int => $record->temporaryReservations()->where('status', ReservationStatus::Expired)->count())
                                    ->numeric()
                                    ->color('gray'),
                                TextEntry::make('converted_reservations')
                                    ->label('Converted')
                                    ->getStateUsing(fn (Allocation $record): int => $record->temporaryReservations()->where('status', ReservationStatus::Converted)->count())
                                    ->numeric()
                                    ->color('success'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 5: Vouchers - Read-only list of vouchers issued from this allocation.
     */
    protected function getVouchersTab(): Tab
    {
        return Tab::make('Vouchers')
            ->icon('heroicon-o-ticket')
            ->badge(fn (Allocation $record): ?string => $record->vouchers()->count() > 0
                ? (string) $record->vouchers()->count()
                : null)
            ->badgeColor('success')
            ->schema([
                Section::make('Voucher Summary')
                    ->description('Summary of voucher lifecycle states for this allocation')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_issued')
                                    ->label('Total Issued')
                                    ->getStateUsing(fn (Allocation $record): int => $record->vouchers()->count())
                                    ->numeric()
                                    ->suffix(' vouchers')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->color('primary'),
                                TextEntry::make('vouchers_issued_state')
                                    ->label('Issued')
                                    ->getStateUsing(fn (Allocation $record): int => $record->vouchers()
                                        ->where('lifecycle_state', VoucherLifecycleState::Issued)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' vouchers')
                                    ->color('success')
                                    ->icon('heroicon-o-ticket'),
                                TextEntry::make('vouchers_locked')
                                    ->label('Locked')
                                    ->getStateUsing(fn (Allocation $record): int => $record->vouchers()
                                        ->where('lifecycle_state', VoucherLifecycleState::Locked)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' vouchers')
                                    ->color('warning')
                                    ->icon('heroicon-o-lock-closed'),
                                TextEntry::make('vouchers_redeemed')
                                    ->label('Redeemed')
                                    ->getStateUsing(fn (Allocation $record): int => $record->vouchers()
                                        ->where('lifecycle_state', VoucherLifecycleState::Redeemed)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' vouchers')
                                    ->color('info')
                                    ->icon('heroicon-o-check-badge'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('vouchers_cancelled')
                                    ->label('Cancelled')
                                    ->getStateUsing(fn (Allocation $record): int => $record->vouchers()
                                        ->where('lifecycle_state', VoucherLifecycleState::Cancelled)
                                        ->count())
                                    ->numeric()
                                    ->suffix(' vouchers')
                                    ->color('danger')
                                    ->icon('heroicon-o-x-circle'),
                            ]),
                    ]),
                Section::make('Issued Vouchers')
                    ->description('Read-only list of vouchers issued from this allocation')
                    ->icon('heroicon-o-ticket')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_all_vouchers')
                            ->label('View All in Vouchers List')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (Allocation $record): string => VoucherResource::getUrl('index', [
                                'tableFilters' => [
                                    'allocation' => [
                                        'allocation_id' => $record->id,
                                    ],
                                ],
                            ]))
                            ->openUrlInNewTab()
                            ->visible(fn (Allocation $record): bool => $record->vouchers()->count() > 0),
                    ])
                    ->schema([
                        RepeatableEntry::make('vouchers')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('Voucher ID')
                                            ->url(fn (Voucher $record): string => VoucherResource::getUrl('view', ['record' => $record]))
                                            ->openUrlInNewTab()
                                            ->color('primary')
                                            ->copyable()
                                            ->copyMessage('Voucher ID copied'),
                                        TextEntry::make('customer.name')
                                            ->label('Customer')
                                            ->default('Unknown'),
                                        TextEntry::make('lifecycle_state')
                                            ->label('State')
                                            ->badge()
                                            ->formatStateUsing(fn (Voucher $record): string => $record->getLifecycleStateLabel())
                                            ->color(fn (Voucher $record): string => $record->getLifecycleStateColor())
                                            ->icon(fn (Voucher $record): string => $record->getLifecycleStateIcon()),
                                        TextEntry::make('flags_display')
                                            ->label('Flags')
                                            ->getStateUsing(function (Voucher $record): string {
                                                $flags = [];
                                                if ($record->suspended) {
                                                    $flags[] = 'Suspended';
                                                }
                                                if ($record->tradable) {
                                                    $flags[] = 'Tradable';
                                                }
                                                if ($record->giftable) {
                                                    $flags[] = 'Giftable';
                                                }

                                                return count($flags) > 0 ? implode(', ', $flags) : '—';
                                            })
                                            ->badge()
                                            ->color(fn (Voucher $record): string => $record->suspended ? 'danger' : 'gray'),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime(),
                                    ]),
                            ])
                            ->columns(1)
                            ->visible(fn (Allocation $record): bool => $record->vouchers()->count() > 0),
                        TextEntry::make('no_vouchers')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No vouchers have been issued from this allocation yet. Vouchers are created when sales are confirmed via VoucherService.')
                            ->html()
                            ->color('gray')
                            ->visible(fn (Allocation $record): bool => $record->vouchers()->count() === 0),
                    ]),
                Section::make('Lineage Information')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('warning')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('lineage_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'All vouchers issued from this allocation inherit its lineage and constraints. Voucher lineage (allocation_id) cannot be modified after issuance. Fulfillment must use physical supply from the same allocation lineage.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Tab 6: Audit - Immutable timeline of events.
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
                            ->getStateUsing(function (Allocation $record): string {
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
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this allocation for compliance and traceability purposes. Events include creation, updates, status changes, constraint edits (draft only), quantity changes, and closing.')
                            ->html(),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable timeline of all changes made to this allocation'];

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
        /** @var Allocation $record */
        $record = $this->record;

        return [
            Actions\EditAction::make(),
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Allocation')
                ->modalDescription('Are you sure you want to activate this allocation? Once activated, constraints become read-only and the allocation can be consumed.')
                ->visible(fn (): bool => $record->isDraft())
                ->authorize('activate', $record)
                ->action(function () use ($record): void {
                    try {
                        $service = app(AllocationService::class);
                        $service->activate($record);

                        Notification::make()
                            ->title('Allocation Activated')
                            ->body("Allocation #{$record->id} is now active and can be consumed.")
                            ->success()
                            ->send();

                        $this->redirect(AllocationResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Activation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('close')
                ->label('Close')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Close Allocation')
                ->modalDescription('Are you sure you want to close this allocation? Closed allocations cannot be reopened. If you need more supply, create a new allocation.')
                ->visible(fn (): bool => $record->isActive() || $record->isExhausted())
                ->authorize('close', $record)
                ->action(function () use ($record): void {
                    try {
                        $service = app(AllocationService::class);
                        $service->close($record);

                        Notification::make()
                            ->title('Allocation Closed')
                            ->body("Allocation #{$record->id} has been closed.")
                            ->success()
                            ->send();

                        $this->redirect(AllocationResource::getUrl('view', ['record' => $record]));
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Close Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
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
