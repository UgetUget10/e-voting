<?php

namespace App\Providers\Filament;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use App\Filament\Voter\Pages\Vote;
use App\Http\Middleware\RequireStatusPage;
use App\Models\Election;
use App\Models\Setting;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class VoterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pemilih')
            ->path('pemilih')
            ->homeUrl(fn () => CheckReceipt::getUrl(panel: 'pemilih'))
            ->login()
            ->brandName('E-Voting Matsanewa')
            ->brandLogo(fn () => view('filament.voter.partials.brand-logo'))
            ->topNavigation()
            ->darkMode(false)
            ->userMenu(false)
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.voter.partials.account-bar'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.voter.partials.styles'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->user()?->is_admin === false && Vote::hasVoted()
                ? view('filament.voter.partials.back-guard', ['statusUrl' => CheckReceipt::getUrl(panel: 'pemilih')])
                : '')
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.voter.partials.login-intro', [
                'election' => Election::current(),
                'institution' => Setting::value(Setting::INSTITUTION_NAME),
                'announcement' => Setting::value(Setting::VOTER_ANNOUNCEMENT),
            ]))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.voter.partials.login-stage'))
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverPages(in: app_path('Filament/Voter/Pages'), for: 'App\Filament\Voter\Pages')
            ->pages([
                Vote::class,
                Results::class,
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
                RequireStatusPage::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
