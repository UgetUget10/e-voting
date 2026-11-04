<?php

namespace App\Filament\Resources\AdminActivities;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Fields\FieldResource;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Field;
use App\Services\AdminActivityClassifier;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class AdminActivityResource extends Resource
{
    protected static ?string $model = AdminActivity::class;

    protected static ?string $navigationLabel = 'Log aktivitas';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Aktivitas';

    /**
     * Entries can name voter emails, which only super admins may see, so the full log is theirs too.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AdminActivity::query()->with(['user', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->defaultGroup(
                Group::make('day')
                    ->label('Hari')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (AdminActivity $record): string => self::dayTitle($record->day)),
                'desc',
            )
            ->recordClasses(fn (AdminActivity $record, $livewire): string => collect([
                'admin-activity-row',
                in_array($record->getKey(), $livewire->freshIds ?? [], true) ? 'admin-row-enter' : null,
                $record->sensitive ? 'is-sensitive' : null,
            ])->filter()->implode(' '))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->sortable()
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::timeLabel($state))
                    ->color('gray')
                    ->extraAttributes(['class' => 'admin-activity-time']),
                ViewColumn::make('user.name')
                    ->label('Admin')
                    ->view('filament.activities.admin')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))),
                IconColumn::make('sensitive')
                    ->label('Penting')
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)
                    ->falseIcon(false)
                    ->trueColor('danger')
                    ->tooltip(fn (bool $state): ?string => $state ? 'Tindakan penting, perlu ditinjau' : null),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminActivityClassifier::categoryLabel($state))
                    ->color(fn (string $state): string => AdminActivityClassifier::categoryColor($state))
                    ->icon(fn (string $state): Heroicon => self::categoryIcon($state)),
                TextColumn::make('description')
                    ->label('Tindakan')
                    ->wrap()
                    ->searchable()
                    ->description(fn (AdminActivity $record): ?string => AdminActivityClassifier::subjectLabel($record->subject_type))
                    ->url(fn (AdminActivity $record): ?string => self::subjectUrl($record))
                    ->tooltip(fn (AdminActivity $record): ?string => self::subjectUrl($record) ? 'Buka data yang dimaksud' : null),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->placeholder('Semua kategori')
                    ->options(AdminActivityClassifier::categoryLabels())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $category): Builder => $query->where('category', $category),
                    )),
                TernaryFilter::make('sensitive')
                    ->label('Penting')
                    ->placeholder('Semua tindakan')
                    ->trueLabel('Penting saja')
                    ->falseLabel('Tidak penting')
                    ->queries(
                        true: fn (Builder $query) => $query->where('sensitive', true),
                        false: fn (Builder $query) => $query->where('sensitive', false),
                    ),
                SelectFilter::make('subject_type')
                    ->label('Jenis data')
                    ->placeholder('Semua jenis data')
                    ->options(AdminActivityClassifier::subjectLabels())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $type): Builder => $query->where('subject_type', $type),
                    )),
                SelectFilter::make('user_id')
                    ->label('Admin')
                    ->relationship('user', 'name'),
                Filter::make('created_between')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label('Perubahan')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->visible(fn (AdminActivity $record): bool => ! empty($record->changes))
                    ->modalHeading('Perubahan data')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (AdminActivity $record) => view('filament.activities.diff', ['record' => $record])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateDescription('Tindakan admin akan tercatat di sini.');
    }

    /**
     * The page of the record an entry is about, or null when there is none or it no longer exists.
     */
    public static function subjectUrl(AdminActivity $activity): ?string
    {
        $subject = $activity->subject;

        return match (true) {
            $subject instanceof Candidate => CandidateResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Field => FieldResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Ballot => BallotResource::getUrl('index', ['kode' => $subject->receipt_code]),
            default => null,
        };
    }

    public static function categoryIcon(string $category): Heroicon
    {
        return match ($category) {
            AdminActivityClassifier::TAMBAH => Heroicon::OutlinedPlusCircle,
            AdminActivityClassifier::UBAH => Heroicon::OutlinedPencilSquare,
            AdminActivityClassifier::HAPUS => Heroicon::OutlinedTrash,
            AdminActivityClassifier::EKSPOR => Heroicon::OutlinedArrowDownTray,
            AdminActivityClassifier::KEAMANAN => Heroicon::OutlinedShieldCheck,
            default => Heroicon::OutlinedCog6Tooth,
        };
    }

    /**
     * Time of day and how long ago, on one line. The day itself is the group heading.
     */
    private static function timeLabel(?CarbonInterface $state): ?string
    {
        if (! $state) {
            return null;
        }

        $local = $state->timezone('Asia/Jakarta');

        return $local->format('H:i').' · '.$state->locale('id')->diffForHumans();
    }

    /**
     * "Hari ini", "Kemarin", or the full date, for a day stored in Jakarta time.
     */
    private static function dayTitle(?string $day): string
    {
        $today = now('Asia/Jakarta')->toDateString();

        return match ($day) {
            $today => 'Hari ini',
            now('Asia/Jakarta')->subDay()->toDateString() => 'Kemarin',
            null => 'Tanpa tanggal',
            default => Carbon::parse($day)->locale('id')->translatedFormat('l, j F Y'),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminActivities::route('/'),
        ];
    }
}
