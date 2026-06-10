<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * spatie/laravel-permission guard for this model. The app authenticates
     * via Passport's `api` guard, so roles/permissions resolve on `api`.
     */
    protected string $guard_name = 'api';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Gate Filament admin panel access. Once inside Filament, resources and
     * actions still enforce their own permissions.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermissionTo('products.viewAny')
            || $this->hasPermissionTo('imports.viewAny')
            || $this->hasPermissionTo('users.manage')
            || $this->hasRole('SuperAdmin');
    }
}
