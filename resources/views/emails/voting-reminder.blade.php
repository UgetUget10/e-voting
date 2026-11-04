<x-mail::message>
# Halo, {{ $voter->name }}

Anda belum mengirim suara untuk **{{ $election?->name ?? 'pemilihan' }}**.

@if ($election?->closes_at)
Voting ditutup pada {{ $election->closes_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') }} WIB.
@endif

<x-mail::button :url="$url">
Pilih sekarang
</x-mail::button>

Terima kasih atas partisipasi Anda.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
