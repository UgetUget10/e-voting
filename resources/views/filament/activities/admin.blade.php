@php
    $name = $record->user?->name;
    $initials = \App\Services\AdminActivityClassifier::initials($name ?? 'Sistem');
@endphp

@if ($record->user_id && $name)
    <a href="{{ \App\Filament\Resources\AdminActivities\AdminActivityResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $record->user_id]]]) }}"
       class="admin-activity-admin"
       title="Tampilkan tindakan {{ $name }} saja">
        <span class="admin-activity-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="admin-activity-admin-name">{{ $name }}</span>
    </a>
@else
    <span class="admin-activity-admin is-system">
        <span class="admin-activity-avatar" aria-hidden="true">{{ $initials }}</span>
        <span class="admin-activity-admin-name">Sistem</span>
    </span>
@endif
