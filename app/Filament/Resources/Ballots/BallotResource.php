<?php

namespace App\Filament\Resources\Ballots;

use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Resources\Ballots\Tables\BallotsTable;
use App\Models\Ballot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class BallotResource extends Resource
{
    protected static ?string $model = Ballot::class;

    protected static ?string $navigationLabel = 'Suara Masuk';

    protected static string|UnitEnum|null $navigationGroup = 'Pemilihan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Suara';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /**
     * Ballots are final once submitted, so the admin panel only reads them.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /**
     * The count is cached briefly, since the navigation renders on every page of the panel.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('ballots.navigation-badge', 10, fn (): int => Ballot::query()->count());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function table(Table $table): Table
    {
        return BallotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBallots::route('/'),
        ];
    }
}
