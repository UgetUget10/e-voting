@php
    $photoUrl = isset($photoUrl) ? $photoUrl : $candidate->photoUrl();
    $initial = mb_substr($candidate->name ?? '', 0, 1);
@endphp
<div class="admin-preview">
    <div class="admin-preview-card">
        <span class="admin-preview-media">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="Foto {{ $candidate->name }}" class="admin-preview-photo">
            @else
                <span class="admin-preview-initial" aria-hidden="true">{{ $initial }}</span>
            @endif
        </span>
        <span class="admin-preview-name">{{ $candidate->name ?: 'Nama kandidat' }}</span>
    </div>
    <p class="admin-preview-note">Seperti inilah kartu kandidat tampil bagi pemilih.</p>
</div>
