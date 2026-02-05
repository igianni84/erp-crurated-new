<?php

namespace App\Filament\Resources\Finance\PaymentResource\Pages;

use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReconciliationStatus;
use App\Filament\Resources\Finance\PaymentResource;
use App\Models\Finance\Payment;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Manual payment recording will be implemented in US-E056
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-banknotes')
                ->badge(fn (): int => Payment::count()),

            'pending_reconciliation' => Tab::make('Pending Reconciliation')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('reconciliation_status', ReconciliationStatus::Pending))
                ->badge(fn (): int => Payment::where('reconciliation_status', ReconciliationStatus::Pending)->count())
                ->badgeColor('warning'),

            'confirmed' => Tab::make('Confirmed')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', PaymentStatus::Confirmed))
                ->badge(fn (): int => Payment::where('status', PaymentStatus::Confirmed)->count())
                ->badgeColor('success'),
        ];
    }
}
