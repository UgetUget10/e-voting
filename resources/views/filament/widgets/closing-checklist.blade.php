<x-filament-widgets::widget>
    <x-filament::section heading="Daftar periksa sebelum voting ditutup" :description="$readyCount === count($checks) ? 'Semua beres. Tidak ada yang perlu dikejar.' : $readyCount.' dari '.count($checks).' hal sudah beres'">
        <ul class="admin-readiness admin-closing">
            @foreach ($checks as $check)
                <li class="admin-readiness-item {{ $check['ok'] ? 'is-ok' : 'is-warn' }}" style="--stagger: {{ $loop->index }}">
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
