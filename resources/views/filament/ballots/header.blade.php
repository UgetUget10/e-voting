@include('filament.partials.motion')

<div class="admin-ballot-header" data-live="{{ $liveUpdates ? '1' : '0' }}" data-new-ballots="{{ $newBallots }}" @if ($liveUpdates) wire:poll.{{ $pollSeconds }}s="checkForChanges" @endif>
    <div class="admin-live-bar">
        <span class="admin-live-status" title="Dicek setiap {{ $pollSeconds }} detik">
            <span class="admin-live-total"><strong>{{ number_format($total, 0, ',', '.') }}</strong> suara masuk</span>
            <span class="admin-live-dot {{ $liveUpdates ? '' : 'is-paused' }}" aria-hidden="true"></span>
            {{ $liveUpdates ? 'Memantau suara masuk' : 'Pembaruan dijeda' }}
            <span class="admin-live-time">· perubahan terakhir {{ $updatedAt->format('H:i:s') }} WIB</span>
        </span>
        <span class="admin-live-tools">
            <span class="admin-live-keys">
                <kbd class="admin-kbd">/</kbd> cari
                <kbd class="admin-kbd">↑</kbd><kbd class="admin-kbd">↓</kbd> pilih baris
                <kbd class="admin-kbd">Enter</kbd> buka detail
            </span>
            <button type="button" class="admin-tool-button" data-shortcuts-open>
                <kbd class="admin-kbd">?</kbd> pintasan
            </button>
            <button type="button" class="admin-tool-button" data-sound-toggle aria-pressed="false" wire:ignore>
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedBellAlert" class="admin-tool-icon" />
                <span data-sound-label>Bunyi mati</span>
            </button>
        </span>
    </div>

    <div class="admin-stats">
        @foreach ($cards as $card)
            <button
                type="button"
                class="admin-stat is-{{ $card['tone'] }}"
                style="--stagger: {{ $loop->index }}"
                wire:click="$set('activeTab', '{{ $card['tab'] }}')"
            >
                <span class="admin-stat-head">
                    <x-filament::icon :icon="$card['icon']" class="admin-stat-icon" />
                    {{ $card['label'] }}
                </span>
                <span class="admin-stat-value">
                    <span data-count-to="{{ $card['value'] }}" data-count-decimals="{{ $card['decimals'] }}">{{ number_format($card['value'], $card['decimals'], ',', '.') }}</span>
                    <span class="admin-stat-delta" data-count-delta wire:ignore></span>
                </span>
                <span class="admin-stat-hint">{{ $card['hint'] }}</span>
            </button>
        @endforeach
    </div>

    @if ($spikes !== [])
        <div class="admin-callout is-warning" role="status">
            <strong>Lonjakan suara terdeteksi</strong>
            <span>
                @foreach ($spikes as $spike)
                    pukul {{ sprintf('%02d.00', $spike['hour']) }} WIB ({{ $spike['total'] }} suara){{ ! $loop->last ? ', ' : '' }}
                @endforeach
                jauh di atas kebiasaan hari ini. Periksa apakah ada pengiriman berulang.
            </span>
        </div>
    @endif

    @if ($newBallots > 0)
        <div class="admin-callout is-info" role="status">
            <strong>{{ $newBallots }} suara baru</strong>
            <span>masuk sejak halaman dibuka.</span>
        </div>
    @endif

    <details class="admin-overview" open>
        <summary>Ringkasan partisipasi dan jam kirim</summary>
        <div class="admin-overview-body">
            @livewire(\App\Filament\Widgets\BallotParticipation::class)
            @livewire(\App\Filament\Widgets\BallotsByHour::class)
        </div>
    </details>

    <h2 class="admin-section-title">Hasil sesuai filter</h2>

    @include('filament.ballots.tally', ['tally' => $tally])

</div>

