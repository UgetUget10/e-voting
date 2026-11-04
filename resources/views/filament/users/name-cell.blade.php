@php
    $initials = collect(preg_split('/\s+/u', trim($record->name), -1, PREG_SPLIT_NO_EMPTY))
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $tone = crc32($record->name) % 4;
@endphp
<div class="admin-person">
    <span class="admin-avatar admin-avatar-tone-{{ $tone }}" aria-hidden="true">{{ $initials }}</span>
    <span class="admin-person-text">
        <span class="admin-person-name">{{ $record->name }}</span>
        @if ($record->is_admin)
            <span class="admin-person-chip">Admin</span>
        @endif
    </span>
</div>
