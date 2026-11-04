<x-filament-widgets::widget>
    <x-filament::section heading="Kesiapan pemilihan" :description="$readyCount === count($checks) ? 'Semua siap untuk dibuka.' : $readyCount.' dari '.count($checks).' persiapan selesai'">
        <ul class="admin-readiness">
            @foreach ($checks as $check)
                <li class="admin-readiness-item {{ $check['ok'] ? 'is-ok' : 'is-warn' }}">
                    <span class="admin-readiness-mark" aria-hidden="true"></span>
                    @if ($check['ok'])
                        <span>{{ $check['label'] }}</span>
                    @else
                        <a href="{{ $check['url'] }}">{{ $check['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
