<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->schema([
                        Section::make('Akun')
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama')
                                    ->weight('semibold')
                                    ->columnSpanFull(),
                                TextEntry::make('username')
                                    ->label('Username')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable()
                                    ->visible(fn (): bool => UsersTable::canSeeEmails()),
                                TextEntry::make('created_at')
                                    ->label('Terdaftar')
                                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => self::formatWib($state)),
                                TextEntry::make('last_login_at')
                                    ->label('Terakhir masuk')
                                    ->formatStateUsing(fn (?CarbonInterface $state): ?string => $state
                                        ? self::formatWib($state).' ('.$state->locale('id')->diffForHumans().')'
                                        : null)
                                    ->placeholder('Belum pernah masuk'),
                                TextEntry::make('account_status')
                                    ->label('Status akun')
                                    ->badge()
                                    ->state(fn (User $record): string => $record->isSuspended() ? 'Dinonaktifkan' : 'Aktif')
                                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'danger'),
                                IconEntry::make('is_admin')
                                    ->label('Admin panel')
                                    ->boolean(),
                            ]),
                        Section::make('Status memilih')
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                TextEntry::make('ballot_status')
                                    ->label('Status')
                                    ->badge()
                                    ->state(fn (User $record): string => $record->ballot ? 'Sudah memilih' : 'Belum memilih')
                                    ->color(fn (string $state): string => $state === 'Sudah memilih' ? 'success' : 'gray'),
                                TextEntry::make('ballot_sent_at')
                                    ->label('Dikirim pada')
                                    ->state(fn (User $record): ?string => $record->ballot ? self::formatWib($record->ballot->created_at) : null)
                                    ->placeholder('Belum ada suara')
                                    ->helperText('Isi pilihan tidak ditampilkan demi kerahasiaan suara.'),
                            ]),
                        Section::make('Peran')
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('roles')
                                    ->hiddenLabel()
                                    ->state(fn (User $record): array => $record->roles
                                        ->map(fn (Role $role): string => Str::headline($role->name))
                                        ->all())
                                    ->badge()
                                    ->placeholder('Tanpa peran')
                                    ->icon(Heroicon::OutlinedShieldCheck),
                            ]),
                    ]),
            ]);
    }

    private static function formatWib(?CarbonInterface $moment): ?string
    {
        return $moment?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i').' WIB';
    }
}
