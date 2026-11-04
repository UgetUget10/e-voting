<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\AdminActivityLog;
use App\Services\FieldStandings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FieldPdfController extends Controller
{
    public function __invoke(Request $request, FieldStandings $standings, AdminActivityLog $activityLog): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $activityLog->record('Mengunduh daftar bidang dalam PDF.');

        return Pdf::loadView('admin.daftar-bidang-pdf', [
            'election' => Election::current(),
            'standings' => $standings->all()->values(),
            'printedAt' => now()->timezone('Asia/Jakarta'),
        ])->setPaper('a4')->download('daftar-bidang.pdf');
    }
}
