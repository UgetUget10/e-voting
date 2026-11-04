{{-- Filament turns an empty list into null, so the state is normalised before it is used. --}}
@php($entries = collect($getState() ?? []))

<ol class="admin-history" aria-label="Riwayat surat suara">
    @foreach ($entries as $entry)
        <li class="admin-history-item" style="--stagger: {{ $loop->index }}">
            <span class="admin-history-dot" aria-hidden="true"></span>
            <span class="admin-history-body">
                <span class="admin-history-text">{{ $entry['text'] }}</span>
                <span class="admin-history-meta">
                    {{ $entry['at']->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i') }} WIB
                    @if ($entry['by']) · {{ $entry['by'] }} @endif
                </span>
            </span>
        </li>
    @endforeach
</ol>
