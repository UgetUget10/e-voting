<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Models\Candidate;
use App\Models\Field;
use App\Services\BallotService;
use App\Services\VoteCorrectionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CandidateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->schema([
                        Section::make('Kartu pemilih')
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                TextEntry::make('preview')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn (Candidate $record): HtmlString => new HtmlString(
                                        view('filament.candidates.preview', ['candidate' => $record])->render(),
                                    )),
                            ]),
                        Section::make('Ringkasan')
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama kandidat')
                                    ->weight('semibold')
                                    ->columnSpanFull(),
                                TextEntry::make('profile_status')
                                    ->label('Kelengkapan')
                                    ->badge()
                                    ->state(fn (Candidate $record): string => $record->profileStatusLabel())
                                    ->color(fn (string $state): string => $state === 'Profil lengkap' ? 'success' : 'warning'),
                                TextEntry::make('votes_total')
                                    ->label('Total suara')
                                    ->state(fn (Candidate $record): int => $record->voteTotal()),
                                TextEntry::make('nip')
                                    ->label('NIP')
                                    ->copyable()
                                    ->placeholder('Belum diisi'),
                                TextEntry::make('golongan')
                                    ->label('Golongan')
                                    ->placeholder('Belum diisi'),
                            ]),
                        Section::make('Pilihan per bidang')
                            ->description('Bidang tempat kandidat ini dipilih, beserta total suaranya termasuk koreksi. Maksimal '.BallotService::MAX_FIELDS_PER_CANDIDATE.' bidang.')
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('field_votes')
                                    ->hiddenLabel()
                                    ->state(fn (Candidate $record): array => Field::query()
                                        ->ordered()
                                        ->where(fn ($query) => $query
                                            ->whereHas('votes', fn ($votes) => $votes->where('candidate_id', $record->getKey()))
                                            ->orWhereHas('corrections', fn ($corrections) => $corrections->where('candidate_id', $record->getKey())))
                                        ->get()
                                        ->map(fn (Field $field): string => $field->name.' · '.app(VoteCorrectionService::class)->currentTotal($record, $field).' suara')
                                        ->all())
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->placeholder('Belum dipilih di bidang mana pun'),
                            ]),
                    ]),
            ]);
    }
}
