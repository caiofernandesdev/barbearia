<?php

namespace App\Providers\Filament;

use App\Http\Middleware\IsolatePanelSession;
use App\Http\Middleware\SetDefaultGuard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super-admin')
            ->path('super-admin')
            ->authGuard('super_admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->brandName('Atendix — Super Admin')
            ->brandLogo(fn () => new HtmlString(
                '<div style="display:flex;align-items:center;gap:9px;">'
                .'<img src="'.asset('images/logo-icone.png').'" alt="Atendix" style="height:2.2rem;width:2.2rem;border-radius:8px;object-fit:contain;">'
                .'<span style="font-weight:800;font-size:1.15rem;letter-spacing:-.01em;">Atendix <span style="opacity:.55;font-weight:600;font-size:.85rem;">Super Admin</span></span>'
                .'</div>'
            ))
            ->favicon(asset('images/logo-icone.png'))
            ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\Filament\SuperAdmin\Resources')
            ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\Filament\SuperAdmin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\Filament\SuperAdmin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Tenants'),
                NavigationGroup::make('Planos'),
                NavigationGroup::make('Sistema'),
            ])
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
                SetDefaultGuard::class.':super_admin',
                IsolatePanelSession::class.':super_admin,admin',
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
