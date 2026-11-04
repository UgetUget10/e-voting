<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Results;
use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class AdminShortcuts extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-shortcuts';

    /**
     * Quick links to the screens admins open most, limited to those the current user may see. Each link
     * shows how many items it holds, so the count is visible before opening the screen.
     *
     * @return array{shortcuts: list<array{label: string, icon: Heroicon, url: string, count: ?int}>}
     */
    protected function getViewData(): array
    {
        $candidates = [
            ['Hasil voting', Heroicon::OutlinedChartBar, Results::canAccess(), fn () => Results::getUrl(), null],
            ['Suara masuk', Heroicon::OutlinedClipboardDocumentList, BallotResource::canViewAny(), fn () => BallotResource::getUrl('index'), fn (): int => Ballot::count()],
            ['Kandidat', Heroicon::OutlinedRectangleStack, CandidateResource::canViewAny(), fn () => CandidateResource::getUrl('index'), fn (): int => Candidate::count()],
            ['Bidang', Heroicon::OutlinedSquares2x2, FieldResource::canViewAny(), fn () => FieldResource::getUrl('index'), fn (): int => Field::count()],
            ['Pemilihan', Heroicon::OutlinedCalendarDays, ElectionResource::canViewAny(), fn () => ElectionResource::getUrl('index'), fn (): int => Election::count()],
            ['Pemilih', Heroicon::OutlinedUsers, UserResource::canViewAny(), fn () => UserResource::getUrl('index'), fn (): int => User::where('is_admin', false)->count()],
        ];

        $shortcuts = [];

        foreach ($candidates as [$label, $icon, $allowed, $url, $count]) {
            if ($allowed) {
                $shortcuts[] = ['label' => $label, 'icon' => $icon, 'url' => $url(), 'count' => $count === null ? null : $count()];
            }
        }

        return ['shortcuts' => $shortcuts];
    }
}
