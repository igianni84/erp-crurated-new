<?php

namespace App\Filament\Resources\Allocation\CaseEntitlementResource\Pages;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\CaseEntitlementResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Models\Allocation\CaseEntitlement;
use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;

class ViewCaseEntitlement extends ViewRecord
{
    protected static string $resource = CaseEntitlementResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var CaseEntitlement $record */
        $record = $this->record;

        return "Case Entitlement #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var CaseEntitlement $record */
        $record = $this->record;

        $sellableSku = $record->sellableSku;
        $skuCode = $sellableSku !== null ? $sellableSku->sku_code : 'Unknown SKU';

        return "{$skuCode} - {$record->status->label()}";
    }

    /**
     * Get the header actions for the view page.
     * No manual actions - case entitlements break automatically.
     *
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                // Header section with status banner
                $this->getHeaderSection(),

                // Case details section
                $this->getCaseDetailsSection(),

                // Vouchers section
                $this->getVouchersSection(),

                // Break information section (shown if broken)
                $this->getBreakInfoSection(),

                // Audit section
                $this->getAuditSection(),
            ]);
    }

    /**
     * Header section with prominent status banner.
     */
    protected function getHeaderSection(): Section
    {
        return Section::make()
            ->schema([
                Grid::make(4)
                    ->schema([
                        Group::make([
                            TextEntry::make('id')
                                ->label('Entitlement ID')
                                ->copyable()
                                ->copyMessage('Entitlement ID copied')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('customer.name')
                                ->label('Customer')
                                ->weight(FontWeight::Bold)
                                ->url(fn (CaseEntitlement $record): ?string => $record->customer
                                    ? route('filament.admin.resources.customer.customers.view', ['record' => $record->customer])
                                    : null)
                                ->color('primary'),
                            TextEntry::make('customer.email')
                                ->label('')
                                ->color('gray'),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('status')
                                ->label('Case Status')
                                ->badge()
                                ->formatStateUsing(fn (CaseEntitlementStatus $state): string => $state->label())
                                ->color(fn (CaseEntitlementStatus $state): string => $state->color())
                                ->icon(fn (CaseEntitlementStatus $state): string => $state->icon())
                                ->size(TextSize::Large),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                        ])->columnSpan(1),
                    ]),
            ])
            ->extraAttributes(fn (CaseEntitlement $record): array => [
                'class' => match ($record->status) {
                    CaseEntitlementStatus::Intact => 'border-l-4 border-l-success-500',
                    CaseEntitlementStatus::Broken => 'border-l-4 border-l-warning-500',
                },
            ]);
    }

    /**
     * Case details section with sellable SKU information.
     */
    protected function getCaseDetailsSection(): Section
    {
        return Section::make('Case Details')
            ->description('Information about the purchased case')
            ->icon('heroicon-o-cube')
            ->collapsible()
            ->schema([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('sellableSku.sku_code')
                            ->label('Sellable SKU')
                            ->weight(FontWeight::Bold)
                            ->copyable(),
                        TextEntry::make('vouchers_count')
                            ->label('Bottles in Case')
                            ->badge()
                            ->color('info')
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()->count()),
                        TextEntry::make('status')
                            ->label('Integrity Status')
                            ->badge()
                            ->formatStateUsing(fn (CaseEntitlement $record): string => $record->checkIntegrity()
                                ? 'Integrity OK'
                                : 'Integrity Lost')
                            ->color(fn (CaseEntitlement $record): string => $record->checkIntegrity()
                                ? 'success'
                                : 'warning')
                            ->icon(fn (CaseEntitlement $record): string => $record->checkIntegrity()
                                ? 'heroicon-o-check-badge'
                                : 'heroicon-o-exclamation-triangle'),
                        TextEntry::make('id')
                            ->label('UUID')
                            ->copyable()
                            ->color('gray'),
                    ]),
                TextEntry::make('case_info')
                    ->label('')
                    ->getStateUsing(fn (CaseEntitlement $record): string => $record->isIntact()
                        ? 'This case is intact. All vouchers belong to the same customer and are available for redemption as a complete case.'
                        : 'This case is broken. Vouchers may have been transferred, traded, or redeemed individually. Remaining vouchers behave as loose bottles.')
                    ->icon(fn (CaseEntitlement $record): string => $record->isIntact()
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->iconColor(fn (CaseEntitlement $record): string => $record->isIntact() ? 'success' : 'warning')
                    ->color(fn (CaseEntitlement $record): string => $record->isIntact() ? 'gray' : 'warning'),
            ]);
    }

    /**
     * Vouchers section showing all vouchers in the case.
     */
    protected function getVouchersSection(): Section
    {
        return Section::make('Vouchers in Case')
            ->description('Individual bottle entitlements that make up this case')
            ->icon('heroicon-o-ticket')
            ->collapsible()
            ->headerActions([
                Action::make('viewAllVouchers')
                    ->label('View All in Vouchers List')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CaseEntitlement $record): string => VoucherResource::getUrl('index', [
                        'filters' => [
                            'case_entitlement' => [
                                'case_entitlement_id' => $record->id,
                            ],
                        ],
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn (CaseEntitlement $record): bool => $record->vouchers()->exists()),
            ])
            ->schema([
                // Voucher summary
                Grid::make(5)
                    ->schema([
                        TextEntry::make('total_vouchers')
                            ->label('Total')
                            ->badge()
                            ->color('primary')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()->count()),
                        TextEntry::make('issued_vouchers')
                            ->label('Issued')
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-ticket')
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()
                                ->where('lifecycle_state', VoucherLifecycleState::Issued->value)
                                ->count()),
                        TextEntry::make('locked_vouchers')
                            ->label('Locked')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-lock-closed')
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()
                                ->where('lifecycle_state', VoucherLifecycleState::Locked->value)
                                ->count()),
                        TextEntry::make('redeemed_vouchers')
                            ->label('Redeemed')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-check-badge')
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()
                                ->where('lifecycle_state', VoucherLifecycleState::Redeemed->value)
                                ->count()),
                        TextEntry::make('cancelled_vouchers')
                            ->label('Cancelled')
                            ->badge()
                            ->color('danger')
                            ->icon('heroicon-o-x-circle')
                            ->getStateUsing(fn (CaseEntitlement $record): string => (string) $record->vouchers()
                                ->where('lifecycle_state', VoucherLifecycleState::Cancelled->value)
                                ->count()),
                    ]),
                // Voucher list
                RepeatableEntry::make('vouchers')
                    ->label('')
                    ->schema([
                        Grid::make(6)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Voucher ID')
                                    ->url(fn (Voucher $record): string => VoucherResource::getUrl('view', ['record' => $record->id]))
                                    ->color('primary')
                                    ->copyable(),
                                TextEntry::make('bottle_sku')
                                    ->label('Bottle SKU')
                                    ->getStateUsing(fn (Voucher $record): string => $record->getBottleSkuLabel()),
                                TextEntry::make('customer.name')
                                    ->label('Current Holder'),
                                TextEntry::make('lifecycle_state')
                                    ->label('State')
                                    ->badge()
                                    ->formatStateUsing(fn (Voucher $record): string => $record->lifecycle_state->label())
                                    ->color(fn (Voucher $record): string => $record->lifecycle_state->color())
                                    ->icon(fn (Voucher $record): string => $record->lifecycle_state->icon()),
                                TextEntry::make('flags')
                                    ->label('Flags')
                                    ->badge()
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

                                        return implode(', ', $flags) ?: 'None';
                                    })
                                    ->color(fn (Voucher $record): string => $record->suspended ? 'danger' : 'gray'),
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * Break information section (shown only if broken).
     */
    protected function getBreakInfoSection(): Section
    {
        return Section::make('Break Information')
            ->description('Details about when and why the case was broken')
            ->icon('heroicon-o-puzzle-piece')
            ->iconColor('warning')
            ->collapsible()
            ->collapsed(fn (CaseEntitlement $record): bool => ! $record->isBroken())
            ->hidden(fn (CaseEntitlement $record): bool => ! $record->isBroken())
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('broken_at')
                            ->label('Broken At')
                            ->dateTime()
                            ->weight(FontWeight::Bold)
                            ->color('danger'),
                        TextEntry::make('broken_reason')
                            ->label('Break Reason')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'transfer' => 'Voucher Transfer',
                                'trade' => 'External Trade',
                                'partial_redemption' => 'Partial Redemption',
                                default => $state ?? 'Unknown',
                            }),
                        TextEntry::make('status')
                            ->label('Reversible')
                            ->badge()
                            ->getStateUsing(fn (): string => 'No')
                            ->color('danger')
                            ->icon('heroicon-o-x-circle'),
                    ]),
                TextEntry::make('break_info')
                    ->label('')
                    ->getStateUsing(fn (): string => 'Case break is irreversible. Once a voucher is transferred, traded, or redeemed individually, the case cannot be restored to an intact state. Remaining vouchers behave as individual loose bottles.')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('info')
                    ->color('gray'),
            ]);
    }

    /**
     * Audit section showing event history.
     */
    protected function getAuditSection(): Section
    {
        return Section::make('Event History')
            ->description('Audit trail of all events for this case entitlement')
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->collapsed()
            ->schema([
                RepeatableEntry::make('auditLogs')
                    ->label('')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('event')
                                    ->label('')
                                    ->badge()
                                    ->formatStateUsing(fn (AuditLog $record): string => $record->getEventLabel())
                                    ->color(fn (AuditLog $record): string => $record->getEventColor())
                                    ->icon(fn (AuditLog $record): string => $record->getEventIcon())
                                    ->columnSpan(1),
                                TextEntry::make('user.name')
                                    ->label('')
                                    ->default('System')
                                    ->icon('heroicon-o-user')
                                    ->columnSpan(1),
                                TextEntry::make('created_at')
                                    ->label('')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->columnSpan(1),
                                TextEntry::make('changes')
                                    ->label('')
                                    ->getStateUsing(fn (AuditLog $record): string => self::formatAuditChanges($record))
                                    ->html()
                                    ->columnSpan(1),
                            ]),
                    ]),
            ]);
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
