<?php

namespace App\Filament\Resources\Fields\Schemas;

use App\Models\Election;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (): bool => Election::isCurrentLocked())
            ->components([
                Section::make('Detail bidang')
                    ->description('Urutan bidang mengikuti urutan langkah pemilih dan tabel hasil. Ubah urutan dengan menggeser baris di daftar bidang.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Bidang')
                            ->placeholder('Contoh: Waka Bidang Akademik')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),
            ]);
    }
}
