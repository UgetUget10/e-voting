@auth
    @php($user = auth()->user())
    @php($roleLabel = $user->roles->pluck('name')->map(fn (string $role): string => \Illuminate\Support\Str::headline($role))->implode(', '))

    <div class="admin-account" x-data>
        <span class="admin-account-avatar" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
        <span class="admin-account-who">
            <span class="admin-account-name">{{ $user->name }}</span>
            @if ($roleLabel !== '')
                <span class="admin-account-role">{{ $roleLabel }}</span>
            @endif
        </span>

        <button type="button" class="admin-account-logout" x-on:click="$refs.logoutDialog.showModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            <span>Keluar</span>
        </button>

        <dialog x-ref="logoutDialog" class="admin-logout-dialog" aria-labelledby="admin-logout-title" aria-describedby="admin-logout-text">
            <div class="admin-logout-body">
                <span class="admin-logout-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                </span>
                <h2 id="admin-logout-title" class="admin-logout-title">Keluar dari panel admin?</h2>
                <p id="admin-logout-text" class="admin-logout-text">Anda perlu masuk lagi untuk mengelola pemilihan.</p>
                <div class="admin-logout-actions">
                    <button type="button" class="admin-logout-cancel" x-on:click="$refs.logoutDialog.close()">Batal</button>
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                        @csrf
                        <button type="submit" class="admin-logout-confirm">Ya, keluar</button>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
@endauth
