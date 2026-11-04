<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Elections\ElectionResource;
use App\Models\Election;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;

class VotingCountdown extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.voting-countdown';

    /**
     * @return array{state: string, title: string, targetMs: ?int, startMs: ?int, progress: ?int, note: ?string, url: string}
     */
    protected function getViewData(): array
    {
        $election = Election::current();
        $url = ElectionResource::getUrl('index');

        if ($election === null) {
            return $this->countdown('none', 'Belum ada pemilihan', null, 'Buat pemilihan agar pemilih bisa mulai memberi suara.', $url);
        }

        if ($election->hasClosed()) {
            return $this->countdown('closed', 'Voting sudah ditutup', null, 'Ditutup '.$this->format($election->closes_at), $url);
        }

        if ($election->isOpen()) {
            if ($election->closes_at === null) {
                return $this->countdown('open', 'Voting sedang dibuka', null, 'Tidak ada batas waktu penutupan.', $url);
            }

            $startMs = $election->opens_at?->getTimestampMs();

            return $this->countdown(
                'open',
                'Voting ditutup dalam',
                $election->closes_at->getTimestampMs(),
                'Ditutup '.$this->format($election->closes_at),
                $url,
                $startMs,
            );
        }

        return $this->countdown('upcoming', 'Voting dibuka dalam', $election->opens_at->getTimestampMs(), 'Dibuka '.$this->format($election->opens_at), $url);
    }

    /**
     * How far the voting window has run, as a percentage. Only shown when both ends of the window are known.
     *
     * @return array{state: string, title: string, targetMs: ?int, startMs: ?int, progress: ?int, note: ?string, url: string}
     */
    private function countdown(string $state, string $title, ?int $targetMs, ?string $note, string $url, ?int $startMs = null): array
    {
        $progress = null;

        if ($targetMs !== null && $startMs !== null && $targetMs > $startMs) {
            $progress = (int) min(100, max(0, round((now()->getTimestampMs() - $startMs) / ($targetMs - $startMs) * 100)));
        }

        return [
            'state' => $state,
            'title' => $title,
            'targetMs' => $targetMs,
            'startMs' => $startMs,
            'progress' => $progress,
            'note' => $note,
            'url' => $url,
        ];
    }

    private function format(CarbonInterface $moment): string
    {
        return $moment->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i').' WIB';
    }
}
