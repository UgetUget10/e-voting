<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu pemilih</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; color: #1c1917; }
        .bar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; }
        .bar p { margin: 0; color: #6b6560; font-size: 14px; }
        button { padding: 8px 16px; border: 0; border-radius: 6px; background: #c2410c; color: #fff; font-weight: 600; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
        .card { border: 1px dashed #a8a29e; border-radius: 8px; padding: 14px; break-inside: avoid; }
        .card h2 { margin: 0 0 8px; font-size: 16px; }
        .card dt { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b6560; }
        .card dd { margin: 0 0 8px; font-size: 14px; }
        .secret { font-family: ui-monospace, monospace; font-size: 18px; font-weight: 700; }
        @media print { .bar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Cetak kartu</button>
        <p>Password hanya tampil sekali. Muat ulang halaman ini tidak akan menampilkannya lagi.</p>
    </div>
    <div class="grid">
        @foreach ($cards as $card)
            <div class="card">
                <h2>{{ $card['name'] }}</h2>
                <dl><dt>Username</dt><dd>{{ $card['username'] }}</dd></dl>
                @if ($card['email'] !== null)
                    <dl><dt>Email</dt><dd>{{ $card['email'] }}</dd></dl>
                @endif
                <dl><dt>Password sementara</dt><dd class="secret">{{ $card['password'] }}</dd></dl>
            </div>
        @endforeach
    </div>
</body>
</html>
