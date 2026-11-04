<?php

namespace App\Services;

use App\Models\Election;
use Filament\Notifications\Notification;

class ElectionScheduleWarning
{
    /**
     * Warn the admin when an election's voting window overlaps another one. The save still goes through,
     * since the admin may have a reason, but voters always use the newest election, so the warning matters.
     */
    public function notifyIfOverlapping(Election $election): void
    {
        $overlapping = $election->overlappingElections();

        if ($overlapping->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Jadwal bertabrakan dengan pemilihan lain.')
            ->body('Bertabrakan dengan: '.$overlapping->pluck('name')->implode(', ').'. Pemilih memakai pemilihan terbaru, jadi pastikan jadwalnya sesuai.')
            ->send();
    }
}
