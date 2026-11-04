<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserForm
{
    private const GENERATED_PASSWORD_LENGTH = 12;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun')
                    ->description('Data yang dipakai pengguna untuk masuk ke sistem.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->live(debounce: 300)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->suffixAction(
                                Action::make('generatePassword')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->tooltip('Buat password acak')
                                    ->action(fn (Set $set) => $set('password', Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false))),
                            )
                            ->helperText(fn (?string $state, ?User $record): string => self::passwordHint($state, $record)),
                    ]),
                Section::make('Akses')
                    ->description('Akses admin membuka panel pengelola. Peran menentukan apa yang boleh dilakukan di panel tersebut.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Admin panel')
                            ->helperText('Akun sendiri dan pemilih yang sudah memilih tidak bisa diubah aksesnya.')
                            ->disabled(fn (?User $record): bool => ($record?->is(auth()->user()) ?? false) || ($record?->ballot()->exists() ?? false)),
                        Select::make('roles')
                            ->label('Peran')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Role $record): string => Str::headline($record->name))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->live()
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText(fn (Get $get): string => 'Peran dibuat dan diatur izinnya di menu Peran. Peran akun sendiri tidak bisa diubah. '
                                .self::roleSummary($get('roles'))),
                    ]),
            ]);
    }

    /**
     * What the field says under it: how to fill it in, or how strong the password typed so far is.
     */
    private static function passwordHint(?string $state, ?User $record): string
    {
        if (blank($state)) {
            return $record
                ? 'Kosongkan jika tidak ingin mengganti password. Minimal 8 karakter.'
                : 'Minimal 8 karakter. Atau klik ikon kilat untuk membuat password acak.';
        }

        $length = mb_strlen($state);
        $mixesLettersAndDigits = preg_match('/[A-Za-z]/', $state) === 1 && preg_match('/\d/', $state) === 1;

        return match (true) {
            $length < 8 => 'Kekuatan: lemah. Minimal 8 karakter.',
            $length >= 12 && $mixesLettersAndDigits => 'Kekuatan: kuat.',
            $length >= 12 || $mixesLettersAndDigits => 'Kekuatan: cukup. Gabungkan huruf dan angka, dan buat lebih panjang, agar lebih kuat.',
            default => 'Kekuatan: lemah. Tambahkan angka dan buat lebih panjang.',
        };
    }

    /**
     * How many permissions the chosen roles give, so an admin sees what a role change means before saving.
     *
     * @param  array<int|string>|null  $roleIds
     */
    private static function roleSummary(?array $roleIds): string
    {
        if (blank($roleIds)) {
            return 'Tanpa peran, akun ini tidak punya izin khusus di panel.';
        }

        $permissions = Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('id', $roleIds))
            ->count();

        return 'Peran yang dipilih memberi '.$permissions.' izin.';
    }
}
