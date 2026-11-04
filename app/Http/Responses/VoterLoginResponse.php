<?php

namespace App\Http\Responses;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Results;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * Voters land on their status page after signing in, even when they opened another page first.
 * Admins land on the results. Other panels keep Filament's default redirect.
 */
class VoterLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        if (Filament::getCurrentPanel()?->getId() !== 'pemilih') {
            return redirect()->intended(Filament::getUrl());
        }

        session()->forget('url.intended');

        $url = auth()->user()?->is_admin
            ? Results::getUrl(panel: 'pemilih')
            : CheckReceipt::getUrl(panel: 'pemilih');

        return redirect($url);
    }
}
