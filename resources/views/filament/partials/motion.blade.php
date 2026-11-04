<script>
    (function () {
        if (window.adminMotion) {
            return;
        }

        const countState = new WeakMap();
        const hooks = [];
        let syncScheduled = false;
        let toastTimer = null;

        const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const format = (value, decimals) => value.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        const animateCount = (element, state, from, target) => {
            const decimals = Number(element.dataset.countDecimals || 0);
            const token = ++state.token;

            state.shown = target;
            element.textContent = format(from, decimals);

            if (from === target || reducedMotion()) {
                element.textContent = format(target, decimals);

                return;
            }

            const startedAt = performance.now();

            const step = (now) => {
                if (state.token !== token) {
                    return;
                }

                const progress = Math.min(1, (now - startedAt) / 700);
                const eased = 1 - (1 - progress) ** 3;

                element.textContent = format(from + (target - from) * eased, decimals);

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        };

        // The badge lives in the card that holds the number, so a change shows "+3" beside it for a few seconds.
        const showDelta = (element, difference) => {
            const badge = element.closest('.admin-stat')?.querySelector('[data-count-delta]');

            if (!badge || difference === 0) {
                return;
            }

            const decimals = Number(element.dataset.countDecimals || 0);

            badge.textContent = (difference > 0 ? '+' : '−') + format(Math.abs(difference), decimals);
            badge.dataset.direction = difference > 0 ? 'up' : 'down';
            badge.classList.remove('is-visible');
            void badge.offsetWidth;
            badge.classList.add('is-visible');

            clearTimeout(badge.hideTimer);
            badge.hideTimer = setTimeout(() => badge.classList.remove('is-visible'), 4000);
        };

        const syncCounts = () => {
            document.querySelectorAll('[data-count-to]').forEach((element) => {
                const target = Number(element.dataset.countTo);
                const state = countState.get(element) ?? { shown: null, token: 0 };

                countState.set(element, state);

                if (state.shown === target) {
                    return;
                }

                const previous = state.shown;

                animateCount(element, state, previous ?? 0, target);

                if (previous !== null) {
                    showDelta(element, target - previous);
                }
            });
        };

        const toast = (message) => {
            let element = document.querySelector('.admin-toast');

            if (!element) {
                element = document.createElement('div');
                element.className = 'admin-toast';
                element.setAttribute('role', 'status');
                element.setAttribute('aria-live', 'polite');
                document.body.appendChild(element);
            }

            if (element.textContent !== message) {
                element.textContent = message;
            }

            element.classList.add('is-visible');

            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => element.classList.remove('is-visible'), 4000);
        };

        // Hooks run after every DOM change. They must only write when something actually changed, or they will
        // keep triggering each other through the observer below.
        const runSync = () => {
            syncCounts();
            hooks.forEach((hook) => hook());
        };

        const scheduleSync = () => {
            if (syncScheduled) {
                return;
            }

            syncScheduled = true;

            requestAnimationFrame(() => {
                syncScheduled = false;
                runSync();
            });
        };

        window.adminMotion = {
            format,
            reducedMotion,
            toast,
            sync: scheduleSync,
            onSync(hook) {
                hooks.push(hook);
            },
        };

        new MutationObserver(scheduleSync).observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['data-count-to', 'data-new-ballots', 'data-live', 'data-tied-fields', 'data-fields-with-votes'],
        });

        scheduleSync();
    })();
</script>
