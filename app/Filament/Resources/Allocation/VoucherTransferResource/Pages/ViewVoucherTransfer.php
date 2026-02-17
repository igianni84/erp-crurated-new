<?php

namespace App\Filament\Resources\Allocation\VoucherTransferResource\Pages;

use App\Enums\Allocation\VoucherTransferStatus;
use App\Filament\Resources\Allocation\VoucherTransferResource;
use App\Models\Allocation\VoucherTransfer;
use App\Services\Allocation\VoucherTransferService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use InvalidArgumentException;

class ViewVoucherTransfer extends ViewRecord
{
    protected static string $resource = VoucherTransferResource::class;

    public function getTitle(): string
    {
        /** @var VoucherTransfer $record */
        $record = $this->record;

        return "Transfer #{$record->id}";
    }

    public function getSubheading(): ?string
    {
        /** @var VoucherTransfer $record */
        $record = $this->record;

        return "From {$record->fromCustomer?->name} to {$record->toCustomer?->name}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                // Header with status banner
                Section::make()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Transfer ID')
                                    ->copyable()
                                    ->copyMessage('Transfer ID copied'),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (VoucherTransferStatus $state): string => $state->label())
                                    ->color(fn (VoucherTransferStatus $state): string => $state->color())
                                    ->icon(fn (VoucherTransferStatus $state): string => $state->icon()),

