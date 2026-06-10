<?php

namespace App\Filament\Support;

use App\Models\User;

class FilamentAccess
{
    private const GUARD = 'api';

    public static function hasPermission(string $permission): bool
    {
        return self::user()?->hasPermissionTo($permission, self::GUARD) ?? false;
    }

    public static function isSuperAdmin(): bool
    {
        return self::user()?->hasRole('SuperAdmin', self::GUARD) ?? false;
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
