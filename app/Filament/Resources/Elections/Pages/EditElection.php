<?php

namespace App\Filament\Resources\Elections\Pages;

use App\Filament\Resources\Elections\ElectionResource;
use App\Models\Ballot;
use App\Models\Election;
use App\Services\ElectionScheduleWarning;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditElection extends EditRecord
{
    protected static string $resource = ElectionResource::class;

    public function getSubheading(): ?string
    {
        return $this->getRecord()->isNot(Election::current())
            ? 'Pemilihan ini bukan yang aktif. Perubahan di sini tidak memengaruhi pemilih, karena pemilih memakai pemilihan terbaru.'
            : null;
    }

    protected function afterSave(): void
    {
        app(ElectionScheduleWarning::class)->notifyIfOverlapping($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! Ballot::query()->exists()),
        ];
    }
}
