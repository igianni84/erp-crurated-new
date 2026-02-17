<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->brandName('Crurated ERP')
            ->viteTheme('resources/css/filament/admin.css')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('PIM')
                    ->icon('heroicon-o-cube')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Allocations')
                    ->icon('heroicon-o-rectangle-stack')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Fulfillment')
                    ->icon('heroicon-o-truck')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Inventory')
                    ->icon('heroicon-o-archive-box')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Finance')
                    ->icon('heroicon-o-banknotes')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Vouchers')
                    ->icon('heroicon-o-ticket')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Commercial')
                    ->icon('heroicon-o-currency-dollar')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Procurement')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Customers')
                    ->icon('heroicon-o-user-group')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('System')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                'panels::user-menu.before',
                fn (): View => view('filament.hooks.ai-chat-icon'),
            );
    }
}
