<div class="admin-candidate-grid">
    @foreach ($records as $record)
        <a href="{{ \App\Filament\Resources\Candidates\CandidateResource::getUrl('view', ['record' => $record]) }}" class="admin-candidate-card-link">
            @include('filament.candidates.card', ['record' => $record])
        </a>
    @endforeach
</div>
