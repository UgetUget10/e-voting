<?php

namespace App\Http\Responses;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * Voters land on their status page after signing in, even when they opened another page first.
 * Admins land on the results. Other panels keep Filament's default redirect.
 *
 * Every panel flashes a welcome for the page that follows, so that page can play the login success animation once.
 */
class VoterLoginResponse implements LoginResponseContract
{
    public const SUCCESS_SESSION_KEY = 'auth.login_success';

    public function toResponse($request): RedirectResponse
    {
        $this->flashWelcome(auth()->user());

        if (Filament::getCurrentPanel()?->getId() !== 'pemilih') {
            return redirect()->intended(Filament::getUrl());
        }

        session()->forget('url.intended');

        $url = auth()->user()?->is_admin
            ? Results::getUrl(panel: 'pemilih')
            : CheckReceipt::getUrl(panel: 'pemilih');

        return redirect($url);
    }

    private function flashWelcome(?User $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        session()->flash(self::SUCCESS_SESSION_KEY, [
            'name' => $user->name ?: $user->username,
            'is_admin' => $user->is_admin,
        ]);
    }
}
