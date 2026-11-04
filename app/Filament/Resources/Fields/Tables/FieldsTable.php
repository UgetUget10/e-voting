<?php

namespace App\Filament\Resources\Fields\Tables;

use App\Filament\Resources\Fields\FieldResource;
use App\Models\Election;
use App\Models\Field;
use App\Services\FieldStandings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->ordered()
                ->select('fields.*')
                ->addSelect([
                    DB::raw('(select count(*) from votes where votes.field_id = fields.id) + (select coalesce(sum(amount), 0) from vote_corrections where vote_corrections.field_id = fields.id) as vote_total'),
                ]))
            ->reorderable('position', fn (): bool => ! Election::isCurrentLocked())
            ->recordUrl(fn (Field $record): string => FieldResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (Field $record): string => collect([
                'admin-field-row',
                $record->updated_at->gt(now()->subSeconds(45)) ? 'admin-row-enter' : null,
            ])->filter()->implode(' '))
            ->filters([
                TernaryFilter::make('has_votes')
                    ->label('Status suara')
                    ->placeholder('Semua bidang')
                    ->trueLabel('Sudah ada suara')
                    ->falseLabel('Belum ada suara')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->whereHas('votes')
                            ->orWhereHas('corrections')),
                        false: fn (Builder $query) => $query
                            ->whereDoesntHave('votes')
                            ->whereDoesntHave('corrections'),
                    ),
                SelectFilter::make('standing')
                    ->label('Hasil')
                    ->placeholder('Semua hasil')
                    ->options([
                        FieldStandings::LEADING => FieldStandings::label(FieldStandings::LEADING),
                        FieldStandings::TIED => FieldStandings::label(FieldStandings::TIED),
                        FieldStandings::EMPTY => FieldStandings::label(FieldStandings::EMPTY),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('fields.id', app(FieldStandings::class)->all()
                            ->where('status', $data['value'])
                            ->map(fn (array $standing): string => $standing['field']->getKey())
                            ->values()
                            ->all())
                        : $query),
            ])
            ->columns([
                TextColumn::make('row_index')
                    ->label('Urutan')
                    ->rowIndex()
                    ->badge()
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('name')
                    ->label('Bidang')
                    ->weight('semibold')
                    ->searchable()
                    ->description(fn (Field $record): string => self::candidateNote($record))
                    ->extraAttributes(['class' => 'admin-field-name']),
                TextColumn::make('standing')
                    ->label('Hasil')
                    ->badge()
                    ->getStateUsing(fn (Field $record): string => app(FieldStandings::class)->for($record)['status'])
                    ->formatStateUsing(fn (string $state): string => FieldStandings::label($state))
                    ->color(fn (string $state): string => match ($state) {
                        FieldStandings::TIED => 'warning',
                        FieldStandings::LEADING => 'success',
                        default => 'gray',
                    })
                    ->grow(false),
                ViewColumn::make('leader')
                    ->label('Pemimpin sementara')
                    ->view('filament.fields.standing')
                    ->viewData(fn (Field $record): array => ['standing' => app(FieldStandings::class)->for($record)]),
                TextColumn::make('vote_total')
                    ->label('Total suara')
                    ->weight('bold')
                    ->sortable()
                    ->alignEnd()
                    ->grow(false),
            ])
            ->recordActions([
                EditAction::make(),
                self::duplicateAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            $blocked = $records->first(fn (Field $field): bool => $field->hasVotes());

                            if ($blocked) {
                                Notification::make()
                                    ->danger()
                                    ->title('Bidang yang sudah ada suara tidak bisa dihapus.')
                                    ->body('Menghapusnya juga akan menghapus pilihan pemilih di bidang tersebut. Buka detailnya untuk melihat suara yang sudah masuk.')
                                    ->actions([
                                        Action::make('open')
                                            ->label('Buka '.$blocked->name)
                                            ->url(FieldResource::getUrl('view', ['record' => $blocked])),
                                    ])
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->emptyStateHeading('Belum ada bidang')
            ->emptyStateDescription('Tambahkan bidang seperti Presiden atau Bendahara. Pemilih akan memilih kandidat untuk setiap bidang sesuai urutan ini.')
            ->emptyStateActions([
                Action::make('createField')
                    ->label('Tambah bidang')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(FieldResource::getUrl('create'))
                    ->visible(fn (): bool => FieldResource::canCreate()),
            ]);
    }

    /**
     * Copies the name only, at the end of the voting order. Votes are never copied.
     */
    private static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplikat')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => FieldResource::canCreate())
            ->requiresConfirmation()
            ->modalHeading('Duplikat bidang ini?')
            ->modalDescription('Bidang baru dibuat dengan nama yang sama dan ditaruh di akhir urutan pemilih. Suara tidak ikut disalin.')
            ->action(function (Field $record): void {
                abort_unless(FieldResource::canCreate(), 403);

                $copy = Field::create([
                    'name' => self::uniqueCopyName($record->name),
                    'position' => (int) Field::query()->max('position') + 1,
                ]);

                Notification::make()->title('Bidang diduplikat: '.$copy->name)->success()->send();
            });
    }

    /**
     * How many candidates have votes in this field, shown under its name.
     */
    private static function candidateNote(Field $record): string
    {
        $count = app(FieldStandings::class)->for($record)['ranked']->count();

        return $count > 0
            ? $count.' kandidat menerima suara'
            : 'Belum ada kandidat yang dipilih';
    }

    private static function uniqueCopyName(string $name): string
    {
        $candidate = $name.' (salinan)';
        $suffix = 2;

        while (Field::query()->where('name', $candidate)->exists()) {
            $candidate = $name.' (salinan '.$suffix++.')';
        }

        return $candidate;
    }
}
