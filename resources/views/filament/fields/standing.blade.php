<div class="admin-field-standing">
    @if ($standing['leader'])
        <span class="admin-field-leader">{{ $standing['leader']->name }}</span>
        <span class="admin-field-share">{{ $standing['leader']->votes_count }} suara · {{ $standing['leaderShare'] }}%</span>
        <span class="admin-field-bar" role="progressbar" aria-label="Porsi suara pemimpin {{ $standing['leader']->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $standing['leaderShare'] }}">
            <span style="width: {{ $standing['leaderShare'] }}%"></span>
        </span>
    @else
        <span class="admin-field-empty">Belum ada suara</span>
    @endif
</div>
