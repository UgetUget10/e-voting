<div class="admin-snapshot">
    @php $snapshot = $record->results_snapshot ?? []; @endphp
    <p class="admin-diff-desc">
        {{ $snapshot['voters'] ?? 0 }} pemilih terdaftar · {{ $snapshot['ballots'] ?? 0 }} suara masuk ·
        partisipasi {{ $snapshot['turnout'] ?? 0 }}%
    </p>
    @foreach ($snapshot['fields'] ?? [] as $field)
        <h4 class="admin-snapshot-field">{{ $field['name'] }}</h4>
        <table class="admin-diff-table">
            <thead><tr><th>Kandidat</th><th>Suara</th><th></th></tr></thead>
            <tbody>
                @foreach ($field['candidates'] as $candidate)
                    <tr>
                        <td>{{ $candidate['name'] }}</td>
                        <td>{{ $candidate['votes'] }}</td>
                        <td>{{ $candidate['advances'] ? 'Lolos' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</div>
