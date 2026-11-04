<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Results;
use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Services\ResultService;
use Filament\Widgets\Widget;

class ClosingChecklist extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.closing-checklist';

    /**
     * Only while voting is open, since that is when there is still time to fix what is listed.
     */
    public static function canView(): bool
    {
        return Election::current()?->isOpen() ?? false;
    }

    /**
     * @return array{checks: list<array{ok: bool, label: string, url: string}>, readyCount: int}
     */
    protected function getViewData(): array
    {
        $results = app(ResultService::class)->results();
        $problems = Ballot::query()->withProblems()->count();
        $fieldsWithoutVotes = $results->filter(fn (array $result): bool => $result['candidates']->sum('votes_count') === 0)->count();
        $tied = $results->filter(fn (array $result): bool => app(ResultService::class)->isTied($result['candidates']))->count();
        $pending = User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count();
        $spikes = BallotsByHour::spikeHours(BallotsByHour::countsForToday());

        $checks = [
            $this->check($problems === 0, 'Tidak ada suara bermasalah', $problems.' suara bermasalah, tanpa pilihan atau tanpa pemilih', BallotResource::getUrl('index')),
            $this->check($fieldsWithoutVotes === 0, 'Semua bidang sudah ada suara', $fieldsWithoutVotes.' bidang belum ada suara', Results::getUrl()),
            $this->check($tied === 0, 'Tidak ada bidang seri', $tied.' bidang seri', Results::getUrl()),
            $this->check($pending === 0, 'Semua pemilih sudah memilih', $pending.' pemilih belum memilih', UserResource::getUrl('index', ['tableFilters' => ['voted' => ['value' => '0']]])),
            $this->check($spikes === [], 'Tidak ada lonjakan suara hari ini', 'Ada lonjakan suara hari ini', BallotResource::getUrl('index')),
        ];

        return [
            'checks' => $checks,
            'readyCount' => count(array_filter($checks, fn (array $check): bool => $check['ok'])),
        ];
    }

    /**
     * @return array{ok: bool, label: string, url: string}
     */
    private function check(bool $ok, string $okLabel, string $openLabel, string $url): array
    {
        return ['ok' => $ok, 'label' => $ok ? $okLabel : $openLabel, 'url' => $url];
    }
}
