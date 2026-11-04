<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Filament\Widgets\Widget;

class ElectionReadiness extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.election-readiness';

    /**
     * Checks that should pass before voting opens. Each one names what is missing and links to where to fix it.
     *
     * @return array{checks: list<array{ok: bool, label: string, url: string}>, readyCount: int}
     */
    protected function getViewData(): array
    {
        $election = Election::current();
        $candidateCount = Candidate::count();
        $withoutPhoto = Candidate::query()->whereNull('photo_path')->count();

        $checks = [
            ['ok' => $election !== null, 'label' => $election ? 'Pemilihan sudah dibuat' : 'Belum ada pemilihan', 'url' => ElectionResource::getUrl('index')],
            ['ok' => $election?->opens_at !== null && $election?->closes_at !== null, 'label' => $election?->opens_at !== null && $election?->closes_at !== null ? 'Jadwal buka dan tutup sudah diatur' : 'Jadwal buka atau tutup belum diatur', 'url' => ElectionResource::getUrl('index')],
            ['ok' => Field::count() > 0, 'label' => Field::count() > 0 ? Field::count().' bidang tersedia' : 'Belum ada bidang', 'url' => FieldResource::getUrl('index')],
            ['ok' => $candidateCount > 0, 'label' => $candidateCount > 0 ? $candidateCount.' kandidat terdaftar' : 'Belum ada kandidat', 'url' => CandidateResource::getUrl('index')],
            ['ok' => $withoutPhoto === 0 && $candidateCount > 0, 'label' => $withoutPhoto === 0 && $candidateCount > 0 ? 'Semua kandidat punya foto' : $withoutPhoto.' kandidat belum ada foto', 'url' => CandidateResource::getUrl('index')],
            ['ok' => User::where('is_admin', false)->exists(), 'label' => User::where('is_admin', false)->exists() ? 'Pemilih sudah terdaftar' : 'Belum ada pemilih terdaftar', 'url' => UserResource::getUrl('index')],
        ];

        return [
            'checks' => $checks,
            'readyCount' => count(array_filter($checks, fn (array $check): bool => $check['ok'])),
        ];
    }
}
