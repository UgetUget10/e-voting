<div class="voter-submit" x-data="{ sending: false }" x-on:vote-submit-failed.window="sending = false; $refs.confirmDialog.close()">
    <x-filament::button type="button" size="lg" x-on:click="$refs.confirmDialog.showModal()" :disabled="! $isOpen">Kirim suara</x-filament::button>

    <dialog x-ref="confirmDialog" class="voter-dialog" aria-labelledby="voter-confirm-title" aria-describedby="voter-confirm-description" x-on:close="sending = false">
        <div class="voter-dialog-body" x-show="! sending">
            <span class="voter-dialog-icon" aria-hidden="true">
                <span class="voter-dialog-ring"></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            </span>
            <h3 id="voter-confirm-title" class="voter-dialog-title">Kirim suara sekarang?</h3>
            <p id="voter-confirm-description" class="voter-dialog-text">Suara tidak bisa diubah setelah dikirim. Periksa kembali pilihan Anda di bawah ini.</p>

            <ul class="voter-summary" aria-label="Ringkasan pilihan">
                @foreach ($summary as $row)
                    <li class="voter-summary-row{{ $row['candidate'] ? '' : ' is-missing' }}">
                        <span class="voter-summary-field">{{ $row['field'] }}</span>
                        <span class="voter-summary-candidate">{{ $row['candidate'] ?? 'Belum dipilih' }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="voter-dialog-actions">
                <button type="button" class="voter-dialog-cancel" x-on:click="$refs.confirmDialog.close()">Periksa lagi</button>
                <button type="button" class="voter-dialog-confirm" x-on:click="sending = true; setTimeout(() => $wire.submit(), 900)">Ya, kirim suara</button>
            </div>
        </div>

        <div class="voter-dialog-body voter-sent" x-show="sending" x-cloak aria-live="polite">
            <span class="voter-sent-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path class="voter-check-path" pathLength="1" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            </span>
            <span class="voter-burst" aria-hidden="true">
                <i style="--x: -4.5rem; --y: -3rem; --c: #f59e0b"></i>
                <i style="--x: 0rem; --y: -4.25rem; --c: #ec4899"></i>
                <i style="--x: 4.5rem; --y: -3rem; --c: #22c55e"></i>
                <i style="--x: -4.75rem; --y: 1rem; --c: #38bdf8"></i>
                <i style="--x: 4.75rem; --y: 1rem; --c: #f97316"></i>
                <i style="--x: 0rem; --y: 3.5rem; --c: #a855f7"></i>
            </span>
            <h3 class="voter-dialog-title">Suara Anda sedang dikirim</h3>
            <p class="voter-dialog-text">Mohon tunggu sebentar. Anda akan diarahkan ke halaman hasil.</p>
        </div>
    </dialog>
</div>
