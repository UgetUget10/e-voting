<div class="voter-login-intro">
    @php
        $schoolLogoPath = 'images/logo-mtsn-1-kota-malang.png';
        $hasSchoolLogo = file_exists(public_path($schoolLogoPath));
    @endphp
    <div class="voter-login-stage">
        <span class="voter-login-ring" aria-hidden="true"></span>
        <span class="voter-login-ring is-delayed" aria-hidden="true"></span>
        <svg class="voter-login-seal" viewBox="0 0 160 160" aria-hidden="true">
            <defs>
                <path id="voter-login-seal-path" d="M80 80m-66 0a66 66 0 1 1 132 0a66 66 0 1 1-132 0"/>
            </defs>
            <circle cx="80" cy="80" r="77" fill="none" stroke="currentColor" stroke-opacity="0.25" stroke-width="1.5"/>
            <circle cx="80" cy="80" r="53" fill="none" stroke="currentColor" stroke-opacity="0.35" stroke-width="1.25" stroke-dasharray="2 5"/>
            <text>
                <textPath href="#voter-login-seal-path" textLength="410" lengthAdjust="spacing">E-VOTING • RAHASIA • LANGSUNG • UMUM •</textPath>
            </text>
        </svg>
        <span class="voter-login-core">
            @if ($hasSchoolLogo)
                <img src="{{ asset($schoolLogoPath) }}" alt="Logo MTsN 1 Kota Malang" class="voter-login-logo">
            @else
                <svg class="voter-login-box" viewBox="0 0 96 96" aria-hidden="true">
                    <defs>
                        <linearGradient id="voter-login-box-fill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#fb923c"/>
                            <stop offset="1" stop-color="#c2410c"/>
                        </linearGradient>
                    </defs>
                    <g class="voter-login-box-paper">
                        <rect x="32" y="8" width="32" height="40" rx="4" fill="#fff" stroke="#fdba74" stroke-width="2"/>
                        <path class="voter-login-box-check" pathLength="1" d="M40 28l6 6 11-12" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                    <rect x="12" y="44" width="72" height="42" rx="9" fill="url(#voter-login-box-fill)"/>
                    <rect x="24" y="50" width="48" height="6" rx="3" fill="#7c2d12" fill-opacity="0.45"/>
                </svg>
            @endif
        </span>
    </div>
    <span class="voter-login-badge">
        <span class="voter-login-badge-dot" aria-hidden="true"></span>
        {{ $election?->isOpen() ? 'Voting sedang dibuka' : 'Voting belum dibuka' }}
    </span>
    @if ($institution ?? null)
        <p class="voter-login-institution">{{ $institution }}</p>
    @endif
    <h2 class="voter-login-title">{{ $title ?? ($election?->name ?? 'Selamat datang di E-Voting Matsanewa') }}</h2>
    <p class="voter-login-text">{{ $text ?? 'Masuk dengan akun pemilih Anda. Setiap akun hanya bisa memberikan satu suara.' }}</p>
    @if ($announcement ?? null)
        <div class="voter-login-announcement" role="note">
            <strong>Pengumuman</strong>
            <p>{{ $announcement }}</p>
        </div>
    @endif
</div>
