@include('filament.partials.motion')

<x-filament-widgets::widget>
    <div class="admin-dash-stats">
        @foreach ($cards as $card)
            <div class="admin-stat admin-dash-card is-{{ $card['tone'] }}" style="--stagger: {{ $loop->index }}">
                <span class="admin-stat-head">
                    <x-filament::icon :icon="$card['icon']" class="admin-stat-icon" />
                    {{ $card['label'] }}
                </span>

                @if (! empty($card['text']))
                    <span class="admin-dash-card-text">{{ $card['value'] }}</span>
                @else
                    <span class="admin-stat-value">
                        <span data-count-to="{{ $card['value'] }}" data-count-decimals="{{ $card['decimals'] }}">{{ number_format($card['value'], $card['decimals'], ',', '.') }}</span>
                        <span class="admin-stat-delta" data-count-delta wire:ignore></span>
                    </span>
                @endif

                @if (! empty($card['change']))
                    <span class="admin-turnout-change {{ $card['change']['tone'] }}">{{ $card['change']['text'] }}</span>
                @endif

                <span class="admin-dash-card-hint">{{ $card['hint'] }}</span>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
