<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Models\Election;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewField extends ViewRecord
{
    protected static string $resource = FieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! Election::isCurrentLocked()),
        ];
    }
}
