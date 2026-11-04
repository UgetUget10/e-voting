<?php

namespace App\Filament\Widgets;

use App\Models\Field;
use App\Models\User;
use App\Models\Vote;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BallotParticipation extends StatsOverviewWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    /**
     * Refreshes with the list, so the numbers in the panel keep up while votes are coming in.
     */
    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Partisipasi per bidang';

    protected ?string $description = 'Berapa pemilih terdaftar sudah memilih di setiap bidang. Tidak terpengaruh filter tabel.';

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $voterCount = User::query()->where('is_admin', false)->count();
        $picksByField = Vote::query()
            ->selectRaw('field_id, count(*) as total')
            ->groupBy('field_id')
            ->pluck('total', 'field_id');

        return Field::query()
            ->ordered()
            ->get()
            ->map(function (Field $field) use ($voterCount, $picksByField): Stat {
                $picks = (int) $picksByField->get($field->id, 0);
                $percent = $voterCount > 0 ? round($picks / $voterCount * 100) : 0;

                return Stat::make($field->name, $picks.' dari '.$voterCount)
                    ->description($percent.'% pemilih terdaftar')
                    ->descriptionIcon(Heroicon::OutlinedUsers)
                    ->color($percent >= 100 ? 'success' : 'primary');
            })
            ->values()
            ->all();
    }
}
