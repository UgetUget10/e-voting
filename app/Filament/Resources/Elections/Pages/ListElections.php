<?php

namespace App\Filament\Resources\Elections\Pages;

use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Voter\Pages\Vote;
use App\Filament\Widgets\ElectionStats;
use App\Filament\Widgets\ElectionTimeline;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

class ListElections extends ListRecords
{
    protected static string $resource = ElectionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ElectionStats::class,
            ElectionTimeline::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyVoterLink')
                ->label('Salin tautan pemilih')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->alpineClickHandler(fn (): string => 'window.navigator.clipboard.writeText('.Js::from(Vote::getUrl(panel: 'pemilih'))->toHtml().'); new FilamentNotification().title(\'Tautan pemilih disalin.\').success().send()'),
            CreateAction::make(),
        ];
    }
}
