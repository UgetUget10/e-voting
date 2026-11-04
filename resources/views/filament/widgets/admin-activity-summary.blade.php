@include('filament.partials.motion')

<x-filament-widgets::widget>
    <div class="admin-activity-summary" wire:poll.15s>
        @if ($waiting > 0)
            <div class="admin-activity-live" role="status" aria-live="polite">
                <span class="admin-activity-live-dot" aria-hidden="true"></span>
                <span class="admin-activity-live-text">{{ $waiting }} aktivitas baru menunggu</span>
                <button type="button" class="admin-activity-live-button" wire:click="$dispatch('activity-refresh')">Muat yang baru</button>
            </div>
        @endif

        <div class="admin-dash-stats admin-activity-stats">
            @foreach ($cards as $card)
                <div class="admin-stat admin-dash-card {{ $card['tone'] ? 'is-'.$card['tone'] : '' }}" style="--stagger: {{ $loop->index }}">
                    <span class="admin-stat-head">
                        <x-filament::icon :icon="$card['icon']" class="admin-stat-icon" />
                        {{ $card['label'] }}
                    </span>

                    <span class="admin-stat-value {{ ($card['is_text'] ?? false) ? 'is-text' : '' }}">
                        @if (is_int($card['value']))
                            <span data-count-to="{{ $card['value'] }}" data-count-decimals="0">{{ number_format($card['value'], 0, ',', '.') }}</span>
                        @else
                            {{ $card['value'] }}
                        @endif
                    </span>

                    <span class="admin-dash-card-hint">{{ $card['hint'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
