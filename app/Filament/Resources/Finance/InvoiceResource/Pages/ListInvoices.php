<?php

namespace App\Filament\Resources\Finance\InvoiceResource\Pages;

use App\Enums\Finance\InvoiceStatus;
use App\Filament\Resources\Finance\InvoiceResource;
use App\Models\Finance\Invoice;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Create action will be implemented in US-E018
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-document-text')
                ->badge(fn (): int => Invoice::count()),

            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Draft))
                ->badge(fn (): int => Invoice::where('status', InvoiceStatus::Draft)->count())
                ->badgeColor('gray'),

            'issued' => Tab::make('Issued')
                ->icon('heroicon-o-document-check')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Issued))
                ->badge(fn (): int => Invoice::where('status', InvoiceStatus::Issued)->count())
                ->badgeColor('info'),

            'overdue' => Tab::make('Overdue')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', InvoiceStatus::Issued)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->startOfDay()))
                ->badge(fn (): int => Invoice::where('status', InvoiceStatus::Issued)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now()->startOfDay())
                    ->count())
                ->badgeColor('danger'),

            'paid' => Tab::make('Paid')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Paid))
                ->badge(fn (): int => Invoice::where('status', InvoiceStatus::Paid)->count())
                ->badgeColor('success'),
        ];
    }
}
