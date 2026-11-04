<?php

use App\Http\Controllers\BeritaAcaraController;
use App\Http\Controllers\CandidatePdfController;
use App\Http\Controllers\FieldPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResultExportController;
use App\Http\Controllers\ResultPdfController;
use App\Http\Controllers\ResultXlsxExportController;
use App\Http\Controllers\VoterCardController;
use Illuminate\Support\Facades\Route;

// Voting and results live in the voter Filament panel at /pemilih.
Route::get('/', fn () => redirect('/pemilih'));
Route::redirect('/vote', '/pemilih/vote')->name('ballots.create');
Route::redirect('/results', '/pemilih/hasil')->name('results.index');
Route::redirect('/dashboard', '/pemilih')->name('dashboard');
Route::get('/login', fn () => redirect('/pemilih/login'))->name('login');
Route::redirect('/pemilih/cek-tanda-terima', '/pemilih/status-memilih');
// The voter panel has no visible menu, so Filament cannot pick a home page and would redirect to itself.
Route::redirect('/pemilih', '/pemilih/status-memilih');

Route::middleware('auth')->group(function () {
    Route::get('/hasil/ekspor', ResultExportController::class)->name('results.export');
    Route::get('/hasil/ekspor.xlsx', ResultXlsxExportController::class)->name('results.export.xlsx');
    Route::get('/hasil/ekspor.pdf', ResultPdfController::class)->name('results.export.pdf');
    Route::get('/hasil/berita-acara', BeritaAcaraController::class)->name('results.berita-acara');

    Route::get('/pemilih-kartu', VoterCardController::class)->name('voter-cards.print');

    Route::get('/kandidat/ekspor.pdf', CandidatePdfController::class)->name('candidates.export.pdf');
    Route::get('/bidang/ekspor.pdf', FieldPdfController::class)->name('fields.export.pdf');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
