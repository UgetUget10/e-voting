@php
    use App\Filament\Resources\AdminActivities\AdminActivityResource;
    use App\Services\AdminActivityClassifier;

    $url = AdminActivityResource::subjectUrl($record);
    $subjectLabel = AdminActivityClassifier::subjectLabel($record->subject_type);
@endphp

<div class="admin-diff">
    <div class="admin-diff-head">
        <p class="admin-diff-desc">{{ $record->description }}</p>

        @if ($url)
            <a href="{{ $url }}" class="admin-diff-link">Buka {{ $subjectLabel ?? 'data' }}</a>
        @endif
    </div>

    <table class="admin-diff-table">
        <thead>
            <tr><th>Kolom</th><th>Sebelum</th><th>Sesudah</th></tr>
        </thead>
        <tbody>
            @foreach ($record->changes ?? [] as $field => $change)
                <tr style="--stagger: {{ $loop->index }}">
                    <th scope="row">{{ AdminActivityClassifier::fieldLabel($field) }}</th>
                    <td class="is-old">{{ AdminActivityClassifier::formatChangeValue($field, $change['old'] ?? null) }}</td>
                    <td class="is-new">{{ AdminActivityClassifier::formatChangeValue($field, $change['new'] ?? null) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
