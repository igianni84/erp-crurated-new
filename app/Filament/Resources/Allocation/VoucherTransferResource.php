<?php

namespace App\Filament\Resources\Allocation;

use App\Enums\Allocation\VoucherTransferStatus;
use App\Filament\Resources\Allocation\VoucherTransferResource\Pages;
use App\Models\Allocation\VoucherTransfer;
use App\Services\Allocation\VoucherTransferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoucherTransferResource extends Resource
{
    protected static ?string $model = VoucherTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Transfers';

    protected static ?string $modelLabel = 'Transfer';

    protected static ?string $pluralModelLabel = 'Transfers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // No form schema - transfers are created from VoucherResource
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Transfer ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transfer ID copied'),

                Tables\Columns\TextColumn::make('voucher_id')
                    ->label('Voucher')
                    ->searchable()
                    ->sortable()
                    ->url(fn (VoucherTransfer $record): string => route('filament.admin.resources.vouchers.view', ['record' => $record->voucher_id]))
                    ->openUrlInNewTab()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('fromCustomer.name')
                    ->label('From')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('fromCustomer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->description(fn (VoucherTransfer $record): ?string => $record->fromCustomer?->email)
                    ->url(fn (VoucherTransfer $record): ?string => $record->fromCustomer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->fromCustomer])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('toCustomer.name')
                    ->label('To')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('toCustomer', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->description(fn (VoucherTransfer $record): ?string => $record->toCustomer?->email)
                    ->url(fn (VoucherTransfer $record): ?string => $record->toCustomer
                        ? route('filament.admin.resources.customer.customers.view', ['record' => $record->toCustomer])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function (VoucherTransferStatus $state, VoucherTransfer $record): string {
                        $label = $state->label();
                        if ($record->isAcceptanceBlockedByLock()) {
                            $label .= ' (BLOCKED)';
                        }

                        return $label;
                    })
                    ->color(function (VoucherTransferStatus $state, VoucherTransfer $record): string {
                        if ($record->isAcceptanceBlockedByLock()) {
                            return 'danger';
                        }

                        return $state->color();
                    })
                    ->icon(function (VoucherTransferStatus $state, VoucherTransfer $record): string {
                        if ($record->isAcceptanceBlockedByLock()) {
                            return 'heroicon-o-lock-closed';
                        }

                        return $state->icon();
                    })
                    ->description(fn (VoucherTransfer $record): ?string => $record->isAcceptanceBlockedByLock()
                        ? 'Locked during transfer'
                        : null)
                    ->sortable(),

                Tables\Columns\TextColumn::make('initiated_at')
                    ->label('Initiated')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (VoucherTransfer $record): string => $record->isPending() && $record->expires_at->isPast() ? 'danger' : 'gray')
                    ->description(fn (VoucherTransfer $record): ?string => $record->isPending() && $record->expires_at->isPast() ? 'Expired' : null),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Accepted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('cancelled_at')
                    ->label('Cancelled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(VoucherTransferStatus::cases())
                        ->mapWithKeys(fn (VoucherTransferStatus $status) => [$status->value => $status->label()])
                        ->toArray())
                    ->default([VoucherTransferStatus::Pending->value])
                    ->multiple()
                    ->label('Status'),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('initiated_from')
                            ->label('Initiated From'),
                        Forms\Components\DatePicker::make('initiated_until')
                            ->label('Initiated Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['initiated_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('initiated_at', '>=', $date)
                            )
                            ->when(
                                $data['initiated_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('initiated_at', '<=', $date)
                            );
                    }),

                Tables\Filters\Filter::make('from_customer')
                    ->form([
                        Forms\Components\Select::make('from_customer_id')
                            ->label('From Customer')
                            ->relationship('fromCustomer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['from_customer_id'] ?? null,
                            fn (Builder $query, string $customerId): Builder => $query->where('from_customer_id', $customerId)
                        );
                    }),

                Tables\Filters\Filter::make('to_customer')
                    ->form([
                        Forms\Components\Select::make('to_customer_id')
                            ->label('To Customer')
                            ->relationship('toCustomer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['to_customer_id'] ?? null,
                            fn (Builder $query, string $customerId): Builder => $query->where('to_customer_id', $customerId)
                        );
                    }),

                Tables\Filters\Filter::make('voucher')
                    ->form([
                        Forms\Components\TextInput::make('voucher_id')
                            ->label('Voucher ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['voucher_id'] ?? null,
                            fn (Builder $query, string $voucherId): Builder => $query->where('voucher_id', $voucherId)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('cancel_transfer')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Transfer')
                    ->modalDescription(fn (VoucherTransfer $record): string => "Are you sure you want to cancel this transfer? The voucher will remain with {$record->fromCustomer?->name}.")
                    ->modalSubmitActionLabel('Yes, Cancel Transfer')
                    ->action(function (VoucherTransfer $record): void {
                        try {
                            $service = app(VoucherTransferService::class);
                            $service->cancelTransfer($record);

                            Notification::make()
                                ->title('Transfer cancelled')
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Cannot cancel transfer')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (VoucherTransfer $record): bool => $record->canBeCancelled())
                    ->authorize('cancelTransfer'),
            ])
            ->bulkActions([
                // No bulk actions - transfers require careful individual handling
            ])
            ->defaultSort('initiated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'voucher',
                'fromCustomer',
                'toCustomer',
            ]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoucherTransfers::route('/'),
            'view' => Pages\ViewVoucherTransfer::route('/{record}'),
        ];
    }

    /**
     * Transfers cannot be created from the admin panel.
     * They are created from the Voucher detail page via VoucherTransferService.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
