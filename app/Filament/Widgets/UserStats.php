<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class UserStats extends Widget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.user-stats';

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $voterCount = User::query()->where('is_admin', false)->count();
        $votedCount = Ballot::count();
        $adminCount = User::query()->where('is_admin', true)->count();
        $suspendedCount = User::query()->whereNotNull('suspended_at')->count();

        return [
            'cards' => [
                [
                    'label' => 'Pemilih terdaftar',
                    'value' => $voterCount,
                    'hint' => $adminCount.' akun admin panel',
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedUsers,
                    'progress' => null,
                ],
                [
                    'label' => 'Sudah memilih',
                    'value' => $votedCount,
                    'hint' => $voterCount > 0 ? round($votedCount / $voterCount * 100).'% dari pemilih' : 'Belum ada pemilih',
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedCheckBadge,
                    'progress' => $voterCount > 0 ? min(100, (int) round($votedCount / $voterCount * 100)) : 0,
                ],
                [
                    'label' => 'Belum memilih',
                    'value' => User::query()->where('is_admin', false)->whereDoesntHave('ballot')->count(),
                    'hint' => 'Pemilih yang belum mengirim suara',
                    'tone' => null,
                    'icon' => Heroicon::OutlinedHandRaised,
                    'progress' => null,
                ],
                [
                    'label' => 'Dinonaktifkan',
                    'value' => $suspendedCount,
                    'hint' => 'Tidak bisa masuk ke panel mana pun',
                    'tone' => $suspendedCount > 0 ? 'warning' : null,
                    'icon' => Heroicon::OutlinedNoSymbol,
                    'progress' => null,
                ],
            ],
        ];
    }
}
