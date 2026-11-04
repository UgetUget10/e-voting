<div
    class="voter-countdown"
    x-data="{
        closesAt: {{ $closesAtMs }},
        now: Date.now(),
        timer: null,
        init() {
            this.timer = setInterval(() => {
                if (! this.$el.isConnected) {
                    clearInterval(this.timer);

                    return;
                }

                this.now = Date.now();
            }, 1000);
        },
        get remaining() {
            if (this.now >= this.closesAt) {
                return 'Waktu voting habis';
            }

            const total = Math.floor((this.closesAt - this.now) / 1000);
            const days = Math.floor(total / 86400);
            const clock = [Math.floor((total % 86400) / 3600), Math.floor((total % 3600) / 60), total % 60]
                .map((part) => String(part).padStart(2, '0'))
                .join(':');

            return days > 0 ? `${days} hari ${clock}` : clock;
        },
    }"
    role="timer"
    aria-live="off"
>
    <span class="voter-countdown-dot" aria-hidden="true"></span>
    <span class="voter-countdown-label">Ditutup dalam</span>
    <span class="voter-countdown-value" x-text="remaining"></span>
</div>
