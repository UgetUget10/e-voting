<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Services\AccountSuspension;
use App\Services\AdminActivityLog;
use App\Services\VoterCardIssuer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

/**
 * Per-voter actions shared by the table row menu and the detail page, so both offer the same things.
 */
class UserActions
{
    public static function changeEmail(): Action
    {
        return Action::make('changeEmail')
            ->label('Ganti email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (User $record): bool => ! $record->is(auth()->user()) && UsersTable::canSeeEmails())
            ->modalHeading('Ganti email pemilih')
            ->modalDescription('Email baru dipakai untuk pengingat pemilih dan untuk admin masuk. Pemilih masuk memakai username, jadi tidak terpengaruh. Masukkan password Anda sebagai konfirmasi.')
            ->schema([
                TextInput::make('email')
                    ->label('Email baru')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(table: User::class, column: 'email', ignoreRecord: true),
                TextInput::make('confirmation')
                    ->label('Password Anda')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),
            ])
            ->action(function (User $record, array $data, AdminActivityLog $activityLog): void {
                $oldEmail = $record->email;
                $record->update(['email' => $data['email']]);
                $activityLog->record('Mengganti email '.$record->name.' dari '.$oldEmail.' menjadi '.$record->email.'.');

                Notification::make()->title('Email '.$record->name.' diperbarui.')->success()->send();
            });
    }

    public static function resetPassword(): Action
    {
        return Action::make('resetPassword')
            ->label('Atur ulang password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (User $record): bool => ! $record->is(auth()->user()))
            ->modalHeading('Atur ulang password')
            ->modalDescription('Password baru berlaku langsung. Sampaikan kepada pemilih secara langsung.')
            ->schema([
                TextInput::make('password')
                    ->label('Password baru')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
            ])
            ->action(function (User $record, array $data, AdminActivityLog $activityLog): void {
                $record->update(['password' => Hash::make($data['password'])]);
                $activityLog->record('Mengatur ulang password '.$record->name.'.');

                Notification::make()->title('Password '.$record->name.' diperbarui.')->success()->send();
            });
    }

    /**
     * A single card, for the voter on this page, without selecting rows first.
     */
    public static function printCard(): Action
    {
        return Action::make('printCard')
            ->label('Cetak kartu')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (User $record): bool => ! $record->is_admin && ! $record->isSuspended())
            ->requiresConfirmation()
            ->modalHeading('Cetak kartu pemilih ini?')
            ->modalDescription('Password pemilih ini diganti dengan password sementara acak yang ditampilkan sekali di kartu yang bisa dicetak.')
            ->action(function (User $record, VoterCardIssuer $issuer, AdminActivityLog $activityLog) {
                if ($issuer->issue(collect([$record])) === 0) {
                    Notification::make()->warning()->title('Kartu tidak bisa dicetak.')->body('Akun admin dan akun yang dinonaktifkan tidak mendapat kartu.')->send();

                    return null;
                }

                $activityLog->record('Mencetak kartu pemilih dan mengatur ulang password '.$record->name.'.');

                return redirect()->route('voter-cards.print');
            });
    }

    /**
     * Suspending keeps the ballot and history but blocks sign-in to both panels. Your own account cannot be suspended.
     */
    public static function toggleSuspension(): Action
    {
        return Action::make('toggleSuspension')
            ->label(fn (User $record): string => $record->isSuspended() ? 'Aktifkan kembali akun' : 'Nonaktifkan akun')
            ->icon(fn (User $record): Heroicon => $record->isSuspended() ? Heroicon::OutlinedLockOpen : Heroicon::OutlinedNoSymbol)
            ->color(fn (User $record): string => $record->isSuspended() ? 'gray' : 'danger')
            ->visible(fn (User $record): bool => ! $record->is(auth()->user()))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => $record->isSuspended() ? 'Aktifkan kembali akun ini?' : 'Nonaktifkan akun ini?')
            ->modalDescription(fn (User $record): string => $record->isSuspended()
                ? 'Pengguna bisa masuk lagi ke panelnya.'
                : 'Pengguna tidak bisa masuk ke panel mana pun. Suara dan riwayatnya tetap tersimpan.')
            ->action(function (User $record, AccountSuspension $suspension, AdminActivityLog $activityLog): void {
                $suspend = ! $record->isSuspended();
                $suspension->apply(collect([$record]), $suspend, auth()->user());

                $activityLog->record(($suspend ? 'Menonaktifkan' : 'Mengaktifkan kembali').' akun '.$record->name.'.', notifyAdmins: true);

                Notification::make()
                    ->success()
                    ->title($suspend ? 'Akun dinonaktifkan.' : 'Akun diaktifkan kembali.')
                    ->send();
            });
    }
}
