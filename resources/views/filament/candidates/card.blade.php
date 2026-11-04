<article class="admin-candidate-card">
    <span class="admin-candidate-card-media">
        @if ($record->photoUrl())
            <img src="{{ $record->photoUrl() }}" alt="Foto {{ $record->name }}" class="admin-candidate-card-photo" loading="lazy">
        @else
            <span class="admin-candidate-card-initial" aria-hidden="true">{{ mb_substr($record->name, 0, 1) }}</span>
        @endif
        <span class="admin-candidate-dot {{ filled($record->photo_path) ? 'is-ok' : 'is-missing' }}" title="{{ $record->profileStatusLabel() }}"></span>
    </span>
    <span class="admin-candidate-card-body">
        <span class="admin-candidate-card-name">{{ $record->name }}</span>
        <span class="admin-candidate-card-meta">{{ $record->nip ? 'NIP '.$record->nip : 'NIP belum diisi' }} · {{ $record->golongan ?? 'Golongan belum diisi' }}</span>
        <span class="admin-candidate-card-foot">
            <span>{{ $record->fields->count() }} / {{ \App\Services\BallotService::MAX_FIELDS_PER_CANDIDATE }} bidang</span>
            <strong>{{ $record->vote_total ?? 0 }} suara</strong>
        </span>
    </span>
</article>
