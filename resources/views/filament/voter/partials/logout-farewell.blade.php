<div class="logout-farewell" data-state="idle" role="status" aria-live="polite">
    <div class="logout-farewell-card">
        <span class="logout-farewell-badge" aria-hidden="true">
            <span class="logout-farewell-ripple"></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <g class="logout-farewell-arrow"><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></g>
            </svg>
        </span>
        <p class="logout-farewell-eyebrow">Sedang keluar</p>
        <h2 class="logout-farewell-title">Sampai jumpa lagi</h2>
        <p class="logout-farewell-text">Mohon tunggu sebentar, sesi Anda sedang ditutup.</p>
        <span class="logout-farewell-bar" aria-hidden="true"><span></span></span>
    </div>
</div>
<script>
    (function () {
        // The farewell needs to be seen, so the sign-out form is sent after the leaving animation has played.
        // A sign-out that is already leaving is not sent twice.
        const leaveDelay = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 400 : 1000);

        const farewellElement = () => document.querySelector('.logout-farewell');

        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.matches('form[action$="/logout"]') || form.dataset.leaving === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.leaving = 'true';
            form.closest('dialog')?.close();

            const farewell = farewellElement();

            if (farewell) {
                farewell.dataset.state = 'leaving';
            }

            window.setTimeout(() => form.submit(), leaveDelay());
        }, true);

        // Going back to a page from the browser cache must not keep the farewell or the sent-flag.
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) {
                return;
            }

            document.querySelectorAll('form[data-leaving="true"]').forEach((form) => delete form.dataset.leaving);

            const farewell = farewellElement();

            if (farewell && farewell.dataset.state === 'leaving') {
                farewell.dataset.state = 'idle';
            }
        });
    })();
</script>