                                TextEntry::make('status_description')
                                    ->label('Status Details')
                                    ->getStateUsing(fn (VoucherTransfer $record): string => $record->getStatusDescription()),
                            ]),
                    ])
                    ->extraAttributes(function (): array {
                        /** @var VoucherTransfer $record */
                        $record = $this->record;

                        return [
                            'class' => 'border-l-4 '.match ($record->status) {
                                VoucherTransferStatus::Pending => 'border-l-warning-500',
                                VoucherTransferStatus::Accepted => 'border-l-success-500',
                                VoucherTransferStatus::Cancelled => 'border-l-danger-500',
                                VoucherTransferStatus::Expired => 'border-l-gray-500',
                            },
                        ];
                    }),

                // Acceptance Blocked Warning (shown when voucher is locked during pending transfer)
                Section::make('')
                    ->schema([
                        TextEntry::make('acceptance_blocked_title')
                            ->label('')
                            ->getStateUsing(fn (): string => '⚠️ LOCKED DURING TRANSFER - ACCEPTANCE BLOCKED')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('danger'),
                        TextEntry::make('acceptance_blocked_message')
                            ->label('')
                            ->getStateUsing(fn (VoucherTransfer $record): string => $record->getAcceptanceBlockedReason() ?? '')
                            ->color('danger'),
                        TextEntry::make('acceptance_blocked_info')
                            ->label('')
                            ->getStateUsing(fn (VoucherTransfer $record): string => 'The voucher was locked for fulfillment at '
                                .($record->voucher?->getLockedAtTimestamp()?->format('Y-m-d H:i:s') ?? 'an unknown time')
                                .' while this transfer was pending. The transfer cannot be accepted until the voucher is unlocked, but it can still be cancelled.')
                            ->color('gray'),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-danger-50 dark:bg-danger-950 border-2 border-danger-500 rounded-lg',
                    ])
                    ->visible(fn (VoucherTransfer $record): bool => $record->isAcceptanceBlockedByLock()),

                // Voucher Information
                Section::make('Voucher Information')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('voucher_id')
                                    ->label('Voucher ID')
                                    ->url(fn (VoucherTransfer $record): string => route('filament.admin.resources.vouchers.view', ['record' => $record->voucher_id]))
                                    ->openUrlInNewTab()
                                    ->color('primary')
                                    ->copyable(),

                                TextEntry::make('voucher_sku')
                                    ->label('Bottle SKU')
                                    ->getStateUsing(fn (VoucherTransfer $record): string => $record->voucher?->getBottleSkuLabel() ?? 'N/A'),

                                TextEntry::make('voucher_lifecycle')
                                    ->label('Current Voucher State')
                                    ->badge()
                                    ->getStateUsing(fn (VoucherTransfer $record): string => $record->voucher?->lifecycle_state->label() ?? 'N/A')
                                    ->color(fn (VoucherTransfer $record): string => $record->voucher?->lifecycle_state->color() ?? 'gray')
                                    ->icon(fn (VoucherTransfer $record): ?string => $record->isAcceptanceBlockedByLock()
                                        ? 'heroicon-o-lock-closed'
                                        : null),

                                TextEntry::make('voucher_allocation')
                                    ->label('Allocation')
                                    ->getStateUsing(fn (VoucherTransfer $record): string => "#{$record->voucher?->allocation_id}")
                                    ->url(fn (VoucherTransfer $record): ?string => $record->voucher?->allocation_id
                                        ? route('filament.admin.resources.allocations.view', ['record' => $record->voucher->allocation_id])
                                        : null)
                                    ->openUrlInNewTab()
                                    ->color('primary'),
                            ]),
                    ]),

                // Transfer Participants
                Section::make('Transfer Participants')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('fromCustomer.name')
                                        ->label('From Customer')
                                        ->url(fn (VoucherTransfer $record): ?string => $record->fromCustomer
                                            ? route('filament.admin.resources.customer.customers.view', ['record' => $record->fromCustomer])
                                            : null)
                                        ->openUrlInNewTab()
                                        ->color('primary'),
                                    TextEntry::make('fromCustomer.email')
                                        ->label('Email'),
                                ])->columnSpan(1),

                                Group::make([
                                    TextEntry::make('toCustomer.name')
                                        ->label('To Customer')
                                        ->url(fn (VoucherTransfer $record): ?string => $record->toCustomer
                                            ? route('filament.admin.resources.customer.customers.view', ['record' => $record->toCustomer])
                                            : null)
                                        ->openUrlInNewTab()
                                        ->color('primary'),
                                    TextEntry::make('toCustomer.email')
                                        ->label('Email'),
                                ])->columnSpan(1),
                            ]),

                        TextEntry::make('transfer_note')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Transfers do not create new vouchers or consume allocation. They only change the voucher holder.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),

                // Timeline
                Section::make('Timeline')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('initiated_at')
                                    ->label('Initiated')
                                    ->dateTime(),

                                TextEntry::make('expires_at')
                                    ->label('Expires At')
                                    ->dateTime()
                                    ->color(fn (VoucherTransfer $record): string => $record->isPending() && $record->expires_at->isPast() ? 'danger' : 'gray')
                                    ->suffix(fn (VoucherTransfer $record): string => $record->isPending() && $record->expires_at->isPast() ? ' (Expired!)' : ''),

                                TextEntry::make('accepted_at')
                                    ->label('Accepted At')
                                    ->dateTime()
                                    ->placeholder('Not accepted')
                                    ->hidden(fn (VoucherTransfer $record): bool => $record->accepted_at === null),

                                TextEntry::make('cancelled_at')
                                    ->label('Cancelled At')
                                    ->dateTime()
                                    ->placeholder('Not cancelled')
                                    ->hidden(fn (VoucherTransfer $record): bool => $record->cancelled_at === null),
                            ]),
                    ]),

                // Event History
                Section::make('Event History')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('auditLogs')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('event')
                                            ->label('Event')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'transfer_initiated' => 'info',
                                                'transfer_accepted' => 'success',
                                                'transfer_cancelled' => 'danger',
                                                'transfer_expired' => 'gray',
                                                default => 'gray',
                                            }),

                                        TextEntry::make('user.name')
                                            ->label('User')
                                            ->placeholder('System'),

                                        TextEntry::make('created_at')
                                            ->label('Time')
                                            ->dateTime(),

                                        TextEntry::make('changes')
                                            ->label('Changes')
                                            ->getStateUsing(function ($record): string {
                                                $changes = [];
                                                if (! empty($record->old_values)) {
                                                    foreach ($record->old_values as $key => $value) {
                                                        if (isset($record->new_values[$key])) {
                                                            $changes[] = "{$key}: {$value} → {$record->new_values[$key]}";
                                                        }
                                                    }
                                                }

                                                return implode(', ', $changes) ?: 'See details';
                                            }),
                                    ]),
                            ])
                            ->placeholder('No audit logs recorded'),
                    ]),
            ]);
    }

    /**
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel_transfer')
                ->label('Cancel Transfer')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Transfer')
                ->modalDescription(function (): string {
                    /** @var VoucherTransfer $record */
                    $record = $this->record;

                    return "Are you sure you want to cancel this transfer? The voucher will remain with {$record->fromCustomer?->name}.";
                })
                ->modalSubmitActionLabel('Yes, Cancel Transfer')
                ->action(function (): void {
                    try {
                        /** @var VoucherTransfer $record */
                        $record = $this->record;

                        $service = app(VoucherTransferService::class);
                        $service->cancelTransfer($record);

                        Notification::make()
                            ->title('Transfer cancelled')
                            ->success()
                            ->send();

                        $this->refreshFormData([]);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Cannot cancel transfer')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(function (): bool {
                    /** @var VoucherTransfer $record */
                    $record = $this->record;

                    return $record->canBeCancelled();
                })
                ->authorize('cancelTransfer'),

            Action::make('view_voucher')
                ->label('View Voucher')
                ->icon('heroicon-o-ticket')
                ->color('gray')
                ->url(function (): string {
                    /** @var VoucherTransfer $record */
                    $record = $this->record;

                    return route('filament.admin.resources.vouchers.view', ['record' => $record->voucher_id]);
                })
                ->openUrlInNewTab(),
        ];
    }
}