<script>
    (function () {
        if (window.adminBallotShortcuts) {
            return;
        }

        window.adminBallotShortcuts = true;

        const SOUND_KEY = 'admin-ballots-sound';
        const baseTitle = document.title.replace(/^\(\d+\) /, '');

        let focusedIndex = -1;
        let lastNewBallots = null;
        let audioContext = null;
        let soundEnabled = false;

        try {
            soundEnabled = localStorage.getItem(SOUND_KEY) === '1';
        } catch (error) {
            // Storage can be blocked; the toggle then only lasts for this visit.
        }

        const rows = () => Array.from(document.querySelectorAll('.fi-ta tbody tr.fi-ta-row'));
        const shortcutsDialog = () => document.querySelector('.admin-shortcuts');
        const isTyping = (element) => element instanceof HTMLElement
            && (element.matches('input, textarea, select') || element.isContentEditable);

        // Built once and appended to <body>, outside the Livewire-rendered header. Inside the header, polling
        // would reset the dialog's open state, and it would not be a true modal that covers the page.
        const buildShortcutsDialog = () => {
            const dialog = document.createElement('dialog');

            dialog.className = 'admin-shortcuts';
            dialog.setAttribute('aria-labelledby', 'admin-shortcuts-title');
            dialog.innerHTML = `
                <div class="admin-shortcuts-body">
                    <h2 id="admin-shortcuts-title" class="admin-shortcuts-title">Pintasan keyboard</h2>
                    <dl class="admin-shortcuts-list">
                        <div><dt><kbd class="admin-kbd">/</kbd></dt><dd>Cari surat suara</dd></div>
                        <div><dt><kbd class="admin-kbd">↑</kbd> <kbd class="admin-kbd">↓</kbd></dt><dd>Pilih baris sebelumnya atau berikutnya</dd></div>
                        <div><dt><kbd class="admin-kbd">Enter</kbd></dt><dd>Buka detail suara yang dipilih</dd></div>
                        <div><dt><kbd class="admin-kbd">?</kbd></dt><dd>Tampilkan atau tutup daftar ini</dd></div>
                        <div><dt><kbd class="admin-kbd">Esc</kbd></dt><dd>Tutup jendela</dd></div>
                    </dl>
                    <form method="dialog" class="admin-shortcuts-footer">
                        <button type="submit" class="admin-tool-button">Tutup</button>
                    </form>
                </div>`;

            // A click on the dimmed backdrop, outside the content, closes the dialog.
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            document.body.appendChild(dialog);

            return dialog;
        };

        const toggleShortcuts = () => {
            const dialog = shortcutsDialog() ?? buildShortcutsDialog();

            if (dialog.open) {
                dialog.close();
            } else {
                dialog.showModal();
            }
        };

        const playChime = () => {
            try {
                audioContext ??= new (window.AudioContext || window.webkitAudioContext)();

                const start = audioContext.currentTime;
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.value = 880;
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(start);
                oscillator.stop(start + 0.4);
            } catch (error) {
                // Browsers without Web Audio stay silent; the toast still appears.
            }
        };

        const syncNewBallots = () => {
            const header = document.querySelector('.admin-ballot-header');

            if (!header) {
                return;
            }

            const count = Number(header.dataset.newBallots || 0);

            if (lastNewBallots !== null && count > lastNewBallots && header.dataset.live === '1') {
                const difference = count - lastNewBallots;

                window.adminMotion.toast(difference === 1 ? '1 suara baru masuk.' : window.adminMotion.format(difference, 0) + ' suara baru masuk.');

                if (soundEnabled) {
                    playChime();
                }
            }

            lastNewBallots = count;

            const title = count > 0 ? '(' + window.adminMotion.format(count, 0) + ') ' + baseTitle : baseTitle;

            if (document.title !== title) {
                document.title = title;
            }
        };

        const syncSoundToggles = () => {
            document.querySelectorAll('[data-sound-toggle]').forEach((button) => {
                const label = button.querySelector('[data-sound-label]');
                const text = soundEnabled ? 'Bunyi nyala' : 'Bunyi mati';

                button.setAttribute('aria-pressed', String(soundEnabled));

                if (label && label.textContent !== text) {
                    label.textContent = text;
                }
            });
        };

        window.adminMotion.onSync(() => {
            syncNewBallots();
            syncSoundToggles();
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            if (event.target.closest('[data-shortcuts-open]')) {
                toggleShortcuts();

                return;
            }

            if (event.target.closest('[data-sound-toggle]')) {
                soundEnabled = ! soundEnabled;

                try {
                    localStorage.setItem(SOUND_KEY, soundEnabled ? '1' : '0');
                } catch (error) {
                    // Same as above: the choice still applies for this visit.
                }

                if (soundEnabled) {
                    playChime();
                }

                syncSoundToggles();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (isTyping(event.target) || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (event.key === '?') {
                event.preventDefault();
                toggleShortcuts();

                return;
            }

            if (document.querySelector('.admin-shortcuts')?.open) {
                return;
            }

            // While a ballot's detail is open, the arrow keys step through the ballots on this page instead of the rows behind it.
            const detail = document.querySelector('.admin-ballot-detail');

            if (detail && detail.getClientRects().length > 0) {
                const step = event.key === 'ArrowLeft' ? 'previous' : (event.key === 'ArrowRight' ? 'next' : null);
                const control = step ? detail.querySelector(`[data-ballot-nav="${step}"]`) : null;

                if (control) {
                    event.preventDefault();
                    control.click();
                }

                return;
            }

            if (event.key === '/') {
                const search = document.querySelector('.fi-ta-search-field input');

                if (search) {
                    event.preventDefault();
                    search.focus();
                }

                return;
            }

            const list = rows();

            if (list.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                focusedIndex = Math.min(list.length - 1, Math.max(0, focusedIndex + (event.key === 'ArrowDown' ? 1 : -1)));

                list.forEach((row) => row.classList.remove('is-focused'));
                list[focusedIndex].classList.add('is-focused');
                list[focusedIndex].scrollIntoView({ block: 'nearest' });

                return;
            }

            if (event.key === 'Enter' && focusedIndex >= 0 && list[focusedIndex]) {
                const detail = list[focusedIndex].querySelector('[wire\\:click*="mountTableAction(\'view\'"]');

                if (detail) {
                    event.preventDefault();
                    detail.click();
                }
            }
        });

        window.adminMotion.sync();
    })();
</script>
