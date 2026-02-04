<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Filament\Resources\Inventory\InboundBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInboundBatches extends ListRecords
{
    protected static string $resource = InboundBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Manual Creation')
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->visible(fn (): bool => InboundBatchResource::canCreate()),
        ];
    }
}
