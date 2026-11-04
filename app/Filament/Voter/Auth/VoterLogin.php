<?php

namespace App\Filament\Voter\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoterLogin extends Login
{
    /**
     * The failure message belongs under the username field, the one this form shows.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * Voters sign in with their username. Admins still sign in with their email, which only opens an admin account.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->helperText('Pemilih masuk dengan username. Admin masuk dengan email.')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * An email only matches an admin account, so a voter cannot sign in with the email on file.
     *
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        $identifier = trim((string) $data['username']);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return ['email' => $identifier, 'is_admin' => true, 'password' => $data['password']];
        }

        return ['username' => Str::lower($identifier), 'password' => $data['password']];
    }
}
