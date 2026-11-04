<?php

namespace App\Http\Middleware;

use App\Filament\Voter\Pages\CheckReceipt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Voters can only open the ballot and the results through the status page, which hands out the link token.
 * A typed address without that token goes back to the status page. Admins are not restricted.
 */
class RequireStatusPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('pemilih/vote', 'pemilih/hasil') || ! $request->user() || $request->user()->is_admin) {
            return $next($request);
        }

        if (CheckReceipt::hasGateAccess($request)) {
            return $next($request);
        }

        return redirect(CheckReceipt::getUrl(panel: 'pemilih'));
    }
}
