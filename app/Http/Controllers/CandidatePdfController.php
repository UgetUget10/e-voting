<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Election;
use App\Services\AdminActivityLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CandidatePdfController extends Controller
{
    public function __invoke(Request $request, AdminActivityLog $activityLog): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $candidates = Candidate::query()
            ->ordered()
            ->with('fields')
            ->withVoteTotals()
            ->get();

        $activityLog->record('Mengunduh daftar kandidat dalam PDF.');

        return Pdf::loadView('admin.daftar-kandidat-pdf', [
            'election' => Election::current(),
            'candidates' => $candidates,
            'printedAt' => now()->timezone('Asia/Jakarta'),
        ])->setPaper('a4')->download('daftar-kandidat.pdf');
    }
}
