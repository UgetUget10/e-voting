@php($picks = $getState())

@if (blank($picks))
    <p class="admin-diff-desc">Tanpa pilihan</p>
@else
    <ul class="admin-pick-chips" aria-label="Pilihan per bidang">
        @foreach ($picks as $pick)
            <li class="admin-pick-chip">
                <span class="admin-pick-field">{{ $pick['field'] }}</span>
                <span class="admin-pick-candidate">{{ $pick['candidate'] }}</span>
            </li>
        @endforeach
    </ul>
@endif
