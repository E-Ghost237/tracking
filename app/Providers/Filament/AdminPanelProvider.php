<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\SecurityHeaders;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Back-office (section 6.3). Staff sign in through the main login (with lockout and TOTP);
 * the panel itself is reachable only by active staff with two-factor authentication (FR-144),
 * optionally from an IP allowlist (FR-149). Every resource is gated by a policy.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->path(trim((string) config('platform.admin.path', 'admin'), '/'))
            ->brandName(config('platform.brand.name').' Back-office')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->favicon(asset('favicon.svg'))
            /*
             * The palette mirrors resources/css/app.css so the back-office reads as part of the
             * same product: one signal accent for primary actions, the site's navy ramp as the
             * neutral grey scale, and status colours kept semantic. `info` is mapped to the
             * site's mid navy rather than a second accent.
             */
            ->colors([
                'primary' => Color::hex('#c2410c'),
                'gray' => [
                    50 => '#f5f7fa',
                    100 => '#eff3f8',
                    200 => '#e3e8ef',
                    300 => '#c0d0e2',
                    400 => '#5d7fa6',
                    500 => '#546078',
                    600 => '#375c86',
                    700 => '#234669',
                    800 => '#0f2740',
                    900 => '#0b1f36',
                    950 => '#061526',
                ],
                'info' => Color::hex('#375c86'),
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->font('Inter', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->strictAuthorization()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->navigationGroups([
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Payments'),
                NavigationGroup::make('Pricing'),
                NavigationGroup::make('Customers and support'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('System'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                SecurityHeaders::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                AdminAccess::class,
                EnsureAccountActive::class,
                NoStore::class,
                'throttle:global',
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true);
    }
}
