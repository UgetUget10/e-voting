<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VoterCardController extends Controller
{
    public const SESSION_KEY = 'voter_cards';

    /**
     * Printable cards with the temporary passwords just issued. The passwords are shown once: they are
     * read from the session and gone after this page loads, since only their hashes are stored.
     */
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->is_admin, 403);

        $cards = $request->session()->pull(self::SESSION_KEY);

        abort_if($cards === null, 404);

        return view('admin.voter-cards', ['cards' => $cards]);
    }
}
