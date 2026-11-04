<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use BezhanSalleh\PluginEssentials\Concerns\Resource as Essentials;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Override;

class RoleResource extends Resource
{
    use Essentials\BelongsToParent;
    use Essentials\BelongsToTenant;
    use Essentials\HasGlobalSearch;
    use Essentials\HasLabels;
    use Essentials\HasNavigation;
    use HasShieldFormComponents;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Sistem';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->helperText(fn (?Model $record): ?string => $record?->name === self::superAdminName()
                                        ? 'Nama super admin dikunci karena dipakai untuk akses penuh.'
                                        : null)
                                    ->disabled(fn (?Model $record): bool => $record?->name === self::superAdminName())
                                    ->unique(
                                        ignoreRecord: true, /** @phpstan-ignore-next-line */
                                        modifyRuleUsing: fn (Unique $rule): Unique => Utils::isTenancyEnabled() ? $rule->where(Utils::getTenantModelForeignKey(), Filament::getTenant()?->id) : $rule
                                    )
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default(Utils::getFilamentAuthGuard())
                                    ->nullable()
                                    ->maxLength(255),

                                Select::make(config('permission.column_names.team_foreign_key'))
                                    ->label(__('filament-shield::filament-shield.field.team'))
                                    ->placeholder(__('filament-shield::filament-shield.field.team.placeholder'))
                                    /** @phpstan-ignore-next-line */
                                    ->default(Filament::getTenant()?->id)
                                    ->options(fn (): array => in_array(Utils::getTenantModel(), [null, '', '0'], true) ? [] : Utils::getTenantModel()::pluck('name', 'id')->toArray())
                                    ->visible(fn (): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled())
                                    ->dehydrated(fn (): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                                static::getSelectAllFormComponent(),

                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                static::getShieldFormComponents(),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->description(fn (Model $record): ?string => $record->name === self::superAdminName() ? 'Akses penuh, tidak bisa dihapus' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color('gray')
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                TextColumn::make('team.name')
                    ->default('Global')
                    ->badge()
                    ->color(fn (mixed $state): string => str($state)->contains('Global') ? 'gray' : 'primary')
                    ->label(__('filament-shield::filament-shield.column.team'))
                    ->searchable()
                    ->visible(fn (): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                TextColumn::make('users_count')
                    ->label('Pengguna')
                    ->counts('users')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i'))
                    ->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('used')
                    ->label('Dipakai pengguna')
                    ->placeholder('Semua peran')
                    ->trueLabel('Sudah dipakai')
                    ->falseLabel('Belum dipakai')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('users'),
                        false: fn (Builder $query) => $query->whereDoesntHave('users'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Duplikat')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->visible(fn (Model $record): bool => $record->name !== self::superAdminName())
                    ->requiresConfirmation()
                    ->modalHeading('Duplikat peran ini?')
                    ->modalDescription('Peran baru memakai izin yang sama dan belum punya pengguna.')
                    ->action(function (Model $record): void {
                        $copy = Utils::getRoleModel()::create([
                            'name' => self::uniqueCopyName($record->name),
                            'guard_name' => $record->guard_name,
                        ]);
                        $copy->syncPermissions($record->permissions);

                        Notification::make()->title('Peran diduplikat: '.Str::headline($copy->name))->success()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => $record->name !== self::superAdminName())
                    ->modalDescription(fn (Model $record): string => $record->users()->exists()
                        ? 'Peran ini masih dipakai pengguna. Pengguna tersebut kehilangan izin dari peran ini.'
                        : 'Peran ini belum dipakai pengguna, sehingga aman dihapus.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            if ($records->contains(fn (Model $role): bool => $role->name === self::superAdminName())) {
                                Notification::make()
                                    ->danger()
                                    ->title('Peran super admin tidak bisa dihapus.')
                                    ->body('Tanpa peran ini, tidak ada yang bisa mengatur izin lagi.')
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('Belum ada peran')
            ->emptyStateDescription('Peran mengelompokkan izin, misalnya untuk panitia pemilihan yang hanya boleh melihat hasil.');
    }

    private static function uniqueCopyName(string $name): string
    {
        $candidate = $name.'_salinan';
        $suffix = 2;

        while (Utils::getRoleModel()::query()->where('name', $candidate)->exists()) {
            $candidate = $name.'_salinan_'.$suffix++;
        }

        return $candidate;
    }

    private static function superAdminName(): string
    {
        return config('filament-shield.super_admin.name', 'super_admin');
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return Utils::getResourceSlug();
    }

    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster();
    }

    public static function getEssentialsPlugin(): ?FilamentShieldPlugin
    {
        return FilamentShieldPlugin::get();
    }
}
