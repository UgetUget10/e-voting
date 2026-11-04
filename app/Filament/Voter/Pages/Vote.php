<?php

namespace App\Filament\Voter\Pages;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Services\BallotService;
use BackedEnum;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Vote extends Page
{
    /**
     * Session key for the picks a voter has made but not yet sent, so a refresh does not lose them.
     */
    public const DRAFT_SESSION_KEY = 'vote.draft';

    protected static ?string $slug = 'vote';

    protected static ?string $title = 'Pilih Kandidat';

    protected static ?string $navigationLabel = 'Pilih Kandidat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    /**
     * Kept out of the navigation. Voters reach the ballot from the status page and the results warning.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.voter.pages.vote';

    /**
     * Form state. Each field step stores its pick under picks.{fieldId}.
     *
     * @var array{picks: array<int|string, int|string|null>}
     */
    public ?array $data = [];

    /**
     * Field ids whose step was left empty after pressing "Selanjutnya".
     *
     * @var array<string, string>
     */
    public array $attemptedFieldIds = [];

    /**
     * Set when the voter was sent here from the results page, which stays closed until they have voted.
     */
    public ?string $notVotedWarning = null;

    public static function hasVoted(): bool
    {
        return (bool) auth()->user()?->ballot()->exists();
    }

    public function mount(): void
    {
        if (auth()->user()?->is_admin) {
            $this->redirect(Results::getUrl(panel: 'pemilih'), navigate: true);

            return;
        }

        if (static::hasVoted()) {
            $this->redirect(CheckReceipt::getUrl(panel: 'pemilih'), navigate: true);

            return;
        }

        $this->notVotedWarning = session(Results::NOT_VOTED_SESSION_KEY);
        $this->data = ['picks' => $this->restoreDraft()];
    }

    public function updatedData(): void
    {
        session()->put(static::DRAFT_SESSION_KEY, $this->data['picks'] ?? []);
    }

    public function content(Schema $schema): Schema
    {
        $fields = Field::query()->ordered()->get();
        $candidates = Candidate::query()->ordered()->get();

        $steps = $fields->map(
            fn (Field $field, int $index) => Step::make($field->name)
                ->beforeValidation(fn () => $this->attemptedFieldIds[] = $field->id)
                ->schema([
                    Callout::make('Langkah ini belum diisi. Pilih satu kandidat untuk melanjutkan.')
                        ->color('warning')
                        ->extraAttributes(['class' => 'voter-shake'])
                        ->hidden(fn (Get $get) => ! in_array($field->id, $this->attemptedFieldIds, true) || filled($get('picks.'.$field->id))),
                    ViewField::make('picks.'.$field->id)
                        ->label('Kandidat')
                        ->required()
                        ->validationMessages(['required' => 'Pilih satu kandidat untuk bidang ini sebelum melanjutkan.'])
                        ->view('filament.voter.forms.candidate-picker')
                        ->viewData(fn (Get $get) => $this->pickerData($field, $index + 1, $fields->count(), $candidates, $get('picks') ?? [])),
                ])
        )->all();

        return $schema
            ->statePath('data')
            ->components([
                Wizard::make($steps)
                    ->extraAttributes(['class' => 'voter-stepper'])
                    ->submitAction(new HtmlString(view('filament.voter.forms.submit-ballot', [
                        'isOpen' => $this->isOpen(),
                        'summary' => $this->pickSummary($fields, $candidates),
                    ])->render())),
            ]);
    }

    /**
     * Data for one field's candidate picker. A candidate that already fills the
     * maximum number of other fields is disabled here.
     *
     * @param  Collection<int, Candidate>  $candidates
     * @param  array<int|string, int|string|null>  $picks
     * @return array<string, mixed>
     */
    protected function pickerData(Field $field, int $fieldNumber, int $fieldCount, Collection $candidates, array $picks): array
    {
        $otherPicks = collect($picks)->except($field->id)->filter();

        $disabledIds = $candidates
            ->filter(fn (Candidate $candidate) => $otherPicks->filter(fn ($pickedId) => (string) $pickedId === $candidate->id)->count() >= BallotService::MAX_FIELDS_PER_CANDIDATE)
            ->pluck('id')
            ->all();

        return [
            'statePath' => 'data.picks.'.$field->id,
            'fieldName' => $field->name,
            'fieldNumber' => $fieldNumber,
            'fieldCount' => $fieldCount,
            'candidates' => $candidates,
            'selectedId' => $picks[$field->id] ?? null,
            'disabledIds' => $disabledIds,
            'maxFieldsPerCandidate' => BallotService::MAX_FIELDS_PER_CANDIDATE,
            'isOpen' => $this->isOpen(),
        ];
    }

    /**
     * The picks as shown in the confirmation dialog, one row per field in step order.
     *
     * @param  Collection<int, Field>  $fields
     * @param  Collection<int, Candidate>  $candidates
     * @return list<array{field: string, candidate: ?string}>
     */
    protected function pickSummary(Collection $fields, Collection $candidates): array
    {
        $picks = $this->data['picks'] ?? [];

        return $fields->map(fn (Field $field) => [
            'field' => $field->name,
            'candidate' => $candidates->firstWhere('id', $picks[$field->id] ?? null)?->name,
        ])->values()->all();
    }

    public function submit(BallotService $ballots): void
    {
        $fieldCount = Field::count();

        if (count(array_filter($this->data['picks'] ?? [])) < $fieldCount) {
            $this->failSubmit('Pilih kandidat di setiap bidang sebelum mengirim suara.');

            return;
        }

        try {
            $ballot = $ballots->submit(auth()->user(), $this->data['picks'] ?? []);
        } catch (ValidationException $exception) {
            $this->failSubmit(collect($exception->errors())->flatten()->first());

            return;
        }

        session()->forget(static::DRAFT_SESSION_KEY);

        Notification::make()
            ->title('Suara Anda telah tercatat.')
            ->success()
            ->send();

        $this->redirect(CheckReceipt::getUrl(panel: 'pemilih'), navigate: true);
    }

    /**
     * Tells the confirmation dialog to close again, since the success animation is shown before submitting.
     */
    protected function failSubmit(string $message): void
    {
        $this->dispatch('vote-submit-failed');

        Notification::make()
            ->title($message)
            ->danger()
            ->send();
    }

    /**
     * Restores the voter's unsent picks, dropping any field or candidate that no longer exists.
     *
     * @return array<string, string>
     */
    protected function restoreDraft(): array
    {
        $fieldIds = Field::query()->pluck('id')->all();
        $candidateIds = Candidate::query()->pluck('id')->all();

        return collect(session()->get(static::DRAFT_SESSION_KEY, []))
            ->filter(fn ($candidateId, $fieldId) => in_array($fieldId, $fieldIds, true) && in_array($candidateId, $candidateIds, true))
            ->map(fn ($candidateId) => (string) $candidateId)
            ->all();
    }

    protected function isOpen(): bool
    {
        return (bool) Election::current()?->isOpen();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $election = Election::current();
        $picks = $this->data['picks'] ?? [];

        return [
            'election' => $election,
            'votingStatus' => $election?->votingStatus() ?? 'none',
            'isOpen' => $this->isOpen(),
            'opensAtMs' => $election?->opens_at?->getTimestampMs(),
            'closesAtMs' => $election?->closes_at?->getTimestampMs(),
            'maxFieldsPerCandidate' => BallotService::MAX_FIELDS_PER_CANDIDATE,
            'fieldCount' => Field::count(),
            'fields' => Field::query()->ordered()->get(['id', 'name']),
            'picks' => $picks,
            'pickedCount' => count(array_filter($picks)),
        ];
    }
}
