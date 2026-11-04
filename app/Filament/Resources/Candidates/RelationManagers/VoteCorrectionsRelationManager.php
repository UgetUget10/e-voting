<?php

namespace App\Filament\Resources\Candidates\RelationManagers;

use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Models\Election;
use App\Models\VoteCorrection;
use App\Services\VoteCorrectionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoteCorrectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'corrections';

    protected static ?string $title = 'Koreksi total suara';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['field', 'user'])->latest())
            ->columns([
                TextColumn::make('field.name')
                    ->label('Bidang')
                    ->weight('semibold'),
                TextColumn::make('amount')
                    ->label('Perubahan')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('Oleh')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('j M Y, H:i', 'Asia/Jakarta'),
            ])
            ->headerActions([
                CandidatesTable::correctionAction()->record($this->getOwnerRecord()),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (): bool => ! Election::isCurrentLocked())
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan koreksi ini?')
                    ->modalDescription('Total suara kembali seperti sebelum koreksi ini dibuat.')
                    ->action(function (VoteCorrection $record, VoteCorrectionService $corrections): void {
                        $corrections->remove($record);

                        Notification::make()->title('Koreksi dibatalkan.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada koreksi')
            ->emptyStateDescription('Total suara kandidat ini masih sama dengan hitungan surat suara.');
    }
}
