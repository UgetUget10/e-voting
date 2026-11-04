<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Setting;
use App\Models\User;
use App\Services\BallotSubmissionSummary;
use App\Services\ResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BeritaAcaraController extends Controller
{
    /**
     * A printable record of the results: election, participation, and the winners of each field.
     * It is plain HTML so the browser's print dialog can save it as PDF without any extra package.
     */
    public function __invoke(Request $request, ResultService $results, BallotSubmissionSummary $submissions): View
    {
        abort_unless($request->user()?->is_admin, 403);

        $election = Election::current();
        $voterCount = User::query()->where('is_admin', false)->count();
        $ballotCount = Ballot::query()->count();
        $institution = Setting::valuesFor([Setting::INSTITUTION_NAME, Setting::SIGNER_NAME, Setting::SIGNER_TITLE]);

        return view('admin.berita-acara', [
            'election' => $election,
            'institutionName' => $institution[Setting::INSTITUTION_NAME],
            'signerName' => $institution[Setting::SIGNER_NAME],
            'signerTitle' => $institution[Setting::SIGNER_TITLE],
            'results' => $results->results(),
            'voterCount' => $voterCount,
            'ballotCount' => $ballotCount,
            'turnout' => $voterCount > 0 ? round($ballotCount / $voterCount * 100, 1) : 0,
            'fieldParticipation' => $submissions->perField($voterCount),
            'hourlySubmissions' => $submissions->perHour(),
            'printedAt' => now()->timezone('Asia/Jakarta'),
        ]);
    }
}
