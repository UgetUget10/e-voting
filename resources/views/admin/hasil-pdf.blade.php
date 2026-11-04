<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil pemilihan</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1917; }
        h1 { margin: 0; font-size: 20px; color: #9a3412; }
        h2 { margin: 22px 0 6px; font-size: 13px; }
        .meta { color: #6b6560; margin: 4px 0 0; }
        .rule { border-top: 2px solid #c2410c; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 7px; border-bottom: 1px solid #e7e5e4; text-align: left; }
        th { background: #fff7ed; font-size: 10px; color: #6b6560; }
        .num { text-align: right; }
        .winner { font-weight: bold; color: #9a3412; }
        .summary td { border: 1px solid #e7e5e4; padding: 8px; width: 33%; }
        .summary strong { display: block; font-size: 16px; }
        .signatures { margin-top: 48px; width: 100%; }
        .signatures td { width: 50%; text-align: center; border: 0; padding-top: 56px; }
        .signatures span { display: inline-block; width: 60%; border-top: 1px solid #1c1917; padding-top: 4px; }
        section { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Hasil pemilihan</h1>
    <p class="meta">{{ $election?->name ?? 'Belum ada pemilihan' }} · dibuat {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
    <div class="rule"></div>

    <table class="summary">
        <tr>
            <td>Pemilih terdaftar<strong>{{ $voterCount }}</strong></td>
            <td>Suara masuk<strong>{{ $ballotCount }}</strong></td>
            <td>Partisipasi<strong>{{ $turnout }}%</strong></td>
        </tr>
    </table>

    @foreach ($results as $result)
        <section>
            <h2>{{ $result['field']->name }}</h2>
            <table>
                <thead>
                    <tr><th>No</th><th>Kandidat</th><th class="num">Suara</th><th>Keterangan</th></tr>
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

    <table class="signatures">
        <tr><td><span>Ketua panitia</span></td><td><span>Saksi</span></td></tr>
    </table>
</body>
</html>
