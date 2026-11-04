<span class="admin-candidate-thumb">
    @if ($record->photoUrl())
        <img src="{{ $record->photoUrl() }}" alt="Foto {{ $record->name }}" class="admin-candidate-photo" loading="lazy">
    @else
        <span class="admin-candidate-thumb-initial" role="img" aria-label="Belum ada foto untuk {{ $record->name }}">{{ mb_substr($record->name, 0, 1) }}</span>
    @endif
    <span class="admin-candidate-dot {{ $record->photoUrl() ? 'is-ok' : 'is-missing' }}" title="{{ $record->profileStatusLabel() }}"></span>
</span>
