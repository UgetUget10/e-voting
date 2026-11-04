<?php

namespace App\Filament\Resources\Elections\Pages;

use App\Filament\Resources\Elections\ElectionResource;
use App\Services\ElectionScheduleWarning;
use Filament\Resources\Pages\CreateRecord;

class CreateElection extends CreateRecord
{
    protected static string $resource = ElectionResource::class;

    protected function afterCreate(): void
    {
        app(ElectionScheduleWarning::class)->notifyIfOverlapping($this->getRecord());
    }
}
