@include('filament.partials.motion')

<x-filament-widgets::widget>
    <div class="admin-dash-stats admin-field-stats">
        @foreach ($cards as $card)
            <div class="admin-stat admin-dash-card {{ $card['tone'] ? 'is-'.$card['tone'] : '' }}" style="--stagger: {{ $loop->index }}">
                <span class="admin-stat-head">
                    <x-filament::icon :icon="$card['icon']" class="admin-stat-icon" />
                    {{ $card['label'] }}
                </span>

                <span class="admin-stat-value">
                    <span data-count-to="{{ $card['value'] }}" data-count-decimals="0">{{ number_format($card['value'], 0, ',', '.') }}</span>
                </span>

                @if ($card['progress'] !== null)
                    <span class="admin-candidate-progress" role="progressbar" aria-label="{{ $card['label'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $card['progress'] }}">
                        <span style="width: {{ $card['progress'] }}%"></span>
                    </span>
                @endif

                <span class="admin-dash-card-hint">{{ $card['hint'] }}</span>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
