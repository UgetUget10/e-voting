<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class VotingStats extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    /**
     * How far back the speed estimate looks, in minutes.
     */
    private const SPEED_WINDOW_MINUTES = 15;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.voting-stats';

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $voters = User::where('is_admin', false)->count();
        $submitted = Ballot::count();
        $election = Election::current();
        $turnout = $voters > 0 ? (int) round($submitted / $voters * 100) : 0;
        $previousTurnout = Election::query()->whereNotNull('archived_at')->latest('archived_at')->first()?->results_snapshot['turnout'] ?? null;

        return [
            'cards' => [
                [
                    'label' => 'Pemilih terdaftar',
                    'value' => $voters,
                    'decimals' => 0,
                    'hint' => 'Tidak termasuk akun admin',
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedUsers,
                ],
                [
                    'label' => 'Sudah memilih',
                    'value' => $submitted,
                    'decimals' => 0,
                    'hint' => $voters > 0 ? $turnout.'% dari pemilih' : 'Belum ada pemilih',
                    'change' => $previousTurnout === null ? null : $this->turnoutChange($turnout, (int) $previousTurnout),
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedClipboardDocumentCheck,
                ],
                [
                    'label' => 'Status voting',
                    'value' => $election?->statusLabel() ?? 'Belum ada',
                    'text' => true,
                    'hint' => $this->scheduleDescription($election),
                    'tone' => 'accent',
                    'icon' => Heroicon::OutlinedCalendarDays,
                ],
                $this->estimateCard($election, max(0, $voters - $submitted)),
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, text: bool, hint: string, tone: string, icon: Heroicon}
     */
    private function estimateCard(?Election $election, int $pending): array
    {
        $card = ['label' => 'Perkiraan selesai', 'text' => true, 'tone' => 'warning', 'icon' => Heroicon::OutlinedClock];
        $recent = $this->recentBallots();
        $rateText = number_format($recent / self::SPEED_WINDOW_MINUTES, 1, ',', '.').' suara per menit';

        if ($election === null || ! $election->isOpen()) {
            return $card + ['value' => '—', 'hint' => $election?->hasClosed() ? 'Voting sudah selesai' : 'Voting belum dibuka'];
        }

        if ($pending === 0) {
            return $card + ['value' => 'Selesai', 'hint' => 'Semua pemilih sudah memilih.'];
        }

        if ($recent === 0) {
            return $card + ['value' => 'Belum bisa', 'hint' => 'Belum ada suara dalam '.self::SPEED_WINDOW_MINUTES.' menit terakhir.'];
        }

        // Whole-number arithmetic, so the minutes are not thrown off by floating point rounding.
        $minutes = (int) ceil($pending * self::SPEED_WINDOW_MINUTES / $recent);
        $finishAt = now()->addMinutes($minutes);
        $hint = 'Kecepatan '.$rateText.', '.$pending.' pemilih lagi.';

        if ($election->closes_at !== null && $finishAt->gt($election->closes_at)) {
            $hint = 'Kecepatan saat ini tidak cukup sebelum penutupan. '.$pending.' pemilih lagi.';
        }

        return $card + ['value' => $this->durationLabel($minutes), 'hint' => $hint];
    }

    private function recentBallots(): int
    {
        return Ballot::query()->where('created_at', '>=', now()->subMinutes(self::SPEED_WINDOW_MINUTES))->count();
    }

    private function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return 'sekitar '.$minutes.' menit';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return 'sekitar '.$hours.' jam'.($rest > 0 ? ' '.$rest.' menit' : '');
    }

    /**
     * @return array{text: string, tone: string}
     */
    private function turnoutChange(int $turnout, int $previous): array
    {
        $difference = $turnout - $previous;

        if ($difference === 0) {
            return ['text' => 'Sama dengan pemilihan sebelumnya', 'tone' => ''];
        }

        return [
            'text' => ($difference > 0 ? '▲ ' : '▼ ').abs($difference).' poin dari pemilihan sebelumnya',
            'tone' => $difference > 0 ? 'is-up' : 'is-down',
        ];
    }

    private function scheduleDescription(?Election $election): string
    {
        if ($election === null) {
            return 'Buat pemilihan di menu Pemilihan';
        }

        if ($election->hasClosed()) {
            return 'Ditutup '.$election->closes_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i');
        }

        if ($election->isOpen() && $election->closes_at !== null) {
            return 'Ditutup '.$election->closes_at->locale('id')->diffForHumans();
        }

        if ($election->opens_at !== null) {
            return 'Dibuka '.$election->opens_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i');
        }

        return 'Tidak ada batas waktu';
    }
}
