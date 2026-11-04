<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar kandidat</title>
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
        .note { font-size: 9px; color: #6b6560; }
    </style>
</head>
<body>
    <h1>Daftar kandidat</h1>
    <p class="meta">{{ $election?->name ?? 'Belum ada pemilihan' }} · {{ $candidates->count() }} kandidat · dibuat {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
    <div class="rule"></div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama kandidat</th>
                <th>NIP</th>
                <th>Golongan</th>
                <th>Foto</th>
                <th>Bidang terpilih</th>
                <th class="num">Total suara</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($candidates as $index => $candidate)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $candidate->name }}</td>
                    <td>{{ $candidate->nip ?: '—' }}</td>
                    <td>{{ $candidate->golongan ?: '—' }}</td>
                    <td>{{ filled($candidate->photo_path) ? 'Ada' : 'Belum' }}</td>
                    <td>
                        {{ $candidate->fields->pluck('name')->implode(', ') ?: 'Belum dipilih' }}
                    </td>
                    <td class="num">
                        {{ $candidate->vote_total }}
                        @if ($candidate->correction_total)
                            <div class="note">termasuk koreksi {{ $candidate->correction_total > 0 ? '+' : '' }}{{ $candidate->correction_total }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
