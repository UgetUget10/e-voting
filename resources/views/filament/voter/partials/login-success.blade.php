@if ($success)
    <div class="login-success" role="status" aria-live="polite">
        <div class="login-success-card">
            <span class="login-success-badge" aria-hidden="true">
                <span class="login-success-ripple"></span>
                <span class="login-success-sparks">
                    @for ($spark = 0; $spark < 8; $spark++)
                        <span style="--angle: {{ $spark * 45 }}deg"></span>
                    @endfor
                </span>
                <svg viewBox="0 0 48 48" aria-hidden="true">
                    <path class="login-success-check" pathLength="1" d="M14 25l7 7 13-15"/>
                </svg>
            </span>
            <p class="login-success-eyebrow">Login berhasil</p>
            <h2 class="login-success-title">Selamat datang, {{ $success['name'] }}</h2>
            <p class="login-success-text">
                {{ $success['is_admin'] ? 'Panel pengelola siap digunakan.' : 'Anda sudah masuk. Silakan lanjutkan ke halaman pemilih.' }}
            </p>
            <span class="login-success-bar" aria-hidden="true"><span></span></span>
        </div>
    </div>
@endif
