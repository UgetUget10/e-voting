<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Widgets\Widget;

class NotVotedVoters extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.not-voted-voters';

    public static function canView(): bool
    {
        return UserResource::canViewAny();
    }

    /**
     * @return array{waiting: int, voters: int, sample: list<string>, url: string}
     */
    protected function getViewData(): array
    {
        $waiting = User::query()->where('is_admin', false)->whereDoesntHave('ballot');

        return [
            'waiting' => (clone $waiting)->count(),
            'voters' => User::query()->where('is_admin', false)->count(),
            'sample' => (clone $waiting)->orderBy('name')->limit(5)->pluck('name')->all(),
            'url' => UserResource::getUrl('index', ['tableFilters' => ['voted' => ['value' => '0']]]),
        ];
    }
}
