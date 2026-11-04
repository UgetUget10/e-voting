@include('filament.partials.motion')

<x-filament-widgets::widget>
    <div class="admin-dash-stats">
        @foreach ($cards as $card)
            <div class="admin-stat admin-dash-card {{ $card['tone'] ? 'is-'.$card['tone'] : '' }}" style="--stagger: {{ $loop->index }}">
                <span class="admin-stat-head">
                    <x-filament::icon :icon="$card['icon']" class="admin-stat-icon" />
                    {{ $card['label'] }}
                </span>

                @if ($card['text'])
                    <span class="admin-dash-card-text">{{ $card['value'] }}</span>
                @else
                    <span class="admin-stat-value">
                        <span data-count-to="{{ $card['value'] }}" data-count-decimals="0">{{ number_format($card['value'], 0, ',', '.') }}</span>
                    </span>
                @endif

                <span class="admin-dash-card-hint">{{ $card['hint'] }}</span>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
