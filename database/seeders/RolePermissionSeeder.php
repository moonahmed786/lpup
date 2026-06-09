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

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [...self::PRODUCT_PERMISSIONS, 'users.manage'];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        // SuperAdmin: gets everything (also short-circuited by ProductPolicy::before).
        $superAdmin = Role::findOrCreate('SuperAdmin', self::GUARD);
        $superAdmin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        // Admin: manage users + full product management.
        $admin = Role::findOrCreate('Admin', self::GUARD);
        $admin->syncPermissions([...self::PRODUCT_PERMISSIONS, 'users.manage']);

        // User: read products only.
        $user = Role::findOrCreate('User', self::GUARD);
        $user->syncPermissions(['products.viewAny', 'products.view']);
    }
}
