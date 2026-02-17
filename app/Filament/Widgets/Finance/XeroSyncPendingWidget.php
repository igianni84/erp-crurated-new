<?php

namespace App\Filament\Widgets\Finance;

use App\Models\Finance\Invoice;
use App\Services\Finance\XeroIntegrationService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * US-E104: Dashboard widget for Xero sync pending invoices.
 *
 * Shows invoices that have been issued but haven't been synced to Xero yet.
 * This is a violation of the invariant that all issued invoices must be synced.
 *
 * Displays in the Finance dashboard to provide visibility into sync issues.
 */
class XeroSyncPendingWidget extends BaseWidget
{
    protected static ?string $heading = 'Invoices Pending Xero Sync';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /**
     * Check if widget should be visible.
     *
     * Only show if there are invoices pending sync.
     */
    public static function canView(): bool
    {
        return Invoice::xeroSyncPending()->exists()
            || Invoice::xeroNotSynced()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where(function ($query) {
                        $query->where('xero_sync_pending', true)
                            ->orWhere(function ($q) {
                                $q->where('status', '!=', 'draft')
                                    ->where('status', '!=', 'cancelled')
                                    ->whereNull('xero_invoice_id');
                            });
                    })
                    ->with('customer')
                    ->orderBy('issued_at', 'asc')
            )
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->code())
                    ->color(fn ($state) => $state?->color()),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                IconColumn::make('xero_sync_status')
                    ->label('Sync Status')
                    ->state(function (Invoice $record): string {
                        $status = $record->getXeroSyncStatusDisplay();

                        return $status['status'];
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-x-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Invoice $record): string => $record->getXeroSyncStatusDisplay()['message']),
            ])
            ->recordActions([
                Action::make('retry_sync')
                    ->label('Retry Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Xero Sync')
                    ->modalDescription(fn (Invoice $record) => "Are you sure you want to retry the Xero sync for invoice {$record->invoice_number}?")
                    ->action(function (Invoice $record): void {
                        try {
                            $xeroService = app(XeroIntegrationService::class);
                            $syncLog = $xeroService->syncInvoice($record);

                            if ($syncLog->isSynced()) {
                                Notification::make()
                                    ->title('Sync successful')
                                    ->body("Invoice {$record->invoice_number} has been synced to Xero.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Sync pending')
                                    ->body('The sync is still pending. Check the error message.')
                                    ->warning()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Invoice $record) => route('filament.admin.resources.finance.invoices.view', $record)),
            ])
            ->toolbarActions([
                BulkAction::make('retry_all_syncs')
                    ->label('Retry Selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Xero Sync for Selected Invoices')
                    ->modalDescription('Are you sure you want to retry the Xero sync for all selected invoices?')
                    ->action(function ($records): void {
                        $xeroService = app(XeroIntegrationService::class);
                        $successCount = 0;
                        $failCount = 0;

                        foreach ($records as $record) {
                            try {
                                $syncLog = $xeroService->syncInvoice($record);
                                if ($syncLog->isSynced()) {
                                    $successCount++;
                                } else {
                                    $failCount++;
                                }
                            } catch (Exception $e) {
                                $failCount++;
                            }
                        }

                        Notification::make()
                            ->title('Bulk sync completed')
                            ->body("{$successCount} synced successfully, {$failCount} failed.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->headerActions([
                Action::make('retry_all')
                    ->label('Retry All Pending')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Retry All Pending Xero Syncs')
                    ->modalDescription('Are you sure you want to retry the Xero sync for all invoices with pending sync?')
                    ->action(function (): void {
                        $xeroService = app(XeroIntegrationService::class);
                        $successCount = $xeroService->retryAllPendingInvoiceSyncs();
                        $totalPending = Invoice::xeroSyncPending()->count();

                        Notification::make()
                            ->title('Bulk retry completed')
                            ->body("{$successCount} invoices synced successfully. {$totalPending} still pending.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('All invoices synced')
            ->emptyStateDescription('There are no invoices pending Xero synchronization.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->poll('30s');
    }
}
