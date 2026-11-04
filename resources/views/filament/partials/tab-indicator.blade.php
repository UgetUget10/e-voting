<script>
    (function () {
        if (window.adminTabIndicator) {
            return;
        }

        window.adminTabIndicator = true;

        // One bar under the tab list that slides to the active tab, instead of each tab drawing its own underline.
        const place = () => {
            document.querySelectorAll('.fi-sc-tabs-tablist, .fi-tabs').forEach((list) => {
                let bar = list.querySelector(':scope > .admin-tab-indicator');

                if (! bar) {
                    bar = document.createElement('span');
                    bar.className = 'admin-tab-indicator';
                    bar.setAttribute('aria-hidden', 'true');
                    list.appendChild(bar);
                }

                const active = list.querySelector(':scope > .fi-active');

                if (! active) {
                    bar.style.width = '0px';

                    return;
                }

                bar.style.transform = `translateX(${active.offsetLeft}px)`;
                bar.style.width = `${active.offsetWidth}px`;
            });
        };

        new MutationObserver(place).observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class'],
        });

        window.addEventListener('resize', place);
        place();
    })();
</script>
