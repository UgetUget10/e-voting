<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ringkasan harian</title>
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
        .figures { width: 100%; margin-top: 6px; }
        .figures td { border: 0; padding: 4px 8px 4px 0; }
        .figure-value { font-size: 15px; font-weight: bold; }
        .figure-label { color: #6b6560; font-size: 10px; }
        .warn { color: #9a3412; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Ringkasan harian</h1>
    <p class="meta">{{ $electionName }} · {{ $status }} · dicetak {{ $printedAt->locale('id')->translatedFormat('j F Y, H:i') }} WIB</p>
    <hr class="rule">

    <table class="figures">
        <tr>
            <td><span class="figure-value">{{ number_format($ballotsToday, 0, ',', '.') }}</span><br><span class="figure-label">suara masuk hari ini</span></td>
            <td><span class="figure-value">{{ number_format($submitted, 0, ',', '.') }}</span><br><span class="figure-label">total suara ({{ $turnout }}% dari {{ $voters }} pemilih)</span></td>
            <td><span class="figure-value">{{ number_format($pending, 0, ',', '.') }}</span><br><span class="figure-label">belum memilih</span></td>
            <td><span class="figure-value">{{ $problems }}</span><br><span class="figure-label">suara bermasalah</span></td>
        </tr>
    </table>

    <p>
        @if ($peakHour)
            Puncak pengiriman pukul <strong>{{ $peakHour }} WIB</strong> dengan {{ $peakCount }} suara.
        @else
            Belum ada suara masuk hari ini.
        @endif
        @if ($spikes !== [])
            <span class="warn">Lonjakan suara terdeteksi pada pukul {{ implode(', ', $spikes) }} WIB.</span>
        @endif
    </p>

    <h2>Suara masuk per jam (WIB)</h2>
    @if ($hourly === [])
        <p class="meta">Belum ada suara masuk hari ini.</p>
    @else
        <table>
            <thead><tr><th>Jam</th><th class="num">Suara</th></tr></thead>
            <tbody>
                @foreach ($hourly as $row)
                    <tr><td>{{ $row['hour'] }}</td><td class="num">{{ $row['total'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Perolehan sementara per bidang</h2>
    @foreach ($fields as $field)
        <p class="meta"><strong>{{ $field['name'] }}</strong> · {{ $field['total'] }} suara sah</p>
        <table>
            <thead><tr><th>Kandidat</th><th class="num">Suara</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($field['candidates'] as $candidate)
                    <tr>
                        <td class="{{ $candidate['advances'] ? 'leading' : '' }}">{{ $candidate['name'] }}</td>
                        <td class="num">{{ $candidate['votes'] }}</td>
                        <td>{{ $candidate['advances'] ? 'Lolos' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="meta">Ringkasan ini tidak memuat kode tanda terima maupun identitas pemilih.</p>
</body>
</html>
