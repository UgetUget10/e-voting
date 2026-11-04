<?php

namespace App\Filament\Widgets;

use App\Models\AdminActivity;
use Carbon\CarbonInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AdminActivityFeed extends TableWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Riwayat aktivitas admin')
            ->description('Tindakan penting yang dilakukan admin, terbaru di atas.')
            ->query(fn (): Builder => AdminActivity::query()
                ->with('user')
                ->latest('created_at')
                ->limit(15))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'))
                    ->description(fn (AdminActivity $record): string => $record->created_at->locale('id')->diffForHumans())
                    ->color('gray'),
                TextColumn::make('user.name')
                    ->label('Admin')
                    ->weight('semibold')
                    ->placeholder('Sistem'),
                TextColumn::make('description')
                    ->label('Tindakan')
                    ->wrap(),
            ])
            ->recordClasses(fn (AdminActivity $record): ?string => $record->created_at->gt(now()->subSeconds(45)) ? 'admin-row-enter' : null)
            ->poll('20s')
            ->paginated(false)
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateDescription('Tindakan admin akan tercatat di sini.');
    }
}
