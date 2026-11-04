<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                UserActions::changeEmail(),
                UserActions::resetPassword(),
                UserActions::printCard(),
                UserActions::toggleSuspension(),
            ])
                ->label('Lainnya')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray'),
        ];
    }
}
