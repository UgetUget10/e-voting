<x-filament-widgets::widget>
    <x-filament::section heading="Jadwal pemilihan" description="Setiap batang adalah jendela voting. Garis oranye menandai waktu sekarang.">
        @if ($rows === [])
            <p class="admin-timeline-empty">Belum ada pemilihan dengan jadwal buka dan tutup.</p>
        @else
            <div class="admin-schedule" role="img" aria-label="Jadwal {{ count($rows) }} pemilihan">
                <ul class="admin-schedule-legend" aria-hidden="true">
                    <li class="is-dibuka">Dibuka</li>
                    <li class="is-belum-dibuka">Belum dibuka</li>
                    <li class="is-ditutup">Ditutup</li>
                </ul>
                <div class="admin-schedule-axis">
                    <span>{{ $axisStart->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i') }}</span>
                    <span>{{ $axisEnd->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i') }}</span>
                </div>
                <div class="admin-schedule-body">
                    @foreach ($rows as $row)
                        <div class="admin-schedule-row">
                            <span class="admin-schedule-name" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                            <div class="admin-schedule-track">
                                <span
                                    class="admin-schedule-bar is-{{ \Illuminate\Support\Str::slug($row['status']) }}"
                                    style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%; --stagger: {{ $loop->index }};"
                                    tabindex="0"
                                    aria-label="{{ $row['name'] }}, {{ $row['status'] }}: {{ $row['opens'] }} sampai {{ $row['closes'] }} WIB"
                                >
                                    <span class="admin-schedule-tip" aria-hidden="true">{{ $row['opens'] }} sampai {{ $row['closes'] }} WIB</span>
                                </span>
                                @if ($nowLeft !== null)
                                    <span class="admin-schedule-now" style="left: {{ $nowLeft }}%;" aria-hidden="true"></span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($undated > 0)
                    <p class="admin-schedule-note">{{ $undated }} pemilihan tanpa jadwal lengkap tidak ditampilkan.</p>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
