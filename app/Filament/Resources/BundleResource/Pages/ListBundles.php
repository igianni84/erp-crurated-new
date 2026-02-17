<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBundles extends ListRecords
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Bundle'),
        ];
    }
}
