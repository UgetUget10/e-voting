<x-filament-widgets::widget>
    <section
        class="admin-countdown is-{{ $state }}"
        @if ($targetMs)
            x-data="{
                target: {{ $targetMs }},
                start: {{ $startMs ?? 'null' }},
                now: Date.now(),
                get parts() {
                    const total = Math.max(0, Math.floor((this.target - this.now) / 1000));
                    const days = Math.floor(total / 86400);
                    const pad = (value) => String(value).padStart(2, '0');
                    return (days > 0 ? days + ' hari ' : '') + pad(Math.floor((total % 86400) / 3600)) + ':' + pad(Math.floor((total % 3600) / 60)) + ':' + pad(total % 60);
                },
                get progress() {
                    if (this.start === null || this.target <= this.start) {
                        return null;
                    }

                    return Math.min(100, Math.max(0, Math.round((this.now - this.start) / (this.target - this.start) * 100)));
                },
                init() {
                    setInterval(() => {
                        this.now = Date.now();
                        this.$refs.clock?.animate(
                            [{ opacity: 0.45, transform: 'translateY(-3px)' }, { opacity: 1, transform: 'none' }],
                            { duration: 300, easing: 'ease-out' },
                        );
                    }, 1000);
                },
            }"
        @endif
    >
        <div class="admin-countdown-main">
            <span class="admin-countdown-title">{{ $title }}</span>
            @if ($targetMs)
                <span class="admin-countdown-clock" x-ref="clock" x-text="parts" aria-live="off">--:--:--</span>
            @endif
            @if ($note)
                <span class="admin-countdown-note">{{ $note }}</span>
            @endif
        </div>

        @if ($progress !== null)
            <div class="admin-countdown-progress" role="progressbar" aria-label="Waktu voting yang sudah berjalan" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                <span style="width: {{ $progress }}%" x-bind:style="'width: ' + progress + '%'"></span>
            </div>
        @endif

        @if ($state === 'none')
            <a href="{{ $url }}" class="admin-countdown-link">Buka menu Pemilihan</a>
        @endif
    </section>
</x-filament-widgets::widget>
