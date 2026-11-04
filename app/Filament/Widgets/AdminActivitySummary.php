<?php

namespace App\Filament\Widgets;

use App\Models\AdminActivity;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class AdminActivitySummary extends Widget
{
    /**
     * Load these with the page instead of after it, so the page does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-activity-summary';

    /**
     * The newest entry the page has shown, passed from the page so new entries can be counted.
     */
    public string $seenThrough = '';

    /**
     * The page tells the widget what it has shown, so the banner clears after "Muat yang baru". The widget is a
     * separate component, so it does not always re-render with the page.
     */
    #[On('activity-seen')]
    public function markSeen(string $seenThrough): void
    {
        $this->seenThrough = $seenThrough;
    }

    /**
     * @return array{cards: list<array<string, mixed>>, waiting: int}
     */
    protected function getViewData(): array
    {
        $startOfToday = now('Asia/Jakarta')->startOfDay()->utc();
        $lastWeek = now()->subDays(7);

        $todayCount = AdminActivity::query()->where('created_at', '>=', $startOfToday)->count();
        $sensitiveCount = AdminActivity::query()->where('sensitive', true)->count();
        $activeAdmins = AdminActivity::query()
            ->where('created_at', '>=', $lastWeek)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
        $latest = AdminActivity::query()->latest('id')->first();

        return [
            'cards' => [
                [
                    'label' => 'Hari ini',
                    'value' => $todayCount,
                    'hint' => 'Tindakan sejak pukul 00.00 WIB',
                    'tone' => 'primary',
                    'icon' => Heroicon::OutlinedCalendarDays,
                    'progress' => null,
                ],
                [
                    'label' => 'Tindakan penting',
                    'value' => $sensitiveCount,
                    'hint' => $sensitiveCount > 0 ? 'Identitas, kunci, akun, jadwal, reset, dan penghapusan' : 'Belum ada tindakan penting',
                    'tone' => $sensitiveCount > 0 ? 'warning' : null,
                    'icon' => Heroicon::OutlinedExclamationTriangle,
                    'progress' => null,
                ],
                [
                    'label' => 'Admin aktif',
                    'value' => $activeAdmins,
                    'hint' => '7 hari terakhir',
                    'tone' => 'success',
                    'icon' => Heroicon::OutlinedUserGroup,
                    'progress' => null,
                ],
                [
                    'label' => 'Terakhir dicatat',
                    'value' => $latest?->created_at?->locale('id')->diffForHumans() ?? 'Belum ada',
                    'is_text' => true,
                    'hint' => $latest ? $latest->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i').' WIB' : 'Tindakan admin akan tercatat di sini',
                    'tone' => null,
                    'icon' => Heroicon::OutlinedClock,
                    'progress' => null,
                ],
            ],
            'waiting' => AdminActivity::query()->where('id', '>', $this->seenThrough)->count(),
        ];
    }
}
