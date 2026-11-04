@auth
    <div class="voter-account" x-data>
        <span class="voter-account-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <span class="voter-account-name">{{ auth()->user()->name }}</span>

        <button type="button" class="voter-account-logout" x-on:click="$refs.logoutDialog.showModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            Keluar
        </button>

        <dialog x-ref="logoutDialog" class="voter-dialog" aria-labelledby="voter-logout-title" aria-describedby="voter-logout-text">
            <div class="voter-dialog-body">
                <span class="voter-dialog-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                </span>
                <h3 id="voter-logout-title" class="voter-dialog-title">Keluar dari akun?</h3>
                <p id="voter-logout-text" class="voter-dialog-text">Anda perlu masuk lagi untuk membuka E-Voting Matsanewa.</p>
                <div class="voter-dialog-actions">
                    <button type="button" class="voter-dialog-cancel" x-on:click="$refs.logoutDialog.close()">Batal</button>
                    <form method="POST" action="{{ route('filament.pemilih.auth.logout') }}" class="voter-account-form">
                        @csrf
                        <button type="submit" class="voter-dialog-confirm">Ya, keluar</button>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
@endauth
