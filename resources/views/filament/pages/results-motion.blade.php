<script>
    (function () {
        if (window.adminResultsMotion || ! window.adminMotion) {
            return;
        }

        window.adminResultsMotion = true;

        const motion = window.adminMotion;
        const positions = new Map();
        const ranks = new Map();
        const votes = new Map();
        const moveTimers = new WeakMap();

        let focusedSeries = null;
        let lastTied = null;
        let lastFieldsWithVotes = null;

        // A candidate can run in several fields, so a row is identified by its field and candidate together.
        const rowKey = (row) => `${row.closest('[data-field-id]').dataset.fieldId}:${row.dataset.candidateId}`;

        // Rows that move glide to their new place, and a row that gains votes flashes once.
        const trackRanking = () => {
            document.querySelectorAll('.admin-ranking > li[data-candidate-id]').forEach((row) => {
                if (row.offsetParent === null) {
                    return;
                }

                const id = rowKey(row);
                const top = row.offsetTop;
                const rank = Number(row.dataset.rank);
                const count = Number(row.dataset.votes);

                const previousTop = positions.get(id);

                positions.set(id, top);

                if (previousTop !== undefined && Math.abs(previousTop - top) > 1 && ! motion.reducedMotion()) {
                    row.animate(
                        [{ transform: `translateY(${previousTop - top}px)` }, { transform: 'translateY(0)' }],
                        { duration: 520, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' },
                    );
                }

                const previousRank = ranks.get(id);

                ranks.set(id, rank);

                if (previousRank !== undefined && previousRank !== rank) {
                    showRankChange(row, previousRank - rank);
                }

                const previousCount = votes.get(id);

                votes.set(id, count);

                if (previousCount !== undefined && count > previousCount && ! motion.reducedMotion()) {
                    row.animate(
                        [{ backgroundColor: 'rgba(234, 88, 12, 0.18)' }, { backgroundColor: 'rgba(234, 88, 12, 0)' }],
                        { duration: 1400, easing: 'ease-out' },
                    );
                }
            });
        };

        // Positive change means the candidate moved up.
        const showRankChange = (row, change) => {
            const badge = row.querySelector('[data-rank-move]');

            if (! badge) {
                return;
            }

            badge.textContent = change > 0 ? `▲ ${change}` : `▼ ${Math.abs(change)}`;
            badge.dataset.direction = change > 0 ? 'up' : 'down';
            badge.classList.remove('is-visible');
            void badge.offsetWidth;
            badge.classList.add('is-visible');

            clearTimeout(moveTimers.get(badge));
            moveTimers.set(badge, setTimeout(() => badge.classList.remove('is-visible'), 6000));
        };

        const applySeriesFocus = () => {
            document.querySelectorAll('.admin-timeline').forEach((timeline) => {
                timeline.classList.toggle('is-focusing', focusedSeries !== null);

                timeline.querySelectorAll('polyline[data-series-index]').forEach((line) => {
                    line.classList.toggle('is-focused', Number(line.dataset.seriesIndex) === focusedSeries);
                });
            });

            document.querySelectorAll('.admin-timeline-legend [data-series-index]').forEach((item) => {
                item.classList.toggle('is-focused', Number(item.dataset.seriesIndex) === focusedSeries);
            });
        };

        const legendItem = (target) => (target instanceof Element ? target.closest('.admin-timeline-legend [data-series-index]') : null);

        document.addEventListener('pointerover', (event) => {
            const item = legendItem(event.target);

            if (item) {
                focusedSeries = Number(item.dataset.seriesIndex);
                applySeriesFocus();
            }
        });

        document.addEventListener('pointerout', (event) => {
            const item = legendItem(event.target);

            if (item && ! item.contains(event.relatedTarget)) {
                focusedSeries = null;
                applySeriesFocus();
            }
        });

        const tooltip = () => {
            let element = document.querySelector('.admin-chart-tip');

            if (! element) {
                element = document.createElement('div');
                element.className = 'admin-chart-tip';
                element.setAttribute('role', 'tooltip');
                element.hidden = true;
                document.body.appendChild(element);
            }

            return element;
        };

        // Points are [x, count] pairs in chart units; the count holds until the next point.
        const countAt = (points, x) => {
            let count = 0;

            for (const [pointX, pointCount] of points) {
                if (pointX > x) {
                    break;
                }

                count = pointCount;
            }

            return count;
        };

        const timeAt = (chart, x) => {
            const startedAt = Date.parse(chart.dataset.startedAt);
            const span = Number(chart.dataset.span || 0);
            const width = Number(chart.dataset.width || 600);

            return new Date(startedAt + (span * 1000 * x) / width).toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
            }) + ' WIB';
        };

        const showTooltip = (event, chart, x) => {
            const element = tooltip();
            const title = document.createElement('strong');

            title.textContent = timeAt(chart, x);
            element.replaceChildren(title);

            if (chart.dataset.chart === 'trend') {
                const total = countAt(JSON.parse(chart.dataset.points), x);
                const denominator = Number(chart.dataset.denominator || 0);
                const line = document.createElement('span');

                line.textContent = `${total} dari ${denominator} pemilih sudah memilih`;
                element.appendChild(line);
            } else {
                JSON.parse(chart.dataset.series).forEach((series) => {
                    const row = document.createElement('span');
                    const swatch = document.createElement('i');
                    const name = document.createElement('span');
                    const value = document.createElement('strong');

                    row.className = 'admin-chart-tip-row';
                    swatch.style.background = series.color;
                    name.textContent = series.name;
                    value.textContent = countAt(series.points, x);
                    row.append(swatch, name, value);
                    element.appendChild(row);
                });
            }

            element.hidden = false;
            element.style.top = `${event.clientY + 14}px`;
            element.style.left = `${Math.min(event.clientX + 14, window.innerWidth - element.offsetWidth - 8)}px`;
        };

        const hideTooltip = () => {
            const element = document.querySelector('.admin-chart-tip');

            if (element) {
                element.hidden = true;
            }
        };

        document.addEventListener('pointermove', (event) => {
            const chart = event.target instanceof Element ? event.target.closest('svg[data-chart]') : null;

            if (! chart) {
                hideTooltip();

                return;
            }

            const rect = chart.getBoundingClientRect();

            if (rect.width === 0) {
                return;
            }

            const width = Number(chart.dataset.width || 600);
            const x = Math.max(0, Math.min(width, ((event.clientX - rect.left) / rect.width) * width));

            showTooltip(event, chart, x);
        });

        const notifyChanges = () => {
            const page = document.querySelector('.admin-results');

            if (! page) {
                return;
            }

            const tied = Number(page.dataset.tiedFields || 0);
            const withVotes = Number(page.dataset.fieldsWithVotes || 0);

            if (lastTied !== null && tied > lastTied) {
                const added = tied - lastTied;

                motion.toast(added === 1 ? 'Sebuah bidang sekarang seri.' : `${added} bidang sekarang seri.`);
            }

            if (lastFieldsWithVotes !== null && withVotes > lastFieldsWithVotes) {
                const added = withVotes - lastFieldsWithVotes;

                motion.toast(added === 1 ? 'Sebuah bidang menerima suara pertamanya.' : `${added} bidang menerima suara pertamanya.`);
            }

            lastTied = tied;
            lastFieldsWithVotes = withVotes;
        };

        const roundRect = (ctx, x, y, width, height, radius) => {
            ctx.beginPath();
            ctx.roundRect(x, y, Math.max(0, width), height, radius);
            ctx.fill();
        };

        // Draws the figures of the visible fields onto a canvas, so the picture is built from the same data as the page.
        const drawCard = (data) => {
            const scale = 2;
            const width = 1080;
            const padding = 64;
            const headerHeight = 150;
            const fieldHeaderHeight = 72;
            const rowHeight = 92;
            const fieldGap = 36;
            const ink = '#1c1917';
            const muted = '#6b6560';
            const accent = '#c2410c';
            const track = '#f0eeec';
            const barLeft = padding + 48;
            const barWidth = width - padding - barLeft;
            const height = headerHeight
                + data.fields.reduce((total, field) => total + fieldHeaderHeight + field.candidates.length * rowHeight + fieldGap, 0)
                + padding;

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = width * scale;
            canvas.height = height * scale;
            ctx.scale(scale, scale);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.textBaseline = 'alphabetic';

            ctx.font = '500 20px Figtree, system-ui, sans-serif';
            ctx.fillStyle = muted;
            ctx.fillText((data.status || 'Hasil voting').toUpperCase(), padding, padding + 16);

            ctx.font = '700 44px Figtree, system-ui, sans-serif';
            ctx.fillStyle = ink;
            ctx.fillText(data.title, padding, padding + 72, width - padding * 2);

            ctx.font = '400 20px Figtree, system-ui, sans-serif';
            ctx.fillStyle = muted;
            ctx.fillText(data.generatedAt, padding, padding + 110);

            let y = headerHeight;

            data.fields.forEach((field) => {
                const total = field.candidates.reduce((sum, candidate) => sum + candidate.votes, 0);

                ctx.font = '600 30px Figtree, system-ui, sans-serif';
                ctx.fillStyle = ink;
                ctx.fillText(field.name, padding, y + 40, width - padding * 2);

                ctx.font = '400 20px Figtree, system-ui, sans-serif';
                ctx.fillStyle = muted;
                ctx.textAlign = 'right';
                ctx.fillText(`${total} suara sah`, width - padding, y + 40);
                ctx.textAlign = 'left';

                y += fieldHeaderHeight;

                field.candidates.forEach((candidate, index) => {
                    const share = total > 0 ? Math.round((candidate.votes / total) * 100) : 0;
                    const rowY = y;

                    ctx.font = '600 24px Figtree, system-ui, sans-serif';
                    ctx.fillStyle = muted;
                    ctx.fillText(String(index + 1), padding, rowY + 34);

                    ctx.font = `${candidate.advances ? 600 : 500} 26px Figtree, system-ui, sans-serif`;
                    ctx.fillStyle = ink;
                    ctx.fillText(candidate.name, barLeft, rowY + 34, 560);

                    if (candidate.advances) {
                        const nameWidth = Math.min(ctx.measureText(candidate.name).width, 560);

                        ctx.font = '600 18px Figtree, system-ui, sans-serif';
                        ctx.fillStyle = accent;
                        ctx.fillText('LOLOS', barLeft + nameWidth + 16, rowY + 34);
                    }

                    ctx.font = '600 24px Figtree, system-ui, sans-serif';
                    ctx.fillStyle = ink;
                    ctx.textAlign = 'right';
                    ctx.fillText(`${candidate.votes} suara · ${share}%`, width - padding, rowY + 34);
                    ctx.textAlign = 'left';

                    ctx.fillStyle = track;
                    roundRect(ctx, barLeft, rowY + 52, barWidth, 12, 6);

                    if (share > 0) {
                        ctx.fillStyle = candidate.advances ? accent : '#a8a29e';
                        roundRect(ctx, barLeft, rowY + 52, barWidth * (share / 100), 12, 6);
                    }

                    y += rowHeight;
                });

                y += fieldGap;
            });

            return canvas;
        };

        window.adminResultsCard = {
            download() {
                const source = document.getElementById('admin-results-card');

                if (! source) {
                    return;
                }

                drawCard(JSON.parse(source.textContent)).toBlob((blob) => {
                    if (! blob) {
                        return;
                    }

                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');

                    link.href = url;
                    link.download = 'hasil-voting-'.concat(new Date().toISOString().slice(0, 10), '.png');
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }, 'image/png');
            },
        };

        motion.onSync(() => {
            if (! document.querySelector('.admin-results')) {
                return;
            }

            trackRanking();
            applySeriesFocus();
            notifyChanges();
        });
    })();
</script>
