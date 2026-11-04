<?php

namespace App\Filament\Widgets;

use App\Models\Permission;
use App\Models\Role;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoleStats extends StatsOverviewWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected function getStats(): array
    {
        $roleCount = Role::count();
        $usedRoleCount = Role::query()->whereHas('users')->count();

        return [
            Stat::make('Jumlah peran', $roleCount)
                ->description(Permission::count().' izin tersedia')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('primary'),
            Stat::make('Peran dipakai', $usedRoleCount)
                ->description($roleCount - $usedRoleCount > 0
                    ? ($roleCount - $usedRoleCount).' peran belum punya pengguna'
                    : 'Semua peran sudah dipakai')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('success'),
        ];
    }
}
