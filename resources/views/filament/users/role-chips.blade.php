@php
    $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');
@endphp
<span class="admin-role-chips">
    @forelse ($record->roles as $role)
        <span class="admin-role-chip{{ $role->name === $superAdminRole ? ' is-super' : '' }}">{{ str($role->name)->headline() }}</span>
    @empty
        <span class="admin-role-empty">Tanpa peran</span>
    @endforelse
</span>
