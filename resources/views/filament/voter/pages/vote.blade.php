<x-filament-panels::page>
    @if ($notVotedWarning)
        {{-- wire:ignore keeps the open dialog from being morphed away by Livewire updates. --}}
        <div wire:ignore x-data x-init="$refs.notVotedWarning.showModal()">
            <dialog x-ref="notVotedWarning" class="voter-dialog" aria-labelledby="voter-not-voted-title" aria-describedby="voter-not-voted-text">
                <div class="voter-dialog-body">
                    <span class="voter-dialog-icon is-warning" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <span class="voter-alert-kicker">Peringatan</span>
                    <h3 id="voter-not-voted-title" class="voter-dialog-title">Anda belum memilih</h3>
                    <p id="voter-not-voted-text" class="voter-dialog-text">{{ $notVotedWarning }}</p>
                    <div class="voter-dialog-actions is-single">
                        <button type="button" class="voter-dialog-confirm is-warning" x-on:click="$refs.notVotedWarning.close()">Saya mengerti</button>
                    </div>
                </div>
            </dialog>
        </div>
    @endif

    <div class="voter-hero">
        @include('filament.voter.partials.ballot-ornament')
        @include('filament.voter.partials.sparkles')
        <span class="voter-pill">
            @switch ($votingStatus)
                @case ('open')
                    <span class="voter-pill-dot"></span> Voting dibuka
                    @break
                @case ('upcoming')
                    Voting belum dibuka
                    @break
                @case ('closed')
                    Voting ditutup
                    @break
                @default
                    Belum ada pemilihan
            @endswitch
        </span>
        <h2 class="mt-3">{{ $election?->name ?? 'Pilih Kandidat' }}</h2>
        <p>
            @if ($isOpen)
                Pilih satu kandidat di bidang mana saja. Satu kandidat tidak boleh dipilih di lebih dari {{ $maxFieldsPerCandidate }} bidang.
            @endif
            @if ($votingStatus === 'upcoming' && $election?->opens_at)
                Dibuka {{ $election->opens_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.
            @endif
            @if ($election?->closes_at)
                Ditutup {{ $election->closes_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.
            @endif
        </p>

        @if ($isOpen && $closesAtMs)
            @include('filament.voter.partials.closing-countdown', ['closesAtMs' => $closesAtMs])
        @endif

        @if ($isOpen && $fieldCount > 0)
            <div class="voter-progress" role="progressbar" aria-label="Kemajuan memilih" aria-valuemin="0" aria-valuemax="{{ $fieldCount }}" aria-valuenow="{{ $pickedCount }}">
                <div class="voter-progress-track">
                    <div class="voter-progress-fill" style="width: {{ round($pickedCount / $fieldCount * 100) }}%"></div>
                </div>
                <span>{{ $pickedCount }} dari {{ $fieldCount }} bidang terpilih</span>
            </div>

            <div class="voter-field-chips" aria-label="Lompat ke bidang">
                @foreach ($fields as $index => $field)
                    <button
                        type="button"
                        class="voter-field-chip{{ filled($picks[$field->id] ?? null) ? ' is-filled' : '' }}"
                        x-on:click="document.querySelectorAll('.voter-stepper .fi-sc-wizard-header-step-btn')[{{ $index }}]?.click(); document.querySelector('.voter-stepper')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    >
                        <span class="voter-field-chip-mark" aria-hidden="true">{{ filled($picks[$field->id] ?? null) ? '✓' : $index + 1 }}</span>
                        {{ $field->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($votingStatus === 'upcoming')
        {{-- Polls until voting opens, then the ballot replaces this card without a manual reload. --}}
        <section class="voter-status is-pending voter-waiting" role="status" aria-live="polite" wire:poll.visible.5s>
            <div class="voter-status-card">
                <span class="voter-status-orb" aria-hidden="true">
                    <span class="voter-status-halo"></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                </span>
                <span class="voter-status-pill">Belum dibuka</span>
                <h2 class="voter-status-title">Voting belum dibuka</h2>
                @if ($election?->opens_at)
                    <p class="voter-waiting-text">Surat suara tersedia pada {{ $election->opens_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.</p>
                @endif
                @if ($opensAtMs)
                    <div
                        class="voter-waiting-countdown"
                        x-data="{
                            opensAt: {{ $opensAtMs }},
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
                                const total = Math.max(0, Math.floor((this.opensAt - this.now) / 1000));
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
                        <span class="voter-waiting-label">Dibuka dalam</span>
                        <span class="voter-waiting-value" x-text="remaining"></span>
                    </div>
                @endif
                <p class="voter-waiting-note">Halaman ini akan terbuka otomatis saat voting dimulai.</p>
            </div>
        </section>
    @else
        @unless ($isOpen)
            <div class="vote-notice" role="status">
                <span class="vote-notice-icon" aria-hidden="true">
                    @if ($votingStatus === 'closed')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v4.5"/><path d="M12 15.5h.01"/></svg>
                    @endif
                </span>
                <p class="vote-notice-text">
                    @switch ($votingStatus)
                        @case ('closed')
                            Voting sudah ditutup. Anda tidak bisa lagi mengirim suara.
                            @break
                        @default
                            Belum ada pemilihan yang aktif. Anda belum bisa mengirim suara sekarang.
                    @endswitch
                </p>
            </div>
        @endunless

        @if ($isOpen)
            {{ $this->content }}
        @endif
    @endif
</x-filament-panels::page>
