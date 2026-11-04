<?php

namespace App\Filament\Resources\Elections\Schemas;

use App\Models\Election;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ElectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nama pemilihan')
                            ->weight('semibold')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->state(fn (Election $record): string => $record->statusLabel()),
                        TextEntry::make('voting_window')
                            ->label('Jendela voting')
                            ->state(fn (Election $record): string => $record->windowProgress() === null
                                ? 'Tidak dibatasi'
                                : $record->windowProgress().'% sudah berlalu'),
                        TextEntry::make('opens_at')
                            ->label('Dibuka pada')
                            ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatSchedule($state))
                            ->placeholder('Tidak diatur'),
                        TextEntry::make('closes_at')
                            ->label('Ditutup pada')
                            ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatSchedule($state))
                            ->placeholder('Tidak diatur'),
                    ]),
                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('locked_at')
                            ->label('Data terkunci')
                            ->state(fn (Election $record): string => $record->isLocked()
                                ? 'Ya, sejak '.self::formatSchedule($record->locked_at)
                                : 'Tidak'),
                        TextEntry::make('show_results_early')
                            ->label('Hasil dibuka dini')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
                        TextEntry::make('archived_at')
                            ->label('Diarsipkan pada')
                            ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatSchedule($state))
                            ->placeholder('Belum diarsipkan'),
                    ]),
                Section::make('Hasil akhir')
                    ->description('Tersimpan saat pemilihan diarsipkan.')
                    ->columns(3)
                    ->visible(fn (Election $record): bool => $record->results_snapshot !== null)
                    ->schema([
                        TextEntry::make('results_snapshot.voters')
                            ->label('Pemilih terdaftar')
                            ->placeholder('0'),
                        TextEntry::make('results_snapshot.ballots')
                            ->label('Suara masuk')
                            ->placeholder('0'),
                        TextEntry::make('results_snapshot.turnout')
                            ->label('Partisipasi')
                            ->suffix('%')
                            ->placeholder('0'),
                    ]),
            ]);
    }

    private static function formatSchedule(?CarbonInterface $schedule): ?string
    {
        return $schedule?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i');
    }
}
