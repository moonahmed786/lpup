<?php

namespace App\Filament\Resources\Permissions;

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Schemas\PermissionForm;
use App\Filament\Resources\Permissions\Tables\PermissionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use UnitEnum;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Permissions';

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return self::canManageAccessControl();
    }

    public static function canCreate(): bool
    {
        return self::canManageAccessControl();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canManageAccessControl();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canManageAccessControl();
    }

    public static function canDeleteAny(): bool
    {
        return self::canManageAccessControl();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }

    private static function canManageAccessControl(): bool
    {
        return auth()->user()?->hasRole('SuperAdmin') ?? false;
    }
}
