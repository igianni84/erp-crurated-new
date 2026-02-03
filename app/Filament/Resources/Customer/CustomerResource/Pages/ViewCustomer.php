<?php

namespace App\Filament\Resources\Customer\CustomerResource\Pages;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Customer\CustomerResource;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Customer $record */
        $record = $this->record;

        return "Customer: {$record->name}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Customer Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getVouchersTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab 1: Overview - Customer information and status.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-user')
            ->schema([
                Section::make('Customer Information')
                    ->description('Basic customer details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Customer ID')
                                        ->copyable()
                                        ->copyMessage('Customer ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('email')
                                        ->label('Email')
                                        ->icon('heroicon-o-envelope')
                                        ->copyable(),
                                ])->columnSpan(2),
                                Group::make([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn (Customer $record): string => match ($record->status) {
                                            Customer::STATUS_ACTIVE => 'success',
                                            Customer::STATUS_SUSPENDED => 'warning',
                                            Customer::STATUS_CLOSED => 'danger',
                                            default => 'gray',
                                        })
                                        ->icon(fn (Customer $record): string => match ($record->status) {
                                            Customer::STATUS_ACTIVE => 'heroicon-o-check-circle',
                                            Customer::STATUS_SUSPENDED => 'heroicon-o-pause-circle',
                                            Customer::STATUS_CLOSED => 'heroicon-o-x-circle',
                                            default => 'heroicon-o-question-mark-circle',
                                        })
                                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                    TextEntry::make('created_at')
                                        ->label('Customer Since')
                                        ->date(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
            ]);
    }

    /**
     * Tab 2: Vouchers - Customer's vouchers with filters and summary.
     */
    protected function getVouchersTab(): Tab
    {
        /** @var Customer $record */
        $record = $this->record;

        $totalVouchers = $record->vouchers()->count();
        $issuedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Issued)->count();
        $lockedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Locked)->count();
        $redeemedCount = $record->vouchers()->where('lifecycle_state', VoucherLifecycleState::Redeemed)->count();

        return Tab::make('Vouchers')
            ->icon('heroicon-o-ticket')
            ->badge($totalVouchers > 0 ? (string) $totalVouchers : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Voucher Summary')
                    ->description('Overview of customer\'s voucher portfolio')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_vouchers')
                                    ->label('Total Vouchers')
                                    ->getStateUsing(fn (): int => $totalVouchers)
                                    ->numeric()
                                    ->weight(FontWeight::Bold)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-ticket'),
                                TextEntry::make('issued_vouchers')
                                    ->label('Issued')
                                    ->getStateUsing(fn (): int => $issuedCount)
                                    ->numeric()
                                    ->color('success')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-check-badge')
                                    ->helperText('Active and available'),
                                TextEntry::make('locked_vouchers')
                                    ->label('Locked')
                                    ->getStateUsing(fn (): int => $lockedCount)
                                    ->numeric()
                                    ->color('warning')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-lock-closed')
                                    ->helperText('Pending fulfillment'),
                                TextEntry::make('redeemed_vouchers')
                                    ->label('Redeemed')
                                    ->getStateUsing(fn (): int => $redeemedCount)
                                    ->numeric()
                                    ->color('gray')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->icon('heroicon-o-check-circle')
                                    ->helperText('Fulfilled'),
                            ]),
                    ]),
                Section::make('Customer Vouchers')
                    ->description('All vouchers owned by this customer. Click on a voucher ID to view details.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_all_vouchers')
                            ->label('View All in Vouchers List')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (): string => VoucherResource::getUrl('index', [
                                'tableFilters' => [
                                    'customer' => ['customer_id' => $record->id],
                                ],
                            ]))
                            ->openUrlInNewTab(),
                    ])
                    ->schema([
                        $this->getVoucherList(),
                    ]),
            ]);
    }

    /**
     * Get the voucher list component as a RepeatableEntry.
     */
    protected function getVoucherList(): RepeatableEntry
    {
        return RepeatableEntry::make('vouchers')
            ->label('')
            ->schema([
                Grid::make(6)
                    ->schema([
                        TextEntry::make('id')
                            ->label('Voucher ID')
                            ->copyable()
                            ->copyMessage('Voucher ID copied')
                            ->url(fn (Voucher $voucher): string => VoucherResource::getUrl('view', ['record' => $voucher]))
                            ->color('primary')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('bottle_sku')
                            ->label('Bottle SKU')
                            ->getStateUsing(fn (Voucher $voucher): string => $voucher->getBottleSkuLabel()),
                        TextEntry::make('lifecycle_state')
                            ->label('State')
                            ->badge()
                            ->formatStateUsing(fn (VoucherLifecycleState $state): string => $state->label())
                            ->color(fn (VoucherLifecycleState $state): string => $state->color())
                            ->icon(fn (VoucherLifecycleState $state): string => $state->icon()),
                        TextEntry::make('flags')
                            ->label('Flags')
                            ->getStateUsing(function (Voucher $voucher): string {
                                $flags = [];
                                if ($voucher->suspended) {
                                    $flags[] = 'Suspended';
                                }
                                if ($voucher->tradable) {
                                    $flags[] = 'Tradable';
                                }
                                if ($voucher->giftable) {
                                    $flags[] = 'Giftable';
                                }

                                return count($flags) > 0 ? implode(', ', $flags) : '—';
                            })
                            ->badge()
                            ->color(fn (Voucher $voucher): string => $voucher->suspended ? 'danger' : 'gray'),
                        TextEntry::make('allocation_id')
                            ->label('Allocation')
                            ->url(fn (Voucher $voucher): string => route('filament.admin.resources.allocations.view', ['record' => $voucher->allocation_id]))
                            ->color('primary'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),
            ])
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),
            ])->label('More')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }
}
