@if ($election)
    @php
        $status = $election->statusLabel();
        $progress = $election->windowProgress();
    @endphp
    <div class="admin-sidebar-status" role="status" title="{{ $election->name }}: {{ $status }}">
        <span class="admin-sidebar-status-name">{{ $election->name }}</span>
        <span class="admin-sidebar-status-state {{ $status === 'Dibuka' ? 'is-open' : '' }}">
            <span class="admin-sidebar-status-dot" aria-hidden="true"></span><span class="admin-sidebar-status-label">{{ $status }}</span>
        </span>
        @if ($progress !== null)
            <span class="admin-sidebar-status-bar" aria-hidden="true"><span style="width: {{ $progress }}%"></span></span>
        @endif
    </div>
@endif
