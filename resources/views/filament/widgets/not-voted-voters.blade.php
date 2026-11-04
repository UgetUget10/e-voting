<x-filament-widgets::widget>
    <x-filament::section heading="Pemilih yang belum memilih" description="Kirim pengingat dari daftar pemilih, satu per satu atau massal.">
        @php($voted = $voters > 0 ? (int) round(($voters - $waiting) / $voters * 100) : 0)

        @if ($waiting === 0)
            <p class="admin-action-done">Semua pemilih sudah memilih.</p>
        @else
            <div class="admin-voted-meter">
                <div class="admin-voted-meter-head">
                    <span><strong>{{ $voters - $waiting }}</strong> dari {{ $voters }} pemilih sudah memilih</span>
                    <span class="admin-voted-meter-figure">{{ $voted }}%</span>
                </div>
                <div class="admin-voted-meter-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $voted }}" aria-label="Sudah memilih">
                    <span style="width: {{ $voted }}%"></span>
                </div>
            </div>
            <p>
                <strong>{{ $waiting }}</strong> pemilih belum memilih.
                @if ($sample !== [])
                    <span class="admin-diff-desc">Contoh: {{ implode(', ', $sample) }}{{ $waiting > count($sample) ? ', dan lainnya' : '' }}.</span>
                @endif
            </p>
            <p><a class="admin-link" href="{{ $url }}">Lihat dan kirim pengingat</a></p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
