<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BallotStats extends StatsOverviewWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $ballotCount = Ballot::count();
        $voterCount = User::where('is_admin', false)->count();
        $latestBallotAt = Ballot::query()->latest('created_at')->value('created_at');
        $todayStart = now('Asia/Jakarta')->startOfDay()->utc();
        $todayEnd = now('Asia/Jakarta')->endOfDay()->utc();
        $todayCount = Ballot::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count();

        return [
            Stat::make('Suara masuk', $ballotCount)
                ->description($voterCount > 0
                    ? round($ballotCount / $voterCount * 100).'% dari pemilih terdaftar'
                    : 'Belum ada pemilih terdaftar')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary'),
            Stat::make('Suara hari ini', $todayCount)
                ->description('Sejak pukul 00:00 WIB')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->icon(Heroicon::OutlinedSun)
                ->color('success'),
            Stat::make('Suara terakhir', $latestBallotAt
                ? $latestBallotAt->timezone('Asia/Jakarta')->format('H:i:s')
                : '—')
                ->description($latestBallotAt ? $latestBallotAt->diffForHumans() : 'Belum ada suara masuk')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray'),
        ];
    }
}
