<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Results;
use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Services\ResultService;
use Filament\Widgets\Widget;

class ActionNeeded extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.action-needed';

    /**
     * Things the admin should act on now, most urgent first. Each item links to the screen where it is fixed.
     *
     * @return array{items: list<array{level: string, label: string, action: string, url: string}>, attention: int}
     */
    protected function getViewData(): array
    {
        $items = self::items();

        return [
            'items' => $items,
            'attention' => self::attentionCount($items),
        ];
    }

    /**
     * Items ordered by level: urgent, then warning, then info.
     *
     * @return list<array{level: string, label: string, action: string, url: string}>
     */
    public static function items(): array
    {
        $election = Election::current();
        $items = [];

        if ($election === null) {
            $items[] = self::item('urgent', 'Belum ada pemilihan. Buat pemilihan agar pemilih bisa mulai memilih.', 'Buat', ElectionResource::getUrl('index'));
        }

        $spikes = BallotsByHour::spikeHours(BallotsByHour::countsForToday());

        if ($spikes !== []) {
            $hours = implode(', ', array_map(fn (int $hour): string => sprintf('%02d.00', $hour), $spikes));

            $items[] = self::item('urgent', 'Lonjakan suara pukul '.$hours.' WIB, jauh di atas kebiasaan hari ini. Periksa apakah ada pengiriman berulang.', 'Periksa', BallotResource::getUrl('index'));
        }

        $pendingCount = User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count();

        if ($election !== null && ! $election->hasClosed() && $pendingCount > 0) {
            $closesSoon = $election->closes_at !== null && $election->closes_at->lte(now()->addDay());

            $items[] = self::item(
                $closesSoon ? 'urgent' : 'warning',
                $closesSoon
                    ? 'Voting ditutup kurang dari 24 jam lagi. '.$pendingCount.' pemilih belum memilih.'
                    : $pendingCount.' pemilih belum memilih.',
                'Kirim pengingat',
                UserResource::getUrl('index', ['tableFilters' => ['voted' => ['value' => '0']]]),
            );
        }

        $problems = Ballot::query()->withProblems()->count();

        if ($problems > 0) {
            $items[] = self::item('warning', $problems.' suara bermasalah: tanpa pilihan atau tanpa pemilih.', 'Periksa', BallotResource::getUrl('index'));
        }

        $tied = app(ResultService::class)->results()
            ->filter(fn (array $result): bool => app(ResultService::class)->isTied($result['candidates']))
            ->count();

        if ($tied > 0) {
            $items[] = self::item('warning', $tied.' bidang seri. Kandidat yang seri ikut lolos ke tahap berikutnya.', 'Lihat hasil', Results::getUrl());
        }

        if ($election?->show_results_early && ! $election->hasClosed()) {
            $items[] = self::item('info', 'Hasil sementara sudah terlihat pemilih sebelum voting ditutup.', 'Lihat hasil', Results::getUrl());
        }

        usort($items, fn (array $first, array $second): int => self::rank($first['level']) <=> self::rank($second['level']));

        return $items;
    }

    /**
     * How many items need a decision now, for the tab badge.
     *
     * @param  list<array{level: string}>|null  $items
     */
    public static function attentionCount(?array $items = null): int
    {
        return count(array_filter($items ?? self::items(), fn (array $item): bool => $item['level'] !== 'info'));
    }

    /**
     * @return array{level: string, label: string, action: string, url: string}
     */
    private static function item(string $level, string $label, string $action, string $url): array
    {
        return ['level' => $level, 'label' => $label, 'action' => $action, 'url' => $url];
    }

    private static function rank(string $level): int
    {
        return match ($level) {
            'urgent' => 0,
            'warning' => 1,
            default => 2,
        };
    }
}
