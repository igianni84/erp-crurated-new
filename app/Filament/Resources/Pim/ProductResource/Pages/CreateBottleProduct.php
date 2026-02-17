<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Filament\Resources\Pim\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class CreateBottleProduct extends Page
{
    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.pim.product-resource.pages.create-bottle-product';

    protected static ?string $title = 'Create Bottle Product';

    protected static ?string $breadcrumb = 'Create Bottle';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->url(ProductResource::getUrl('choose-category'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function getLivexAction(): Action
    {
        return Action::make('livex')
            ->label('Import from Liv-ex')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('primary')
            ->size('xl')
            ->extraAttributes(['class' => 'w-full justify-center py-8'])
            ->url(ProductResource::getUrl('import-livex'));
    }

    public function getManualAction(): Action
    {
        return Action::make('manual')
            ->label('Create Manually')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->size('xl')
            ->extraAttributes(['class' => 'w-full justify-center py-8'])
            ->url(ProductResource::getUrl('create-manual'));
    }
}
