<?php

namespace App\Filament\Widgets;

use App\Models\Ballot;
use Carbon\CarbonInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestBallots extends TableWidget
{
    /**
     * Load these with the page instead of after it, so the dashboard does not flash empty boxes.
     */
    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Suara terbaru')
            ->description('Lima suara terakhir yang masuk, dikenali dari kode tanda terimanya. Daftar lengkap ada di menu Suara Masuk.')
            ->query(fn (): Builder => Ballot::query()
                ->when(auth()->user()?->isSuperAdmin(), fn (Builder $query) => $query->with('user'))
                ->withCount('votes')
                ->latest('created_at')
                ->limit(5))
            ->columns([
                ...$this->voterColumns(),
                TextColumn::make('receipt_code')
                    ->label('Kode tanda terima')
                    ->weight('semibold')
                    ->fontFamily('mono'),
                TextColumn::make('votes_count')
                    ->label('Bidang dipilih')
                    ->badge()
                    ->color('success')
                    ->suffix(' bidang'),
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'))
                    ->description(fn (Ballot $record): string => $record->created_at->locale('id')->diffForHumans()),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada suara masuk')
            ->emptyStateDescription('Suara akan muncul di sini begitu pemilih mengirim surat suaranya.');
    }

    /**
     * Super admins see the voter next to each ballot. Other admins see the receipt code only.
     *
     * @return list<TextColumn>
     */
    private function voterColumns(): array
    {
        if (! auth()->user()?->isSuperAdmin()) {
            return [];
        }

        return [
            TextColumn::make('user.name')
                ->label('Pemilih')
                ->weight('semibold'),
            TextColumn::make('user.email')
                ->label('Email')
                ->color('gray'),
        ];
    }
}
