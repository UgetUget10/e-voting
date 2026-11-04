<x-filament-panels::page>
    @php
        $turnout = $voterCount > 0 ? min(100, round($ballotCount / $voterCount * 100)) : 0;
        $gaugeCircumference = 326.7;
        $gaugeOffset = round($gaugeCircumference * (1 - $turnout / 100), 1);
        $votedPercent = $voterCount > 0 ? min(100, round($ballotCount / $voterCount * 100)) : 0;
        $notVotedPercent = $voterCount > 0 ? 100 - $votedPercent : 0;
    @endphp

    <div @if ($isLive) wire:poll.visible.5s @endif class="results-page">
        <header class="results-hero">
            <div class="results-hero-art" aria-hidden="true">
                <span class="results-hero-slip is-one"></span>
                <span class="results-hero-slip is-two"></span>
                <span class="results-hero-slip is-three"></span>
                <span class="results-hero-spark is-one">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.6 6.2 1.8 10.4 12 12-10.2 1.6-11.4 5.8-12 12-.6-6.2-1.8-10.4-12-12C10.2 10.4 11.4 6.2 12 0Z"/></svg>
                </span>
                <span class="results-hero-spark is-two">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.6 6.2 1.8 10.4 12 12-10.2 1.6-11.4 5.8-12 12-.6-6.2-1.8-10.4-12-12C10.2 10.4 11.4 6.2 12 0Z"/></svg>
                </span>
            </div>

            <div class="results-hero-copy">
                <div class="results-hero-top">
                    <span class="results-status{{ $resultsVisible ? ' is-open' : '' }}">
                        <span class="results-status-dot" aria-hidden="true"></span>
                        {{ $resultsVisible ? 'Hasil terbuka' : 'Hasil belum dibuka' }}
                    </span>

                    @if ($isLive)
                        <span class="results-live" aria-live="off">
                            <span class="results-live-dot" aria-hidden="true"></span>
                            Live · {{ $updatedAt->timezone('Asia/Jakarta')->format('H:i:s') }} WIB
                        </span>
                    @endif
                </div>

                <h2 class="results-hero-title">{{ $election?->name ?? 'Hasil Voting' }}</h2>
                <p class="results-hero-text">
                    @if ($isAdmin)
                        Akun admin memantau hasil dan tidak ikut memberikan suara.
                    @elseif ($hasVoted)
                        Anda sudah memberikan suara. Terima kasih sudah ikut memilih.
                    @else
                        Anda belum memberikan suara.
                        <a href="{{ \App\Filament\Voter\Pages\Vote::getUrl(panel: 'pemilih') }}" class="results-hero-link">Pilih sekarang</a>.
                    @endif
                </p>
            </div>

            <div class="results-gauge" role="progressbar" aria-label="Partisipasi pemilih" aria-valuemin="0" aria-valuemax="{{ $voterCount }}" aria-valuenow="{{ $ballotCount }}">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle class="results-gauge-track" cx="60" cy="60" r="52"/>
                    <circle class="results-gauge-fill" cx="60" cy="60" r="52" style="--gauge-offset: {{ $gaugeOffset }}"/>
                </svg>
                <span class="results-gauge-value">
                    <strong>{{ $turnout }}%</strong>
                    <span>partisipasi</span>
                </span>
                <span class="results-gauge-meta">{{ $ballotCount }} dari {{ $voterCount }} pemilih</span>
                @unless ($isLive)
                    <span class="results-stamp" aria-hidden="true">Sah</span>
                @endunless
            </div>
        </header>

        <div class="results-stats" role="list" aria-label="Ringkasan pemilih">
            <div class="results-stat" role="listitem">
                <span class="results-stat-label">Pemilih terdaftar</span>
                <span class="results-stat-value">{{ $voterCount }}</span>
            </div>
            <div class="results-stat is-voted" role="listitem">
                <span class="results-stat-label">Sudah memilih</span>
                <span class="results-stat-value">{{ $ballotCount }}</span>
                <span class="results-stat-meta">{{ $votedPercent }}% dari pemilih</span>
            </div>
            <div class="results-stat is-pending" role="listitem">
                <span class="results-stat-label">Belum memilih</span>
                <span class="results-stat-value">{{ $notVotedCount }}</span>
                <span class="results-stat-meta">{{ $notVotedPercent }}% dari pemilih</span>
            </div>
        </div>

        <section class="results-panel results-trend" aria-labelledby="results-trend-title">
            <div class="results-panel-head">
                <div>
                    <h3 id="results-trend-title">Tren partisipasi</h3>
                    <p>
                        @if ($trend['startedAt'])
                            Suara pertama masuk pukul {{ $trend['startedAt']->timezone('Asia/Jakarta')->format('H:i') }} WIB
                        @else
                            Belum ada suara masuk
                        @endif
                    </p>
                </div>
                <span class="results-trend-figure">{{ $trend['percent'] }}%</span>
            </div>

            @if ($trend['total'] > 0)
                <svg class="results-trend-chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="Partisipasi mencapai {{ $trend['percent'] }} persen dari pemilih terdaftar">
                    <defs>
                        <linearGradient id="results-trend-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#f97316" stop-opacity="0.5"/>
                            <stop offset="100%" stop-color="#f97316" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <line x1="0" x2="600" y1="30" y2="30" class="results-trend-grid"/>
                    <line x1="0" x2="600" y1="60" y2="60" class="results-trend-grid"/>
                    <line x1="0" x2="600" y1="90" y2="90" class="results-trend-grid"/>
                    <polygon class="results-trend-area" points="{{ $trend['area'] }}" fill="url(#results-trend-fill)"/>
                    <polyline class="results-trend-line" pathLength="1" points="{{ $trend['line'] }}"/>
                </svg>
                <div class="results-trend-scale" aria-hidden="true">
                    <span>0%</span>
                    <span>{{ $voterCount }} pemilih terdaftar</span>
                    <span>100%</span>
                </div>
            @else
                <p class="results-trend-empty">Grafik akan muncul setelah suara pertama masuk.</p>
            @endif
        </section>

        @if ($resultsVisible)
            <div class="results-ballots">
                @foreach ($results as $result)
                    @php
                        $fieldTotal = max(1, $result['candidates']->sum('votes_count'));
                        $topVotes = max(1, $result['candidates']->max('votes_count'));
                        $rankedCandidates = $result['candidates']->sortByDesc('votes_count')->values();
                    @endphp
                    <section class="results-ballot" wire:key="field-{{ $result['field']->id }}">
                        <header class="results-ballot-head">
                            <span class="results-ballot-no">Surat suara {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $result['field']->name }}</h3>
                            <span class="results-ballot-meta">
                                {{ $result['winners']->count() }} kandidat lolos · {{ $fieldTotal }} suara
                            </span>
                        </header>

                        <ol class="results-ranking">
                            @foreach ($rankedCandidates as $index => $candidate)
                                @php
                                    $advances = $result['winners']->contains('id', $candidate->id);
                                    $share = round($candidate->votes_count / $fieldTotal * 100);
                                    $barWidth = round($candidate->votes_count / $topVotes * 100);
                                @endphp
                                <li class="results-rank{{ $advances ? ' is-advancing' : '' }}" style="--delay: {{ $index * 80 }}ms" wire:key="candidate-{{ $candidate->id }}">
                                    <span class="results-rank-number" aria-hidden="true">{{ $index + 1 }}</span>

                                    @if ($advances && $index === 0)
                                        <span class="results-crown" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8Z"/></svg>
                                        </span>
                                    @elseif ($advances)
                                        <span class="results-spark" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.6 6.2 1.8 10.4 12 12-10.2 1.6-11.4 5.8-12 12-.6-6.2-1.8-10.4-12-12C10.2 10.4 11.4 6.2 12 0Z"/></svg>
                                        </span>
                                    @endif

                                    @if ($candidate->photoUrl())
                                        <img src="{{ $candidate->photoUrl() }}" alt="" class="results-rank-avatar" loading="lazy">
                                    @else
                                        <span class="results-rank-avatar is-initial" aria-hidden="true">{{ mb_substr($candidate->name, 0, 1) }}</span>
                                    @endif

                                    <div class="results-rank-body">
                                        <div class="results-rank-head">
                                            <span class="results-rank-name">
                                                {{ $candidate->name }}
                                                @if ($advances)
                                                    <span class="results-stamp-chip">Lolos</span>
                                                @endif
                                            </span>
                                            <span class="results-rank-votes">
                                                <strong>{{ $candidate->votes_count }}</strong> suara · {{ $share }}%
                                            </span>
                                        </div>
                                        <div class="results-rank-track" role="img" aria-label="{{ $candidate->votes_count }} suara, {{ $share }} persen">
                                            <div class="results-rank-fill" style="--bar: {{ $barWidth }}%"></div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            </div>

            <section class="results-panel results-recap" aria-labelledby="results-recap-title">
                <div class="results-panel-head">
                    <div>
                        <h3 id="results-recap-title">Rekapitulasi perolehan suara</h3>
                        <p>{{ $ballotCount }} suara masuk · diperbarui {{ $updatedAt->timezone('Asia/Jakarta')->format('H:i:s') }} WIB</p>
                    </div>
                </div>

                @foreach ($results as $result)
                    @php
                        $recapTotal = max(1, $result['candidates']->sum('votes_count'));
                        $recapRanking = $result['candidates']->sortByDesc('votes_count')->values();
                    @endphp
                    <table class="results-table">
                        <caption>{{ $result['field']->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">No</th>
                                <th scope="col">Nama kandidat</th>
                                <th scope="col" class="is-num">Suara</th>
                                <th scope="col" class="is-num">Persen</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recapRanking as $index => $candidate)
                                <tr class="{{ $result['winners']->contains('id', $candidate->id) ? 'is-advancing' : '' }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $candidate->name }}</td>
                                    <td class="is-num">{{ $candidate->votes_count }}</td>
                                    <td class="is-num">{{ round($candidate->votes_count / $recapTotal * 100) }}%</td>
                                    <td>{{ $result['winners']->contains('id', $candidate->id) ? 'Lolos' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row" colspan="2">Total suara sah</th>
                                <td class="is-num">{{ $result['candidates']->sum('votes_count') }}</td>
                                <td class="is-num">{{ $result['candidates']->sum('votes_count') > 0 ? '100%' : '0%' }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @endforeach
            </section>
        @else
            <div class="results-locked">
                <div class="results-locked-art" aria-hidden="true">
                    <span class="results-locked-ring"></span>
                    <span class="results-locked-ring is-delayed"></span>
                    <span class="results-locked-slip is-one"></span>
                    <span class="results-locked-slip is-two"></span>
                    <span class="results-locked-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                </div>
                <p class="results-locked-title">Hasil akan ditampilkan setelah voting ditutup</p>
                @if ($election?->closes_at)
                    <p class="results-locked-text">
                        Ditutup {{ $election->closes_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }} WIB.
                    </p>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
