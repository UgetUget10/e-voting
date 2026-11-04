{{-- Filament turns an empty collection into null, so the state is normalised before it is used. --}}
@php($notes = collect($getState() ?? []))

@if ($notes->isEmpty())
    <p class="admin-diff-desc">Belum ada catatan untuk surat suara ini.</p>
@else
    <ol class="admin-notes" aria-label="Catatan internal">
        @foreach ($notes as $note)
            <li class="admin-note">
                <p class="admin-note-body">{{ $note->body }}</p>
                <p class="admin-note-meta">
                    {{ $note->user?->name ?? 'Admin dihapus' }} · {{ $note->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i') }} WIB
                </p>
            </li>
        @endforeach
    </ol>
@endif
