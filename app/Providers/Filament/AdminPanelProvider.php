<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ElectionDashboard;
use App\Filament\Widgets\LatestBallots;
use App\Filament\Widgets\VotingStats;
use App\Http\Responses\VoterLoginResponse;
use App\Models\Election;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('E-Voting Matsanewa')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->brandLogo(fn () => view('filament.voter.partials.brand-logo'))
            ->darkMode(false)
            ->userMenu(false)
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.partials.admin-account-bar'))
            ->renderHook(PanelsRenderHook::PAGE_START, fn () => view('filament.theme.page-ornament'))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.voter.partials.login-success', [
                'success' => session()->pull(VoterLoginResponse::SUCCESS_SESSION_KEY),
            ]))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.voter.partials.logout-farewell'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.voter.partials.login-intro', [
                'election' => Election::current(),
                'title' => 'Panel Pengelola',
                'text' => 'Masuk untuk mengelola pemilihan, kandidat, dan data pemilih.',
            ]))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.voter.partials.login-stage'))
            ->navigationGroups([
                NavigationGroup::make('Pemilihan'),
                NavigationGroup::make('Data'),
                NavigationGroup::make('Sistem'),
            ])
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.theme.sidebar-status', [
                'election' => Election::current(),
            ]))
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                ElectionDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                VotingStats::class,
                LatestBallots::class,
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
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->multiFactorAuthentication([
                EmailAuthentication::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
