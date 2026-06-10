<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'api';

    /** @var array<int, string> */
    private const IMPORT_PERMISSIONS = [
        'imports.viewAny',
        'imports.upload',
        'imports.start',
        'imports.stop',
        'imports.downloadFailures',
        'imports.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::IMPORT_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        Role::findOrCreate('SuperAdmin', self::GUARD)
            ->givePermissionTo(self::IMPORT_PERMISSIONS);

        Role::findOrCreate('Admin', self::GUARD)
            ->givePermissionTo('imports.viewAny');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
