<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Election;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCandidate extends EditRecord
{
    protected static string $resource = CandidateResource::class;

    /**
     * While the election is locked the page opens read-only, so the admin can still see the profile and
     * the reason it cannot change, instead of an error page.
     */
    public function getSubheading(): ?string
    {
        return Election::isCurrentLocked()
            ? 'Data pemilihan sedang dikunci, jadi kandidat hanya bisa dilihat. Buka kunci di menu Pemilihan untuk mengubahnya.'
            : null;
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (Election::isCurrentLocked()) {
            Notification::make()->danger()->title('Data pemilihan sedang dikunci.')->send();

            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getFormActions(): array
    {
        return Election::isCurrentLocked() ? [] : parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! Election::isCurrentLocked() && ! $this->getRecord()->hasRecordedVotes())
                ->modalDescription('Kandidat ini belum dipilih, sehingga aman dihapus.'),
        ];
    }
}
