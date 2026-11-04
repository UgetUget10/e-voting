<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Schemas\CandidateForm;
use App\Filament\Resources\Candidates\Schemas\VoteCorrectionForm;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Vote;
use App\Services\AdminActivityLog;
use App\Services\BallotService;
use App\Services\CandidateNameSimilarity;
use App\Services\VoteCorrectionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidatesTable
{
    /**
     * The table of candidates. In card mode the same records render as a grid of cards instead, see the content below.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->ordered()
                ->with('fields')
                ->withVoteTotals()
                ->addSelect([
                    'fields_count' => Vote::query()
                        ->selectRaw('count(distinct field_id)')
                        ->whereColumn('candidate_id', 'candidates.id'),
                ]))
            ->reorderable('position', fn ($livewire): bool => ! Election::isCurrentLocked() && $livewire->displayMode !== 'grid')
            ->content(fn ($livewire): ?View => $livewire->displayMode === 'grid'
                ? view('filament.candidates.card-grid')
                : null)
            ->recordClasses(fn (Candidate $record): string => collect([
                'admin-candidate-row',
                $record->updated_at->gt(now()->subSeconds(45)) ? 'admin-row-enter' : null,
            ])->filter()->implode(' '))
            ->recordUrl(fn (Candidate $record): string => CandidateResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('row_index')
                    ->label('Urutan')
                    ->rowIndex()
                    ->badge()
                    ->color('gray'),
                ViewColumn::make('photo_path')
                    ->label('Foto')
                    ->view('filament.candidates.photo-cell'),
                TextColumn::make('name')
                    ->label('Nama kandidat')
                    ->weight('semibold')
                    ->description(fn (Candidate $record): ?string => self::nameNote($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%"))),
                SelectColumn::make('golongan')
                    ->label('Golongan')
                    ->options(array_combine(Candidate::GOLONGAN, Candidate::GOLONGAN))
                    ->placeholder('Belum diisi')
                    ->searchable()
                    ->disabled(fn (): bool => Election::isCurrentLocked()),
                TextColumn::make('profile_status')
                    ->label('Kelengkapan')
                    ->badge()
                    ->getStateUsing(fn (Candidate $record): string => $record->profileStatusLabel())
                    ->color(fn (string $state): string => $state === 'Profil lengkap' ? 'success' : 'warning')
                    ->tooltip(fn (string $state): ?string => $state === 'Profil lengkap' ? null : 'Klik untuk melengkapi profil')
                    ->url(fn (Candidate $record, string $state): ?string => $state === 'Profil lengkap' ? null : CandidateResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('picked_fields')
                    ->label('Bidang terpilih')
                    ->state(fn (Candidate $record): ?array => $record->fields->pluck('name')->all() ?: null)
                    ->badge()
                    ->color('success')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->description(fn (Candidate $record): string => $record->fields->count().' / '.BallotService::MAX_FIELDS_PER_CANDIDATE.' bidang')
                    ->placeholder('Belum dipilih'),
                TextColumn::make('vote_total')
                    ->label('Total suara')
                    ->weight('bold')
                    ->description(fn (Candidate $record): ?string => $record->correction_total
                        ? 'Termasuk koreksi '.self::signed((int) $record->correction_total)
                        : null)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('completeness')
                    ->label('Kelengkapan profil')
                    ->placeholder('Semua kandidat')
                    ->options([
                        'complete' => 'Profil lengkap',
                        'no_photo' => 'Belum ada foto',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'complete' => $query->whereNotNull('photo_path'),
                        'no_photo' => $query->whereNull('photo_path'),
                        default => $query,
                    }),
                SelectFilter::make('field')
                    ->label('Bidang')
                    ->placeholder('Semua bidang')
                    ->options(fn (): array => Field::query()->ordered()->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('votes', fn (Builder $query) => $query->where('field_id', $data['value']))
                        : $query),
                TernaryFilter::make('picked')
                    ->label('Status dipilih')
                    ->placeholder('Semua kandidat')
                    ->trueLabel('Sudah dipilih')
                    ->falseLabel('Belum dipilih')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('votes'),
                        false: fn (Builder $query) => $query->whereDoesntHave('votes'),
                    ),
                TernaryFilter::make('photo')
                    ->label('Foto')
                    ->placeholder('Semua kandidat')
                    ->trueLabel('Sudah ada foto')
                    ->falseLabel('Belum ada foto')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('photo_path'),
                        false: fn (Builder $query) => $query->whereNull('photo_path'),
                    ),
                SelectFilter::make('golongan')
                    ->label('Golongan')
                    ->placeholder('Semua golongan')
                    ->options(array_combine(Candidate::GOLONGAN, Candidate::GOLONGAN))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('golongan', $data['value'])
                        : $query),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Pratinjau')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (Candidate $record): string => 'Pratinjau '.$record->name)
                    ->modalContent(fn (Candidate $record) => view('filament.candidates.preview', ['candidate' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make(),
                ActionGroup::make([
                    self::correctionAction(),
                    self::replacePhotoAction(),
                    self::duplicateAction(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontal)
                    ->color('gray')
                    ->tooltip('Aksi lainnya'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::changeGolonganBulkAction(),
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            if ($records->contains(fn (Candidate $candidate): bool => $candidate->hasRecordedVotes())) {
                                Notification::make()
                                    ->danger()
                                    ->title('Kandidat yang sudah dipilih tidak bisa dihapus.')
                                    ->body('Menghapusnya juga akan menghapus suara pemilih yang sudah dikirim.')
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('Belum ada kandidat')
            ->emptyStateDescription('Kandidat tersedia untuk semua bidang. Pemilih memilih dari daftar ini di setiap bidang.')
            ->emptyStateActions([
                Action::make('createCandidate')
                    ->label('Tambah kandidat')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(CandidateResource::getUrl('create'))
                    ->visible(fn (): bool => CandidateResource::canCreate()),
            ]);
    }

    /**
     * Correct the total of one candidate in one field. Also offered on the candidate's own page.
     */
    public static function correctionAction(): Action
    {
        return Action::make('correct')
            ->label('Koreksi suara')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (): bool => ! Election::isCurrentLocked())
            ->modalHeading(fn (Candidate $record): string => 'Koreksi total suara '.$record->name)
            ->modalDescription('Koreksi disimpan sebagai selisih dari hitungan sekarang. Suara yang masuk setelahnya tetap dihitung, dan surat suara pemilih tidak berubah.')
            ->schema(fn (Candidate $record): array => VoteCorrectionForm::components($record))
            ->action(function (array $data, Candidate $record, VoteCorrectionService $corrections): void {
                $corrections->correct(
                    $record,
                    Field::query()->findOrFail($data['field_id']),
                    (int) $data['total'],
                    trim($data['reason']),
                );

                Notification::make()->title('Total suara '.$record->name.' dikoreksi.')->success()->send();
            });
    }

    private static function replacePhotoAction(): Action
    {
        return Action::make('replacePhoto')
            ->label('Ganti foto')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->visible(fn (): bool => ! Election::isCurrentLocked())
            ->schema([
                CandidateForm::photoUpload()->required(),
            ])
            ->action(function (array $data, Candidate $record): void {
                abort_if(Election::isCurrentLocked(), 403);

                $record->update(['photo_path' => $data['photo_path']]);

                Notification::make()->title('Foto '.$record->name.' diganti.')->success()->send();
            });
    }

    /**
     * Sets the golongan of every selected candidate at once. One summary entry is logged instead of one per candidate.
     */
    private static function changeGolonganBulkAction(): BulkAction
    {
        return BulkAction::make('changeGolongan')
            ->label('Ubah golongan')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (): bool => ! Election::isCurrentLocked())
            ->deselectRecordsAfterCompletion()
            ->schema([
                Select::make('golongan')
                    ->label('Golongan baru')
                    ->options(array_combine(Candidate::GOLONGAN, Candidate::GOLONGAN))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data, AdminActivityLog $activityLog): void {
                abort_if(Election::isCurrentLocked(), 403);

                Candidate::withoutRecordingAdminActivity(function () use ($records, $data): void {
                    $records->each(function (Candidate $candidate) use ($data): void {
                        $candidate->update(['golongan' => $data['golongan']]);
                    });
                });

                $activityLog->record('Mengubah golongan '.$records->count().' kandidat menjadi '.$data['golongan'].'.');

                Notification::make()
                    ->success()
                    ->title($records->count().' kandidat diubah ke golongan '.$data['golongan'].'.')
                    ->send();
            });
    }

    /**
     * A copy with the same golongan and photo, so a candidate who runs again needs only a new name. The NIP is
     * left empty, since each NIP belongs to one candidate.
     */
    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplikat')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => CandidateResource::canCreate())
            ->requiresConfirmation()
            ->modalHeading('Duplikat kandidat ini?')
            ->modalDescription('Kandidat baru dibuat dengan visi dan foto yang sama, dan ditaruh di akhir daftar. Ubah namanya setelah ini.')
            ->action(function (Candidate $record): void {
                abort_unless(CandidateResource::canCreate(), 403);

                $photoPath = null;

                if (filled($record->photo_path) && Storage::disk('public')->exists($record->photo_path)) {
                    $photoPath = 'candidates/'.Str::uuid().'.'.pathinfo($record->photo_path, PATHINFO_EXTENSION);
                    Storage::disk('public')->copy($record->photo_path, $photoPath);
                }

                $copy = Candidate::create([
                    'name' => self::uniqueCopyName($record->name),
                    'golongan' => $record->golongan,
                    'photo_path' => $photoPath,
                    'position' => (int) Candidate::query()->max('position') + 1,
                ]);

                Notification::make()->title('Kandidat diduplikat: '.$copy->name)->success()->send();
            });
    }

    /**
     * The NIP, and any other candidate whose name reads almost the same, shown under the name.
     */
    private static function nameNote(Candidate $record): ?string
    {
        $notes = collect([filled($record->nip) ? 'NIP '.$record->nip : null]);
        $similar = app(CandidateNameSimilarity::class)->similarTo($record->name, $record->getKey());

        if ($similar->isNotEmpty()) {
            $notes->push('Mirip dengan '.$similar->pluck('name')->implode(', '));
        }

        return $notes->filter()->implode(' · ') ?: null;
    }

    private static function signed(int $amount): string
    {
        return ($amount > 0 ? '+' : '').$amount;
    }

    private static function uniqueCopyName(string $name): string
    {
        $candidate = $name.' (salinan)';
        $suffix = 2;

        while (Candidate::query()->where('name', $candidate)->exists()) {
            $candidate = $name.' (salinan '.$suffix++.')';
        }

        return $candidate;
    }
}
