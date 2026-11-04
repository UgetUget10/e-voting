<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Models\Field;
use Filament\Resources\Pages\CreateRecord;

class CreateField extends CreateRecord
{
    protected static string $resource = FieldResource::class;

    /**
     * A new field goes to the end of the voting order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = (int) Field::query()->max('position') + 1;

        return $data;
    }
}
