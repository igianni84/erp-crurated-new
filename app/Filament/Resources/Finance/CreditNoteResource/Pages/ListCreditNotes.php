<?php

namespace App\Filament\Resources\Finance\CreditNoteResource\Pages;

use App\Enums\Finance\CreditNoteStatus;
use App\Filament\Resources\Finance\CreditNoteResource;
use App\Models\Finance\CreditNote;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCreditNotes extends ListRecords
{
    protected static string $resource = CreditNoteResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Create action will be implemented in US-E065
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
                ->badge(fn (): int => CreditNote::count()),

            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CreditNoteStatus::Draft))
                ->badge(fn (): int => CreditNote::where('status', CreditNoteStatus::Draft)->count())
                ->badgeColor('gray'),

            'issued' => Tab::make('Issued')
                ->icon('heroicon-o-document-check')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CreditNoteStatus::Issued))
                ->badge(fn (): int => CreditNote::where('status', CreditNoteStatus::Issued)->count())
                ->badgeColor('info'),

            'applied' => Tab::make('Applied')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CreditNoteStatus::Applied))
                ->badge(fn (): int => CreditNote::where('status', CreditNoteStatus::Applied)->count())
                ->badgeColor('success'),
        ];
    }
}
