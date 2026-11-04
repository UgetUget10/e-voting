<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berita acara hasil pemilihan</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 2rem; background: #f5f5f4; color: #1c1917; font-family: Figtree, ui-sans-serif, system-ui, sans-serif; font-size: 14px; line-height: 1.5; }
        main { max-width: 52rem; margin: 0 auto; padding: 2.5rem; background: #ffffff; border: 1px solid #e7e5e4; border-radius: 0.75rem; }
        h1 { margin: 0; font-size: 1.5rem; letter-spacing: -0.01em; }
        h2 { margin: 2rem 0 0.5rem; font-size: 1.05rem; }
        .meta { color: #6b6560; margin-top: 0.25rem; }
        .rule { height: 2px; background: #c2410c; margin: 1.25rem 0; border: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.45rem 0.6rem; border-bottom: 1px solid #e7e5e4; text-align: left; }
        th { font-size: 0.8rem; color: #6b6560; font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .winner { font-weight: 600; color: #9a3412; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-top: 1rem; }
        .summary div { border: 1px solid #e7e5e4; border-radius: 0.5rem; padding: 0.75rem; }
        .summary strong { display: block; font-size: 1.25rem; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 3rem; text-align: center; }
        .signatures div { padding-top: 4rem; border-top: 1px solid #1c1917; }
        .toolbar { max-width: 52rem; margin: 0 auto 1rem; display: flex; justify-content: flex-end; gap: 0.5rem; }
        .toolbar button { padding: 0.5rem 1rem; border: 1px solid #d6d3d1; border-radius: 0.5rem; background: #ffffff; cursor: pointer; font: inherit; }
        .toolbar button.primary { background: #c2410c; border-color: #c2410c; color: #ffffff; }
        @page { size: A4; margin: 14mm; }
        @media print {
            body { padding: 0; background: #ffffff; }
            main { border: 0; padding: 0; max-width: none; }
            .toolbar { display: none; }
            section { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Cetak atau simpan PDF</button>
    </div>

    <main>
        @if ($institutionName)
            <p class="meta">{{ $institutionName }}</p>
        @endif
        <h1>Berita acara hasil pemilihan</h1>
        <p class="meta">{{ $election?->name ?? 'Belum ada pemilihan' }} · dicetak {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
        <hr class="rule">

        <div class="summary">
            <div><span>Pemilih terdaftar</span><strong>{{ $voterCount }}</strong></div>
            <div><span>Suara masuk</span><strong>{{ $ballotCount }}</strong></div>
            <div><span>Partisipasi</span><strong>{{ $turnout }}%</strong></div>
        </div>

        @foreach ($results as $result)
            <section>
                <h2>{{ $result['field']->name }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kandidat</th>
                            <th class="num">Suara</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['candidates']->sortByDesc('votes_count')->values() as $index => $candidate)
                            @php $advances = $result['winners']->contains('id', $candidate->id); @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="{{ $advances ? 'winner' : '' }}">{{ $candidate->name }}</td>
                                <td class="num">{{ $candidate->votes_count }}</td>
                                <td>{{ $advances ? 'Lolos' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        <section>
            <h2>Partisipasi per bidang</h2>
            <table>
                <thead>
                    <tr>
                        <th>Bidang</th>
                        <th class="num">Memilih</th>
                        <th class="num">Pemilih terdaftar</th>
                        <th class="num">Porsi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fieldParticipation as $field)
                        <tr>
                            <td>{{ $field['name'] }}</td>
                            <td class="num">{{ $field['picks'] }}</td>
                            <td class="num">{{ $field['voters'] }}</td>
                            <td class="num">{{ $field['percent'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2>Pengiriman suara per jam (WIB)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th class="num">Suara</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hourlySubmissions as $slot)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($slot['day'])->locale('id')->translatedFormat('j M Y') }}</td>
                            <td>{{ sprintf('%02d.00 - %02d.59', $slot['hour'], $slot['hour']) }}</td>
                            <td class="num">{{ $slot['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Belum ada suara masuk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="signatures">
            <div>
                <strong>{{ $signerName ?: '......................................' }}</strong><br>
                {{ $signerTitle ?: 'Ketua panitia' }}
            </div>
            <div>Saksi</div>
        </div>
    </main>
</body>
</html>
