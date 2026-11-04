<?php

namespace App\Filament\Resources\Elections\Schemas;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ElectionForm
{
    private const SCHEDULE_TIMEZONE = 'Asia/Jakarta';

    private const PRESET_DURATION_HOURS = 8;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail pemilihan')
                    ->description('Pemilihan terbaru otomatis menjadi pemilihan aktif yang dipakai pemilih. Pemilihan tidak bisa dihapus, dan pemilihan baru tidak bisa dibuat, setelah ada suara masuk. Reset suara dulu untuk memulai pemilihan baru.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama pemilihan')
                            ->placeholder('Contoh: Pemilihan Ketua OSIS 2026')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('show_results_early')
                            ->label('Tampilkan hasil sebelum voting ditutup')
                            ->helperText('Jika aktif, pemilih bisa melihat hasil sementara.'),
                    ]),
                Section::make('Jadwal voting')
                    ->description('Kosongkan jika tidak ada batas waktu. Semua jadwal memakai waktu WIB.')
                    ->columns(2)
                    ->schema([
                        Actions::make([
                            Action::make('presetNow')
                                ->label('Mulai sekarang, '.self::PRESET_DURATION_HOURS.' jam')
                                ->icon(Heroicon::OutlinedBolt)
                                ->color('gray')
                                ->action(fn (Set $set) => self::fillSchedule(
                                    $set,
                                    Carbon::now(self::SCHEDULE_TIMEZONE)->startOfMinute(),
                                )),
                            Action::make('presetTomorrow')
                                ->label('Besok pukul 08.00, '.self::PRESET_DURATION_HOURS.' jam')
                                ->icon(Heroicon::OutlinedSun)
                                ->color('gray')
                                ->action(fn (Set $set) => self::fillSchedule(
                                    $set,
                                    Carbon::tomorrow(self::SCHEDULE_TIMEZONE)->setTime(8, 0),
                                )),
                        ])->key('schedulePresets')->columnSpanFull(),
                        ...self::scheduleFields(),
                        Placeholder::make('duration')
                            ->label('Lama voting')
                            ->content(fn (Get $get): string => self::durationLabel($get('opens_at'), $get('closes_at')))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * The opening and closing fields, shared by the create and edit form and the quick schedule action.
     *
     * @return array<int, DateTimePicker>
     */
    public static function scheduleFields(): array
    {
        return [
            DateTimePicker::make('opens_at')
                ->label('Dibuka pada')
                ->seconds(false)
                ->timezone(self::SCHEDULE_TIMEZONE)
                ->live(),
            DateTimePicker::make('closes_at')
                ->label('Ditutup pada')
                ->seconds(false)
                ->timezone(self::SCHEDULE_TIMEZONE)
                ->after('opens_at')
                ->live(),
        ];
    }

    /**
     * Human-readable length of the voting window, or a hint when one end of it is still missing.
     */
    public static function durationLabel(mixed $opensAt, mixed $closesAt): string
    {
        if (blank($opensAt) || blank($closesAt)) {
            return 'Isi kedua jadwal untuk melihat lama voting.';
        }

        $start = self::parseSchedule($opensAt);
        $end = self::parseSchedule($closesAt);

        if ($end->lte($start)) {
            return 'Jadwal tutup harus setelah jadwal buka.';
        }

        $minutes = (int) $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        $parts = array_filter([
            $hours > 0 ? $hours.' jam' : null,
            $remainder > 0 ? $remainder.' menit' : null,
        ]);

        return 'Berlangsung '.implode(' ', $parts).'.';
    }

    private static function fillSchedule(Set $set, CarbonInterface $opensAt): void
    {
        $set('opens_at', $opensAt->copy()->utc()->format('Y-m-d H:i:s'));
        $set('closes_at', $opensAt->copy()->addHours(self::PRESET_DURATION_HOURS)->utc()->format('Y-m-d H:i:s'));
    }

    private static function parseSchedule(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value, 'UTC');
    }
}
