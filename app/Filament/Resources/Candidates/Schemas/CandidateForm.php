<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Models\Candidate;
use App\Models\Election;
use App\Services\CandidateNameSimilarity;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (): bool => Election::isCurrentLocked())
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->schema([
                        Section::make('Profil kandidat')
                            ->description('Nama, NIP, dan golongan kandidat. Satu kandidat bisa dipilih di beberapa bidang.')
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama kandidat')
                                    ->placeholder('Contoh: Andi Pratama')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->live(debounce: 400)
                                    ->helperText(fn (Get $get, ?Candidate $record): ?string => self::similarNameHint($get('name'), $record)),
                                TextInput::make('nip')
                                    ->label('NIP')
                                    ->placeholder('Contoh: 198501012010011001')
                                    ->regex('/^\d+$/')
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true)
                                    ->nullable()
                                    ->helperText('Angka saja, tanpa spasi. Setiap NIP hanya boleh dipakai satu kandidat.'),
                                Select::make('golongan')
                                    ->label('Golongan')
                                    ->options(array_combine(Candidate::GOLONGAN, Candidate::GOLONGAN))
                                    ->placeholder('Belum diisi')
                                    ->searchable()
                                    ->nullable(),
                                self::photoUpload()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Pratinjau')
                            ->description('Kartu ini berubah mengikuti isian di samping.')
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Placeholder::make('preview')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get, ?Candidate $record) => view('filament.candidates.preview', [
                                        'candidate' => new Candidate([
                                            'name' => $get('name'),
                                        ]),
                                        'photoUrl' => self::previewPhotoUrl($get('photo_path')) ?? $record?->photoUrl(),
                                    ])),
                            ]),
                    ]),
            ]);
    }

    /**
     * The candidate photo, cropped to the 4:5 frame voters see. Shared by the form and the photo row action.
     */
    public static function photoUpload(): FileUpload
    {
        return FileUpload::make('photo_path')
            ->label('Foto')
            ->image()
            ->disk('public')
            ->directory('candidates')
            ->imageEditor()
            ->imageCropAspectRatio('4:5')
            ->imageResizeTargetWidth('800')
            ->imageResizeTargetHeight('1000')
            ->imageResizeMode('cover')
            ->imageEditorAspectRatios(['4:5'])
            ->maxSize(4096)
            ->live()
            ->helperText('Foto dipotong 4:5 seperti kartu yang dilihat pemilih, maksimal 4 MB. Foto lama dihapus otomatis saat diganti.');
    }

    /**
     * The other candidates whose name is the same or nearly the same, shown under the name field before saving.
     */
    private static function similarNameHint(?string $name, ?Candidate $record): ?string
    {
        if (blank($name)) {
            return null;
        }

        $similar = app(CandidateNameSimilarity::class)->similarTo($name, $record?->getKey());

        if ($similar->isEmpty()) {
            return null;
        }

        return 'Mirip dengan '.$similar->pluck('name')->implode(', ').'. Pastikan ini orang yang berbeda.';
    }

    /**
     * The photo to show in the live preview: the file just picked, or the one already stored.
     */
    private static function previewPhotoUrl(mixed $state): ?string
    {
        $file = is_array($state) ? collect($state)->first() : $state;

        if ($file instanceof TemporaryUploadedFile) {
            return $file->isPreviewable() ? $file->temporaryUrl() : null;
        }

        return is_string($file) && filled($file) ? Storage::disk('public')->url($file) : null;
    }
}
