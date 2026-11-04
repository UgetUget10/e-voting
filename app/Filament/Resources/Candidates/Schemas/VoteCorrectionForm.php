<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Models\Candidate;
use App\Models\Field;
use App\Services\VoteCorrectionService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class VoteCorrectionForm
{
    /**
     * The fields of a correction: the field, the total the count should show, and why it changed.
     *
     * @return array<int, Select|TextInput|Textarea>
     */
    public static function components(Candidate $candidate): array
    {
        return [
            Select::make('field_id')
                ->label('Bidang')
                ->options(fn (): array => Field::query()->ordered()->pluck('name', 'id')->all())
                ->required()
                ->live(),
            TextInput::make('total')
                ->label('Total suara yang benar')
                ->integer()
                ->minValue(0)
                ->required()
                ->helperText(fn (Get $get): string => self::currentTotalHint($candidate, $get('field_id'))),
            Textarea::make('reason')
                ->label('Alasan koreksi')
                ->required()
                ->maxLength(500)
                ->rows(3)
                ->helperText('Dicatat di riwayat aktivitas dan dikirim ke admin lain.'),
        ];
    }

    private static function currentTotalHint(Candidate $candidate, mixed $fieldId): string
    {
        $field = Field::query()->find($fieldId);

        if (! $field) {
            return 'Pilih bidang untuk melihat total saat ini.';
        }

        return 'Total saat ini: '.app(VoteCorrectionService::class)->currentTotal($candidate, $field).' suara.';
    }
}
