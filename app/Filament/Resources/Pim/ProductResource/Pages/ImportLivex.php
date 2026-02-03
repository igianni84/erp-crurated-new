<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Filament\Resources\Pim\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

/**
 * Placeholder page for Liv-ex import flow.
 * Full implementation will be done in US-013.
 */
class ImportLivex extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.pim.product-resource.pages.import-livex';

    protected static ?string $title = 'Import from Liv-ex';

    protected static ?string $breadcrumb = 'Import Liv-ex';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->url(ProductResource::getUrl('create-bottle'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
