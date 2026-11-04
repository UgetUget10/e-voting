@php
    $percent = $record->windowProgress();
@endphp
@if ($percent === null)
    <span class="admin-window-empty">Tidak dibatasi</span>
@else
    <div class="admin-window" role="img" aria-label="{{ $percent }} persen jendela voting sudah berlalu">
        <div class="admin-window-bar" aria-hidden="true">
            <span style="width: {{ $percent }}%"></span>
        </div>
        <span class="admin-window-figure">{{ $percent }}%</span>
    </div>
@endif
