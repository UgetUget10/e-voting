<style>
    .back-guard-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(28, 12, 4, 0.55);
        backdrop-filter: blur(4px);
        animation: back-guard-fade 0.2s ease-out;
    }

    .back-guard-card {
        width: 100%;
        max-width: 24rem;
        padding: 2rem 1.5rem 1.5rem;
        text-align: center;
        background: #fffaf5;
        border: 1px solid #fed7aa;
        border-radius: 1.25rem;
        box-shadow: 0 25px 50px -12px rgba(124, 45, 18, 0.45);
        animation: back-guard-pop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .back-guard-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        margin: 0 auto 1rem;
        color: #fff;
        background: linear-gradient(135deg, #fb923c, #c2410c);
        border-radius: 9999px;
        box-shadow: 0 0 0 6px #ffedd5;
    }

    .back-guard-title {
        margin: 0 0 0.5rem;
        font-size: 1.125rem;
        font-weight: 700;
        color: #431407;
    }

    .back-guard-text {
        margin: 0 0 1.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #7c4a2d;
    }

    .back-guard-button {
        width: 100%;
        padding: 0.65rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
        background: linear-gradient(135deg, #f97316, #c2410c);
        border: 0;
        border-radius: 0.75rem;
    }

    .back-guard-button:hover {
        filter: brightness(1.08);
    }

    @keyframes back-guard-fade {
        from { opacity: 0; }
    }

    @keyframes back-guard-pop {
        from { opacity: 0; transform: scale(0.9) translateY(8px); }
    }
</style>
<script>
    (() => {
        if (window.__voterBackGuard) {
            return;
        }

        window.__voterBackGuard = true;

        const statusUrl = @js($statusUrl);

        const showWarning = () => {
            if (document.querySelector('.back-guard-overlay')) {
                return;
            }

            const overlay = document.createElement('div');
            overlay.className = 'back-guard-overlay';
            overlay.setAttribute('role', 'alertdialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML = `
                <div class="back-guard-card">
                    <div class="back-guard-icon">
                        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        </svg>
                    </div>
                    <h2 class="back-guard-title">Anda sudah melakukan voting</h2>
                    <p class="back-guard-text">Suara Anda sudah tercatat dan tidak dapat kembali ke halaman sebelumnya. Anda akan diarahkan ke halaman status memilih.</p>
                    <button type="button" class="back-guard-button">Lihat Status Memilih</button>
                </div>`;

            const go = () => window.location.replace(statusUrl);

            overlay.querySelector('button').addEventListener('click', go);
            document.body.appendChild(overlay);
            overlay.querySelector('button').focus();
            setTimeout(go, 4000);
        };

        history.pushState({ voterBackGuard: true }, '', location.href);

        window.addEventListener('popstate', showWarning);

        window.addEventListener('pageshow', (event) => {
            if (event.persisted && location.href !== statusUrl) {
                window.location.replace(statusUrl);
            }
        });
    })();
</script>
