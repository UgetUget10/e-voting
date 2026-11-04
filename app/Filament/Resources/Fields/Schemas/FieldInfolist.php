<?php

namespace App\Filament\Resources\Fields\Schemas;

use App\Models\Field;
use App\Services\FieldStandings;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FieldInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->schema([
                        Section::make('Ringkasan')
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                TextEntry::make('standing')
                                    ->label('Hasil')
                                    ->badge()
                                    ->state(fn (Field $record): string => FieldStandings::label(app(FieldStandings::class)->for($record)['status']))
                                    ->color(fn (Field $record): string => match (app(FieldStandings::class)->for($record)['status']) {
                                        FieldStandings::TIED => 'warning',
                                        FieldStandings::LEADING => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('leader')
                                    ->label('Pemimpin sementara')
                                    ->state(fn (Field $record): ?string => app(FieldStandings::class)->for($record)['leader']?->name)
                                    ->placeholder('Belum ada suara'),
                                TextEntry::make('total')
                                    ->label('Total suara')
                                    ->weight('bold')
                                    ->state(fn (Field $record): int => app(FieldStandings::class)->for($record)['total']),
                                TextEntry::make('position')
                                    ->label('Langkah pemilih')
                                    ->state(fn (Field $record): string => 'Langkah '.($record->position ?? '—').' dari '.Field::count()),
                            ]),
                        Section::make('Perolehan suara')
                            ->description('Kandidat yang menerima suara di bidang ini, dari yang terbanyak. Kandidat yang lolos ke putaran berikutnya ditandai.')
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                ViewEntry::make('standings')
                                    ->hiddenLabel()
                                    ->view('filament.fields.standings')
                                    ->viewData(fn (Field $record): array => ['standing' => app(FieldStandings::class)->for($record)]),
                            ]),
                    ]),
            ]);
    }
}
