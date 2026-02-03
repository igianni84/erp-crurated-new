<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBundle extends ViewRecord
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Commercial\Bundle $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible($record->isEditable()),
        ];
    }
}
