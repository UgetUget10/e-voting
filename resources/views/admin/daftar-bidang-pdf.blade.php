<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar bidang</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1917; }
        h1 { margin: 0; font-size: 20px; color: #9a3412; }
        .meta { color: #6b6560; margin: 4px 0 0; }
        .rule { border-top: 2px solid #c2410c; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 7px; border-bottom: 1px solid #e7e5e4; text-align: left; vertical-align: top; }
        th { background: #fff7ed; font-size: 10px; color: #6b6560; }
        .num { text-align: right; }
        .muted { color: #6b6560; }
        .winner { font-weight: bold; color: #9a3412; }
        section { page-break-inside: avoid; }
        h2 { margin: 18px 0 4px; font-size: 13px; }
    </style>
</head>
<body>
    <h1>Daftar bidang</h1>
    <p class="meta">{{ $election?->name ?? 'Belum ada pemilihan' }} · {{ $standings->count() }} bidang · dibuat {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
    <div class="rule"></div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Bidang</th>
                <th>Hasil</th>
                <th>Pemimpin sementara</th>
                <th class="num">Suara pemimpin</th>
                <th class="num">Total suara</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($standings as $index => $standing)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $standing['field']->name }}</td>
                    <td>{{ \App\Services\FieldStandings::label($standing['status']) }}</td>
                    <td>{{ $standing['leader']?->name ?? '—' }}</td>
                    <td class="num">{{ $standing['leader']?->votes_count ?? 0 }}</td>
                    <td class="num">{{ $standing['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($standings as $standing)
        @if ($standing['ranked']->isNotEmpty())
            <section>
                <h2>{{ $standing['field']->name }}</h2>
                <table>
                    <thead>
                        <tr><th>No</th><th>Kandidat</th><th class="num">Suara</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($standing['ranked'] as $index => $candidate)
                            @php($advances = $standing['winners']->contains('id', $candidate->id))
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
        @endif
    @endforeach
</body>
</html>
