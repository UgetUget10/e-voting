<?php

namespace App\Filament\Resources\AdminActivities\Pages;

use App\Filament\Resources\AdminActivities\AdminActivityResource;
use App\Filament\Widgets\AdminActivitySummary;
use App\Models\AdminActivity;
use App\Services\AdminActivityCsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAdminActivities extends ListRecords
{
    protected static string $resource = AdminActivityResource::class;

    /**
     * The newest entry the page has shown. Anything after it is new and waits behind the "Muat yang baru" button.
     */
    public string $seenThrough = '';

    /**
     * The entries that arrived with the last refresh, so their rows can flash once when they appear.
     *
     * @var list<string>
     */
    public array $freshIds = [];

    public function mount(): void
    {
        parent::mount();

        $this->seenThrough = (string) AdminActivity::query()->max('id');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AdminActivitySummary::class,
        ];
    }

    /**
     * Gives the summary widget the newest id it has already shown, so it can count what is still waiting.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            ...parent::getWidgetData(),
            'seenThrough' => $this->seenThrough,
        ];
    }

    #[On('activity-refresh')]
    public function refreshFeed(): void
    {
        $this->freshIds = AdminActivity::query()
            ->where('id', '>', $this->seenThrough)
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $this->seenThrough = (string) AdminActivity::query()->max('id');

        $this->dispatch('activity-seen', seenThrough: $this->seenThrough);

        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(fn (): string => 'Ekspor CSV ('.$this->getFilteredTableQuery()->count().')')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (AdminActivityCsvExport $exporter): StreamedResponse => $exporter->stream($this->getFilteredTableQuery())),
        ];
    }
}
