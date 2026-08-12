<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\OperationsDashboard;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('operations')
            ->path('operasyon')
            ->brandName(__('panel.brand'))
            ->login()
            ->profile()
            ->revealablePasswords(false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->codeWindow(4),
            ], isRequired: false)
            ->strictAuthorization()
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()->label(__('panel.navigation.groups.overview')),
                NavigationGroup::make()->label(__('panel.navigation.groups.marketing')),
                NavigationGroup::make()->label(__('panel.navigation.groups.crm')),
                NavigationGroup::make()->label(__('panel.navigation.groups.operations')),
                NavigationGroup::make()->label(__('panel.navigation.groups.work')),
                NavigationGroup::make()->label(__('panel.navigation.groups.reports')),
                NavigationGroup::make()->label(__('panel.navigation.groups.configuration')),
            ])
            ->viteTheme('resources/css/filament/operations/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([OperationsDashboard::class])
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
