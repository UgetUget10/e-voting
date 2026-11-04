<x-filament-panels::page>
    @include('filament.partials.motion')

    <div
        @if ($pollActive) wire:poll.visible.5s @endif
        class="admin-results{{ $projector ? ' admin-projector' : '' }}"
        data-projector="{{ $projector ? '1' : '0' }}"
        data-tied-fields="{{ $tiedFields }}"
        data-fields-with-votes="{{ $fieldsWithVotes }}"
        x-data="{
            active: 0,
            total: {{ max(1, count($results)) }},
            paused: false,
            timer: null,
            init() {
                this.restartBar();
                this.timer = setInterval(() => this.tick(), 8000);
            },
            tick() {
                if (! this.$el.isConnected) {
                    clearInterval(this.timer);

                    return;
                }

                if (this.$el.dataset.projector === '1' && this.total > 1 && ! this.paused) {
                    this.show((this.active + 1) % this.total);
                }
            },
            show(index) {
                this.active = index;
                this.restartBar();
            },
            restartBar() {
                const bar = this.$refs.progress;

                if (! bar) {
                    return;
                }

                bar.classList.remove('is-running');
                void bar.offsetWidth;
                bar.classList.add('is-running');
            },
            onKey(event) {
                if (event.ctrlKey || event.metaKey || event.altKey) {
                    return;
                }

                if (event.target instanceof HTMLElement && (event.target.matches('input, textarea, select') || event.target.isContentEditable)) {
                    return;
                }

                if (event.key === 'p' || event.key === 'P') {
                    event.preventDefault();
                    this.$wire.toggleProjector();

                    return;
                }

                if (event.key === 'f' || event.key === 'F') {
                    event.preventDefault();

                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else {
                        document.documentElement.requestFullscreen();
                    }

                    return;
                }

                if (this.$el.dataset.projector !== '1') {
                    return;
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    this.show((this.active + 1) % this.total);
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    this.show((this.active - 1 + this.total) % this.total);
                }
            },
        }"
        @keydown.window="onKey($event)"
        @mouseenter="paused = true; $refs.progress?.classList.add('is-paused')"
        @mouseleave="paused = false; $refs.progress?.classList.remove('is-paused')"
    >
        <div class="admin-rotation-bar" wire:ignore>
            <div class="admin-rotation-progress" x-ref="progress"><span></span></div>
            <p class="admin-rotation-hint">
                <kbd class="admin-kbd">←</kbd> <kbd class="admin-kbd">→</kbd> ganti bidang
                · <kbd class="admin-kbd">P</kbd> mode proyektor
                · <kbd class="admin-kbd">F</kbd> layar penuh
            </p>
        </div>

        <div class="admin-status" role="status">
            @if ($election)
                <div class="admin-status-main">
                    <span class="admin-status-kicker">Pemilihan aktif</span>
                    <span class="admin-status-name">{{ $election->name }}</span>
                </div>
                <div class="admin-status-chips">
                    <x-filament::badge :color="$election->statusLabel() === 'Dibuka' ? 'success' : 'gray'" size="lg">
                        {{ $election->statusLabel() }}
                    </x-filament::badge>
                    @if ($resultsVisibleToVoters)
                        <x-filament::badge color="info" size="lg">Hasil terlihat pemilih</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="lg">Hasil disembunyikan dari pemilih</x-filament::badge>
                    @endif
                    @if ($fieldsWithoutVotes > 0)
                        <x-filament::badge color="warning" size="lg">{{ $fieldsWithoutVotes }} bidang belum ada suara</x-filament::badge>
                    @endif
                    @if ($tiedFields > 0)
                        <x-filament::badge color="danger" size="lg">{{ $tiedFields }} bidang seri</x-filament::badge>
                    @endif
                </div>
            @else
                <div class="admin-status-main">
                    <span class="admin-status-kicker">Belum ada pemilihan</span>
                    <span class="admin-status-name">Buat pemilihan di menu Pemilihan untuk memulai.</span>
                </div>
            @endif
        </div>

        <div class="admin-results-summary" role="list" aria-label="Ringkasan pemilih">
            <div class="admin-stat" role="listitem" style="--stagger: 0">
                <span class="admin-stat-label">Pemilih terdaftar</span>
                <span class="admin-stat-value">
                    <span data-count-to="{{ $voterCount }}">{{ number_format($voterCount, 0, ',', '.') }}</span>
                    <span class="admin-stat-delta" data-count-delta wire:ignore></span>
                </span>
            </div>
            <div class="admin-stat is-voted" role="listitem" style="--stagger: 1">
                <span class="admin-stat-label">Sudah memilih</span>
                <span class="admin-stat-value">
                    <span data-count-to="{{ $ballotCount }}">{{ number_format($ballotCount, 0, ',', '.') }}</span>
                    <span class="admin-stat-delta" data-count-delta wire:ignore></span>
                </span>
                <span class="admin-stat-meta">{{ $turnout }}% partisipasi</span>
                @if ($previousTurnout !== null)
                    @php
                        $turnoutChange = $turnout - $previousTurnout;
                    @endphp
                    <span class="admin-turnout-change {{ $turnoutChange > 0 ? 'is-up' : ($turnoutChange < 0 ? 'is-down' : '') }}">
                        @if ($turnoutChange === 0)
                            Sama dengan pemilihan sebelumnya
                        @else
                            {{ $turnoutChange > 0 ? '▲' : '▼' }} {{ abs($turnoutChange) }} poin dari pemilihan sebelumnya
                        @endif
                    </span>
                @endif
            </div>
            <a href="{{ $notVotedUrl }}" class="admin-stat is-pending" role="listitem" style="--stagger: 2">
                <span class="admin-stat-label">Belum memilih</span>
                <span class="admin-stat-value">
                    <span data-count-to="{{ $notVotedCount }}">{{ number_format($notVotedCount, 0, ',', '.') }}</span>
                    <span class="admin-stat-delta" data-count-delta wire:ignore></span>
                </span>
                <span class="admin-stat-meta">Lihat daftar pemilih</span>
            </a>
            <div class="admin-stat is-live" role="listitem" style="--stagger: 3">
                <span class="admin-stat-label">Diperbarui</span>
                <span class="admin-stat-value admin-stat-time">{{ $updatedAt->timezone('Asia/Jakarta')->format('H:i:s') }}</span>
                <span class="admin-stat-meta">
                    @if ($election?->hasClosed())
                        <span class="admin-live-dot is-paused" aria-hidden="true"></span> Voting ditutup, hasil final
                    @elseif ($liveUpdates)
                        <span class="admin-live-dot" aria-hidden="true"></span> Live setiap 5 detik
                    @else
                        <span class="admin-live-dot is-paused" aria-hidden="true"></span> Live dijeda
                    @endif
                </span>
            </div>
        </div>

        <section class="voter-trend" aria-labelledby="admin-trend-title">
            <div class="voter-trend-head">
                <div>
                    <h3 id="admin-trend-title">Tren partisipasi</h3>
                    <p>
                        @if ($trend['startedAt'])
                            Suara pertama masuk pukul {{ $trend['startedAt']->timezone('Asia/Jakarta')->format('H:i') }} WIB
                        @else
                            Belum ada suara masuk
                        @endif
                    </p>
                </div>
                <span class="voter-trend-figure"><span data-count-to="{{ $trend['percent'] }}">{{ $trend['percent'] }}</span>%</span>
                @if ($previousTurnout !== null)
                    <span class="admin-diff-desc">Pemilihan sebelumnya: {{ $previousTurnout }}%</span>
                @endif
            </div>

            @if ($trend['total'] > 0)
                @php
                    $ticks = collect(range(0, 4))->map(fn (int $step): string => $trend['startedAt']->copy()->addSeconds((int) round($trend['span'] * $step / 4))->timezone('Asia/Jakarta')->format('H:i'));
                @endphp
                <svg
                    class="voter-trend-chart"
                    viewBox="0 0 600 120"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label="Partisipasi mencapai {{ $trend['percent'] }} persen dari pemilih terdaftar"
                    data-chart="trend"
                    data-width="600"
                    data-points="{{ json_encode($trend['points']) }}"
                    data-started-at="{{ $trend['startedAt']->toIso8601String() }}"
                    data-span="{{ $trend['span'] }}"
                    data-denominator="{{ $voterCount }}"
                >
                    <defs>
                        <linearGradient id="admin-trend-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#f97316" stop-opacity="0.45"/>
                            <stop offset="100%" stop-color="#f97316" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <line x1="0" x2="600" y1="30" y2="30" class="voter-trend-grid"/>
                    <line x1="0" x2="600" y1="60" y2="60" class="voter-trend-grid"/>
                    <line x1="0" x2="600" y1="90" y2="90" class="voter-trend-grid"/>
                    <polygon points="{{ $trend['area'] }}" fill="url(#admin-trend-fill)" class="voter-trend-area"/>
                    <polyline points="{{ $trend['line'] }}" class="voter-trend-line"/>
                </svg>
                <div class="voter-trend-scale" aria-hidden="true">
                    <span>0%</span>
                    <span>{{ $voterCount }} pemilih terdaftar</span>
                    <span>100%</span>
                </div>
                <div class="admin-time-ticks" aria-hidden="true">
                    @foreach ($ticks as $tick)
                        <span>{{ $tick }}</span>
                    @endforeach
                </div>
            @else
                <p class="voter-trend-empty">Grafik akan muncul setelah suara pertama masuk.</p>
            @endif
        </section>

        @if (count($fieldOptions) > 1)
            <label class="admin-field-filter">
                <span>Tampilkan bidang</span>
                <select wire:model.live="fieldId" class="admin-field-select">
                    <option value="">Semua bidang</option>
                    @foreach ($fieldOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($fieldOptions === [])
            <p class="voter-trend-empty">Belum ada bidang. Tambahkan bidang di menu Bidang & Kandidat.</p>
        @endif

        <div class="admin-leaders" role="list" aria-label="Pemenang sementara per bidang">
            @foreach ($results as $index => $result)
                @php
                    $ranked = $result['candidates']->sortByDesc('votes_count')->values();
                    $leader = $ranked->first();
                    $isTie = $leader !== null && $leader->votes_count > 0 && $ranked->get(1)?->votes_count === $leader->votes_count;
                    $leadMargin = $leader !== null && $leader->votes_count > 0 && ! $isTie
                        ? $leader->votes_count - ($ranked->get(1)?->votes_count ?? 0)
                        : null;
                    $fieldTotal = max(1, $result['candidates']->sum('votes_count'));
                    $leadShare = $leader !== null && $leader->votes_count > 0 ? round($leader->votes_count / $fieldTotal * 100) : 0;
                @endphp
                <div class="admin-leader{{ $isTie ? ' is-tie' : '' }}" role="listitem" style="--stagger: {{ $index }}">
                    <span class="admin-leader-field">{{ $result['field']->name }}</span>
                    <span class="admin-leader-name">{{ $leader === null ? 'Belum ada kandidat' : ($leader->votes_count > 0 ? $leader->name : 'Belum ada suara') }}</span>
                    <span class="admin-leader-bar" aria-hidden="true"><span style="width: {{ $leadShare }}%"></span></span>
                    <span class="admin-leader-meta">
                        <strong data-count-to="{{ $leader?->votes_count ?? 0 }}">{{ $leader?->votes_count ?? 0 }}</strong> suara · {{ $result['winners']->count() }} kandidat lolos
                        @if ($isTie)
                            <span class="admin-tie">Seri</span>
                        @endif
                    </span>
                    @if ($leadMargin !== null && $ranked->count() > 1)
                        <span class="admin-leader-lead">Unggul {{ $leadMargin }} suara atas peringkat kedua</span>
                    @endif
                </div>
            @endforeach
        </div>

        @foreach ($results as $index => $result)
            @php
                $fieldTotal = max(1, $result['candidates']->sum('votes_count'));
                $topVotes = max(1, $result['candidates']->max('votes_count'));
                $rankedCandidates = $result['candidates']->sortByDesc('votes_count')->values();
                $wasAdvancing = null;
                $timeline = $timelines[$result['field']->getKey()];
            @endphp
            <div x-show="{{ $projector ? 'active === '.$index : 'true' }}" x-transition.opacity.duration.400ms class="admin-field-slide">
            <x-filament::section
                :heading="$result['field']->name"
                :description="$result['winners']->count().' kandidat lolos ke presentasi · '.$result['candidates']->sum('votes_count').' suara sah'"
            >
                <ol class="admin-ranking" data-field-id="{{ $result['field']->getKey() }}">
                    @foreach ($rankedCandidates as $position => $candidate)
                        @php
                            $advances = $result['winners']->contains('id', $candidate->id);
                            $share = round($candidate->votes_count / $fieldTotal * 100);
                        @endphp
                        @if ($wasAdvancing === true && ! $advances)
                            <li class="admin-quota" wire:key="admin-quota-{{ $result['field']->getKey() }}" aria-hidden="true">
                                <span>Batas lolos</span>
                            </li>
                        @endif
                        <li
                            class="admin-rank{{ $advances ? ' is-advancing' : '' }}"
                            wire:key="admin-candidate-{{ $candidate->id }}"
                            data-candidate-id="{{ $candidate->id }}"
                            data-rank="{{ $position + 1 }}"
                            data-votes="{{ $candidate->votes_count }}"
                        >
                            <span class="admin-rank-number">{{ $position + 1 }}</span>
                            <div class="admin-rank-body">
                                <div class="admin-rank-head">
                                    <span class="admin-rank-name">
                                        {{ $candidate->name }}
                                        <span class="admin-rank-move" data-rank-move wire:ignore></span>
                                        @if ($advances)
                                            <x-filament::badge color="success" size="sm">Lolos</x-filament::badge>
                                        @endif
                                    </span>
                                    <span class="admin-rank-votes"><strong data-count-to="{{ $candidate->votes_count }}">{{ $candidate->votes_count }}</strong> suara · {{ $share }}%</span>
                                </div>
                                <div class="admin-bar" role="img" aria-label="{{ $candidate->votes_count }} suara, {{ $share }} persen">
                                    <span style="width: {{ round($candidate->votes_count / $topVotes * 100) }}%"></span>
                                </div>
                            </div>
                        </li>
                        @php
                            $wasAdvancing = $advances;
                        @endphp
                    @endforeach
                </ol>

                @if ($timeline['series'] !== [])
                    <div class="admin-timeline">
                        <p class="admin-timeline-title">Pergerakan suara sejak {{ $timeline['startedAt']->timezone('Asia/Jakarta')->format('H:i') }} WIB</p>
                        <svg
                            viewBox="0 0 600 140"
                            preserveAspectRatio="none"
                            role="img"
                            aria-label="Pergerakan suara {{ $result['field']->name }}"
                            data-chart="series"
                            data-width="600"
                            data-started-at="{{ $timeline['startedAt']->toIso8601String() }}"
                            data-span="{{ $timeline['span'] }}"
                            data-series="{{ json_encode(array_map(fn (array $line): array => ['name' => $line['name'], 'color' => $line['color'], 'points' => $line['points']], $timeline['series'])) }}"
                        >
                            <line x1="0" x2="600" y1="35" y2="35" class="voter-trend-grid"/>
                            <line x1="0" x2="600" y1="70" y2="70" class="voter-trend-grid"/>
                            <line x1="0" x2="600" y1="105" y2="105" class="voter-trend-grid"/>
                            @foreach ($timeline['series'] as $seriesIndex => $line)
                                <polyline
                                    points="{{ $line['line'] }}"
                                    fill="none"
                                    stroke="{{ $line['color'] }}"
                                    stroke-width="2"
                                    stroke-linejoin="round"
                                    vector-effect="non-scaling-stroke"
                                   
                                    class="admin-timeline-line"
                                    data-series-index="{{ $seriesIndex }}"
                                />
                            @endforeach
                        </svg>
                        <ul class="admin-timeline-legend">
                            @foreach ($timeline['series'] as $seriesIndex => $line)
                                <li class="admin-timeline-legend-item" data-series-index="{{ $seriesIndex }}">
                                    <span class="admin-timeline-swatch" style="background: {{ $line['color'] }}" aria-hidden="true"></span>
                                    <span class="admin-timeline-name">{{ $line['name'] }}</span>
                                    <strong>{{ $line['votes'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
            </div>
        @endforeach

        <script type="application/json" id="admin-results-card">@json($cardData, JSON_HEX_TAG | JSON_HEX_AMP)</script>
    </div>

    @include('filament.pages.results-motion')
</x-filament-panels::page>
