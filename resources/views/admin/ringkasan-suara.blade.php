<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ringkasan suara</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1917; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        .meta { color: #6b6560; margin-top: 3px; }
        .rule { border: 0; border-top: 2px solid #c2410c; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #e7e5e4; text-align: left; }
        th { color: #6b6560; font-weight: bold; font-size: 10px; }
        td.num, th.num { text-align: right; }
        .leading { font-weight: bold; color: #9a3412; }
        .total { font-size: 13px; font-weight: bold; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Ringkasan suara sesuai filter</h1>
    <p class="meta">{{ $election?->name ?? 'Belum ada pemilihan' }} · dicetak {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
    <hr class="rule">

    <p class="total">{{ $summary['ballots'] }} suara masuk dalam ringkasan ini</p>
    <p class="meta">Ringkasan ini tidak memuat kode tanda terima maupun identitas pemilih.</p>

    @foreach ($summary['fields'] as $field)
        <h2>{{ $field['name'] }} <span class="meta">· {{ $field['picks'] }} pilihan</span></h2>
        <table>
            <thead>
                <tr>
                    <th>Kandidat</th>
                    <th class="num">Suara</th>
                    <th class="num">Porsi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($field['candidates'] as $index => $candidate)
                    <tr>
                        <td class="{{ $index === 0 && $candidate['votes'] > 0 ? 'leading' : '' }}">{{ $candidate['name'] }}</td>
                        <td class="num">{{ $candidate['votes'] }}</td>
                        <td class="num">{{ $candidate['share'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Belum ada kandidat.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
