<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * All roles and permissions are created on the `api` guard, because the
     * application authenticates through Passport's `api` guard.
     */
    private const GUARD = 'api';

    /** @var array<int, string> */
    private const PRODUCT_PERMISSIONS = [
        'products.viewAny',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
    ];

    /** @var array<int, string> */
    private const IMPORT_PERMISSIONS = [
        'imports.viewAny',
        'imports.upload',
        'imports.start',
        'imports.stop',
        'imports.downloadFailures',
        'imports.delete',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [...self::PRODUCT_PERMISSIONS, ...self::IMPORT_PERMISSIONS, 'users.manage'];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        // SuperAdmin: gets everything (also short-circuited by ProductPolicy::before).
        $superAdmin = Role::findOrCreate('SuperAdmin', self::GUARD);
        $superAdmin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        // Admin: manage users and products, but not access control or imports.
        $admin = Role::findOrCreate('Admin', self::GUARD);
        $admin->syncPermissions([...self::PRODUCT_PERMISSIONS, 'imports.viewAny', 'users.manage']);

        // User: read products only.
        $user = Role::findOrCreate('User', self::GUARD);
        $user->syncPermissions(['products.viewAny', 'products.view']);
    }
}
