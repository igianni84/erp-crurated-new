<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Filament\Resources\Pim\LiquidProductResource;
use App\Filament\Resources\Pim\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ChooseProductCategory extends Page
{
    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.pim.product-resource.pages.choose-product-category';

    protected static ?string $title = 'Create Product';

    protected static ?string $breadcrumb = 'Choose Category';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->url(ProductResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getBottleAction(): Action
    {
        return Action::make('bottle')
            ->label('Create Bottle Product')
            ->icon('heroicon-o-cube')
            ->color('success')
            ->size('xl')
            ->extraAttributes(['class' => 'w-full justify-center py-8'])
            ->url(route('filament.admin.resources.pim.products.create-bottle'));
    }

    public function getLiquidAction(): Action
    {
        return Action::make('liquid')
            ->label('Create Liquid Product')
            ->icon('heroicon-o-beaker')
            ->color('info')
            ->size('xl')
            ->extraAttributes(['class' => 'w-full justify-center py-8'])
            ->url(LiquidProductResource::getUrl('create'));
    }
}
