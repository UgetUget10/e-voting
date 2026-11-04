@if ($standing['ranked']->isEmpty())
    <p class="admin-field-empty">Belum ada kandidat yang menerima suara di bidang ini.</p>
@else
    <ol class="admin-standings">
        @foreach ($standing['ranked'] as $candidate)
            @php($share = $standing['total'] > 0 ? (int) round($candidate->votes_count / $standing['total'] * 100) : 0)
            <li class="admin-standing-row" style="--stagger: {{ $loop->index }}">
                <span class="admin-standing-rank">{{ $loop->iteration }}</span>
                <span class="admin-standing-name">
                    {{ $candidate->name }}
                    @if ($standing['winners']->contains('id', $candidate->id))
                        <span class="admin-standing-badge">Lolos</span>
                    @endif
                </span>
                <span class="admin-standing-votes">{{ $candidate->votes_count }} suara · {{ $share }}%</span>
                <span class="admin-field-bar" role="progressbar" aria-label="Porsi suara {{ $candidate->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $share }}">
                    <span style="width: {{ $share }}%"></span>
                </span>
            </li>
        @endforeach
    </ol>
@endif
