<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Services\AdminActivityLog;
use App\Services\ResultService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResultPdfController extends Controller
{
    public function __invoke(Request $request, ResultService $results, AdminActivityLog $activityLog): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $voterCount = User::query()->where('is_admin', false)->count();
        $ballotCount = Ballot::query()->count();

        $activityLog->record('Mengunduh hasil voting dalam PDF.');

        return Pdf::loadView('admin.hasil-pdf', [
            'election' => Election::current(),
            'results' => $results->results(),
            'voterCount' => $voterCount,
            'ballotCount' => $ballotCount,
            'turnout' => $voterCount > 0 ? round($ballotCount / $voterCount * 100, 1) : 0,
            'printedAt' => now()->timezone('Asia/Jakarta'),
        ])->setPaper('a4')->download('hasil-voting.pdf');
    }
}
