<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\SalesOverviewWidget;
use App\Filament\Widgets\TopSellingProductsWidget;
use App\Http\Middleware\ForceAdminLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('Zarzour Sport')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('44px')
            ->font(
                'Cairo',
                asset('fonts/zarzour/cairo.css'),
                LocalFontProvider::class,
            )
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'المبيعات والعملاء',
                'المنتجات والكتالوج',
                'التسويق والعروض',
                'محتوى التطبيق',
                'إعدادات الشحن',
                'إدارة الوصول',
            ])
            ->colors([
                'primary' => Color::hex('#7dc142'),
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn () => view('filament.components.brand', ['compact' => true]),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.components.notification-sound'),
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                SalesOverviewWidget::class,
                TopSellingProductsWidget::class,
            ])
            ->middleware([
                ForceAdminLocale::class,
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
            ->persistentMiddleware([
                ForceAdminLocale::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
