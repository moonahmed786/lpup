<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);

        $authGuard = Filament::auth();
        $authProvider = $authGuard->getProvider();
        $user = $authProvider->retrieveByCredentials($credentials);

        if (
            $user instanceof FilamentUser
            && $authProvider->validateCredentials($user, $credentials)
            && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())
        ) {
            throw ValidationException::withMessages([
                'data.email' => 'This account does not have any admin panel permissions.',
            ]);
        }

        return parent::authenticate();
    }
}
