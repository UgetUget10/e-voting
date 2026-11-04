<x-filament-panels::page>
    @php
        $statusLabel = match ($votingStatus) {
            'open' => 'Voting dibuka',
            'upcoming' => 'Voting belum dibuka',
            'closed' => 'Voting ditutup',
            default => 'Belum ada pemilihan',
        };
        $voteStepState = $hasVoted ? 'is-done' : ($votingStatus === 'open' ? 'is-current' : 'is-waiting');
        $resultStepState = $hasVoted ? 'is-current' : 'is-waiting';
        $pendingStampLabel = match ($votingStatus) {
            'closed' => 'Ditutup',
            'none' => 'Tidak aktif',
            default => 'Belum memilih',
        };
    @endphp

    <div class="status-page">
        <header class="status-hero">
            <div class="status-hero-art" aria-hidden="true">
                <span class="status-hero-ring"></span>
                <span class="status-hero-ring is-delayed"></span>
                <span class="status-hero-slip is-one"></span>
                <span class="status-hero-slip is-two"></span>
            </div>

            <div class="status-hero-copy">
                <span class="status-pill{{ $votingStatus === 'open' ? ' is-open' : '' }}">
                    @if ($votingStatus === 'open')
                        <span class="status-pill-dot" aria-hidden="true"></span>
                    @endif
                    {{ $statusLabel }}
                </span>

                <h2 class="status-hero-title">{{ $election?->name ?? 'Status Memilih' }}</h2>

                @if ($votingStatus === 'upcoming' && $election?->opens_at || $election?->closes_at)
                    <p class="status-hero-text">
                        @if ($votingStatus === 'upcoming' && $election?->opens_at)
                            Dibuka {{ $election->opens_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.
                        @endif
                        @if ($election?->closes_at)
                            Ditutup {{ $election->closes_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.
                        @endif
                    </p>
                @endif

                @if ($votingStatus === 'open' && $closesAtMs)
                    @include('filament.voter.partials.closing-countdown', ['closesAtMs' => $closesAtMs])
                @endif
            </div>
        </header>

        <ol class="status-steps" style="--progress: {{ $hasVoted ? '33.33%' : '0%' }}" aria-label="Langkah voting">
            <li class="status-step is-done">
                <span class="status-step-dot" aria-hidden="true">1</span>
                <span class="status-step-label">Masuk akun</span>
            </li>
            <li class="status-step {{ $voteStepState }}">
                <span class="status-step-dot" aria-hidden="true">2</span>
                <span class="status-step-label">Memilih</span>
            </li>
            <li class="status-step {{ $resultStepState }}">
                <span class="status-step-dot" aria-hidden="true">3</span>
                <span class="status-step-label">Lihat hasil</span>
            </li>
        </ol>

        @if ($hasVoted)
            <section class="status-receipt is-voted" role="status" aria-live="polite">
                <div class="status-receipt-main">
                    <span class="status-orb" aria-hidden="true">
                        <span class="status-orb-ring"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path class="status-check-draw" pathLength="1" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    </span>
                    <span class="status-kicker">Status pemilih</span>
                    <h2 class="status-receipt-title">Terima kasih, Anda sudah memilih.</h2>
                    <a href="{{ $resultsUrl }}" class="status-cta">
                        Lihat Hasil
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                    </a>
                </div>
                <div class="status-receipt-stub" aria-hidden="true">
                    <span class="status-stamp">Tercatat</span>
                    <span class="status-barcode"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                </div>
            </section>
        @else
            <section class="status-receipt is-pending" role="status" aria-live="polite">
                <div class="status-receipt-main">
                    <span class="status-orb" aria-hidden="true">
                        <span class="status-orb-ring"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7.5v6"/><path d="M12 17.5h.01"/></svg>
                    </span>
                    <span class="status-kicker">Status pemilih</span>
                    @switch ($votingStatus)
                        @case ('open')
                            <h2 class="status-receipt-title">Anda belum memilih, mohon segera memilih.</h2>
                            <a href="{{ $voteUrl }}" class="status-cta">
                                Pilih Sekarang
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                            </a>
                            @break
                        @case ('upcoming')
                            <h2 class="status-receipt-title">Voting belum dibuka. Anda bisa memilih saat voting dibuka.</h2>
                            @break
                        @case ('closed')
                            <h2 class="status-receipt-title">Voting sudah ditutup. Anda tidak bisa lagi mengirim suara.</h2>
                            @break
                        @default
                            <h2 class="status-receipt-title">Belum ada pemilihan yang aktif.</h2>
                    @endswitch
                </div>
                <div class="status-receipt-stub" aria-hidden="true">
                    <span class="status-stamp">{{ $pendingStampLabel }}</span>
                    <span class="status-barcode"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
