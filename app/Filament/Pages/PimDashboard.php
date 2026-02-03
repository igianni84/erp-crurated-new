<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PimDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'PIM Dashboard';

    protected static string $view = 'filament.pages.pim-dashboard';
}
