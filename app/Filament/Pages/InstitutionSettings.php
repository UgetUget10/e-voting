<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminActivityLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class InstitutionSettings extends Page
{
    protected static ?string $title = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.institution-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::valuesFor([
            Setting::INSTITUTION_NAME,
            Setting::SIGNER_NAME,
            Setting::SIGNER_TITLE,
            Setting::VOTER_ANNOUNCEMENT,
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Instansi')
                        ->description('Nama ini muncul di judul berita acara dan di bagian atas halaman login pemilih.')
                        ->schema([
                            TextInput::make(Setting::INSTITUTION_NAME)
                                ->label('Nama instansi')
                                ->placeholder('Contoh: MTsN 1 Kota Malang')
                                ->maxLength(255),
                        ]),
                    Section::make('Penandatangan berita acara')
                        ->description('Dicetak di bagian tanda tangan berita acara. Kosongkan jika belum ada.')
                        ->schema([
                            TextInput::make(Setting::SIGNER_NAME)
                                ->label('Nama penandatangan')
                                ->placeholder('Contoh: Dr. Siti Rahayu, M.Pd.')
                                ->maxLength(255),
                            TextInput::make(Setting::SIGNER_TITLE)
                                ->label('Jabatan')
                                ->placeholder('Contoh: Ketua panitia')
                                ->maxLength(255),
                        ])
                        ->columns(2),
                    Section::make('Pengumuman untuk pemilih')
                        ->description('Ditampilkan di halaman login pemilih. Kosongkan untuk menyembunyikannya.')
                        ->schema([
                            Textarea::make(Setting::VOTER_ANNOUNCEMENT)
                                ->label('Isi pengumuman')
                                ->placeholder('Contoh: Voting ditutup pukul 14.00 WIB. Pastikan Anda sudah memilih sebelum waktu tersebut.')
                                ->rows(3)
                                ->maxLength(1000),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan pengaturan')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::store($this->form->getState());

        app(AdminActivityLog::class)->record('Memperbarui pengaturan instansi.');

        Notification::make()
            ->success()
            ->title('Pengaturan disimpan.')
            ->send();
    }
}
