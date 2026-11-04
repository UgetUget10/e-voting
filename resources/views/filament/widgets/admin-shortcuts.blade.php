<x-filament-widgets::widget>
    <nav class="admin-quick-links" aria-label="Pintasan">
        @foreach ($shortcuts as $shortcut)
            <a href="{{ $shortcut['url'] }}" class="admin-shortcut" style="--stagger: {{ $loop->index }}">
                <x-filament::icon :icon="$shortcut['icon']" class="admin-shortcut-icon" />
                <span class="admin-shortcut-label">{{ $shortcut['label'] }}</span>
                @if ($shortcut['count'] !== null)
                    <span class="admin-shortcut-count">{{ number_format($shortcut['count'], 0, ',', '.') }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</x-filament-widgets::widget>
