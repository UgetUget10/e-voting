<x-filament-widgets::widget>
    <section class="admin-attention" aria-labelledby="admin-attention-title">
        <header class="admin-attention-head">
            <h3 id="admin-attention-title">Perlu tindakan</h3>
            @if ($attention > 0)
                <span class="admin-attention-count">{{ $attention }} hal</span>
            @endif
        </header>

        @if ($items === [])
            <p class="admin-action-done">Tidak ada yang perlu tindakan saat ini.</p>
        @else
            <ul class="admin-action-list">
                @foreach ($items as $item)
                    <li class="admin-action-item is-{{ $item['level'] }}" style="--stagger: {{ $loop->index }}">
                        <span class="admin-action-mark" aria-hidden="true"></span>
                        <span class="admin-action-label">{{ $item['label'] }}</span>
                        <a href="{{ $item['url'] }}" class="admin-action-link">{{ $item['action'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-filament-widgets::widget>
